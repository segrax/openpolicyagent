RUN = docker-compose run php

.DEFAULT_GOAL : test

all: style test
tests:
	$(RUN) env XDEBUG_MODE=coverage vendor/bin/phpunit
style:
	$(RUN) env XDEBUG_MODE=off vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php
psalm:
	$(RUN) env XDEBUG_MODE=off vendor/bin/psalm
