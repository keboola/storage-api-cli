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
use Keboola\Symfony\Console\Helper\NestedFormatterHelper\NestedFormatterHelper;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
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
		parent::__construct('Keboola Storage API CLI', '@package_version@');

		$this->getDefinition()
			->addOption(new InputOption('token', null, InputOption::VALUE_REQUIRED, "Storage API Token"));

		$this->getDefinition()
			->addOption(new InputOption('--shell', null, InputOption::VALUE_NONE, 'Launch the shell.'));
	}

	public function doRun(InputInterface $input, OutputInterface $output)
	{
		$output->getFormatter()
			->setStyle('success', new OutputFormatterStyle('green'));

		$output->getFormatter()
			->setStyle('warning', new OutputFormatterStyle('yellow'));

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
			$this->sapiClient = new Client(
				$this->sapiToken,
				null,
				$this->userAgent()
			);
			$logData = $this->sapiClient->getLogData();
			$this->output->writeln("Authorized as: {$logData['description']} ({$logData['owner']['name']})");
		}
		return $this->sapiClient;
	}

	public function userAgent()
	{
		return "{$this->getName()}/{$this->getVersion()}";
	}

	public function getDefaultCommands()
	{
		return array_merge(array(
			new Command\ListBuckets(),
			new Command\CopyBucket(),
			new Command\DeleteBucket(),
			new Command\CreateTable(),
			new Command\DeleteTable(),
			new Command\WriteTable(),
			new Command\CopyTable(),
			new Command\RestoreTableFromImports(),
			new Command\TruncateTable(),
			new Command\ListEvents(),
			new Command\ExportTable(),
		), parent::getDefaultCommands());
	}

	protected function getDefaultHelperSet()
	{
		$helperSet = parent::getDefaultHelperSet();
		$helperSet->set(new NestedFormatterHelper());
		return $helperSet;
	}

}
