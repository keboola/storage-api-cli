<?php

namespace Keboola\DockerBundle\Tests\Command;

use Aws\S3\S3Client;
use Keboola\StorageApi\Cli\Command\BackupProject;
use Keboola\StorageApi\Cli\Console\Application;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Keboola\StorageApi\Options\Components\ConfigurationRow;
use Symfony\Component\Console\Tester\ApplicationTester;

class BackupProjectTest extends \PHPUnit_Framework_TestCase
{
    const S3_PATH = 'cli-client-test/';
    const S3_REGION = 'us-east-1';

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
            'region' => self::S3_REGION,
            'path' => self::S3_PATH
        ]);
        $ret = $applicationTester->getDisplay();
        $this->assertContains('Buckets metadata', $ret);
        $this->assertContains('Tables metadata', $ret);
        $this->assertContains('Configurations', $ret);

        $tmp = sys_get_temp_dir();
        $s3Client = new S3Client([
            'version' => 'latest',
            'region' => self::S3_REGION,
            'credentials' => [
                'key' => TEST_AWS_ACCESS_KEY_ID,
                'secret' => TEST_AWS_SECRET_ACCESS_KEY,
            ]
        ]);
        $targetFile = $tmp . 'configurations.json';
        $s3Client->getObject(array(
            'Bucket' => TEST_S3_BUCKET,
            'Key' => self::S3_PATH . 'configurations.json',
            'SaveAs' => $targetFile
        ));
        $targetContents = file_get_contents($targetFile);
        $targetData = json_decode($targetContents, true);
        $targetComponent = [];
        foreach ($targetData as $component) {
            if ($component['id'] == 'transformation') {
                $targetComponent = $component;
                break;
            }
        }
        $this->assertGreaterThan(0, count($targetComponent));

        $targetConfiguration = [];
        foreach ($targetComponent['configurations'] as $configuration) {
            if ($configuration['name'] == 'test-configuration') {
                $targetConfiguration = $configuration;
            }
        }
        $this->assertGreaterThan(0, count($targetConfiguration));
        $this->assertEquals('Test Configuration', $targetConfiguration['description']);
        $this->assertNotContains('rows', $targetConfiguration);

        $configurationId = $targetConfiguration['id'];
        $targetFile = $tmp . $configurationId . 'configurations.json';
        $s3Client->getObject(array(
            'Bucket' => TEST_S3_BUCKET,
            'Key' => self::S3_PATH . 'configurations/transformation/' . $configurationId . '.json',
            'SaveAs' => $targetFile
        ));
        $targetContents = file_get_contents($targetFile);
        $targetData = json_decode($targetContents, true);
        $targetComponent = [];
        foreach ($targetData as $component) {
            if ($component['id'] == 'transformation') {
                $targetComponent = $component;
                break;
            }
        }
        $this->assertGreaterThan(0, count($targetComponent));

        $targetConfiguration = [];
        foreach ($targetComponent['configurations'] as $configuration) {
            if ($configuration['id'] == 'sapi-php-test') {
                $targetConfiguration = $configuration;
            }
        }
        $this->assertGreaterThan(0, count($targetConfiguration));
        $this->assertEquals('test-configuration', $targetConfiguration['name']);
        $this->assertEquals('Test Configuration', $targetConfiguration['description']);
        $this->assertContains('rows', $targetConfiguration);
        $this->assertEquals(2, count($targetConfiguration['rows']));
        $this->assertEquals('foo', count($targetConfiguration['rows'][0]['queries'][0]));
        $this->assertEquals('bar', count($targetConfiguration['rows'][1]['queries'][0]));
        $this->assertNotContains('versions', $targetConfiguration);
        $this->assertNotContains('versions', $targetConfiguration['rows'][0]);
    }

    public function tearDown()
    {
        $client = new Client(['token' => TEST_STORAGE_API_TOKEN]);
        $component = new Components($client);
        $component->deleteConfiguration('transformation', 'sapi-php-test');

        $s3Client = new S3Client([
            'version' => 'latest',
            'region' => self::S3_REGION,
            'credentials' => [
                'key' => TEST_AWS_ACCESS_KEY_ID,
                'secret' => TEST_AWS_SECRET_ACCESS_KEY,
            ]
        ]);
        $keys = $s3Client->listObjects(['Bucket' => TEST_S3_BUCKET]);
        $keys = $keys->toArray()['Contents'];
        $deleteObjects = [];
        foreach ($keys as $key) {
            if (substr($key['Key'], 0, strlen(self::S3_PATH)) == self::S3_PATH) {
                $deleteObjects[] = $key;
            }
        }
        $s3Client->deleteObjects([
            'Bucket' => TEST_S3_BUCKET,
            'Delete' => ['Objects' => $deleteObjects]
        ]);
    }
}
