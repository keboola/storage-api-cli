<?php
namespace Keboola\StorageApi\Cli\Tests\Command;

use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Cli\Command\CopyTable;
use Keboola\StorageApi\Cli\Command\PurgeProject;
use Keboola\StorageApi\Cli\Console\Application;
use Keboola\StorageApi\Client;
use Keboola\Temp\Temp;
use Symfony\Component\Console\Tester\ApplicationTester;

class CopyTableTest extends BaseTest
{
    /**
     * @var Temp
     */
    private $temp;

    public function setUp(): void
    {
        // add configs
        $client = $this->createStorageClient();

        // add table
        $client->createBucket("main", Client::STAGE_IN);
        $this->temp = new Temp();
        $this->temp->initRunFolder();
        $filename = $this->temp->createFile("table.csv");
        $csv = new CsvFile($filename->getPathname());
        $csv->writeRow(["Id", "Name"]);
        $csv->writeRow(["1", "test"]);

        $client->createTable("in.c-main", "testNoPk", $csv);

        $client->createTable("in.c-main", "testPk", $csv);
        $client->createTablePrimaryKey("in.c-main.testPk", ["Id"]);

        $client->createTable("in.c-main", "testCompositePk", $csv);
        $client->createTablePrimaryKey("in.c-main.testCompositePk", ["Id", "Name"]);

        // check stats
        self::assertCount(3, $client->listTables());
    }

    public function testExecute(): void
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

            self::assertEquals(0, $applicationTester->getStatusCode());

            // check for the results
            $client = $this->createStorageClient();

            $table = $client->getTable($tableId . 'Copy');
            self::assertArrayHasKey('primaryKey', $table);
            self::assertEquals($primaryKey, $table['primaryKey']);
        }
    }

    public function tearDown(): void
    {
        // run command
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new PurgeProject());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'purge-project',
            '--token' => TEST_STORAGE_API_TOKEN,
        ]);
    }
}
