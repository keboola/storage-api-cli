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

class WriteTable extends Command
{

    public function configure(): void
    {
        $this
            ->setName('write-table')
            ->setDescription('Write data into table')
            ->setDefinition(array(
                new InputArgument('tableId', InputArgument::REQUIRED, "target table"),
                new InputArgument('filePath', InputArgument::REQUIRED, "import csv file"),
                new InputOption('incremental', 'i', InputOption::VALUE_NONE, "incremental load"),
                new InputOption('partial', 'p', InputOption::VALUE_NONE, "partial load"),
                new InputOption('delimiter', null, InputOption::VALUE_REQUIRED, "csv delimiter", CsvFile::DEFAULT_DELIMITER),
                new InputOption('enclosure', null, InputOption::VALUE_OPTIONAL, "csv enclosure", CsvFile::DEFAULT_ENCLOSURE),
                new InputOption('escapedBy', null, InputOption::VALUE_OPTIONAL, "csv escape character", ''),
            ));
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $sapiClient = $this->getSapiClient();
        if (!$sapiClient->tableExists($input->getArgument('tableId'))) {
            throw new \Exception("Table {$input->getArgument('tableId')} does not exist or is not accessible.");
        }

        $output->writeln("Table found ok");

        $filePath = $input->getArgument('filePath');
        if (!is_file($filePath)) {
            throw new \Exception("File $filePath does not exist.");
        }

        $output->writeln("Import start");
        $startTime = time();

        $csvFile = new CsvFile(
            $filePath,
            stripcslashes($input->getOption('delimiter')),
            $input->getOption('enclosure'),
            $input->getOption('escapedBy')
        );

        $result = $sapiClient->writeTableAsync(
            $input->getArgument('tableId'),
            $csvFile,
            array(
                'incremental' => $input->getOption('incremental'),
                'partial' => $input->getOption('partial'),
            )
        );

        $duration = time() - $startTime;

        $output->writeln("Import done in $duration secs.");
        $output->writeln("");
        $output->writeln($this->getFormatterHelper()->formatBlock("Results:", "info"));
        $output->write($this->getNestedFormatterHelper()->format($result, 1));
    }
}
