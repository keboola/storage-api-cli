# Keboola Storage API Command Line Interface

[![Build Status](https://travis-ci.org/keboola/storage-api-cli.png?branch=master)](https://travis-ci.org/keboola/storage-api-cli)

Storage API CLI is a simple command line wrapper for [Keboola Storage REST API](http://docs.keboola.apiary.io/). The CLI is available as a [Docker](https://www.docker.com/) image.

## Running in Docker
The client is packaged in a Docker image [keboola/storage-api-cli](https://quay.io/repository/keboola/storage-api-cli). All you need to do is simply run it:

```
docker run quay.io/keboola/storage-api-cli
```

or with parameters:

```
docker run quay.io/keboola/storage-api-cli list-buckets --token=your-token
```

## Usage
If you run the client without parameters (`docker run quay.io/keboola/storage-api-cli`), help will be displayed.

```
Keboola Storage API CLI version 1.0.0

Usage:
  command [options] [arguments]

Options:
  -h, --help            Display this help message
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi            Force ANSI output
      --no-ansi         Disable ANSI output
  -n, --no-interaction  Do not ask any interactive question
      --token=TOKEN     Storage API Token
      --url=URL         Storage API URL
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Available commands:
  backup-project              Backup whole project to AWS S3
  copy-bucket                 Copy bucket with all tables in it
  copy-table                  Copy table with PK and indexes (transferring nongzipped data)
  create-bucket               Create bucket
  create-table                Create table in bucket
  delete-bucket               Delete bucket
  delete-metadata             Delete metadata from project, bucket, table, or column
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
```

You can also display help for any command by running it with the `--help` parameter, e.g:

```
docker run quay.io/keboola/storage-api-cli export-table --help
```

### export-table

Export table command accepts the same options as the `/v2/storage/tables/{table_id}/export-async` call in [Storage API](http://docs.keboola.apiary.io/#tables) with the exception of deprecated `days` option. 

```
export-table [-f|--format="..."] [-g|--gzip] [--columns="..."] [--limit="..."] 
[--changedSince="..."] [--changedUntil="..."] 
[--whereColumn="..."] [--whereOperator="..."] [--whereValues="..."] 
tableId filePath
```

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

#### Examples

Simply export the table to `table.csv`:

```
docker run --volume=$("pwd"):/data quay.io/keboola/storage-api-cli --token=your_sapi_token \
export-table in.c-main.table /data/table.csv
```

*Please note that the Docker container can only access folders within the container, so you need to mount local folder.     
In the example above, the local folder `$("pwd")` (replaced by the absolute path on runtime) is mounted as `/data` into the container. 
The table is then accessible in this this folder. The same approach applies for all other commands working with local files.*

Export columns `Name` and `Id`:

```
docker run --volume=$("pwd"):/data quay.io/keboola/storage-api-cli --token=your_sapi_token \
export-table in.c-main.table /data/table.csv --columns=Name --columns=Id
```

Export first 100 rows:

```
docker run --volume=$("pwd"):/data quay.io/keboola/storage-api-cli --token=your_sapi_token \
export-table in.c-main.table /data/table.csv --limit=1
```
(note: sorting is not defined and depends on the storage backend)

Export records where `AccountId = 001C000000ofWffIAE`:

```
docker run --volume=$("pwd"):/data quay.io/keboola/storage-api-cli --token=your_sapi_token \
export-table in.c-main.table /data/table.csv --whereColumn=AccountId --whereValues=001C000000ofWffIAE \
--whereOperator=eq
```
(note: all three of the `where*` options need to be defined and `whereColumn` only accepts indexed columns)

Export records where `AccountId != (001C000000ofWffIAE, 001C000000ofWffIAA)`:

```
docker run --volume=$("pwd"):/data quay.io/keboola/storage-api-cli --token=your_sapi_token \
export-table in.c-main.table /data/table.csv --whereColumn=AccountId --whereValues=001C000000ofWffIAE \
--whereValues=001C000000ofWffIAA --whereOperator=ne
```

Export records modified in last 2 days::

```
docker run --volume=$("pwd"):/data quay.io/keboola/storage-api-cli --token=your_sapi_token \
export-table in.c-main.table /data/table.csv --whereColumn=AccountId --changedSince="-2 days"
```
(note: `changedSince` accepts any datetime description that can be parsed by [strtotime PHP function](http://php.net/manual/en/function.strtotime.php)) 

Export records modified in until 2 days ago:

```
docker run --volume=$("pwd"):/data quay.io/keboola/storage-api-cli --token=your_sapi_token \
export-table in.c-main.table /data/table.csv --whereColumn=AccountId --changedUntil="-2 days"
```
(note: `changedUntil` accepts any datetime description that can be parsed by [strtotime PHP function](http://php.net/manual/en/function.strtotime.php)) 

## Development

### Clone project

```
git clone git@github.com:keboola/storage-api-cli.git
cd storage-api-cli
docker-compose build
docker-compose run --rm dev composer install
```

### AWS and KBC Resources

- Use [test-cf-stack.json](./test-cf-stack.json) to set up a CloudFormation stack with all required resources
- Create two empty Keboola Connection projects 
- Create `.env` file with the following environment variables

```
TEST_BACKUP_AWS_ACCESS_KEY_ID=
TEST_BACKUP_AWS_SECRET_ACCESS_KEY=
TEST_BACKUP_S3_BUCKET=
TEST_RESTORE_AWS_ACCESS_KEY_ID=
TEST_RESTORE_AWS_SECRET_ACCESS_KEY=
TEST_RESTORE_S3_BUCKET=
TEST_AWS_REGION=eu-
TEST_STORAGE_API_URL=
TEST_STORAGE_API_TOKEN=
TEST_STORAGE_API_SECONDARY_TOKEN=

```  

- Load fixtures to S3

```
docker-compose run --rm dev php tests/loadToS3.php
```

- Run tests 

``` 
docker-compose run dev composer tests
```

## License

MIT licensed, see [LICENSE](./LICENSE) file.
