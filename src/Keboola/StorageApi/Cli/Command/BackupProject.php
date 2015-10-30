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
use Keboola\StorageApi\Options\GetFileOptions;
use Keboola\StorageApi\TableExporter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface,
	Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Filesystem;


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
                new InputArgument('region', InputArgument::OPTIONAL, 'region', 'us-east-1'),
				new InputOption('structure-only', '-s', InputOption::VALUE_NONE, 'Backup only structure')
			]);

	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$s3 = S3Client::factory([
            'region' => $input->getArgument('region'),
        ]);
		$bucket = $input->getArgument('bucket');
		$basePath = $input->getArgument('path');
		$basePath = rtrim($basePath, '/') . '/';

		$sapiClient = $this->getSapiClient();
		$components = new Components($sapiClient);

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
		$onlyStructure = $input->getOption('structure-only');
		usort($tables, function($a, $b) {
			return strcmp($a["id"], $b["id"]);
		});
		foreach (array_values($tables) as $i => $table) {
			$output->write($this->format("Table $i/$tablesCount - {$table['id']}"));
			if ($onlyStructure && $table['bucket']['stage'] !== 'sys') {
				$output->writeln('<comment>Skipped (not sys table)</comment>');
			} else if (!$table['isAlias']) {
				$this->exportTable($table['id'], $s3, $bucket, $basePath);
				$output->writeln($this->check());
			} else {
				$output->writeln('<comment>Skipped (alias table)</comment>');
			}
		}
	}

	private function exportTable($tableId, S3Client $targetS3, $targetBucket, $targetBasePath)
	{
		$client = $this->getSapiClient();
		$fileId = $client->exportTableAsync($tableId, [
			'gzip' => true,
		]);
		$fileInfo = $client->getFile($fileId["file"]["id"], (new GetFileOptions())->setFederationToken(true));

		// Initialize S3Client with credentials from Storage API
		$s3Client = S3Client::factory(array(
			"key" => $fileInfo["credentials"]["AccessKeyId"],
			"secret" => $fileInfo["credentials"]["SecretAccessKey"],
			"token" => $fileInfo["credentials"]["SessionToken"]
		));


		$fs = new Filesystem();
		if ($fileInfo['isSliced'] === true) {
			// Download manifest with all sliced files
			$manifest = json_decode(file_get_contents($fileInfo["url"]), true);

			// Download all slices
			$tmpFilePath = $this->getTmpDir() . '/' . uniqid('sapi-export-');
			foreach($manifest["entries"] as $i => $part) {
				$fileKey = substr($part["url"], strpos($part["url"], '/', 5) + 1);
				$filePath = $tmpFilePath . '_' . md5(str_replace('/', '_', $fileKey));
				$s3Client->getObject(array(
					'Bucket' => $fileInfo["s3Path"]["bucket"],
					'Key'    => $fileKey,
					'SaveAs' => $filePath
				));
				$targetS3->putObject([
					'Bucket' => $targetBucket,
					'Key' => $targetBasePath . str_replace('.', '/', $tableId) . '.part_' . $i . '.csv.gz',
					'Body' => fopen($filePath, 'r'),
				]);
				$fs->remove($tmpFilePath);
			}
		} else {
			$tmpFilePath = $this->getTmpDir() . "/" . uniqid('table');
			$s3Client->getObject(array(
				'Bucket' => $fileInfo["s3Path"]["bucket"],
				'Key'    => $fileInfo["s3Path"]["key"],
				'SaveAs' => $tmpFilePath
			));

			$targetS3->putObject([
				'Bucket' => $targetBucket,
				'Key' => $targetBasePath . str_replace('.', '/', $tableId) . '.csv.gz',
				'Body' => fopen($tmpFilePath, 'r'),
			]);
			$fs->remove($tmpFilePath);
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
