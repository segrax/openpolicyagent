RUN = docker-compose run php

.DEFAULT_GOAL : test

all: style test
composer-install:
	$(RUN) env XDEBUG_MODE=coverage composer install --dev
composer-update:
	$(RUN) env XDEBUG_MODE=coverage composer update --dev
test:
	$(RUN) env XDEBUG_MODE=debug,coverage XDEBUG_CONFIG="client_host=host.docker.internal" XDEBUG_SESSION=1 vendor/bin/phpunit 
style:
	$(RUN) env XDEBUG_MODE=off vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php 
psalm:
	$(RUN) env XDEBUG_MODE=off vendor/bin/psalm
rector:
	$(RUN) env XDEBUG_MODE=off  vendor/bin/rector -v