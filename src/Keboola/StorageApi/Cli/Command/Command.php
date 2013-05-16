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


abstract class Command extends BaseCommand
{

	/**
	 * @var Client
	 */
	private $sapiClient;

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
}