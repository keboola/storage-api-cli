<?php

namespace Keboola\DockerBundle\Tests\Command;

use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Cli\Command\DeleteMetadata;
use Keboola\StorageApi\Cli\Command\PurgeProject;
use Keboola\StorageApi\Cli\Console\Application;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Metadata;
use Keboola\Temp\Temp;
use Symfony\Component\Console\Tester\ApplicationTester;

class DeleteMetadataTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $client = new Client(['token' => TEST_STORAGE_API_TOKEN]);
        $client->createBucket("main", Client::STAGE_IN);
        $client->createBucket("meta-test", Client::STAGE_IN);
        $temp = new Temp();
        $temp->initRunFolder();
        $filename = $temp->createFile("table.csv");
        $csv = new CsvFile($filename->getPathname());
        $csv->writeRow(["Id", "Name"]);
        $csv->writeRow(["1", "test"]);
        $client->createTable("in.c-main", "some-table", $csv);
        unset($csv);
        $metadata = new Metadata($client);
        $metadata->postBucketMetadata('in.c-main', 'test-component', [['key' => 'some-key', 'value' => 'some-value']]);
        $metadata->postBucketMetadata('in.c-meta-test', 'test-component', [['key' => 'foo', 'value' => 'baz']]);
        $metadata->postTableMetadata('in.c-main.some-table', 'test-component', [['key' => 'foo', 'value' => 'bar']]);
        $metadata->postColumnMetadata(
            'in.c-main.some-table.Id',
            'test-component',
            [['key' => 'fooId', 'value' => 'barId'], ['key' => 'keyId', 'value' => 'valueId']]
        );
        $metadata->postColumnMetadata(
            'in.c-main.some-table.Name',
            'test-component',
            [['key' => 'fooName', 'value' => 'barName'], ['key' => 'keyName', 'value' => 'valueName']]
        );
    }

    public function testExecuteColumn()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new DeleteMetadata());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'delete-metadata',
            'type' => 'column',
            'id' => 'in.c-main.some-table.Name',
            '--token' => TEST_STORAGE_API_TOKEN,
        ]);

        self::assertContains('Summary of deletions:', $applicationTester->getDisplay());
        self::assertContains('in.c-main.some-table.Name: 2', $applicationTester->getDisplay());
        self::assertEquals(0, $applicationTester->getStatusCode());

        // check for the results
        $client = new Client(['token' => TEST_STORAGE_API_TOKEN]);
        $metadata = new Metadata($client);
        $bucketMetadata = $metadata->listBucketMetadata('in.c-main');
        $this->compareMetadata(
            [['key' => 'some-key', 'value' => 'some-value', 'provider' => 'test-component']],
            $bucketMetadata
        );
        $bucketMetadata = $metadata->listBucketMetadata('in.c-meta-test');
        $this->compareMetadata(
            [['key' => 'foo', 'value' => 'baz', 'provider' => 'test-component']],
            $bucketMetadata
        );
        $tableMetadata = $metadata->listTableMetadata('in.c-main.some-table');
        $this->compareMetadata(
            [['key' => 'foo', 'value' => 'bar', 'provider' => 'test-component']],
            $tableMetadata
        );
        $columnMetadata = $metadata->listColumnMetadata('in.c-main.some-table.Id');
        $this->compareMetadata(
            [
                ['key' => 'fooId', 'value' => 'barId', 'provider' => 'test-component'],
                ['key' => 'keyId', 'value' => 'valueId', 'provider' => 'test-component']
            ],
            $columnMetadata
        );
        $columnMetadata = $metadata->listColumnMetadata('in.c-main.some-table.Name');
        $this->compareMetadata([], $columnMetadata);
    }

    public function testExecuteTable()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new DeleteMetadata());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'delete-metadata',
            'type' => 'table',
            'id' => 'in.c-main.some-table',
            '--token' => TEST_STORAGE_API_TOKEN,
        ]);

        self::assertContains('Summary of deletions:', $applicationTester->getDisplay());
        self::assertContains('in.c-main.some-table.Name: 2', $applicationTester->getDisplay());
        self::assertEquals(0, $applicationTester->getStatusCode());

        // check for the results
        $client = new Client(['token' => TEST_STORAGE_API_TOKEN]);
        $metadata = new Metadata($client);
        $bucketMetadata = $metadata->listBucketMetadata('in.c-main');
        $this->compareMetadata(
            [['key' => 'some-key', 'value' => 'some-value', 'provider' => 'test-component']],
            $bucketMetadata
        );
        $bucketMetadata = $metadata->listBucketMetadata('in.c-meta-test');
        $this->compareMetadata(
            [['key' => 'foo', 'value' => 'baz', 'provider' => 'test-component']],
            $bucketMetadata
        );
        $tableMetadata = $metadata->listTableMetadata('in.c-main.some-table');
        $this->compareMetadata([], $tableMetadata);
        $columnMetadata = $metadata->listColumnMetadata('in.c-main.some-table.Id');
        $this->compareMetadata([], $columnMetadata);
        $columnMetadata = $metadata->listColumnMetadata('in.c-main.some-table.Name');
        $this->compareMetadata([], $columnMetadata);
    }

    public function testExecuteBucket()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new DeleteMetadata());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'delete-metadata',
            'type' => 'bucket',
            'id' => 'in.c-main',
            '--token' => TEST_STORAGE_API_TOKEN,
        ]);

        self::assertContains('Summary of deletions:', $applicationTester->getDisplay());
        self::assertContains('in.c-main.some-table.Name: 2', $applicationTester->getDisplay());
        self::assertEquals(0, $applicationTester->getStatusCode());

        // check for the results
        $client = new Client(['token' => TEST_STORAGE_API_TOKEN]);
        $metadata = new Metadata($client);
        $bucketMetadata = $metadata->listBucketMetadata('in.c-main');
        $this->compareMetadata([], $bucketMetadata);
        $bucketMetadata = $metadata->listBucketMetadata('in.c-meta-test');
        $this->compareMetadata(
            [['key' => 'foo', 'value' => 'baz', 'provider' => 'test-component']],
            $bucketMetadata
        );
        $tableMetadata = $metadata->listTableMetadata('in.c-main.some-table');
        $this->compareMetadata([], $tableMetadata);
        $columnMetadata = $metadata->listColumnMetadata('in.c-main.some-table.Id');
        $this->compareMetadata([], $columnMetadata);
        $columnMetadata = $metadata->listColumnMetadata('in.c-main.some-table.Name');
        $this->compareMetadata([], $columnMetadata);
    }

    public function testExecuteProject()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new DeleteMetadata());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'delete-metadata',
            'type' => 'project',
            'id' => '',
            '--token' => TEST_STORAGE_API_TOKEN,
        ]);

        self::assertContains('Summary of deletions:', $applicationTester->getDisplay());
        self::assertContains('in.c-main.some-table.Name: 2', $applicationTester->getDisplay());
        self::assertEquals(0, $applicationTester->getStatusCode());

        // check for the results
        $client = new Client(['token' => TEST_STORAGE_API_TOKEN]);
        $metadata = new Metadata($client);
        $bucketMetadata = $metadata->listBucketMetadata('in.c-main');
        $this->compareMetadata([], $bucketMetadata);
        $bucketMetadata = $metadata->listBucketMetadata('in.c-meta-test');
        $this->compareMetadata([], $bucketMetadata);
        $tableMetadata = $metadata->listTableMetadata('in.c-main.some-table');
        $this->compareMetadata([], $tableMetadata);
        $columnMetadata = $metadata->listColumnMetadata('in.c-main.some-table.Id');
        $this->compareMetadata([], $columnMetadata);
        $columnMetadata = $metadata->listColumnMetadata('in.c-main.some-table.Name');
        $this->compareMetadata([], $columnMetadata);
    }

    public function testExecuteInvalid()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new DeleteMetadata());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'delete-metadata',
            'type' => 'invalid',
            'id' => 'some-id',
            '--token' => TEST_STORAGE_API_TOKEN,
        ]);

        self::assertContains('Unknown object type for metadata storage: invalid', $applicationTester->getDisplay());
        self::assertEquals(1, $applicationTester->getStatusCode());
    }

    public function testExecuteNotFound()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new DeleteMetadata());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'delete-metadata',
            'type' => 'table',
            'id' => 'some-id',
            '--token' => TEST_STORAGE_API_TOKEN,
        ]);

        self::assertContains('Table some-id does not exist or is not accessible.', $applicationTester->getDisplay());
        self::assertEquals(1, $applicationTester->getStatusCode());
    }

    private function compareMetadata($expected, $actual)
    {
        self::assertEquals(count($expected), count($actual));
        foreach ($actual as &$actualRow) {
            unset($actualRow['timestamp']);
            unset($actualRow['id']);
        }
        self::assertEquals($expected, $actual);
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
