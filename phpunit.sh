#!/bin/bash

echo "Running tests"
/code/vendor/bin/phpcs --standard=psr2 --ignore=vendor -n /code/
/code/tests/loadToS3.php
/code/vendor/bin/phpunit --verbose --debug