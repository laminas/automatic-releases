FROM composer:1 AS composer

FROM php:7.4-cli

COPY --from=composer /usr/bin/composer /usr/bin/composer

LABEL "com.github.actions.name"="doctrine/automatic-releases"
LABEL "com.github.actions.description"="Creates git tags, releases, release branches and merge-up PRs based on closed milestones"
LABEL "com.github.actions.icon"="check"
LABEL "com.github.actions.color"="blue"

LABEL "repository"="http://github.com/laminas/automatic-releases"
LABEL "homepage"="http://github.com/laminas/automatic-releases"
LABEL "maintainer"="https://github.com/laminas/technical-steering-committee/"

WORKDIR /app

RUN apt update \
    && apt install -y \
        git \
        libzip-dev \
        zip \
    && docker-php-ext-install zip \
    && apt clean


ADD composer.json /app/composer.json
ADD composer.lock /app/composer.lock

RUN COMPOSER_CACHE_DIR=/dev/null composer install --no-dev --no-autoloader

ADD .git /app/.git
ADD bin /app/bin
ADD src /app/src

RUN composer install -a --no-dev

ADD entrypoint.sh /app/entrypoint.sh

ENTRYPOINT ["/app/entrypoint.sh"]
