# Keboola Storage API Command Line Interface

[![Build Status](https://travis-ci.org/keboola/storage-api-cli.png?branch=master)](https://travis-ci.org/keboola/storage-api-cli)

Simple command line wrapper for [Keboola Storage REST API](http://docs.keboola.apiary.io/)

## Installation
Download the latest version from [https://s3.amazonaws.com/keboola-storage-api-cli/builds/sapi-client.phar](https://s3.amazonaws.com/keboola-storage-api-cli/builds/sapi-client.phar).

From there, you may place it anywhere that will make it easier for you to access (such as /usr/local/bin) and chmod it to 755.
You can even rename it to just sapi-client to avoid having to type the .phar extension every time.

Storage API cli requires PHP 5.6 or newer, 
for PHP 5.5 you can use [https://s3.amazonaws.com/keboola-storage-api-cli/builds/sapi-client.0.6.0.phar](https://s3.amazonaws.com/keboola-storage-api-cli/builds/sapi-client.0.6.0.phar) (5.5 version is no longer supported),
for PHP 5.4 you can use [https://s3.amazonaws.com/keboola-storage-api-cli/builds/sapi-client.0.2.9.phar](https://s3.amazonaws.com/keboola-storage-api-cli/builds/sapi-client.0.2.9.phar),
for PHP 5.3 you can use [https://s3.amazonaws.com/keboola-storage-api-cli/builds/sapi-client.0.1.9.phar](https://s3.amazonaws.com/keboola-storage-api-cli/builds/sapi-client.0.1.9.phar).

## Usage

```
➜  storage-api-cli git:(master) php sapi-client.phar --token=your_sapi_token
Keboola Storage API CLI version 0.6.0

Usage:
  [options] command [arguments]

Options:
  --help           -h Display this help message
  --quiet          -q Do not output any message
  --verbose        -v|vv|vvv Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
  --version        -V Display this application version
  --ansi              Force ANSI output
  --no-ansi           Disable ANSI output
  --no-interaction -n Do not ask any interactive question
  --token             Storage API Token
  --url               Storage API URL

Available commands:
  backup-project              Backup whole project to AWS S3
  copy-bucket                 Copy bucket with all tables in it
  copy-table                  Copy table with PK, indexes and attributes (transferring nongzipped data)
  create-table                Create table in bucket
  create-bucket               Create bucket
  delete-bucket               Delete bucket
  delete-metadata             Delete metadata from column, table, bucket or entire project
  delete-table                Delete table
  export-table                Export data from table to file
  help                        Displays help for a command
  list                        Lists commands
  list-buckets                list all available buckets
  list-events                 List events
  purge-project               Purge the project
  restore-project             Restore a project from a backup in AWS S3. Only the latest versions of all configs are used.
  restore-table-from-imports  Creates new table from source table imports
  truncate-table              Remove all data from table
  write-table                 Write data into table
  delete-metadataa            Delete all metadata from project, bucket, table or column
```

#### export-table

Export table command accepts the same options as the `/v2/storage/tables/{table_id}/export-async` call in [Storage API](http://docs.keboola.apiary.io/#tables) with the exception of deprecated `days` option. 


    export-table [-f|--format="..."] [-g|--gzip] [--columns="..."] [--limit="..."] 
    [--changedSince="..."] [--changedUntil="..."] 
    [--whereColumn="..."] [--whereOperator="..."] [--whereValues="..."] 
    tableId filePath


**Arguments**

 - `tableId` - Storage API table id
 - `filePath` - path to file on local filesystem
 
**Options**

 - `-f|--format` (optional) - values `rfc`, `raw` or `escaped`
 - `-g|--gzip` (optional) - if the result will be gzipped
 - `--columns="..."` (optional, multiple) - list of columns to export (exports all by default)
 - `--limit="..."` (optional) - number of rows to export
 - `--changedSince="..."` (optional) - export only rows changed since given date/time 
 - `--changedUntil="..."` (optional) - export only rows changed until given date/time 
 - `--whereColumn="..."`, `--whereOperator="..."`, `--whereValues="..."` - filters the results; the column specified in `whereColumn` must be indexed, `whereValues` can contain multiple values and `whereOperator` is `eq` or `ne`.
 
These options can be combined freely. `whereValues` and `columns` options accept multiple values by additional usage of the same option.

##### Examples

Simply export the table to `table.csv`:

```
php sapi-client.phar --token=your_sapi_token export-table in.c-main.table table.csv
```

Export columns `Name` and `Id`:

```
php sapi-client.phar --token=your_sapi_token export-table in.c-main.table table.csv \ 
--columns=Name --columns=Id
```

Export first 100 rows:

```
php sapi-client.phar --token=your_sapi_token export-table in.c-main.table table.csv \
--limit=1
```
(note: sorting is not defined and depends on the storage backend)

Export records where `AccountId = 001C000000ofWffIAE`:

```
php sapi-client.phar --token=your_sapi_token export-table in.c-main.table table.csv \
--whereColumn=AccountId --whereValues=001C000000ofWffIAE --whereOperator=eq
```
(note: all three of the `where*` options need to be defined and `whereColumn` only accepts indexed columns)

Export records where `AccountId != (001C000000ofWffIAE, 001C000000ofWffIAA)`:

```
php sapi-client.phar --token=your_sapi_token export-table in.c-main.table table.csv \
--whereColumn=AccountId --whereValues=001C000000ofWffIAE --whereValues=001C000000ofWffIAA --whereOperator=ne
```

Export records modified in last 2 days::

```
php sapi-client.phar --token=your_sapi_token export-table in.c-main.table table.csv \
--whereColumn=AccountId --changedSince="-2 days"
```
(note: `changedSince` accepts any datetime description that can be parsed by [strtotime PHP function](http://php.net/manual/en/function.strtotime.php)) 

Export records modified in until 2 days ago:

```
php sapi-client.phar --token=your_sapi_token export-table in.c-main.table table.csv \
--whereColumn=AccountId --changedUntil="-2 days"
```
(note: `changedUntil` accepts any datetime description that can be parsed by [strtotime PHP function](http://php.net/manual/en/function.strtotime.php)) 

## Development

```
git clone git@github.com:keboola/storage-api-cli.git
cd storage-api-cli
composer install
```

## Build
Tool is distributed as PHAR package, follow these steps to create package of current version:

```
curl -LSs http://box-project.github.io/box2/installer.php | php -dphar.readonly=0 ./box.phar build -v
```

`sapi-client.phar` archive will be created, you can execute it `./sapi-client.phar`
