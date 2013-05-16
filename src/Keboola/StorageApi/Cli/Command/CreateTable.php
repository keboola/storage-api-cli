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
use Symfony\Component\Console\Input\InputInterface,
	Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;


class CreateTable extends Command {

	public function configure()
	{
		$this
			->setName('create-table')
			->setDescription('Create table in bucket')
			->setDefinition(array(
				new InputArgument('bucketId', InputArgument::REQUIRED, "destination bucket"),
				new InputArgument('name', InputArgument::REQUIRED, "table name"),
				new InputArgument('filePath', InputArgument::REQUIRED, "source csv file path, table will be created from file"),
			));
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$sapiClient = $this->getSapiClient();
		if (!$sapiClient->bucketExists($input->getArgument('bucketId'))) {
			throw new \Exception("Bucket {$input->getArgument('bucketId')} does not exist or is not accessible.");
		}

		$output->writeln("Bucket found ok");

		$filePath = $input->getArgument('filePath');
		if (!is_file($filePath)) {
			throw new Exception("File $filePath does not exist.");
		}

		$output->writeln("Table created start");

		$tableId = $sapiClient->createTable(
			$input->getArgument('bucketId'),
			$input->getArgument('name'),
			new CsvFile($filePath)
		);

		$output->writeln("Table create end");
		$output->writeln("Table id: $tableId");
	}


}