<?php
/**
 * Created by JetBrains PhpStorm.
 * User: ondrej hlavacek
 * Date: 5/16/13
 * Time: 3:19 PM
 * To change this template use File | Settings | File Templates.
 */

namespace Keboola\StorageApi\Cli\Command;

use Keboola\Csv\CsvFile;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface,
	Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;


class TruncateTable extends Command {

	public function configure()
	{
		$this
			->setName('truncate-table')
			->setDescription('Remove all data from table')
			->setDefinition(array(
				new InputArgument('tableId', InputArgument::REQUIRED, "target table")
			));
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$sapiClient = $this->getSapiClient();
		if (!$sapiClient->tableExists($input->getArgument('tableId'))) {
			throw new \Exception("Table {$input->getArgument('tableId')} does not exist or is not accessible.");
		}

		$output->writeln("Table found ok");

		$output->writeln("Truncate start");
		$startTime = time();

		$tmpFile = $this->getTmpDir() . "/" . $input->getArgument('tableId') . ".csv";

		$sapiClient->exportTable(
			$input->getArgument('tableId'),
			$tmpFile,
			array("limit" => 1)
		);

		$csvFile = new CsvFile($tmpFile);
		$headFile = new CsvFile($tmpFile . ".head");
		$headFile->writeRow($csvFile->getHeader());


		exec("head -n 1 $tmpFile > $tmpFile.head");

		$sapiClient->writeTable(
			$input->getArgument('tableId'),
			$headFile
		);

		$this->destroyTmpDir();

		$duration = time() - $startTime;

		$output->writeln("Truncate done in $duration secs.");
	}


}