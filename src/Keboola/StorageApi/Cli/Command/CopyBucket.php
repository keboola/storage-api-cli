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
				new InputArgument('dstToken', null, InputArgument::OPTIONAL, "Destination Storage API Token")
			));
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$sapiClient = $this->getSapiClient();

		$srcBucketId = $input->getArgument('sourceBucketId');
		$dstBucketId = $input->getArgument('destinationBucketId');

		if (!$sapiClient->bucketExists($srcBucketId)) {
			throw new \Exception("Source bucket {$srcBucketId} does not exist or is not accessible.");
		}

		$output->writeln("Source bucket found ok");

		// Different token
		if ($input->getArgument('dstToken')) {
			$sapiClientDst = new Client(
				$input->getArgument('dstToken'),
				null,
				$this->getApplication()->userAgent()
			);
		} else {
			$sapiClientDst = $sapiClient;
		}

		if ($sapiClientDst->bucketExists($dstBucketId)) {
			throw new \Exception("Destination bucket {$dstBucketId} already exists.");
		}


		$srcBucketInfo = $sapiClient->getBucket($srcBucketId);
		list($dstBucketStage, $dstBucketName) = explode(".", $dstBucketId);
		$dstBucketDesc = "Copy of $srcBucketId\n" . $srcBucketInfo["description"];

		// Remove c- prefix
		if (substr($dstBucketName, 0, 2) == "c-") {
			$dstBucketName = substr($dstBucketName, 2);
		}

		// Create bucket
		$sapiClientDst->createBucket($dstBucketName, $dstBucketStage, $dstBucketDesc);

		// Copy attributes
		foreach($srcBucketInfo["attributes"] as $attribute) {
			$sapiClientDst->setBucketAttribute($dstBucketId, $attribute["name"], $attribute["value"], $attribute["protected"]);
		}

		// Copy tables
		foreach ($srcBucketInfo["tables"] as $srcTable) {
			$command = $this->getApplication()->find('copy-table');

			list($sStage, $sBucket, $sTable) = explode('.', $srcTable['id']);

			$arguments = array(
				'command'               => 'copy-table',
				'--token'               => $input->getParameterOption('--token'),
				'sourceTableId'         => $srcTable['id'],
				'destinationTableId'    => $dstBucketId . '.' . $sTable
			);

			if ($input->getArgument('dstToken')) {
				$arguments['dstToken'] = $input->getArgument('dstToken');
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
