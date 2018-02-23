<?php
/**
 * Created by JetBrains PhpStorm.
 * User: martinhalamicek
 * Date: 5/16/13
 * Time: 3:19 PM
 * To change this template use File | Settings | File Templates.
 */

namespace Keboola\StorageApi\Cli\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class ListEvents extends Command
{

    public function configure(): void
    {
        $this
            ->setName('list-events')
            ->setDescription('List events')
            ->setDefinition(array(
                new InputOption('component', null, InputOption::VALUE_REQUIRED, 'Component name'),
                new InputOption('runId', null, InputOption::VALUE_REQUIRED, 'Run id'),
                new InputOption('configurationId', null, InputOption::VALUE_REQUIRED, 'Configuration id'),
                new InputOption('limit', null, InputOption::VALUE_REQUIRED, 'pagination - count per page', 100),
                new InputOption('offset', null, InputOption::VALUE_REQUIRED, 'pagination - offset', 0),
            ));
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $sapiClient = $this->getSapiClient();

        $options = array(
            'limit' => $input->getOption('limit'),
            'offset' => $input->getOption('offset'),
            'component' => $input->getOption('component'),
            'runId' => $input->getOption('runId'),
            'configurationId' => $input->getOption('configurationId'),
        );

        do {
            $events = $sapiClient->listEvents($options);

            $formatter = $this->getFormatterHelper();
            $maxId = null;
            foreach ($events as $event) {
                $output->write($event['created']);
                $output->write(" ");
                $output->write($event['id']);
                $output->write(" ");
                $output->write(
                    $event['component'] . (isset($event['configurationId']) ? "($event[configurationId])" : "")
                );
                $output->write($formatter->formatSection($event['event'], '', $event['type']));
                $output->writeln($event['message']);

                $maxId = $event['id'];
            }
            $options['maxId'] = $maxId; // older events
        } while ($input->isInteractive() &&
            $this->getQuestionHelper()->ask(
                $input,
                $output,
                new ConfirmationQuestion('<question>Load older events?</question>', false)
            )
        );
    }
}
