<?php
/**
 * Created by JetBrains PhpStorm.
 * User: ondrej hlavacek
 * Date: 5/16/13
 * Time: 3:19 PM
 * To change this template use File | Settings | File Templates.
 */

namespace Keboola\StorageApi\Cli\Command;

use GuzzleHttp\Client;
use Keboola\Csv\CsvFile;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Filesystem;

class RestoreTableFromImports extends Command
{

    private $importEventsCount = 0;

    private $importedEventsCount = 0;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var InputInterface
     */
    private $input;

    private $isDryRun = false;

    private $createdTableId = null;

    private $restoreDate = null;

    /**
     * @var Client
     */
    private $httpClient;

    public function configure()
    {
        $this
            ->setName('restore-table-from-imports')
            ->setDescription('Creates new table from source table imports')
            ->setDefinition(array(
                new InputArgument('sourceTableId', InputArgument::REQUIRED, "source table"),
                new InputArgument('destinationTableId', InputArgument::REQUIRED, "destination table"),
                new InputOption('dry-run', null, InputOption::VALUE_NONE, 'Just analyze events, dont perform any writes'),
                new InputOption('restore-date', null, InputOption::VALUE_REQUIRED, 'Date to restore')
            ))
            ->setHelp('
				Creates new table and restores data from source table imports. It start with first full
				load and then performs all subsequent increments.
				Data can be restored to specified data - import events until this date are processed.
			');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->isDryRun = $input->getOption('dry-run');
        $this->httpClient = new Client();

        if ($this->isDryRun) {
            $output->writeln('<info>Executing dry run</info>');
        }

        $sapiClient = $this->getSapiClient();
        $sourceTableId = $input->getArgument('sourceTableId');
        if (!$sapiClient->tableExists($sourceTableId)) {
            throw new \Exception("Table {$sourceTableId} does not exist or is not accessible.");
        }
        if ($sapiClient->tableExists($input->getArgument('destinationTableId'))) {
            throw new \Exception("Table {$input->getArgument('destinationTableId')} cannot be overwritten.");
        }

        $destinationTable = $input->getArgument('destinationTableId');
        list($dStage, $dBucket, $dTable) = explode(".", $destinationTable);
        if (!$sapiClient->bucketExists($dStage . "." . $dBucket)) {
            throw new \Exception("Bucket {$dStage}.{$dBucket} does not exist or is not accessible.");
        }

        $this->restoreDate = null;
        if ($input->getOption('restore-date') !== null) {
            $this->restoreDate = strtotime($input->getOption('restore-date'));
            if ($this->restoreDate === false) {
                throw new \Exception("Invalid restore date format: " . $input->getOption('restore-date'));
            }
            $output->writeln('Data will be restored to date:  ' . date('c', $this->restoreDate));
        }

        $output->writeln("Table found ok");

        $maxEventId = false;
        $importEvents = array();
        do {
            $events = $sapiClient->listTableEvents($sourceTableId, array(
                'tableId' => $sourceTableId,
                'limit' => 100,
                'maxId' => $maxEventId,
            ));

            foreach ($events as $event) {
                if (!($this->isImportEvent($event) && $this->isEventBeforeRestoreDate($event))) {
                    continue;
                }
                $importEvents[] = $event;

                if ($this->isFullLoadEvent($event)) {
                    break(2);
                }
            }

            $lastEvent = end($events);
            $maxEventId = $lastEvent['id'];
        } while (!empty($events));

        $this->importEventsCount = count($importEvents);
        $output->writeln('Import events found ' . $this->importEventsCount);

        // sort from oldest to newest
        usort($importEvents, function ($event1, $event2) {
            return $event1['id'] < $event2['id'] ? -1 : 1;
        });

        foreach ($importEvents as $event) {
            // re-fetch event for fresh S3 link
            $this->processEvent($sapiClient->getEvent($event['id']));
        }
    }

    private function isImportEvent($event)
    {
        return $event['event'] == 'storage.tableImportDone';
    }

    private function isFullLoadEvent($event)
    {
        return isset($event['params']['incremental']) && $event['params']['incremental'] == false;
    }

    private function isEventBeforeRestoreDate($event)
    {
        if (!$this->restoreDate) {
            return true;
        }
        return strtotime($event['created']) < $this->restoreDate;
    }

    private function processEvent(array $event)
    {
        $this->output->writeln("event $event[id]: start");
        $this->output->writeln("created: $event[created]");

        $attachment = reset($event['attachments']);
        if (!$attachment) {
            throw new \Exception('Attachment not found for import event: ' . $event['id']);
        }

        if (!isset($event['params']['incremental'])) {
            throw new \Exception("Event $event[id] incremental flag is not set");
        }

        if (!isset($event['params']['partial'])) {
            throw new \Exception("Event $event[id] partial flag not set");
        }

        if (!isset($event['params']['csv'])) {
            throw new \Exception("Event $event[id] csv settings are not set");
        }

        if (!array_key_exists('delimiter', $event['params']['csv'])) {
            throw new \Exception("Event $event[id] csv delimiter not set");
        }
        if (!array_key_exists('enclosure', $event['params']['csv'])) {
            throw new \Exception("Event $event[id] csv enclosure not set");
        }

        $csvParams = array();
        if (is_string($event['params']['csv']['delimiter']) && !empty($event['params']['csv']['delimiter'])) {
            $csvParams['delimiter'] = $event['params']['csv']['delimiter'];
        } else {
            $csvParams['delimiter'] = CsvFile::DEFAULT_DELIMITER;
        }

        if (is_string($event['params']['csv']['enclosure']) && !empty($event['params']['csv']['enclosure'])) {
            $csvParams['enclosure'] = $event['params']['csv']['enclosure'];
        } else {
            $csvParams['enclosure'] = CsvFile::DEFAULT_ENCLOSURE;
        }

        if (isset($event['params']['csv']['escapedBy']) && is_string($event['params']['csv']['escapedBy'])) {
            $csvParams['escapedBy'] = $event['params']['csv']['escapedBy'];
        } else {
            $csvParams['escapedBy'] = "";
        }

        $fileName = "";
        if (isset($event['params']['source']) && isset($event['params']['source']['fileName'])) {
            $fileName = $event['params']['source']['fileName'];
        } elseif (isset($event['params']['csv']) && isset($event['params']['csv']['file'])) {
            $fileName = $event['params']['csv']['file'];
        } elseif (isset($event['params']['file'])) {
            $fileName = $event['params']['file'];
        } else {
            $fileName = $attachment['name'];
        }

        $importOptions = array(
            'incremental' => (int)$event['params']['incremental'],
            'partial' => (int)$event['params']['partial'],
        );

        $logData = array_merge($importOptions, array(
            'file' => $fileName,
            'delimiter' => $csvParams['delimiter'],
            'enclosure' => $csvParams['enclosure'],
            'escapedBy' => isset($csvParams['escapedBy']) ? $csvParams['escapedBy'] : "",
        ));
        $this->output->writeln('Import params:');
        $this->output->write($this->getNestedFormatterHelper()->format($logData, 1));

        if (!$this->isDryRun) {
            // fetch from s3
            $tmpFile = $this->getTmpDir() . '/' . pathinfo($fileName, PATHINFO_FILENAME) . '.' . pathinfo($fileName, PATHINFO_EXTENSION);
            $this->fetchFileFromBackup($attachment['url'], $tmpFile);

            $csvFile = new CsvFile(
                $tmpFile,
                $csvParams['delimiter'],
                $csvParams['enclosure'],
                $csvParams['escapedBy']
            );

            $this->output->writeln(' Backup fetched from S3. File size: ' . filesize($tmpFile));

            if (!$this->createdTableId) {
                $this->createdTableId = $this->createTable($csvFile, $importOptions);
            } else {
                // import table
                $this->getSapiClient()
                    ->writeTableAsync($this->input->getArgument('destinationTableId'), $csvFile, $importOptions);
            }
            $fs = new Filesystem();
            $fs->remove($tmpFile);
        }

        $this->importedEventsCount++;
        $this->output->writeln("event $event[id]: end. {$this->importedEventsCount}/{$this->importEventsCount}");
    }

    private function createTable(CsvFile $csvFile, $options)
    {
        $sapiClient = $this->getSapiClient();
        $sourceTableInfo = $sapiClient->getTable($this->input->getArgument('sourceTableId'));
        $createOptions = array_merge($options, array());
        if (isset($sourceTableInfo["primaryKey"]) && count($sourceTableInfo["primaryKey"])) {
            $createOptions["primaryKey"] = $sourceTableInfo["primaryKey"][0];
        }

        list($dStage, $dBucket, $dTable) = explode(".", $this->input->getArgument('destinationTableId'));

        return $sapiClient->createTableAsync(
            $dStage . "." . $dBucket,
            $dTable,
            $csvFile,
            $createOptions
        );
    }

    private function fetchFileFromBackup($url, $destinationPath)
    {
        $fh = fopen($destinationPath, 'w');
        if (!$fh) {
            throw new \Exception("Could not open file");
        }
        $request = $this->httpClient->get($url);
        $request->setResponseBody($fh);
        $request->send();
    }
}
