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
use Keboola\ProjectRestore\S3Restore;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class RestoreProject extends Command
{
    public const COMPONENTS_WITH_CUSTOM_RESTORE = [
        'orchestrator',
        'gooddata-writer',
        'keboola.wr-db-snowflake',
    ];

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
        $sapiClient = $this->getSapiClient();
        $logger = $this->getLogger($output);

        $s3 = new S3Client([
            'version' => 'latest',
            'region' => $input->getArgument('region'),
        ]);

        $limit = false;
        if ($input->getOption('configurations') || $input->getOption('data')) {
            $limit = true;
        }

        $restore = new S3Restore($s3, $sapiClient, $logger);

        $bucket = $input->getArgument('bucket');
        $basePath = $input->getArgument('path');
        $basePath = rtrim($basePath, '/') . '/';

        if (!$limit || $input->getOption('data')) {
            $restore->restoreBuckets($bucket, $basePath, !$input->getOption('ignore-storage-backend'));
            $restore->restoreTables($bucket, $basePath);
            $restore->restoreTableAliases($bucket, $basePath);
        }

        if (!$limit || $input->getOption('configurations')) {
            $restore->restoreConfigs($bucket, $basePath, self::COMPONENTS_WITH_CUSTOM_RESTORE);
        }

        $logger->info("Project successfully restored. Please note what's missing:");
        $logger->info(" - linked buckets");
        $logger->info(" - table snapshots");
        $logger->info(" - created/modified date of all objects");
        $logger->info(" - encrypted data (eg. passwords)");
        $logger->info(" - oauth authorizations");
        $logger->info(" - configurations for non-existing components");
        $logger->info(" - configurations for components having custom API");
        $logger->info(" - configuration versions");
        $logger->info(" - features of the original project");
        return 0;
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
