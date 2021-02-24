FROM composer:2 AS composer

FROM php:8.0-alpine

COPY --from=composer /usr/bin/composer /usr/bin/composer

LABEL "com.github.actions.name"="laminas/automatic-releases"
LABEL "com.github.actions.description"="Creates git tags, releases, release branches and merge-up PRs based on closed milestones"
LABEL "com.github.actions.icon"="check"
LABEL "com.github.actions.color"="blue"

LABEL "repository"="http://github.com/laminas/automatic-releases"
LABEL "homepage"="http://github.com/laminas/automatic-releases"
LABEL "maintainer"="https://github.com/laminas/technical-steering-committee/"

WORKDIR /app

RUN apk add --no-cache git gnupg libzip \
    && apk add --no-cache --virtual .build-deps libzip-dev \
    && docker-php-ext-install zip \
    && apk del .build-deps

ADD composer.json /app/composer.json
ADD composer.lock /app/composer.lock

RUN COMPOSER_CACHE_DIR=/dev/null composer install --no-dev --no-autoloader

# @TODO https://github.com/laminas/automatic-releases/issues/8 we skip `.git` for now, as it isn't available in the build environment
# @TODO https://github.com/laminas/automatic-releases/issues/9 we skip `.git` for now, as it isn't available in the build environment
#ADD .git /app/.git
ADD bin /app/bin
ADD src /app/src

RUN composer install -a --no-dev

ENTRYPOINT ["/app/bin/console.php"]
