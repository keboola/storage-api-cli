# Keboola Storage API Command Line Interface

[![Build Status](https://travis-ci.org/keboola/storage-api-cli.png?branch=master)](https://travis-ci.org/keboola/storage-api-cli)

Simple command line wrapper for [Keboola Storage REST API](http://docs.keboola.apiary.io/)

## Installation

Download the latest version from https://s3.amazonaws.com/keboola-storage-api-cli/builds/sapi-client.phar .

From there, you may place it anywhere that will make it easier for you to access (such as /usr/local/bin) and chmod it to 755.
You can even rename it to just sapi-client to avoid having to type the .phar extension every time.

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
  delete-table   Delete table
  copy-table     Copy table including content, primary keys, indexes and attributes
  copy-bucket    Copy bucket and all tables inside
  help           Displays help for a command
  list           Lists commands
  list-buckets   List all available buckets
  delete-bucket  Delete bucket
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

## Development

```bash
git clone git@github.com:keboola/storage-api-cli.git
cd storage-api-cli
composer install
```

## Build
Tool is distributed as PHAR package, follow these steps to create package of current version:

```bash
curl -s http://box-project.org/installer.php | php
./box.phar build -v
```

`sapi-client.phar` archive will be created, you can execute it `./sapi-client.phar`





