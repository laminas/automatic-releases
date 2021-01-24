FROM composer:2 AS composer

FROM ubuntu:20.04

COPY --from=composer /usr/bin/composer /usr/bin/composer

LABEL "com.github.actions.name"="laminas/automatic-releases"
LABEL "com.github.actions.description"="Creates git tags, releases, release branches and merge-up PRs based on closed milestones"
LABEL "com.github.actions.icon"="check"
LABEL "com.github.actions.color"="blue"

LABEL "repository"="http://github.com/laminas/automatic-releases"
LABEL "homepage"="http://github.com/laminas/automatic-releases"
LABEL "maintainer"="https://github.com/laminas/technical-steering-committee/"

WORKDIR /app

RUN apt update \
    && apt install -y software-properties-common \
    && add-apt-repository -y ppa:ondrej/php \
    && apt install -y \
        git \
        gnupg \
        libzip-dev \
        zip \
        php8.0-cli \
        php8.0-curl \
        php8.0-mbstring \
        php8.0-readline \
        php8.0-xml \
        php8.0-zip \
    && apt clean

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
