FROM php:7.1-alpine

ENV DEBIAN_FRONTEND noninteractive
ENV COMPOSER_ALLOW_SUPERUSER 1
ARG KEBOOLA_STORAGE_API_CLI_VERSION
ENV KEBOOLA_STORAGE_API_CLI_VERSION ${KEBOOLA_STORAGE_API_CLI_VERSION:-DEV}

# Deps
RUN apk add --no-cache wget git unzip gzip bash

COPY . /code/
WORKDIR /code/

RUN ./composer.sh \
  && rm composer.sh \
  && mv composer.phar /usr/local/bin/composer \
  && composer install --no-interaction \
  && apk del wget git unzip

ENTRYPOINT ["/code/bin/cli"]
