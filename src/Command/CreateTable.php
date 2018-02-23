<?php
/**
 * Created by JetBrains PhpStorm.
 * User: martinhalamicek
 * Date: 5/16/13
 * Time: 3:19 PM
 * To change this template use File | Settings | File Templates.
 */

namespace Keboola\StorageApi\Cli\Command;

use Keboola\Csv\CsvFile;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class CreateTable extends Command
{

    public function configure(): void
    {
        $this
            ->setName('create-table')
            ->setDescription('Create table in bucket')
            ->setDefinition(array(
                new InputArgument('bucketId', InputArgument::REQUIRED, "destination bucket"),
                new InputArgument('name', InputArgument::REQUIRED, "table name"),
                new InputArgument('filePath', InputArgument::REQUIRED, "source csv file path, table will be created from file"),
                new InputOption('delimiter', null, InputOption::VALUE_REQUIRED, "csv delimiter", CsvFile::DEFAULT_DELIMITER),
                new InputOption('enclosure', null, InputOption::VALUE_OPTIONAL, "csv enclosure", CsvFile::DEFAULT_ENCLOSURE),
                new InputOption('escapedBy', null, InputOption::VALUE_OPTIONAL, "csv escape character", ''),
            ));
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $sapiClient = $this->getSapiClient();
        if (!$sapiClient->bucketExists($input->getArgument('bucketId'))) {
            throw new \Exception("Bucket {$input->getArgument('bucketId')} does not exist or is not accessible.");
        }

        $output->writeln("Bucket found ok");

        $filePath = $input->getArgument('filePath');
        if (!is_file($filePath)) {
            throw new \Exception("File $filePath does not exist.");
        }

        $output->writeln("Table create start");

        $csvFile = new CsvFile(
            $filePath,
            stripcslashes($input->getOption('delimiter')),
            $input->getOption('enclosure'),
            $input->getOption('escapedBy')
        );

        $tableId = $sapiClient->createTableAsync(
            $input->getArgument('bucketId'),
            $input->getArgument('name'),
            $csvFile
        );

        $output->writeln("Table create end");
        $output->writeln("Table id: $tableId");
    }
}
