# Keboola Storage API Command Line Interface

[![Build Status](https://travis-ci.org/keboola/storage-api-cli.png?branch=master)](https://travis-ci.org/keboola/storage-api-cli)

Simple command line wrapper for [Keboola Storage REST API](http://docs.keboola.apiary.io/)

## Installation

Download the latest version from https://s3.amazonaws.com/keboola-storage-api-cli/builds/sapi-client.phar .

From there, you may place it anywhere that will make it easier for you to access (such as /usr/local/bin) and chmod it to 755.
You can even rename it to just sapi-client to avoid having to type the .phar extension every time.

Storage API cli requires PHP 5.4 or newer, for PHP 5.3 you can use https://s3.amazonaws.com/keboola-storage-api-cli/builds/sapi-client.0.1.9.phar (5.3 version is no longer supported)

## Usage

```
➜  storage-api-cli git:(master) php sapi-client.phar --token=your_sapi_token
Keboola Storage API CLI version 0.2.3

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
  --shell             Launch the shell.

Available commands:
  copy-bucket                  Copy bucket with all tables in it
  copy-table                   Copy table with PK, indexes and attributes (transferring nongzipped data)
  create-table                 Create table in bucket
  delete-bucket                Delete bucket
  delete-table                 Delete table
  export-table                 Export data from table to file
  help                         Displays help for a command
  list                         Lists commands
  list-buckets                 list all available buckets
  list-events                  List events
  restore-table-from-imports   Creates new table from source table imports
  truncate-table               Remove all data from table
  write-table                  Write data into table

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

### Shell mode
There is an interactive shell which allows you to enter commands without having to specify php app/console each time, which is useful if you need to run several commands.
Autocomplete of commands is supported in shell model.

```
➜  storage-api-cli git:(master) php bin/sapi-client --token=your_sapi_token --shell
Authorized as: martin@keboola.com (KB Paymo (TAPI UI testing clone))

Welcome to the Keboola Storage API Client shell.

At the prompt, type help for some help,
or list to get a list of available commands.

To exit the shell, type ^D.

Keboola Storage API Client >
```

## Development

```
git clone git@github.com:keboola/storage-api-cli.git
cd storage-api-cli
composer install
```

## Build
Tool is distributed as PHAR package, follow these steps to create package of current version:

```
curl -s http://box-project.org/installer.php | php
./box.phar build -v
```

`sapi-client.phar` archive will be created, you can execute it `./sapi-client.phar`
