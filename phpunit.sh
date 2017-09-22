#!/bin/bash
set -e

echo "Running tests"
./vendor/bin/phpcs --standard=psr2 --ignore=vendor -n ./
./vendor/bin/phpunit --verbose --debug
