FROM php:7.4.0RC5-zts-alpine

LABEL "com.github.actions.name"="doctrine/automatic-releases"
LABEL "com.github.actions.description"="Creates git tags, releases, release branches and merge-up PRs based on closed milestones"
# @TODO what icons are available?
LABEL "com.github.actions.icon"="check"
# @TODO what icons are available?
LABEL "com.github.actions.color"="blue"

LABEL "repository"="http://github.com/doctrine/automatic-releases"
LABEL "homepage"="http://github.com/doctrine/automatic-releases"
LABEL "maintainer"="Marco Pivetta <ocramius@gmail.com>"

ADD composer.json /app/composer.json
ADD composer.lock /app/composer.lock

RUN composer install --no-dev --no-autoloader

ADD bin /app/bin
ADD src /app/src

RUN composer install -a --no-dev

ADD entrypoint.sh /app/entrypoint.sh

ENTRYPOINT ["/app/entrypoint.sh"]
