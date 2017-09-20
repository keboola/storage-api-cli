<?php

namespace Keboola\DockerBundle\Tests\Command;

use Aws\S3\S3Client;
use Keboola\StorageApi\Cli\Command\BackupProject;
use Keboola\StorageApi\Cli\Console\Application;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Exception;
use Keboola\StorageApi\Options\Components\Configuration;
use Keboola\StorageApi\Options\Components\ConfigurationRow;
use Symfony\Component\Console\Tester\ApplicationTester;

class BackupProjectTest extends \PHPUnit_Framework_TestCase
{
    private $s3path;

    public function setUp()
    {
        parent::setUp();
        $client = new Client(['token' => TEST_STORAGE_API_TOKEN]);
        $component = new Components($client);
        try {
            $component->deleteConfiguration('transformation', 'sapi-php-test');
        } catch (Exception $e) {
            if ($e->getCode() != 404) {
                throw $e;
            }
        }
        $this->s3path = 'cli-client-test/';
    }

    public function testExecuteNoVersions()
    {
        $client = new Client(['token' => TEST_STORAGE_API_TOKEN]);
        $config = new Configuration();
        $config->setComponentId('transformation');
        $config->setDescription('Test Configuration');
        $config->setConfigurationId('sapi-php-test');
        $config->setName('test-configuration');
        $component = new Components($client);
        $configData = $component->addConfiguration($config);
        $config->setConfigurationId($configData['id']);

        $row = new ConfigurationRow($config);
        $row->setChangeDescription('Row 1');
        $row->setConfiguration(
            ['name' => 'test 1', 'backend' => 'docker', 'type' => 'r', 'queries' => ['foo']]
        );
        $component->addConfigurationRow($row);

        $row = new ConfigurationRow($config);
        $row->setChangeDescription('Row 2');
        $row->setConfiguration(
            ['name' => 'test 2', 'backend' => 'docker', 'type' => 'r', 'queries' => ['bar']]
        );
        $component->addConfigurationRow($row);

        putenv('AWS_ACCESS_KEY_ID=' . TEST_AWS_ACCESS_KEY_ID);
        putenv('AWS_SECRET_ACCESS_KEY=' . TEST_AWS_SECRET_ACCESS_KEY);
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new BackupProject());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'backup-project',
            '--token' => TEST_STORAGE_API_TOKEN,
            '--structure-only' => true,
            'bucket' => TEST_S3_BUCKET,
            'region' => TEST_AWS_REGION,
            'path' => $this->s3path
        ]);
        $ret = $applicationTester->getDisplay();
        self::assertContains('Exporting buckets', $ret);
        self::assertContains('Exporting tables', $ret);
        self::assertContains('Exporting configurations', $ret);

        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR;
        $s3Client = new S3Client([
            'version' => 'latest',
            'region' => TEST_AWS_REGION,
            'credentials' => [
                'key' => TEST_AWS_ACCESS_KEY_ID,
                'secret' => TEST_AWS_SECRET_ACCESS_KEY,
            ]
        ]);
        $targetFile = $tmp . 'configurations.json';
        $s3Client->getObject([
            'Bucket' => TEST_S3_BUCKET,
            'Key' => $this->s3path . 'configurations.json',
            'SaveAs' => $targetFile
        ]);
        $targetContents = file_get_contents($targetFile);
        $targetData = json_decode($targetContents, true);
        $targetComponent = [];
        foreach ($targetData as $component) {
            if ($component['id'] == 'transformation') {
                $targetComponent = $component;
                break;
            }
        }
        self::assertGreaterThan(0, count($targetComponent));

        $targetConfiguration = [];
        foreach ($targetComponent['configurations'] as $configuration) {
            if ($configuration['name'] == 'test-configuration') {
                $targetConfiguration = $configuration;
            }
        }
        self::assertGreaterThan(0, count($targetConfiguration));
        self::assertEquals('Test Configuration', $targetConfiguration['description']);
        self::assertNotContains('rows', $targetConfiguration);

        $configurationId = $targetConfiguration['id'];
        $targetFile = $tmp . $configurationId . 'configurations.json';
        $s3Client->getObject([
            'Bucket' => TEST_S3_BUCKET,
            'Key' => $this->s3path . 'configurations/transformation/' . $configurationId . '.json',
            'SaveAs' => $targetFile
        ]);
        $targetContents = file_get_contents($targetFile);
        $targetConfiguration = json_decode($targetContents, true);
        self::assertGreaterThan(0, count($targetConfiguration));
        self::assertEquals('test-configuration', $targetConfiguration['name']);
        self::assertEquals('Test Configuration', $targetConfiguration['description']);
        self::assertArrayHasKey('rows', $targetConfiguration);
        self::assertEquals(2, count($targetConfiguration['rows']));
        self::assertEquals('foo', $targetConfiguration['rows'][0]['configuration']['queries'][0]);
        self::assertEquals('bar', $targetConfiguration['rows'][1]['configuration']['queries'][0]);
        self::assertNotContains('versions', $targetConfiguration);
        self::assertNotContains('versions', $targetConfiguration['rows'][0]);
    }

    public function testPreserveEmptyObjectAndArray()
    {
        $client = new Client(['token' => TEST_STORAGE_API_TOKEN]);
        $config = new Configuration();
        $config->setComponentId('transformation');
        $config->setDescription('Test Configuration');
        $config->setConfigurationId('sapi-php-test');
        $config->setName('test-configuration');
        $config->setConfiguration(
            [
                "dummyObject" => new \stdClass(),
                "dummyArray" => []
            ]
        );
        $component = new Components($client);
        $configData = $component->addConfiguration($config);
        $config->setConfigurationId($configData['id']);



        $row = new ConfigurationRow($config);
        $row->setChangeDescription('Row 1');
        $row->setConfiguration(
            [
                'name' => 'test 1',
                'backend' => 'docker',
                'type' => 'r',
                'queries' => ['foo'],
                "dummyObject" => new \stdClass(),
                "dummyArray" => []
            ]
        );
        $component->addConfigurationRow($row);

        $row = new ConfigurationRow($config);
        $row->setChangeDescription('Row 2');
        $row->setConfiguration(
            [
                'name' => 'test 2',
                'backend' => 'docker',
                'type' => 'r',
                'queries' => ['bar'],
                "dummyObject" => new \stdClass(),
                "dummyArray" => []
            ]
        );
        $component->addConfigurationRow($row);

        putenv('AWS_ACCESS_KEY_ID=' . TEST_AWS_ACCESS_KEY_ID);
        putenv('AWS_SECRET_ACCESS_KEY=' . TEST_AWS_SECRET_ACCESS_KEY);
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new BackupProject());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'backup-project',
            '--token' => TEST_STORAGE_API_TOKEN,
            '--structure-only' => true,
            'bucket' => TEST_S3_BUCKET,
            'region' => TEST_AWS_REGION,
            'path' => $this->s3path
        ]);
        $ret = $applicationTester->getDisplay();
        self::assertContains('Exporting buckets', $ret);
        self::assertContains('Exporting tables', $ret);
        self::assertContains('Exporting configurations', $ret);

        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR;
        $s3Client = new S3Client([
            'version' => 'latest',
            'region' => TEST_AWS_REGION,
            'credentials' => [
                'key' => TEST_AWS_ACCESS_KEY_ID,
                'secret' => TEST_AWS_SECRET_ACCESS_KEY,
            ]
        ]);
        $targetFile = $tmp . 'configurations.json';
        $s3Client->getObject([
            'Bucket' => TEST_S3_BUCKET,
            'Key' => $this->s3path . 'configurations.json',
            'SaveAs' => $targetFile
        ]);
        $targetContents = file_get_contents($targetFile);
        $targetData = json_decode($targetContents);
        $targetConfiguration = $targetData[0]->configurations[0];

        self::assertEquals(new \stdClass(), $targetConfiguration->configuration->dummyObject);
        self::assertEquals([], $targetConfiguration->configuration->dummyArray);

        $configurationId = $targetConfiguration->id;
        $targetFile = $tmp . $configurationId . 'configurations.json';
        $s3Client->getObject([
            'Bucket' => TEST_S3_BUCKET,
            'Key' => $this->s3path . 'configurations/transformation/' . $configurationId . '.json',
            'SaveAs' => $targetFile
        ]);
        $targetContents = file_get_contents($targetFile);
        $targetConfiguration = json_decode($targetContents);

        self::assertEquals(new \stdClass(), $targetConfiguration->rows[0]->configuration->dummyObject);
        self::assertEquals([], $targetConfiguration->rows[0]->configuration->dummyArray);
    }

    public function tearDown()
    {
        $client = new Client(['token' => TEST_STORAGE_API_TOKEN]);
        $component = new Components($client);
        $component->deleteConfiguration('transformation', 'sapi-php-test');

        $s3Client = new S3Client([
            'version' => 'latest',
            'region' => TEST_AWS_REGION,
            'credentials' => [
                'key' => TEST_AWS_ACCESS_KEY_ID,
                'secret' => TEST_AWS_SECRET_ACCESS_KEY,
            ]
        ]);
        $keys = $s3Client->listObjects(['Bucket' => TEST_S3_BUCKET]);
        $keys = $keys->toArray()['Contents'];
        $deleteObjects = [];
        foreach ($keys as $key) {
            if (substr($key['Key'], 0, strlen($this->s3path)) == $this->s3path) {
                $deleteObjects[] = $key;
            }
        }

        if (count($deleteObjects) > 0) {
            $s3Client->deleteObjects(
                [
                    'Bucket' => TEST_S3_BUCKET,
                    'Delete' => ['Objects' => $deleteObjects]
                ]
            );
        }
    }
}
