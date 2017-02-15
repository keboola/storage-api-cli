<?php
/**
 * Created by JetBrains PhpStorm.
 * User: martinhalamicek
 * Date: 5/16/13
 * Time: 3:19 PM
 * To change this template use File | Settings | File Templates.
 */

namespace Keboola\StorageApi\Cli\Command;

use Keboola\StorageApi\Components;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class PurgeProject extends Command
{
    public function configure()
    {
        $this
            ->setName('purge-project')
            ->setDescription('Purge the project')
            ->setDefinition([
                new InputOption('configurations', '-c', InputOption::VALUE_NONE, 'Purge configurations'),
                new InputOption('aliases', '-a', InputOption::VALUE_NONE, 'Purge aliases'),
                new InputOption('data', '-d', InputOption::VALUE_NONE, 'Purge tables, aliases and buckets'),
                new InputOption('file-uploads', '-f', InputOption::VALUE_NONE, 'Purge file uploads')
            ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $client = $this->getSapiClient();

        $limit = false;
        if ($input->getOption('configurations') || $input->getOption('aliases') || $input->getOption('data') || $input->getOption('file-uploads')) {
            $limit = true;
        }

        if (!$limit || $input->getOption('configurations')) {
            $output->write($this->format('Dropping configurations'));
            $components = new Components($client);
            $componentList = $components->listComponents();
            foreach ($componentList as $componentItem) {
                foreach ($componentItem["configurations"] as $config) {
                    $components->deleteConfiguration($componentItem["id"], $config["id"]);
                }
            }
            $output->writeln($this->check());
        }

        $buckets = $client->listBuckets();

        if (!$limit || $input->getOption('aliases') || $input->getOption('data')) {
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
        }

        if (!$limit ||$input->getOption('data')) {
            $output->write($this->format('Dropping buckets'));
            foreach ($buckets as $bucket) {
                $client->dropBucket($bucket["id"], ["force" => true]);
            }
            $output->writeln($this->check());
        }

        if (!$limit ||$input->getOption('file-uploads')) {
            $output->write($this->format('Dropping file uploads'));
            foreach ($client->listFiles() as $file) {
                $client->deleteFile($file["id"]);
            }
            $output->writeln($this->check());
        }
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
