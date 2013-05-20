<?php
/**
 * Created by JetBrains PhpStorm.
 * User: martinhalamicek
 * Date: 5/16/13
 * Time: 3:10 PM
 * To change this template use File | Settings | File Templates.
 */

namespace Keboola\StorageApi\Cli\Command;

use Symfony\Component\Console\Command\Command as BaseCommand;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Cli\Console\Application;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


abstract class Command extends BaseCommand
{

	/**
	 * @var Client
	 */
	private $sapiClient;

	/**
	 * @var string
	 */
	private $tmpDir = "";

	/**
	 * @param Client $client
	 * @return $this
	 */
	public function setSapiClient(Client $client)
	{
		$this->sapiClient = $client;
		return $this;
	}


	/**
	 * @return Client
	 */
	public function getSapiClient()
	{
		if ($this->sapiClient === null) {
			$application = $this->getApplication();
			if ($application instanceof Application) {
				$this->setSapiClient($application->getStorageApiClient());
			} else {
				throw new \RuntimeException("Storage api client must be injected.");
			}
		}

		return $this->sapiClient;
	}

	/**
	 *
	 * Get or create temporary folder
	 *
	 * @return string
	 */
	public function getTmpDir()
	{
		if ($this->tmpDir == "") {
			$fs = new Filesystem();
			$dir = "/tmp/sapi-cli-" . uniqid();
			$fs->mkdir($dir);
			$this->tmpDir = $dir;
		}
		return $this->tmpDir;
	}

	/**
	 * Deletes temporary dir and all its contents
	 */
	public function destroyTmpDir()
	{
		if ($this->tmpDir != "") {
			$fs = new Filesystem();
			$fs->remove($this->tmpDir);
		}
		$this->tmpDir = "";
	}

	protected function initialize(InputInterface $input, OutputInterface $output)
	{
		// require sapi client
		$this->getSapiClient();
	}

	/**
	 * @return FormatterHelper
	 */
	public function getFormatterHelper()
	{
		return $this->getHelper('formatter');
	}

	/**
	 * @return DialogHelper
	 */
	public function getDialogHelper()
	{
		return $this->getHelper('dialog');
	}

}