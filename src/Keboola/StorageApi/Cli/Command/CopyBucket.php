<?php
/**
 * CopyBucket.php
 *
 * @author: Miroslav Čillík <miro@keboola.com>
 * @created: 15.8.13
 */

namespace Keboola\StorageApi\Cli\Command;


use Keboola\StorageApi\Client;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CopyBucket extends Command
{
	public function configure()
	{
		$this
			->setName('copy-bucket')
			->setDescription('Copy bucket with all tables in it')
			->setDefinition(array(
				new InputArgument('sourceBucketId', InputArgument::REQUIRED, "source bucket"),
				new InputArgument('destinationBucketId', InputArgument::REQUIRED, "destination bucket"),
				new InputOption('dstToken', null, InputOption::VALUE_OPTIONAL, "Destination Storage API Token")
			));
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$sapiClient = $this->getSapiClient();

		$srcBucketId = $input->getArgument('sourceBucketId');
		$dstBucketId = $input->getArgument('destinationBucketId');

		if (!$sapiClient->bucketExists($srcBucketId)) {
			throw new \Exception("Bucket {$srcBucketId} does not exist or is not accessible.");
		}

		$output->writeln("Source bucket found ok");
		$srcTables = $sapiClient->listTables($srcBucketId);

		if ($input->hasParameterOption('--dstToken')) {
			$sapiClient = new Client(
				$input->getParameterOption('--dstToken'),
				null,
				$this->getApplication()->userAgent()
			);
		}

		if (!$sapiClient->bucketExists($dstBucketId)) {
			throw new \Exception("Bucket {$dstBucketId} does not exist or is not accessible.");
		}

		foreach ($srcTables as $srcTable) {
			$command = $this->getApplication()->find('copy-table');

			list($sStage, $sBucket, $sTable) = explode('.', $srcTable['id']);

			$arguments = array(
				'command'               => 'copy-table',
				'--token'               => $input->getParameterOption('--token'),
				'sourceTableId'         => $srcTable['id'],
				'destinationTableId'    => $dstBucketId . '.' . $sTable
			);

			if ($input->hasParameterOption('--dstToken')) {
				$arguments['--dstToken'] = $input->getParameterOption('--dstToken');
			}

			$cmdInput = new ArrayInput($arguments);

			$output->writeln("Copying table {$srcTable['id']}");
			$return = $command->run($cmdInput, $output);

			if ($return === 0) {
				$output->writeln("Success");
			}
		}
	}
}
