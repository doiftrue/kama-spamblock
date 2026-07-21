define php_run
	docker run $(1) --rm --name SPAMBLOCK__php \
		-v "$(CURDIR):/app" -w /app \
		--user "$$(id -u):$$(id -g)" \
		-e COMPOSER_HOME=/tmp/composer \
		serversideup/php:8.2-cli  sh -c "$(2)"
endef

php.connect:
	$(call php_run, -it, bash)

phpunit:
	$(call php_run,, composer run phpunit)

composer.install:
	$(call php_run,, composer install)
composer.update:
	$(call php_run,, composer update)

