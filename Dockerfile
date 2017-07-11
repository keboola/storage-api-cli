FROM php:7.1-cli

ENV DEBIAN_FRONTEND noninteractive
ENV COMPOSER_ALLOW_SUPERUSER 1

# Deps
RUN apt-get update && apt-get install -y --no-install-recommends \
  	  git \
  	  unzip \
    && rm -r /var/lib/apt/lists/* \
    && cd /root/ \
    && curl -sS https://getcomposer.org/installer | php \
    && ln -s /root/composer.phar /usr/local/bin/composer

COPY . /code/
WORKDIR /code/
RUN composer install --no-interaction
ENTRYPOINT ["/code/bin/sapi-client"]
