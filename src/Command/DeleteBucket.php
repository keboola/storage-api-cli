<?php
/**
 * Created by JetBrains PhpStorm.
 * User: ondrej hlavacek
 * Date: 5/16/13
 * Time: 3:19 PM
 * To change this template use File | Settings | File Templates.
 */

namespace Keboola\StorageApi\Cli\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class DeleteBucket extends Command
{
    public function configure()
    {
        $this
            ->setName('delete-bucket')
            ->setDescription('Delete bucket')
            ->setDefinition(array(
                new InputArgument('bucketId', InputArgument::REQUIRED, "bucket to delete"),
                new InputOption('recursive', 'r', InputOption::VALUE_NONE, "delete all tables in the bucket recursively"),
            ));
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sapiClient = $this->getSapiClient();
        if (!$sapiClient->bucketExists($input->getArgument('bucketId'))) {
            throw new \Exception("Bucket {$input->getArgument('bucketId')} does not exist or is not accessible.");
        }

        $output->writeln("Bucket found ok");

        $startTime = time();

        // Delete tables in bucket
        $tablesInBucket = $sapiClient->listTables($input->getArgument('bucketId'));
        if (is_array($tablesInBucket) && count($tablesInBucket)) {
            if (!$input->getOption("recursive")) {
                throw new \Exception("Bucket {$input->getArgument('bucketId')} is not empty. Delete tables manually or use --recursive option.");
            }
            $output->writeln("Deleting tables in bucket {$input->getArgument('bucketId')}");
            foreach ($tablesInBucket as $table) {
                $sapiClient->dropTable($table["id"]);
            }
        }

        $output->writeln("Deleting bucket {$input->getArgument('bucketId')}");

        $sapiClient->dropBucket($input->getArgument('bucketId'));

        $duration = time() - $startTime;
        $output->writeln("Bucket {$input->getArgument('bucketId')} deleted in $duration secs.");
    }
}
