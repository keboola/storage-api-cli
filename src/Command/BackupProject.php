<?php

namespace Keboola\StorageApi\Cli\Command;

use Aws\S3\S3Client;
use Keboola\ProjectBackup\S3Backup;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class BackupProject extends Command
{
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
        $sapiClient = $this->getSapiClient();
        $logger = $this->getLogger($output);

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

        $backup = new S3Backup($sapiClient, $s3, $logger);

        $backup->backupTablesMetadata($bucket, $basePath);
        $backup->backupConfigs($bucket, $basePath, $input->getOption('include-versions'));

        if ($input->getOption('structure-only')) {
            $logger->warning('Skipping exporting tables data (structure-only export)');
        } else {
            $tables = $sapiClient->listTables(null);

            $tablesCount = count($tables);
            usort($tables, function ($a, $b) {
                return strcmp($a["id"], $b["id"]);
            });

            foreach ($tables as $i => $table) {
                $output->writeln(sprintf('Table %d/%d', $i + 1, $tablesCount));
                $backup->backupTable($table['id'], $bucket, $basePath);
            }
        }
    }

    private function getLogger(OutputInterface $output): ConsoleLogger
    {
        return new ConsoleLogger(
            $output,
            [
                LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL,
            ],
            [
                LogLevel::WARNING => 'comment',
            ]
        );
    }
}
