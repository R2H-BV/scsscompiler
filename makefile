main: vendor
	zip -r -9 -q scsscompiler.zip language vendor *.php *.xml

vendor: composer.json composer.lock
	composer install --no-dev --no-interaction --no-progress --no-suggest --optimize-autoloader
