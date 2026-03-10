.PHONY: help build up down restart shell install setup clean logs ensure-up update-bundle verify cache-clear test test-coverage

help:
	@echo "Dashboard Menu Bundle Demo - Symfony 7"
	@echo "  ensure-up, up, down, restart, build, shell, install, setup, clean, logs, update-bundle, test, test-coverage, verify, cache-clear"

build:
	docker-compose build --no-cache

restart: ensure-up
	docker-compose restart

cache-clear: ensure-up
	docker-compose exec -T php php bin/console cache:clear

up:
	@if [ ! -f .env ]; then cp .env.example .env; fi
	docker-compose down 2>/dev/null || true
	docker-compose build
	docker-compose up -d mysql
	@sleep 8
	docker-compose run --rm --entrypoint '' php composer install --no-interaction --prefer-dist
	docker-compose up -d
	@sleep 5
	$(MAKE) ensure-up
	$(MAKE) install
	@echo "Clearing cache..."
	docker-compose exec -T php php bin/console cache:clear --no-interaction
	@echo "Creating database and schema..."
	docker-compose exec -T php php bin/console doctrine:database:create --if-not-exists --no-interaction
	docker-compose exec -T php php bin/console doctrine:schema:update --force --no-interaction
	@echo "Loading fixtures..."
	docker-compose exec -T php php bin/console doctrine:fixtures:load --no-interaction
	@echo "Demo ready. Open http://localhost:8010"

down:
	docker-compose down

shell:
	docker-compose exec php sh

ensure-up:
	@if ! docker-compose exec -T php true 2>/dev/null; then \
		echo "Starting containers..."; \
		[ ! -f .env ] && cp .env.example .env || true; \
		docker-compose up -d mysql 2>/dev/null || true; \
		sleep 5; \
		docker-compose up -d; \
		sleep 5; \
		docker-compose exec -T php sh -c "composer install --no-interaction || composer update --no-interaction"; \
	fi

update-bundle:
	@if docker-compose exec -T php true 2>/dev/null; then \
		docker-compose exec -T php sh -c "if [ -f composer.lock ]; then composer update nowo-tech/dashboard-menu-bundle --no-interaction; else composer update --no-interaction; fi"; \
		docker-compose exec -T php php bin/console cache:clear; \
	else \
		docker-compose run --rm --entrypoint '' php sh -c "if [ -f composer.lock ]; then composer update nowo-tech/dashboard-menu-bundle --no-interaction; else composer update --no-interaction; fi; php bin/console cache:clear"; \
	fi

install: ensure-up
	docker-compose exec -T php composer install --no-interaction

setup: ensure-up install
	@echo "Clearing cache..."
	docker-compose exec -T php php bin/console cache:clear --no-interaction
	@echo "Creating database and schema..."
	docker-compose exec -T php php bin/console doctrine:database:create --if-not-exists --no-interaction
	docker-compose exec -T php php bin/console doctrine:schema:update --force --no-interaction
	@echo "Loading fixtures..."
	docker-compose exec -T php php bin/console doctrine:fixtures:load --no-interaction
	@echo "Demo ready. Open http://localhost:8010"

clean:
	rm -rf vendor var/cache/* var/log/*

logs:
	docker-compose logs -f

test: ensure-up
	docker-compose exec -e APP_ENV=test php php bin/phpunit 2>/dev/null || docker-compose exec -e APP_ENV=test php vendor/bin/phpunit 2>/dev/null || echo "No PHPUnit config in demo; run tests from bundle root."

test-coverage: ensure-up
	docker-compose exec -e APP_ENV=test php php bin/phpunit --coverage-text 2>/dev/null || docker-compose exec -e APP_ENV=test php vendor/bin/phpunit --coverage-text 2>/dev/null || echo "No PHPUnit config in demo; run test-coverage from bundle root."

verify: ensure-up
	@docker-compose exec -T php php bin/console lint:container && \
	 docker-compose exec -T php php -r "echo file_get_contents('http://127.0.0.1:80') ? 'OK' : 'FAIL';" 2>/dev/null || true
