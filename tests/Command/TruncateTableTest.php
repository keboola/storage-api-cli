<?php

namespace Keboola\DockerBundle\Tests\Command;

use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Cli\Command\PurgeProject;
use Keboola\StorageApi\Cli\Command\TruncateTable;
use Keboola\StorageApi\Cli\Console\Application;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\TableExporter;
use Keboola\Temp\Temp;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Filesystem\Filesystem;

class TruncateTableTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Temp
     */
    private $temp;

    public function setUp()
    {
        $this->temp = new Temp('sapi-cli-test');
        $client = new Client(['token' => TEST_STORAGE_API_TOKEN]);
        $client->createBucket('test', 'in');
        $fs = new Filesystem();
        $fileName = $this->temp->getTmpFolder() . DIRECTORY_SEPARATOR . 'someData.csv';
        $fs->dumpFile($fileName, "name,id\nfoo,1\nbar,2\nbaz,3");
        $csv = new CsvFile($fileName);
        $client->createTable('in.c-test', 'some-table', $csv);
    }

    public function testExecute()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new TruncateTable());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'truncate-table',
            'tableId' => 'in.c-test.some-table',
            '--token' => TEST_STORAGE_API_TOKEN,
        ]);

        self::assertContains('Truncate done', $applicationTester->getDisplay());
        self::assertEquals(0, $applicationTester->getStatusCode());
        // check for the results
        $client = new Client(['token' => TEST_STORAGE_API_TOKEN]);
        $exporter = new TableExporter($client);
        $fileName = $this->temp->getTmpFolder() . DIRECTORY_SEPARATOR . 'destination.csv';
        $exporter->exportTable('in.c-test.some-table', $fileName, []);
        $csv = new CsvFile($fileName);
        $results = [];
        foreach ($csv as $line) {
            $results[] = $line;
        }
        self::assertEquals(['name', 'id'], $csv->getHeader());
        self::assertCount(1, $results);
    }

    public function tearDown()
    {
        // run command
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new PurgeProject());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'purge-project',
            '--token' => TEST_STORAGE_API_TOKEN
        ]);
    }
}
