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


class ListEvents extends Command {

	public function configure()
	{
		$this
			->setName('list-events')
			->setDescription('List events')
			->setDefinition(array(
				new InputOption('component', null, InputOption::VALUE_REQUIRED, 'Component name'),
				new InputOption('runId', null, InputOption::VALUE_REQUIRED, 'Run id'),
				new InputOption('configurationId', null, InputOption::VALUE_REQUIRED, 'Configuration id'),
			));
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$sapiClient = $this->getSapiClient();
		$events = $sapiClient->listEvents(array(
			'limit' => 100,
			'offset' => 0,
			'component' => $input->getOption('component'),
			'runId' => $input->getOption('runId'),
			'configurationId' => $input->getOption('configurationId'),
		));

		$formatter = $this->getFormatterHelper();
		foreach ($events as $event) {
			$output->write($event['created']);
			$output->write(" ");
			$output->write($event['id']);
			$output->write(" ");
			$output->write($event['component'] . (isset($event['configurationId']) ? "($event[configurationId])" : ""));
			$output->write($formatter->formatSection($event['event'], '', $event['type']));
			$output->writeln($event['message']);
		}
	}


}