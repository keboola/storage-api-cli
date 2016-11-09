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
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\ListConfigurationRowVersionsOptions;
use Keboola\StorageApi\Options\Components\ListConfigurationsOptions;
use Keboola\StorageApi\Options\Components\ListConfigurationVersionsOptions;
use Keboola\StorageApi\Options\GetFileOptions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Filesystem;

class PurgeProject extends Command
{
    public function configure()
    {
        $this
            ->setName('purge-project')
            ->setDescription('Purge the project');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $client = $this->getSapiClient();

        $output->write($this->format('Dropping configurations'));
        $components = new Components($client);
        $componentList = $components->listComponents();
        foreach($componentList as $componentItem) {
            foreach($componentItem["configurations"] as $config) {
                $components->deleteConfiguration($componentItem["id"], $config["id"]);
            }
        }
        $output->writeln($this->check());

        $buckets = $client->listBuckets();

        if (count($buckets) > 0) {
            $output->write($this->format('Dropping aliases'));
            foreach ($client->listTables() as $table) {
                if (!$table["isAlias"]) {
                    continue;
                }
                $client->dropTable($table["id"]);
            }
            $output->writeln($this->check());
        }

        $output->write($this->format('Dropping buckets'));
        foreach($buckets as $bucket) {
            $client->dropBucket($bucket["id"], ["force" => true]);
        }
        $output->writeln($this->check());

        $output->write($this->format('Dropping file uploads'));
        foreach($client->listFiles() as $file) {
            $client->deleteFile($file["id"]);
        }
        $output->writeln($this->check());

    }


    private function format($message)
    {
        return sprintf('%-50s', $message);
    }

    private function check()
    {
        return '<info>ok</info>';
    }
}
