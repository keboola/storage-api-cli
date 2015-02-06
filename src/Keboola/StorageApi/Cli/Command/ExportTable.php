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
use Keboola\StorageApi\TableExporter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface,
	Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;


class ExportTable extends Command {

	public function configure()
	{
		$this
			->setName('export-table')
			->setDescription('Export data from table to file')
			->setDefinition(array(
				new InputArgument('tableId', InputArgument::REQUIRED, "target table"),
				new InputArgument('filePath', InputArgument::REQUIRED, "export csv file"),
				new InputOption('format', 'f', InputOption::VALUE_REQUIRED, "output format", "rfc"),
				new InputOption('gzip', 'g', InputOption::VALUE_NONE, "gzip file")
			));
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$sapiClient = $this->getSapiClient();
		if (!$sapiClient->tableExists($input->getArgument('tableId'))) {
			throw new \Exception("Table {$input->getArgument('tableId')} does not exist or is not accessible.");
		}

		$output->writeln("Table found ok");

		$startTime = time();

		$exporter = new TableExporter($sapiClient);

		$exporter->exportTable(
			$input->getArgument('tableId'),
			$input->getArgument('filePath'),
			array(
				'format' => $input->getOption('format'),
				'gzip' => $input->getOption('gzip'),
			)
		);

		$duration = time() - $startTime;

		$output->writeln("Export done in $duration secs.");


	}


}
