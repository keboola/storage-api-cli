<?php
namespace Keboola\DockerBundle\Tests\Command;

use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Cli\Command\CopyTable;
use Keboola\StorageApi\Cli\Command\PurgeProject;
use Keboola\StorageApi\Cli\Console\Application;
use Keboola\StorageApi\Client;
use Keboola\Temp\Temp;
use Symfony\Component\Console\Tester\ApplicationTester;

class CopyTableTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        // add configs
        $client = new Client(['token' => TEST_STORAGE_API_TOKEN]);

        // add table
        $client->createBucket("main", Client::STAGE_IN);
        $temp = new Temp();
        $temp->initRunFolder();
        $filename = $temp->createFile("table.csv");
        $csv = new CsvFile($filename->getPathname());
        $csv->writeRow(["Id", "Name"]);
        $csv->writeRow(["1", "test"]);


        $client->createTable("in.c-main", "testNoPk", $csv);

        $client->createTable("in.c-main", "testPk", $csv);
        $client->createTablePrimaryKey("in.c-main.testPk", ["Id"]);

        $client->createTable("in.c-main", "testCompositePk", $csv);
        $client->createTablePrimaryKey("in.c-main.testCompositePk", ["Id", "Name"]);

        // check stats
        $this->assertCount(3, $client->listTables());
    }

    public function testExecute()
    {
        $tablesPk = [
            'in.c-main.testNoPk' => [],
            'in.c-main.testPk' => ['Id'],
            'in.c-main.testCompositePk' => ['Id', 'Name'],
        ];

        foreach ($tablesPk as $tableId => $primaryKey) {
            // run command
            $application = new Application();
            $application->setAutoExit(false);
            $application->add(new CopyTable());
            $applicationTester = new ApplicationTester($application);
            $applicationTester->run([
                'copy-table',
                'sourceTableId' => $tableId,
                'destinationTableId' => $tableId . 'Copy',
                '--token' => TEST_STORAGE_API_TOKEN,
            ]);

            $this->assertEquals(0, $applicationTester->getStatusCode());

            // check for the results
            $client = new Client(['token' => TEST_STORAGE_API_TOKEN]);

            $table = $client->getTable($tableId . 'Copy');
            $this->assertArrayHasKey('primaryKey', $table);
            $this->assertEquals($primaryKey, $table['primaryKey']);
        }
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