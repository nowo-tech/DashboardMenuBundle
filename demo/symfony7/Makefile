.PHONY: help build up down restart shell install setup clean logs ensure-up update-bundle verify cache-clear schema-update migrations-status migrate test test-coverage ts-assets assets demo-started-msg

help:
	@echo "Dashboard Menu Bundle Demo - Symfony 7"
	@echo "  ensure-up, up, down, restart, build, shell, install, setup, clean, logs, update-bundle, assets, schema-update, migrations-status, migrate, test, test-coverage, ts-assets, verify, cache-clear"

assets: ensure-up
	# Build assets inside the demo container (so pnpm/vite run with container tooling).
	$(COMPOSE) exec -T php sh -c "cd /app && pnpm install && pnpm run build"

# If assets/ is root-owned (e.g. after Docker): sudo chown -R $$(whoami) assets && make ts-assets
ts-assets: ensure-up
	# Generate TS→JS assets inside the demo container.
	$(COMPOSE) exec -T php sh -c "cd /app && sh scripts/create-ts-assets.sh"

restart: ensure-up
	$(COMPOSE) restart

schema-update: ensure-up
	@echo "Updating database schema (adds missing columns, e.g. icon, class_* in dashboard_menu)..."
	$(COMPOSE) exec -T php php bin/console doctrine:schema:update --force --no-interaction
	@echo "Done. If you still see 'Unknown column' errors, run: make setup"

migrations-status: ensure-up
	$(COMPOSE) exec -T php php bin/console doctrine:migrations:status

migrate: ensure-up
	$(COMPOSE) exec -T php php bin/console doctrine:migrations:migrate --no-interaction

build:
	$(COMPOSE) build --no-cache

cache-clear: ensure-up
	$(COMPOSE) exec -T php sh -c "rm -rf var/cache/*"
	$(COMPOSE) exec -T php php bin/console cache:clear --no-interaction --no-warmup

up:
	@if [ ! -f .env ]; then cp .env.example .env; fi
	$(COMPOSE) down 2>/dev/null || true
	$(COMPOSE) build
	$(COMPOSE) up -d mysql
	@sleep 8
	$(COMPOSE) run --rm --entrypoint '' php composer install --no-interaction --prefer-dist
	$(COMPOSE) up -d
	@sleep 5
	$(MAKE) ensure-up
	$(MAKE) install
	@echo "Syncing composer.lock (remove gedmo/stof if present)..."
	$(COMPOSE) exec -T php composer update --no-interaction --no-scripts
	@echo "Restarting PHP so workers load vendor without Gedmo..."
	$(COMPOSE) restart php
	@echo "Waiting for PHP container..."
	@for i in 1 2 3 4 5 6 7 8 9 10; do $(COMPOSE) exec -T php true 2>/dev/null && break || sleep 2; done
	@sleep 2
	@echo "Clearing cache..."
	$(COMPOSE) exec -T php sh -c "rm -rf var/cache/*"
	$(COMPOSE) exec -T php php bin/console cache:clear --no-interaction --no-warmup
	@echo "Recreating database and running migrations (fresh)..."
	$(COMPOSE) exec -T php php bin/console doctrine:database:drop --if-exists --force --no-interaction
	$(COMPOSE) exec -T php php bin/console doctrine:database:create --no-interaction
	$(COMPOSE) exec -T php php bin/console doctrine:migrations:migrate --no-interaction
	@echo "Loading fixtures..."
	$(COMPOSE) exec -T php php bin/console doctrine:fixtures:load --no-interaction
	@$(MAKE) demo-started-msg

demo-started-msg:
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	echo "Demo started at: http://localhost:$$PORT"

down:
	$(COMPOSE) down

shell:
	$(COMPOSE) exec php sh

ensure-up:
	@if ! $(COMPOSE) exec -T php true 2>/dev/null; then \
		echo "Starting containers..."; \
		[ ! -f .env ] && cp .env.example .env || true; \
		$(COMPOSE) up -d mysql 2>/dev/null || true; \
		sleep 5; \
		$(COMPOSE) up -d; \
		sleep 5; \
		$(COMPOSE) exec -T php sh -c "composer install --no-interaction || composer update --no-interaction"; \
	fi

update-bundle: ensure-up
	@if $(COMPOSE) exec -T php true 2>/dev/null; then \
		$(COMPOSE) exec -T php sh -c "if [ -f composer.lock ]; then composer update nowo-tech/dashboard-menu-bundle --no-interaction; else composer update --no-interaction; fi"; \
		$(COMPOSE) exec -T php sh -c "rm -rf var/cache/*"; \
		$(COMPOSE) exec -T php php bin/console cache:clear --no-interaction --no-warmup; \
	else \
		$(COMPOSE) run --rm --entrypoint '' php sh -c "if [ -f composer.lock ]; then composer update nowo-tech/dashboard-menu-bundle --no-interaction; else composer update --no-interaction; fi; rm -rf var/cache/*; php bin/console cache:clear --no-interaction --no-warmup"; \
	fi

install: ensure-up
	$(COMPOSE) exec -T php composer install --no-interaction

setup: ensure-up install
	@echo "Syncing composer.lock (remove gedmo/stof if present)..."
	$(COMPOSE) exec -T php composer update --no-interaction --no-scripts
	@echo "Restarting PHP so workers load vendor without Gedmo..."
	$(COMPOSE) restart php
	@sleep 3
	@echo "Clearing cache..."
	$(COMPOSE) exec -T php sh -c "rm -rf var/cache/*"
	$(COMPOSE) exec -T php php bin/console cache:clear --no-interaction --no-warmup
	@echo "Recreating database and running migrations (fresh)..."
	$(COMPOSE) exec -T php php bin/console doctrine:database:drop --if-exists --force --no-interaction
	$(COMPOSE) exec -T php php bin/console doctrine:database:create --no-interaction
	$(COMPOSE) exec -T php php bin/console doctrine:migrations:migrate --no-interaction
	@echo "Loading fixtures..."
	$(COMPOSE) exec -T php php bin/console doctrine:fixtures:load --no-interaction
	@$(MAKE) demo-started-msg

clean:
	rm -rf vendor var/cache/* var/log/*

logs:
	$(COMPOSE) logs -f

test: ensure-up
	# Run bundle tests from within the demo container, using bundle PHPUnit config.
	$(COMPOSE) exec -e APP_ENV=test -T php php /var/dashboard-menu-bundle/vendor/bin/phpunit -c /var/dashboard-menu-bundle/phpunit.xml.dist

test-coverage: ensure-up
	# Run bundle tests from within the demo container, using bundle PHPUnit config.
	$(COMPOSE) exec -e APP_ENV=test -T php php /var/dashboard-menu-bundle/vendor/bin/phpunit -c /var/dashboard-menu-bundle/phpunit.xml.dist --coverage-text

verify: ensure-up
	@$(COMPOSE) exec -T php php bin/console lint:container && \
	 $(COMPOSE) exec -T php php -r "echo file_get_contents('http://127.0.0.1:80') ? 'OK' : 'FAIL';" 2>/dev/null || true


# REQ-MAKE-008: update-deps (REQ-MAKE-008)
SERVICE_PHP ?= php
# Prefer Compose V2 plugin (GitHub Actions / modern Docker Desktop); fall back to docker-compose V1 (REQ-MAKE-010).
COMPOSE     ?= $(shell docker compose version >/dev/null 2>&1 && echo "docker compose" || echo "docker-compose")
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/../..)
# Optional: monorepo helper absent on standalone GitHub Actions checkout (REQ-MAKE-009).
-include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-update-deps.mk
