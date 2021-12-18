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

RUN apk add --no-cache git gnupg libzip icu-dev \
    && apk add --no-cache --virtual .build-deps libzip-dev \
    && docker-php-ext-install zip \
    && docker-php-ext-install bcmath \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl \
    && apk del .build-deps

ENV GH_VERSION=2.0.0
## GITHUB CLI 
RUN wget --no-check-certificate https://github.com/cli/cli/releases/download/v${GH_VERSION}/gh_${GH_VERSION}_linux_amd64.tar.gz \
	&& tar -zxvf gh_${GH_VERSION}_linux_amd64.tar.gz \
	&& mv gh_${GH_VERSION}_linux_amd64/bin/gh /usr/local/bin/gh \
	&& chmod +x /usr/local/bin/gh \
	&& gh --version

COPY composer.* /app/

RUN COMPOSER_CACHE_DIR=/dev/null composer install --no-dev --no-autoloader

# @TODO https://github.com/laminas/automatic-releases/issues/8 we skip `.git` for now, as it isn't available in the build environment
# @TODO https://github.com/laminas/automatic-releases/issues/9 we skip `.git` for now, as it isn't available in the build environment
#ADD .git /app/.git
COPY bin /app/bin/
COPY src /app/src/

RUN composer dump-autoload -a --no-dev

ENV SHELL_VERBOSITY=3

ENTRYPOINT ["/app/bin/console.php"]
