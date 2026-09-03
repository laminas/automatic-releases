FROM composer:2 AS composer

FROM php:8.3-alpine

COPY --from=composer /usr/bin/composer /usr/bin/composer

LABEL "com.github.actions.name"="laminas/automatic-releases"
LABEL "com.github.actions.description"="Creates git tags, releases, release branches and merge-up PRs based on closed milestones"
LABEL "com.github.actions.icon"="check"
LABEL "com.github.actions.color"="blue"

LABEL "repository"="http://github.com/laminas/automatic-releases"
LABEL "homepage"="http://github.com/laminas/automatic-releases"
LABEL "maintainer"="https://github.com/laminas/technical-steering-committee/"

WORKDIR /app

RUN apk add --no-cache git git-lfs gnupg libzip icu-dev \
    && apk add --no-cache --virtual .build-deps libzip-dev \
    && git lfs install --skip-repo \
    && docker-php-ext-install zip \
    && docker-php-ext-install bcmath \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl \
    && apk del .build-deps

COPY composer.* /app/

RUN COMPOSER_CACHE_DIR=/dev/null composer install --no-dev --no-autoloader

# @TODO https://github.com/laminas/automatic-releases/issues/8 we skip `.git` for now, as it isn't available in the build environment
# @TODO https://github.com/laminas/automatic-releases/issues/9 we skip `.git` for now, as it isn't available in the build environment
#ADD .git /app/.git
COPY bin /app/bin/
COPY src /app/src/

RUN composer dump-autoload -a --no-dev

# Keep the GnuPG state inside the container. In GitHub Actions $HOME is a volume shared by every step of a
# job, so a GNUPGHOME under $HOME leaks from one step to the next: the imported private key stays readable
# by later steps, and GnuPG 2.4 makes it worse by enabling keyboxd when it creates a default home directory,
# leaving a database and a dead socket that make the next import time out. A directory created here is fresh
# in every container, so no state is ever shared. As a bonus, GnuPG 2.4 leaves an existing GNUPGHOME on the
# plain keyring instead of switching it to keyboxd.
ENV GNUPGHOME=/gnupg
RUN mkdir -m 0700 /gnupg

ENV SHELL_VERBOSITY=3

ENTRYPOINT ["/app/bin/console.php"]
