<?php
/**
 * Created by JetBrains PhpStorm.
 * User: martinhalamicek
 * Date: 5/16/13
 * Time: 2:34 PM
 * To change this template use File | Settings | File Templates.
 */

namespace Keboola\StorageApi\Cli\Console;

use Keboola\StorageApi\Client;
use Keboola\StorageApi\Cli\Command;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Shell;


class Application extends BaseApplication
{
	/**
	 * @var \Keboola\StorageApi\Client
	 */
	private $sapiClient;

	/**
	 * @var string Token
	 */
	private $sapiToken;

	/**
	 * @var OutputInterface
	 */
	private $output;


	public function __construct()
	{
		parent::__construct('Keboola Storage API Client', '@package_version@');

		$this->getDefinition()
			->addOption(new InputOption('token', null, InputOption::VALUE_REQUIRED, "Storage API Token"));

		$this->getDefinition()
			->addOption(new InputOption('--shell', null, InputOption::VALUE_NONE, 'Launch the shell.'));
	}

	public function doRun(InputInterface $input, OutputInterface $output)
	{
		if ($input->getParameterOption('--token')) {
			$this->sapiToken = $input->getParameterOption('--token');
			$this->sapiClient = null;
		}
		$this->output = $output;

		if (true === $input->hasParameterOption(array('--shell'))) {
			$shell = new Shell($this);
			$shell->setProcessIsolation($input->hasParameterOption(array('--process-isolation')));
			$shell->run();

			return 0;
		}

		return parent::doRun($input, $output);
	}

	/**
	 * @return Client
	 */
	public function getStorageApiClient()
	{
		if (!$this->sapiClient) {
			if (!$this->sapiToken) {
				throw new \RuntimeException('Token --token must be set');
			}
			$this->sapiClient = new Client($this->sapiToken);
			$logData = $this->sapiClient->getLogData();
			$this->output->writeln("Authorized as: {$logData['description']} ({$logData['owner']['name']})");
		}
		return $this->sapiClient;
	}


	public function getDefaultCommands()
	{
		return array_merge(array(
			new Command\ListBuckets(),
			new Command\CreateTable(),
			new Command\WriteTable(),
			new Command\TruncateTable(),
		), parent::getDefaultCommands());

	}

}