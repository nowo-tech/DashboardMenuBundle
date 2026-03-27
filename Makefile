# Makefile for Dashboard Menu Bundle
.PHONY: help up down build shell install test test-coverage validate-translations cs-check cs-fix qa clean assets ensure-up rector rector-dry phpstan release-check release-check-demos composer-sync update validate

help:
	@echo "Dashboard Menu Bundle - Development Commands"
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@echo "Targets:"
	@echo "  up              Start Docker container"
	@echo "  down            Stop Docker container"
	@echo "  ensure-up       Ensure container is up (start + composer install if needed)"
	@echo "  build           Rebuild Docker image (no cache)"
	@echo "  shell           Open shell in container"
	@echo "  install         Install Composer dependencies"
	@echo "  assets          Build frontend assets (Vite: dashboard.js + stimulus-live.js)"
	@echo "  test            Run PHPUnit tests"
	@echo "  test-coverage   Run PHPUnit tests with code coverage"
	@echo "  validate-translations  Lint bundle translation YAML files"
	@echo "  cs-check        Check code style"
	@echo "  cs-fix          Fix code style"
	@echo "  rector          Apply Rector refactoring"
	@echo "  rector-dry      Run Rector in dry-run mode"
	@echo "  phpstan         Run PHPStan static analysis"
	@echo "  qa              Run all QA checks"
	@echo "  release-check   Pre-release: cs-fix, cs-check, rector-dry, phpstan, test-coverage, demo verify"
	@echo "  composer-sync   Validate composer.json and align composer.lock"
	@echo "  clean           Remove vendor and cache"
	@echo "  update          Run composer update"
	@echo "  validate        Run composer validate --strict"
	@echo ""
	@echo "Demos: make -C demo or make -C demo/symfony7"

COMPOSE_FILE ?= docker-compose.yml
COMPOSE     ?= docker-compose -f $(COMPOSE_FILE)
SERVICE_PHP ?= php

build:
	$(COMPOSE) build --no-cache

up:
	@echo "Building Docker image..."
	$(COMPOSE) build
	@echo "Starting container..."
	$(COMPOSE) up -d
	@echo "Waiting for container to be ready..."
	@sleep 3
	@echo "Installing dependencies..."
	$(COMPOSE) exec -T $(SERVICE_PHP) sh -c "composer install --no-interaction || composer update --no-interaction"
	@echo "✅ Container ready!"

down:
	$(COMPOSE) down

ensure-up:
	@if ! $(COMPOSE) exec -T $(SERVICE_PHP) true 2>/dev/null; then \
		echo "Starting container..."; \
		$(COMPOSE) up -d; \
		sleep 3; \
		$(COMPOSE) exec -T $(SERVICE_PHP) sh -c "composer install --no-interaction || composer update --no-interaction"; \
	fi

shell:
	$(COMPOSE) exec $(SERVICE_PHP) sh

install: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer install

test: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) composer test

test-coverage: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) composer test-coverage | tee coverage-php.txt
	sh ./.scripts/php-coverage-percent.sh coverage-php.txt

validate-translations: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) php .scripts/validate-translations.php

cs-check: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer cs-check

cs-fix: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer cs-fix

rector: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer rector

rector-dry: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer rector-dry

phpstan: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer phpstan

qa: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer qa

composer-sync: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer validate --strict
	$(COMPOSE) exec -T $(SERVICE_PHP) composer update --no-install

update: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer update --no-interaction

validate: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer validate --strict

release-check: ensure-up composer-sync cs-fix cs-check rector-dry phpstan test-coverage release-check-demos

release-check-demos:
	@$(MAKE) -C demo release-check 2>/dev/null || true

assets:
	@if [ ! -d node_modules ]; then pnpm install; fi
	@pnpm run build
	@echo "✅ Assets built: src/Resources/public/js/dashboard.js, js/stimulus-live.js"

clean:
	rm -rf vendor .phpunit.cache coverage coverage.xml .php-cs-fixer.cache
