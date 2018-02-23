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

class DeleteTable extends Command
{

    public function configure(): void
    {
        $this
            ->setName('delete-table')
            ->setDescription('Delete table')
            ->setDefinition(array(
                new InputArgument('tableId', InputArgument::REQUIRED, "table to delete"),
            ));
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $sapiClient = $this->getSapiClient();
        if (!$sapiClient->tableExists($input->getArgument('tableId'))) {
            throw new \Exception("Table {$input->getArgument('tableId')} does not exist or is not accessible.");
        }
        $output->writeln("Table found ok");

        $output->writeln("Deleting {$input->getArgument('tableId')}");
        $startTime = time();

        // Just delete, hello
        $sapiClient->dropTable($input->getArgument('tableId'));

        $duration = time() - $startTime;

        $output->writeln("Table {$input->getArgument('tableId')} deleted in $duration secs.");
    }
}
