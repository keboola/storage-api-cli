<?php

namespace Keboola\StorageApi\Cli\Command;

use Aws\S3\S3Client;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Downloader\DownloaderFactory;
use Keboola\StorageApi\HandlerStack;
use Keboola\StorageApi\Options\GetFileOptions;
use Keboola\Temp\Temp;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Filesystem;

class BackupProject extends Command
{
    private const VERSION_LIMIT = 2;

    public function configure(): void
    {
        $this
            ->setName('backup-project')
            ->setDescription('Backup whole project to AWS S3')
            ->setHelp('AWS credentials should be provided AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY environment variables.')
            ->setDefinition([
                new InputArgument('bucket', InputArgument::REQUIRED, 'S3 bucket name'),
                new InputArgument('path', InputArgument::OPTIONAL, 'path in S3', '/'),
                new InputArgument('region', InputArgument::OPTIONAL, 'region', 'us-east-1'),
                new InputOption('structure-only', '-s', InputOption::VALUE_NONE, 'Backup only structure'),
                new InputOption('include-versions', '-i', InputOption::VALUE_NONE, 'Include configuration versions in backup'),
            ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $s3 = new S3Client([
            'version' => 'latest',
            'region' => $input->getArgument('region'),
        ]);
        $bucket = $input->getArgument('bucket');
        $basePath = $input->getArgument('path');
        if ($basePath === '' || $basePath === '/') {
            $basePath = '';
        } else {
            $basePath = rtrim($basePath, '/') . '/';
        }

        $sapiClient = $this->getSapiClient();

        $tables = $sapiClient->listTables(null, [
            'include' => 'columns,buckets,metadata,columnMetadata',
        ]);
        $output->write("Exporting tables\n");
        $s3->putObject([
            'Bucket' => $bucket,
            'Key' => $basePath . 'tables.json',
            'Body' => json_encode($tables),
        ]);

        $output->write("Exporting buckets\n");
        $s3->putObject([
            'Bucket' => $bucket,
            'Key' => $basePath . 'buckets.json',
            'Body' => json_encode($sapiClient->listBuckets(["include" => "metadata"])),
        ]);

        $output->write("Exporting configurations\n");
        $this->exportConfigs($sapiClient, $s3, $bucket, $basePath, $input->getOption('include-versions'));

        $tablesCount = count($tables);
        usort($tables, function ($a, $b) {
            return strcmp($a["id"], $b["id"]);
        });
        $onlyStructure = $input->getOption('structure-only');
        foreach (array_values($tables) as $i => $table) {
            $currentTable = $i + 1;

            if ($onlyStructure && $table['bucket']['stage'] !== 'sys') {
                $output->write("Skipping table $currentTable/$tablesCount - {$table['id']} (sys bucket)\n");
            } elseif (!$table['isAlias']) {
                $output->write("Exporting table $currentTable/$tablesCount - {$table['id']}\n");
                $this->exportTable($table['id'], $s3, $bucket, $basePath);
            } else {
                $output->write("Skipping table $currentTable/$tablesCount - {$table['id']} (alias)\n");
            }
        }
    }

    private function exportConfigs(Client $sapiClient, S3Client $s3, string $targetBucket, string $targetBasePath, bool $saveVersions): void
    {
        $limit = self::VERSION_LIMIT;
        $tmp = new Temp();
        $tmp->initRunFolder();

        $configurationsFile = $tmp->createFile("configurations.json");
        $versionsFile = $tmp->createFile("versions.json");

        // use raw api call to prevent parsing json - preserve empty JSON objects
        $sapiClient->apiGet('storage/components?include=configuration', $configurationsFile->getPathname());
        $handle = fopen($configurationsFile, "r");
        $s3->putObject([
            'Bucket' => $targetBucket,
            'Key' => $targetBasePath . 'configurations.json',
            'Body' => $handle,
        ]);
        fclose($handle);

        $url = "storage/components";
        $url .= "?include=configuration,rows,state";
        $sapiClient->apiGet($url, $configurationsFile->getPathname());
        $configurations = json_decode(file_get_contents($configurationsFile->getPathname()));

        foreach ($configurations as $component) {
            foreach ($component->configurations as $configuration) {
                if ($saveVersions) {
                    $offset = 0;
                    $versions = [];
                    do {
                        $url = "storage/components/{$component->id}/configs/{$configuration->id}/versions";
                        $url .= "?include=name,description,configuration,state";
                        $url .= "&limit={$limit}&offset={$offset}";
                        $sapiClient->apiGet($url, $versionsFile->getPathname());
                        $versionsTmp = json_decode(file_get_contents($versionsFile->getPathname()));
                        $versions = array_merge($versions, $versionsTmp);
                        $offset = $offset + $limit;
                    } while (count($versionsTmp) > 0);
                    $configuration->_versions = $versions;
                }
                if ($saveVersions) {
                    foreach ($configuration->rows as &$row) {
                        $offset = 0;
                        $versions = [];
                        do {
                            $url = "storage/components/{$component->id}/configs/{$configuration->id}/rows/{$row->id}/versions";
                            $url .= "?include=configuration";
                            $url .= "&limit={$limit}&offset={$offset}";
                            $sapiClient->apiGet($url, $versionsFile->getPathname());
                            $versionsTmp = json_decode(file_get_contents($versionsFile->getPathname()));
                            $versions = array_merge($versions, $versionsTmp);
                            $offset = $offset + $limit;
                        } while (count($versionsTmp) > 0);
                        $row->_versions = $versions;
                    }
                }
                $s3->putObject([
                    'Bucket' => $targetBucket,
                    'Key' => $targetBasePath . 'configurations/' . $component->id . '/' .
                        $configuration->id . '.json',
                    'Body' => json_encode($configuration),
                ]);
            }
        }
    }


    private function exportTable(string $tableId, S3Client $targetS3, string $targetBucket, string $targetBasePath): void
    {
        $client = $this->getSapiClient();
        $fileId = $client->exportTableAsync($tableId, [
            'gzip' => true,
        ]);
        $fileInfo = $client->getFile($fileId["file"]["id"], (new GetFileOptions())->setFederationToken(true));

        // Initialize Downloader with credentials from Storage API
        $downloader = DownloaderFactory::createDownloaderForFileResponse($fileInfo);

        $fs = new Filesystem();
        if ($fileInfo['isSliced'] === true) {
            // Download manifest with all sliced files
            $client = new \GuzzleHttp\Client([
                'handler' => HandlerStack::create([
                    'backoffMaxTries' => 10,
                ]),
            ]);
            $manifest = json_decode($client->get($fileInfo['url'])->getBody(), true);

            // Download all slices
            $tmpFilePath = $this->getTmpDir() . DIRECTORY_SEPARATOR . uniqid('sapi-export-');
            foreach ($manifest["entries"] as $i => $part) {
                $filePath = $downloader->downloadManifestEntry($fileInfo, $part, $tmpFilePath);

                $fh = fopen($filePath, 'r');
                $targetS3->putObject([
                    'Bucket' => $targetBucket,
                    'Key' => $targetBasePath . str_replace('.', '/', $tableId) . '.part_' . $i . '.csv.gz',
                    'Body' => $fh,
                ]);
                fclose($fh);
                $fs->remove($filePath);
            }
        } else {
            $tmpFilePath = $this->getTmpDir() . DIRECTORY_SEPARATOR . uniqid('table');
            $downloader->downloadFileFromFileResponse($fileInfo, $tmpFilePath);

            $fh = fopen($tmpFilePath, 'r');
            $targetS3->putObject([
                'Bucket' => $targetBucket,
                'Key' => $targetBasePath . str_replace('.', '/', $tableId) . '.csv.gz',
                'Body' => $fh,
            ]);
            fclose($fh);
            $fs->remove($tmpFilePath);
        }
    }
}
