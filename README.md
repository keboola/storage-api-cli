# Keboola Storage API Command Line Interface

Simple command line wrapper for [Keboola Storage REST API](http://docs.keboola.apiary.io/)

## Installation

```bash
git clone git@github.com:keboola/storage-api-cli.git
cd storage-api-cli
composer install
```

## Usage

```bash
➜  storage-api-cli git:(master) php bin/sapi-client --token=your_sapi_token
Authorized as: martin@keboola.com (Demo Project)
Console Tool

Usage:
  [options] command [arguments]

Options:
  --help           -h Display this help message.
  --quiet          -q Do not output any message.
  --verbose        -v Increase verbosity of messages.
  --version        -V Display this application version.
  --ansi              Force ANSI output.
  --no-ansi           Disable ANSI output.
  --no-interaction -n Do not ask any interactive question.
  --token             Storage API Token
  --shell             Launch the shell.

Available commands:
  create-table   Create table in bucket
  help           Displays help for a command
  list           Lists commands
  list-buckets   list all available buckets
  write-table    Write data into table
```

### Shell mode
There is an interactive shell which allows you to enter commands without having to specify php app/console each time, which is useful if you need to run several commands.
Autocomplete of commands is supported in shell model.

```bash
➜  storage-api-cli git:(master) php bin/sapi-client --token=your_sapi_token --shell
Authorized as: martin@keboola.com (KB Paymo (TAPI UI testing clone))

Welcome to the Keboola Storage API Client shell.

At the prompt, type help for some help,
or list to get a list of available commands.

To exit the shell, type ^D.

Keboola Storage API Client >
```


### Build
Tool is distributed as PHAR package, follow these steps to create package of current version:

```bash
curl -s http://box-project.org/installer.php | php
./box.phar build -v
```

`sapi-client.phar` archive will be created, you can execute it `./sapi-client.phar`





