<?php
/**
 * Created by JetBrains PhpStorm.
 * User: martinhalamicek
 * Date: 5/16/13
 * Time: 3:19 PM
 * To change this template use File | Settings | File Templates.
 */

namespace Keboola\StorageApi\Cli\Command;

use Aws\S3\S3Client;
use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Metadata;
use Keboola\StorageApi\Options\Components\Configuration;
use Keboola\StorageApi\Options\Components\ConfigurationRow;
use Keboola\StorageApi\Options\FileUploadOptions;
use Keboola\Temp\Temp;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Filesystem;

class RestoreProject extends Command
{
    public function configure(): void
    {
        $this
            ->setName('restore-project')
            ->setDescription('Restore a project from a backup in AWS S3. Only the latest versions of all configs are used.')
            ->setHelp('AWS credentials should be provided AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY environment variables.')
            ->setDefinition([
                new InputArgument('bucket', InputArgument::REQUIRED, 'S3 bucket name'),
                new InputArgument('path', InputArgument::OPTIONAL, 'path in S3', '/'),
                new InputArgument('region', InputArgument::OPTIONAL, 'region', 'us-east-1'),
                new InputOption('ignore-storage-backend', '-i', InputOption::VALUE_NONE, 'Restore all tables to the default backend'),
                new InputOption('configurations', '-c', InputOption::VALUE_NONE, 'Restore only configurations'),
                new InputOption('data', '-d', InputOption::VALUE_NONE, 'Restore only tables, aliases and buckets'),
            ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $s3 = new S3Client([
            'version' => 'latest',
            'region' => $input->getArgument('region'),
        ]);

        $limit = false;
        if ($input->getOption('configurations') || $input->getOption('data')) {
            $limit = true;
        }

        $bucket = $input->getArgument('bucket');
        $basePath = $input->getArgument('path');
        $basePath = rtrim($basePath, '/') . '/';

        $client = $this->getSapiClient();
        $metadataClient = new Metadata($client);

        $tmp = new Temp();
        $tmp->initRunFolder();

        if (!$limit || $input->getOption('data')) {
            $output->write($this->format('Downloading buckets'));
            $s3->getObject(
                [
                'Bucket' => $bucket,
                'Key' => ltrim($basePath . 'buckets.json', '/'),
                'SaveAs' => $tmp->getTmpFolder() . 'buckets.json',
                ]
            );
            $buckets = json_decode(file_get_contents($tmp->getTmpFolder() . 'buckets.json'), true);
            $output->writeln($this->check());

            if (!$input->getOption('ignore-storage-backend')) {
                $output->write($this->format('Checking bucket compatibility'));
                $tokenInfo = $client->verifyToken();
                foreach ($buckets as $bucketInfo) {
                    switch ($bucketInfo["backend"]) {
                        case "mysql":
                            if (!isset($tokenInfo["owner"]["hasMysql"]) || $tokenInfo["owner"]["hasMysql"] === false) {
                                $output->writeln("<error>Missing MySQL backend</error>");

                                return 1;
                            }
                            break;
                        case "redshift":
                            if (!isset($tokenInfo["owner"]["hasRedshift"]) || $tokenInfo["owner"]["hasRedshift"] === false) {
                                $output->writeln("<error>Missing Redshift backend</error>");

                                return 1;
                            }
                            break;
                        case "snowflake":
                            if (!isset($tokenInfo["owner"]["hasSnowflake"]) || $tokenInfo["owner"]["hasSnowflake"] === false) {
                                $output->writeln("<error>Missing Snowflake backend</error>");

                                return 1;
                            }
                            break;
                    }
                }
                $output->writeln($this->check());
            }

            $restoredBuckets = [];

            // buckets restore
            foreach ($buckets as $bucketInfo) {
                $output->write($this->format('Restoring bucket ' . $bucketInfo["name"]));

                if (isset($bucketInfo['sourceBucket']) || substr($bucketInfo["name"], 0, 2) != 'c-') {
                    $output->writeln("Skipping");
                    continue;
                }

                $bucketName = substr($bucketInfo["name"], 2);
                $restoredBuckets[] = $bucketInfo["id"];

                if ($input->getOption('ignore-storage-backend')) {
                    $client->createBucket($bucketName, $bucketInfo['stage'], $bucketInfo['description']);
                } else {
                    $client->createBucket(
                        $bucketName,
                        $bucketInfo['stage'],
                        $bucketInfo['description'],
                        $bucketInfo['backend']
                    );
                }

                // bucket attributes
                if (count($bucketInfo["attributes"])) {
                    $client->replaceBucketAttributes($bucketInfo["id"], $bucketInfo["attributes"]);
                }
                if (isset($bucketInfo["metadata"]) && count($bucketInfo["metadata"])) {
                    foreach ($this->prepareMetadata($bucketInfo["metadata"]) as $provider => $metadata) {
                        $metadataClient->postBucketMetadata($bucketInfo["id"], $provider, $metadata);
                    }
                }


                $output->writeln($this->check());
            }


            $output->write($this->format('Downloading tables'));
            $s3->getObject(
                [
                    'Bucket' => $bucket,
                    'Key' => ltrim($basePath . 'tables.json', '/'),
                    'SaveAs' => $tmp->getTmpFolder() . '/tables.json',
                ]
            );
            $output->writeln($this->check());

            // tables restore
            $tables = json_decode(file_get_contents($tmp->getTmpFolder() . '/tables.json'), true);
            foreach ($tables as $table) {
                if ($table["isAlias"] === true) {
                    continue;
                }
                $output->write($this->format('Restoring table ' . $table["id"]));

                if (!in_array($table["bucket"]["id"], $restoredBuckets)) {
                    $output->writeln("Skipping");
                    continue;
                }

                // Create header and create table
                $headerFileInfo = $tmp->createFile($table["id"] . ".header.csv");
                $headerFile = new CsvFile($headerFileInfo->getPathname());
                $headerFile->writeRow($table["columns"]);
                $tableId = $client->createTable(
                    $table["bucket"]["id"],
                    $table["name"],
                    $headerFile,
                    ["primaryKey" => join(",", $table["primaryKey"])]
                );

                // upload data
                $prefix = $basePath . $table["bucket"]["stage"] . "/" . $table["bucket"]["name"] . "/" . $table["name"] . ".";
                $slices = $s3->listObjects(
                    [
                        'Bucket' => $bucket,
                        'Prefix' => $prefix,
                    ]
                );

                // no files for the table found, probably an empty table
                if (!isset($slices["Contents"])) {
                    unset($headerFile);
                    continue;
                }

                if (count($slices["Contents"]) == 1 && substr($slices["Contents"][0]["Key"], -14) != '.part_0.csv.gz') {
                    // one file and no slices => the file has header
                    // no slices = file does not end with .part_0.csv.gz
                    $fileName = $tmp->getTmpFolder() . "/" . $table["id"] . ".csv.gz";
                    $s3->getObject(
                        [
                            'Bucket' => $bucket,
                            'Key' => $slices["Contents"][0]["Key"],
                            'SaveAs' => $fileName,
                        ]
                    );
                    $fileUploadOptions = new FileUploadOptions();
                    $fileUploadOptions
                        ->setFileName($table["id"] . ".csv.gz");
                    $fileId = $client->uploadFile($fileName, $fileUploadOptions);
                    $client->writeTableAsyncDirect(
                        $tableId,
                        [
                            "name" => $table["name"],
                            "dataFileId" => $fileId,
                        ]
                    );
                } else {
                    // sliced file, requires some more work
                    // prepare manifest and prepare upload params
                    $manifest = [
                        "entries" => [],
                    ];
                    $fileUploadOptions = new FileUploadOptions();
                    $fileUploadOptions
                        ->setFederationToken(true)
                        ->setFileName($table["id"])
                        ->setIsSliced(true)
                    ;
                    $fileUploadInfo = $client->prepareFileUpload($fileUploadOptions);
                    $uploadParams = $fileUploadInfo["uploadParams"];
                    $s3FileClient = new S3Client(
                        [
                            "credentials" => [
                                "key" => $uploadParams["credentials"]["AccessKeyId"],
                                "secret" => $uploadParams["credentials"]["SecretAccessKey"],
                                "token" => $uploadParams["credentials"]["SessionToken"],
                            ],
                            "region" => $fileUploadInfo["region"],
                            "version" => "2006-03-01",
                        ]
                    );
                    $fs = new Filesystem();
                    $part = 0;

                    // download and upload each slice
                    foreach ($slices["Contents"] as $slice) {
                        $fileName = $tmp->getTmpFolder() . "/" . $table["id"] . $table["id"] . ".part_" . $part . ".csv.gz";
                        $s3->getObject(
                            [
                                'Bucket' => $bucket,
                                'Key' => $slice["Key"],
                                'SaveAs' => $fileName,
                            ]
                        );

                        $manifest["entries"][] = [
                            "url" => "s3://" . $uploadParams["bucket"] . "/" . $uploadParams["key"] . ".part_" . $part . ".csv.gz",
                            "mandatory" => true,
                        ];

                        $handle = fopen($fileName, 'r+');
                        $s3FileClient->putObject(
                            [
                                'Bucket' => $uploadParams['bucket'],
                                'Key' => $uploadParams['key'] . ".part_" . $part . ".csv.gz",
                                'Body' => $handle,
                                'ServerSideEncryption' => $uploadParams['x-amz-server-side-encryption'],
                            ]
                        );

                        // remove the uploaded file
                        fclose($handle);
                        $fs->remove($fileName);
                        $part++;
                    }

                    // Upload manifest
                    $s3FileClient->putObject(
                        array(
                            'Bucket' => $uploadParams['bucket'],
                            'Key' => $uploadParams['key'] . 'manifest',
                            'ServerSideEncryption' => $uploadParams['x-amz-server-side-encryption'],
                            'Body' => json_encode($manifest),
                        )
                    );


                    // Upload data to table
                    $client->writeTableAsyncDirect(
                        $tableId,
                        array(
                            'dataFileId' => $fileUploadInfo['id'],
                            'columns' => $headerFile->getHeader()
                        )
                    );
                }
                unset($headerFile);

                $output->writeln($this->check());
            }

            // alias restore
            foreach ($tables as $table) {
                if ($table["isAlias"] !== true) {
                    continue;
                }

                $output->write($this->format('Restoring alias ' . $table["id"]));

                if (!in_array($table["bucket"]["id"], $restoredBuckets)) {
                    $output->writeln("Skipping");
                    continue;
                }

                $aliasOptions = [];
                if (isset($table["aliasFilter"])) {
                    $aliasOptions["aliasFilter"] = $table["aliasFilter"];
                }
                if (isset($table["aliasColumnsAutoSync"]) && $table["aliasColumnsAutoSync"] === false) {
                    $aliasOptions["aliasColumns"] = $table["columns"];
                }
                $client->createAliasTable(
                    $table["bucket"]["id"],
                    $table["sourceTable"]["id"],
                    $table["name"],
                    $aliasOptions
                );

                $output->writeln($this->check());
            }

            $output->write($this->format('Restoring table attributes'));
            foreach ($tables as $table) {
                if (isset($table["attributes"]) && count($table["attributes"])) {
                    $client->replaceTableAttributes($table["id"], $table["attributes"]);
                }
                if (isset($table["metadata"]) && count($table["metadata"])) {
                    foreach ($this->prepareMetadata($table["metadata"]) as $provider => $metadata) {
                        $metadataClient->postTableMetadata($table["id"], $provider, $metadata);
                    }
                }
                if (isset($table["columnMetadata"]) && count($table["columnMetadata"])) {
                    foreach ($table["columnMetadata"] as $column => $columnMetadata) {
                        foreach ($this->prepareMetadata($columnMetadata) as $provider => $metadata) {
                            $metadataClient->postColumnMetadata($table["id"] . "." . $column, $provider, $metadata);
                        }
                    }
                }
            }
            $output->writeln($this->check());
        }

        if (!$limit || $input->getOption('configurations')) {
            $output->write($this->format('Downloading configurations'));
            $s3->getObject(
                [
                    'Bucket' => $bucket,
                    'Key' => ltrim($basePath . 'configurations.json', '/'),
                    'SaveAs' => $tmp->getTmpFolder() . '/configurations.json',
                ]
            );
            $configurations = json_decode(file_get_contents($tmp->getTmpFolder() . '/configurations.json'), true);
            $output->writeln($this->check());

            $components = new Components($client);

            $componentList = [];
            foreach ($client->indexAction()['components'] as $component) {
                $componentList[$component['id']] = $component;
            }

            foreach ($configurations as $componentWithConfigurations) {
                $output->write($this->format(sprintf('Restoring %s configurations', $componentWithConfigurations["id"])));

                // skip non-existing components
                if (!array_key_exists($componentWithConfigurations["id"], $componentList)) {
                    $output->writeln("Skipping");
                    continue;
                }

                // skip obsolete components - orchestrator, old writers, etc.
                if ($this->isObsoleteComponent($componentList[$componentWithConfigurations["id"]])) {
                    $output->writeln("Skipping");
                    continue;
                }

                foreach ($componentWithConfigurations["configurations"] as $componentConfiguration) {
                    $s3->getObject(
                        [
                            'Bucket' => $bucket,
                            'Key' => ltrim($basePath . "configurations/{$componentWithConfigurations["id"]}/{$componentConfiguration["id"]}.json", '/'),
                            'SaveAs' => $tmp->getTmpFolder() . "/configurations-{$componentWithConfigurations["id"]}-{$componentConfiguration["id"]}.json",
                        ]
                    );

                    // configurations as objects to preserve empty arrays or empty objects
                    $configurationData = json_decode(
                        file_get_contents(
                            $tmp->getTmpFolder() . "/configurations-{$componentWithConfigurations["id"]}-{$componentConfiguration["id"]}.json"
                        )
                    );

                    // create empty configuration
                    $configuration = new Configuration();
                    $configuration->setComponentId($componentWithConfigurations["id"]);
                    $configuration->setConfigurationId($componentConfiguration["id"]);
                    $configuration->setDescription($configurationData->description);
                    $configuration->setName($configurationData->name);
                    $components->addConfiguration($configuration);

                    // update configuration and state
                    $configuration->setChangeDescription(
                        "Configuration {$componentConfiguration["id"]} restored from backup"
                    );
                    $configuration->setConfiguration($configurationData->configuration);
                    if (isset($configurationData->state)) {
                        $configuration->setState($configurationData->state);
                    }
                    $components->updateConfiguration($configuration);

                    // create configuration rows
                    if (count($configurationData->rows)) {
                        foreach ($configurationData->rows as $row) {
                            // create empty row
                            $configurationRow = new ConfigurationRow($configuration);
                            $configurationRow->setRowId($row->id);
                            $components->addConfigurationRow($configurationRow);

                            // update row configuration and state
                            $configurationRow->setConfiguration($row->configuration);
                            $configurationRow->setChangeDescription("Row {$row->id} restored from backup");
                            if (isset($row->state)) {
                                $configurationRow->setState($row->state);
                            }
                            $components->updateConfigurationRow($configurationRow);
                        }
                    }
                }

                $output->writeln($this->check());
            }
        }

        $output->writeln("Project successfully restored. Please note what's missing:");
        $output->writeln(" - linked buckets");
        $output->writeln(" - table snapshots");
        $output->writeln(" - created/modified date of all objects");
        $output->writeln(" - encrypted data (eg. passwords)");
        $output->writeln(" - oauth authorizations");
        $output->writeln(" - configuration versions");
        $output->writeln(" - orchestrations");
        $output->writeln(" - features of the original project");
        return 0;
    }

    private function format(string $message): string
    {
        return sprintf('%-50s', $message);
    }

    private function check(): string
    {
        return '<info>ok</info>';
    }

    private function prepareMetadata(array $rawMetadata): array
    {
        $result = [];
        foreach ($rawMetadata as $item) {
            $result[$item["provider"]][] = [
                "key" => $item["key"],
                "value" => $item["value"],
            ];
        }
        return $result;
    }

    /**
     * List of KBC components without api
     *
     * @see https://github.com/keboola/kbc-ui/blob/master/src/scripts/modules/components/utils/hasComponentApi.coffee
     * @return array
     */
    private function componentsWithoutApi(): array
    {
        return [
            'wr-dropbox', 'tde-exporter', 'geneea-topic-detection',
            'geneea-language-detection', 'geneea-lemmatization', 'geneea-sentiment-analysis',
            'geneea-text-correction', 'geneea-entity-recognition', 'ex-adform', 'geneea-nlp-analysis',
            'rcp-anomaly', 'rcp-basket', 'rcp-correlations', 'rcp-data-type-assistant',
            'rcp-distribution-groups', 'rcp-linear-dependency', 'rcp-linear-regression',
            'rcp-next-event', 'rcp-next-order-simple',
            'rcp-segmentation', 'rcp-var-characteristics', 'ex-sklik', 'ex-dropbox', 'wr-portal-sas', 'ag-geocoding',
            'keboola.ex-db-pgsql', 'keboola.ex-db-db2', 'keboola.ex-db-firebird'
        ];
    }

    /**
     * Check if component is obsolete
     *
     * @see https://github.com/keboola/kbc-ui/blob/master/src/scripts/modules/trash/utils.js
     * @param array $component component data
     * @return bool
     */
    private function isObsoleteComponent(array $component): bool
    {
        $componentId = $component['id'];
        if ($componentId == 'gooddata-writer') {
            return true;
        }

        if ($componentId == 'transformation') {
            return false;
        }

        $flags = $component['flags'];
        if (isset($component['uri']) &&
            !in_array($componentId, $this->componentsWithoutApi()) &&
            !in_array('genericUI', $flags) &&
            !in_array('genericDockerUI', $flags) &&
            !in_array('genericTemplatesUI', $flags)
        ) {
            return true;
        }

        return false;
    }
}
