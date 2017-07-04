<?php

namespace Keboola\DockerBundle\Tests\Command;

use Keboola\StorageApi\Cli\Command\CreateBucket;
use Keboola\StorageApi\Cli\Command\PurgeProject;
use Keboola\StorageApi\Cli\Console\Application;
use Keboola\StorageApi\Client;
use Symfony\Component\Console\Tester\ApplicationTester;

class CreateBucketTest extends \PHPUnit_Framework_TestCase
{
    public function testExecute()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new CreateBucket());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'create-bucket',
            'bucketStage' => 'in',
            'bucketName' => 'clientTest',
            'bucketDescription' => 'Client testing',
            '--token' => TEST_STORAGE_API_TOKEN,
        ]);

        self::assertContains('Bucket created: in.c-clientTest', $applicationTester->getDisplay());
        self::assertEquals(0, $applicationTester->getStatusCode());
        // check for the results
        $client = new Client(['token' => TEST_STORAGE_API_TOKEN]);
        $bucket = $client->getBucket('in.c-clientTest');
        self::assertEquals('c-clientTest', $bucket['name']);
        self::assertEquals('Client testing', $bucket['description']);
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
