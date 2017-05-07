<?php

namespace Keboola\StorageApi\Cli\Command;

use Keboola\StorageApi\Metadata;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteMetadata extends Command
{

    public function configure()
    {
        $this
            ->setName('delete-metadata')
            ->setDescription('Delete metadata from project, bucket, table, or column')
            ->setDefinition(array(
                new InputArgument('type', InputArgument::REQUIRED, "one of: project, bucket, table, or column"),
                new InputArgument('id', InputArgument::REQUIRED, "id of the object to clean of metadata")
            ));
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sapiClient = $this->getSapiClient();
        switch ($input->getArgument('type')) {
            case 'project':
                $buckets = $sapiClient->listBuckets();
                foreach ($buckets as $bucket) {
                    $result = $this->deleteMetadataFromBucket($bucket['id']);
                }
                break;
            case 'bucket':
                $this->deleteMetadataFromBucket($input->getArgument('id'));
                break;
            case 'table':
                $this->deleteMetadataFromTable($input->getArgument('id'));
                break;
            case 'column':
                $this->deleteMetadataFromColumn($input->getArgument('id'));
                break;
            default:
                throw new \Exception(sprintf("Unknown object type for metadata storage: %s", $input->getArgument('type')));
        }
    }

    private function deleteMetadataFromBucket($bucketId) {

        $sapiClient = $this->getSapiClient();
        $metadataClient = new Metadata($sapiClient);

        if (!$sapiClient->bucketExists($bucketId)) {
            throw new \Exception("Bucket {$bucketId} does not exist or is not accessible.");
        }

        $tables = $sapiClient->listTables($bucketId);
        foreach ($tables as $table) {
            $this->deleteMetadataFromTable($table['id']);
        }
        $bucketMetadata = $metadataClient->listBucketMetadata($bucketId);
        foreach ($bucketMetadata as $meta) {
            $metadataClient->deleteBucketMetadata($bucketId, $meta['id']);
        }
        return [$bucketId => count($bucketMetadata)];
    }

    private function deleteMetadataFromTable($tableId) {

        $sapiClient = $this->getSapiClient();
        $metadataClient = new Metadata($sapiClient);

        if (!$sapiClient->tableExists($tableId)) {
            throw new \Exception("Table {$tableId} does not exist or is not accessible.");
        }

        $table = $sapiClient->getTable($tableId);

        foreach ($table['columns'] as $column) {
            $this->deleteMetadataFromColumn($table['id'] . '.' . $column, $table['columnMetadata'][$column]);
        }

        $tableMetadata = $table['metadata'];
        foreach ($tableMetadata as $meta) {
            $metadataClient->deleteTableMetadata($tableId, $meta['id']);
        }

    }

    private function deleteMetadataFromColumn($columnId, $columnMeta = null) {

        $metadataClient = new Metadata($this->getSapiClient());

        if (is_null($columnMeta)) {
            $columnMeta = $metadataClient->listColumnMetadata($columnId);
        }
        foreach ($columnMeta as $meta) {
            $metadataClient->deleteColumnMetadata($columnId, $meta['id']);
        }
        return [$columnId => count($columnMeta)];
    }
}