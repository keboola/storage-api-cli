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
use Keboola\StorageApi\Client;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface,
	Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;


class CopyTable extends Command {

	public function configure()
	{
		$this
			->setName('copy-table')
			->setDescription('Copy table with PK, indexes and attributes (transferring nongzipped data)')
			->setDefinition(array(
				new InputArgument('sourceTableId', InputArgument::REQUIRED, "source table"),
				new InputArgument('destinationTableId', InputArgument::REQUIRED, "destination table"),
				new InputArgument('dstToken', InputArgument::OPTIONAL, "Destination Storage API Token")
			));
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$sapiClient = $this->getSapiClient();
		if (!$sapiClient->tableExists($input->getArgument('sourceTableId'))) {
			throw new \Exception("Table {$input->getArgument('sourceTableId')} does not exist or is not accessible.");
		}

		$output->writeln("Source table found ok");

		$output->writeln("Copying {$input->getArgument('sourceTableId')} to {$input->getArgument('destinationTableId')}");
		$startTime = time();

		// Table metadata
		$tableInfo = $sapiClient->getTable($input->getArgument('sourceTableId'));

		$createOptions = array();
		if (isset($tableInfo["primaryKey"]) && count($tableInfo["primaryKey"])) {
			$createOptions["primaryKey"] = $tableInfo["primaryKey"][0];
		}

		// Download the table
		$tmpFile = $this->getTmpDir() . "/" . $input->getArgument('sourceTableId') . ".csv";

		$sapiClient->exportTable(
			$input->getArgument('sourceTableId'),
			$tmpFile
		);

		$sapiClientDst = $sapiClient;
		// Destination token
		if ($input->getArgument('dstToken')) {

			$output->writeln("Setting destination token");

			$sapiClientDst = new Client([
				'token' => $input->getArgument('dstToken'),
				'userAgent' => $this->getApplication()->userAgent(),
			]);
		}

		if ($sapiClientDst ->tableExists($input->getArgument('destinationTableId'))) {
			throw new \Exception("Table {$input->getArgument('destinationTableId')} cannot be overwritten.");
		}

		$destinationTable = $input->getArgument('destinationTableId');
		list($dStage, $dBucket, $dTable) = explode(".", $destinationTable);
		if (!$sapiClientDst ->bucketExists($dStage . "." . $dBucket)) {
			throw new \Exception("Bucket {$dStage}.{$dBucket} does not exist or is not accessible.");
		}

		$output->writeln("Destination table found ok");

		// Upload the table
		$csvFile = new CsvFile($tmpFile);
		$sapiClientDst ->createTableAsync(
			$dStage . "." . $dBucket,
			$dTable,
			$csvFile,
			$createOptions
		);

		// Set indexes
		if ($tableInfo["indexedColumns"] && count($tableInfo["indexedColumns"])) {
			foreach($tableInfo["indexedColumns"] as $index) {
				if (in_array($index, $tableInfo["primaryKey"])) {
					continue;
				}
				$sapiClientDst->markTableColumnAsIndexed($destinationTable, $index);
			}
		}

		// Set attributes
		if ($tableInfo["attributes"] && count($tableInfo["attributes"])) {
			foreach($tableInfo["attributes"] as $attribute) {
				$sapiClientDst->setTableAttribute($destinationTable, $attribute["name"], $attribute["value"], $attribute["protected"]);
			}

		}

		// All done, cleanup
		$this->destroyTmpDir();

		$duration = time() - $startTime;

		$output->writeln("Table {$input->getArgument('sourceTableId')} copied to {$input->getArgument('destinationTableId')} in $duration secs.");
	}


}
