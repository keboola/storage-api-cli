#!/bin/bash
set -e
docker login -u="$QUAY_USERNAME" -p="$QUAY_PASSWORD" quay.io
docker tag keboola/storage-api-cli quay.io/keboola/storage-api-cli:${TRAVIS_TAG}
docker tag keboola/storage-api-cli quay.io/keboola/storage-api-cli:latest
docker images
docker push quay.io/keboola/storage-api-cli:${TRAVIS_TAG}
docker push quay.io/keboola/storage-api-cli:latest
