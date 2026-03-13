install:
	docker compose run --rm php composer install

test:
	docker compose run --rm php vendor/bin/phpunit

bash:
	docker compose run --rm php bash
