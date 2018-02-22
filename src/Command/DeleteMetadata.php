<?php

namespace Keboola\StorageApi\Cli\Command;

use Keboola\StorageApi\Metadata;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteMetadata extends Command
{

    public function configure(): void
    {
        $this
            ->setName('delete-metadata')
            ->setDescription('Delete metadata from project, bucket, table, or column')
            ->setDefinition(array(
                new InputArgument('type', InputArgument::REQUIRED, "one of: project, bucket, table, or column"),
                new InputArgument('id', InputArgument::REQUIRED, "id of the object to clean of metadata")
            ));
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $sapiClient = $this->getSapiClient();
        $result = [];
        switch ($input->getArgument('type')) {
            case 'project':
                $buckets = $sapiClient->listBuckets();
                foreach ($buckets as $bucket) {
                    $result['buckets'][] = $this->deleteMetadataFromBucket($bucket['id']);
                }
                break;
            case 'bucket':
                $result = $this->deleteMetadataFromBucket($input->getArgument('id'));
                break;
            case 'table':
                $result = $this->deleteMetadataFromTable($input->getArgument('id'));
                break;
            case 'column':
                $result = $this->deleteMetadataFromColumn($input->getArgument('id'));
                break;
            default:
                throw new \Exception(sprintf("Unknown object type for metadata storage: %s", $input->getArgument('type')));
        }
        $output->writeln("Summary of deletions:");
        $this->dumpResult($result, $output);
    }

    private function deleteMetadataFromBucket($bucketId)
    {

        $sapiClient = $this->getSapiClient();
        $metadataClient = new Metadata($sapiClient);

        if (!$sapiClient->bucketExists($bucketId)) {
            throw new \Exception("Bucket {$bucketId} does not exist or is not accessible.");
        }

        $tables = $sapiClient->listTables($bucketId);
        $tablesResult = [];
        foreach ($tables as $table) {
            $tablesResult[] = $this->deleteMetadataFromTable($table['id']);
        }
        $bucketMetadata = $metadataClient->listBucketMetadata($bucketId);
        foreach ($bucketMetadata as $meta) {
            $metadataClient->deleteBucketMetadata($bucketId, $meta['id']);
        }
        return [$bucketId => count($bucketMetadata), 'tables' => $tablesResult];
    }

    private function deleteMetadataFromTable($tableId)
    {

        $sapiClient = $this->getSapiClient();
        $metadataClient = new Metadata($sapiClient);

        if (!$sapiClient->tableExists($tableId)) {
            throw new \Exception("Table {$tableId} does not exist or is not accessible.");
        }

        $table = $sapiClient->getTable($tableId);

        $columnsResult = [];
        foreach ($table['columnMetadata'] as $column => $columnMetadata) {
            $columnsResult[] = $this->deleteMetadataFromColumn(
                $table['id'] . '.' . $column,
                $columnMetadata
            );
        }

        $tableMetadata = $table['metadata'];
        foreach ($tableMetadata as $meta) {
            $metadataClient->deleteTableMetadata($tableId, $meta['id']);
        }
        return [$tableId => count($tableMetadata), 'columns' => $columnsResult];
    }

    private function deleteMetadataFromColumn($columnId, $columnMeta = null)
    {

        $metadataClient = new Metadata($this->getSapiClient());

        if (is_null($columnMeta)) {
            $columnMeta = $metadataClient->listColumnMetadata($columnId);
        }
        foreach ($columnMeta as $meta) {
            $metadataClient->deleteColumnMetadata($columnId, $meta['id']);
        }
        return [$columnId => count($columnMeta)];
    }

    private function dumpResult(array $result, OutputInterface $output): void
    {
        foreach ($result as $key => $value) {
            if (is_array($value)) {
                $output->writeln($key . ": ");
                $this->dumpResult($value, $output);
            } else {
                $output->writeln($key . ": " . $value);
            }
        }
    }
}
