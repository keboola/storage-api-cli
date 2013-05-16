<?php
/**
 * Created by JetBrains PhpStorm.
 * User: martinhalamicek
 * Date: 5/16/13
 * Time: 3:19 PM
 * To change this template use File | Settings | File Templates.
 */

namespace Keboola\StorageApi\Cli\Command;

use Symfony\Component\Console\Input\InputInterface,
	Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;


class ListBuckets extends Command {

	public function configure()
	{
		$this
			->setName('list-buckets')
			->setDescription('list all available buckets')
			->addOption('include-tables', 't', InputOption::VALUE_NONE, "Include also tables in list");
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$output->writeln("Buckets:");
		foreach ($this->getSapiClient()->listBuckets() as $bucket) {
			$output->writeln(" - $bucket[id]");
			if (!$input->getOption('include-tables')) {
				continue;
			}
			foreach ($this->getSapiClient()->listTables($bucket['id']) as $table) {
				$output->writeln("   - $table[id]");
			}
		}
	}


}