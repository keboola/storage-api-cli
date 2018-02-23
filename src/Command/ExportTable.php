<?php
/**
 * Created by JetBrains PhpStorm.
 * User: martinhalamicek
 * Date: 5/16/13
 * Time: 3:19 PM
 * To change this template use File | Settings | File Templates.
 */

namespace Keboola\StorageApi\Cli\Command;

use Keboola\StorageApi\TableExporter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class ExportTable extends Command
{
    public function configure(): void
    {
        $this
            ->setName('export-table')
            ->setDescription('Export data from table to file')
            ->setDefinition(array(
                new InputArgument('tableId', InputArgument::REQUIRED, "table to export"),
                new InputArgument('filePath', InputArgument::REQUIRED, "CSV file"),
                new InputOption('format', 'f', InputOption::VALUE_REQUIRED, "output format (raw, rfc or escaped)", "rfc"),
                new InputOption('gzip', 'g', InputOption::VALUE_NONE, "gzip file"),
                new InputOption('columns', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, "comma separated list of columns"),
                new InputOption('limit', null, InputOption::VALUE_REQUIRED, "number of rows to export"),
                new InputOption('changedSince', null, InputOption::VALUE_REQUIRED, "start of time range"),
                new InputOption('changedUntil', null, InputOption::VALUE_REQUIRED, "end of time range"),
                new InputOption('whereColumn', null, InputOption::VALUE_REQUIRED, "filter by column"),
                new InputOption('whereOperator', null, InputOption::VALUE_REQUIRED, "filtering operator (eq, ne)", "eq"),
                new InputOption('whereValues', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, "filter value"),
            ));
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $sapiClient = $this->getSapiClient();
        if (!$sapiClient->tableExists($input->getArgument('tableId'))) {
            throw new \Exception("Table {$input->getArgument('tableId')} does not exist or is not accessible.");
        }

        $output->writeln("Table found ok");

        $startTime = time();

        $exporter = new TableExporter($sapiClient);

        $exportOptions = array(
            'format' => $input->getOption('format'),
            'gzip' => $input->getOption('gzip'),
        );
        if ($input->getOption("columns")) {
            $exportOptions["columns"] = $input->getOption("columns");
        }
        if ($input->getOption("limit")) {
            $exportOptions["limit"] = $input->getOption("limit");
        }
        if ($input->getOption("whereColumn") && $input->getOption("whereOperator") && $input->getOption("whereValues")) {
            $exportOptions["whereColumn"] = $input->getOption("whereColumn");
            $exportOptions["whereOperator"] = $input->getOption("whereOperator");
            $exportOptions["whereValues"] = $input->getOption("whereValues");
        }
        if ($input->getOption("changedSince")) {
            $exportOptions["changedSince"] = $input->getOption("changedSince");
        }
        if ($input->getOption("changedUntil")) {
            $exportOptions["changedUntil"] = $input->getOption("changedUntil");
        }

        $exporter->exportTable(
            $input->getArgument('tableId'),
            $input->getArgument('filePath'),
            $exportOptions
        );

        $duration = time() - $startTime;

        $output->writeln("Export done in $duration secs.");
    }
}
