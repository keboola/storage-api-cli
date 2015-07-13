<?php
/**
 * Created by JetBrains PhpStorm.
 * User: martinhalamicek
 * Date: 5/16/13
 * Time: 3:19 PM
 * To change this template use File | Settings | File Templates.
 */

namespace Keboola\StorageApi\Cli\Command;

use Aws\S3\S3Client;
use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\ListConfigurationsOptions;
use Keboola\StorageApi\TableExporter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface,
	Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;


class BackupProject extends Command {

	public function configure()
	{
		$this
			->setName('backup-project')
			->setDescription('Backup whole project to AWS S3')
			->setHelp('AWS credentials should be provided AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY  environment variables.')
			->setDefinition([
				new InputArgument('bucket', InputArgument::REQUIRED, 'S3 bucket name'),
				new InputArgument('path', InputArgument::OPTIONAL, 'path in S3', '/'),
			]);

	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$s3 = S3Client::factory();
		$bucket = $input->getArgument('bucket');
		$basePath = $input->getArgument('path');
		$basePath = rtrim($basePath, '/') . '/';

		$sapiClient = $this->getSapiClient();
		$components = new Components($sapiClient);
		$tableExporter = new TableExporter($sapiClient);

		$tables =  $sapiClient->listTables(null, [
			'include' => 'attributes,columns,buckets'
		]);
		$output->write($this->format('Tables metadata'));
		$s3->putObject([
			'Bucket' => $bucket,
			'Key' => $basePath . 'tables.json',
			'Body' => json_encode($tables),
		]);
		$output->writeln($this->check());

		$output->write($this->format('Buckets metadata'));
		$s3->putObject([
			'Bucket' => $bucket,
			'Key' => $basePath . 'buckets.json',
			'Body' => json_encode($sapiClient->listBuckets()),
		]);
		$output->writeln($this->check());

		$output->write($this->format('Configurations'));
		$s3->putObject([
			'Bucket' => $bucket,
			'Key' => $basePath . 'configurations.json',
			'Body' => json_encode($components->listComponents((new ListConfigurationsOptions())
				->setInclude(['configuration', 'state']))),
		]);
		$output->writeln($this->check());

		$tablesCount = count($tables);
		foreach (array_values($tables) as $i => $table) {
			$output->write($this->format("Table $i/$tablesCount - {$table['id']}"));
			$tmpFile = $this->getTmpDir() . "/" . uniqid('table');
			if (!$table['isAlias']) {
				$tableExporter->exportTable($table['id'], $tmpFile, [
					'gzip' => true,
				]);
				$s3->putObject([
					'Bucket' => $bucket,
					'Key' => $basePath . str_replace('.', '/', $table['id']) . '.csv.gz',
					'Body' => fopen($tmpFile, 'r'),
				]);
				unlink($tmpFile);
				$output->writeln($this->check());
			} else {
				$output->writeln('<comment>Skipped (alias table)</comment>');
			}
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
