FROM php:8.3-cli-alpine

WORKDIR /srv/app

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
    ast \
    xdebug-stable \
    @composer
