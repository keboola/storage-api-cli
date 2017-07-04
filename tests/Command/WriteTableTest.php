<?php

namespace Keboola\DockerBundle\Tests\Command;

use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Cli\Command\PurgeProject;
use Keboola\StorageApi\Cli\Command\WriteTable;
use Keboola\StorageApi\Cli\Console\Application;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\TableExporter;
use Keboola\Temp\Temp;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Filesystem\Filesystem;

class WriteTableTest extends \PHPUnit_Framework_TestCase
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
        $fs->dumpFile($fileName, "name,id\nfoo,1\nbar,2");
        $csv = new CsvFile($fileName);
        $client->createTable('in.c-test', 'some-table', $csv);
    }

    public function testExecute()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new WriteTable());
        $applicationTester = new ApplicationTester($application);
        $fs = new Filesystem();
        $fileName = $this->temp->getTmpFolder() . DIRECTORY_SEPARATOR . 'otherData.csv';
        $fs->dumpFile($fileName, "name,id\nfooBar,4\nbarBaz,5\nbazFoo,6");
        $applicationTester->run([
            'write-table',
            'tableId' => 'in.c-test.some-table',
            'filePath' => $fileName,
            '--token' => TEST_STORAGE_API_TOKEN,
        ]);

        $this->assertContains('Import done', $applicationTester->getDisplay());
        $this->assertEquals(0, $applicationTester->getStatusCode());
        // check for the results
        $client = new Client(['token' => TEST_STORAGE_API_TOKEN]);
        $exporter = new TableExporter($client);
        $fileName = $this->temp->getTmpFolder() . DIRECTORY_SEPARATOR . 'destination.csv';
        $exporter->exportTable('in.c-test.some-table', $fileName, []);
        $csv = new CsvFile($fileName);
        $results = [];
        foreach ($csv as $line) {
            $results[$line[1]] = $line[0];
        }
        $this->assertEquals(['name', 'id'], $csv->getHeader());
        $this->assertEquals([4 => 'fooBar', 5 => 'barBaz', 6 => 'bazFoo', 'id' => 'name'], $results);
    }

    public function testExecuteIncremental()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new WriteTable());
        $applicationTester = new ApplicationTester($application);
        $fs = new Filesystem();
        $fileName = $this->temp->getTmpFolder() . DIRECTORY_SEPARATOR . 'otherData.csv';
        $fs->dumpFile($fileName, "name,id\nfooBar,4\nbarBaz,5");
        $applicationTester->run([
            'write-table',
            'tableId' => 'in.c-test.some-table',
            'filePath' => $fileName,
            '--incremental' => 1,
            '--token' => TEST_STORAGE_API_TOKEN,
        ]);

        $this->assertContains('Import done', $applicationTester->getDisplay());
        $this->assertEquals(0, $applicationTester->getStatusCode());
        // check for the results
        $client = new Client(['token' => TEST_STORAGE_API_TOKEN]);
        $exporter = new TableExporter($client);
        $fileName = $this->temp->getTmpFolder() . DIRECTORY_SEPARATOR . 'destination.csv';
        $exporter->exportTable('in.c-test.some-table', $fileName, []);
        $csv = new CsvFile($fileName);
        $results = [];
        foreach ($csv as $line) {
            $results[$line[1]] = $line[0];
        }
        $this->assertEquals(['name', 'id'], $csv->getHeader());
        $this->assertEquals([1 => 'foo', 2 => 'bar', 4 => 'fooBar', 5 => 'barBaz', 'id' => 'name'], $results);
    }

    public function testExecuteExtendedDelimiter()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new WriteTable());
        $applicationTester = new ApplicationTester($application);
        $fs = new Filesystem();
        $fileName = $this->temp->getTmpFolder() . DIRECTORY_SEPARATOR . 'otherData.csv';
        $fs->dumpFile($fileName, "name\tid\nfooBar\t4\nbarBaz\t5\n");
        $applicationTester->run([
            'write-table',
            'tableId' => 'in.c-test.some-table',
            'filePath' => $fileName,
            '--incremental' => 1,
            '--delimiter' => "\t",
            '--token' => TEST_STORAGE_API_TOKEN,
        ]);

        $this->assertContains('Import done', $applicationTester->getDisplay());
        $this->assertEquals(0, $applicationTester->getStatusCode());
        // check for the results
        $client = new Client(['token' => TEST_STORAGE_API_TOKEN]);
        $exporter = new TableExporter($client);
        $fileName = $this->temp->getTmpFolder() . DIRECTORY_SEPARATOR . 'destination.csv';
        $exporter->exportTable('in.c-test.some-table', $fileName, []);
        $csv = new CsvFile($fileName);
        $results = [];
        foreach ($csv as $line) {
            $results[$line[1]] = $line[0];
        }
        $this->assertEquals(['name', 'id'], $csv->getHeader());
        $this->assertEquals([1 => 'foo', 2 => 'bar', 4 => 'fooBar', 5 => 'barBaz', 'id' => 'name'], $results);
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