#!/usr/bin/env bash

echo "Running tests"
/code/vendor/bin/phpcs --standard=psr2 --ignore=vendor -n /code/
/code/vendor/bin/phpunit --verbose --debug