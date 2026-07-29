# Makefile for Dashboard Menu Bundle
.PHONY: help up down build shell install test test-coverage coverage-check validate-translations cs-check cs-fix qa clean assets ensure-up rector rector-dry phpstan release-check release-check-demos demo-smoke composer-sync update validate check-no-cursor-coauthor check-open-prs strip-cursor-coauthor-from-history

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
	@echo "  coverage-check  Coverage with fail-under 99% lines"
	@echo "  demo-smoke      Boot demos and assert HTTP 200"
	@echo "  validate-translations  Lint bundle translation YAML files"
	@echo "  cs-check        Check code style"
	@echo "  cs-fix          Fix code style"
	@echo "  rector          Apply Rector refactoring"
	@echo "  rector-dry      Run Rector in dry-run mode"
	@echo "  phpstan         Run PHPStan static analysis"
	@echo "  qa              Run all QA checks"
	@echo "  release-check   Pre-release gates (PRs, QA, coverage-check, demos, assets)"
	@echo "  composer-sync   Validate composer.json and align composer.lock"
	@echo "  clean           Remove vendor and cache"
	@echo "  update          Run composer update"
	@echo "  validate        Run composer validate --strict"
	@echo ""
	@echo "Demos: make -C demo or make -C demo/symfony7"

COMPOSE_FILE ?= docker-compose.yml
# Prefer Compose V2 plugin (GitHub Actions / modern Docker Desktop); fall back to docker-compose V1 (REQ-MAKE-010).
COMPOSE_BIN ?= $(shell docker compose version >/dev/null 2>&1 && echo "docker compose" || echo "docker-compose")
COMPOSE     ?= $(COMPOSE_BIN) -f $(COMPOSE_FILE)
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
	@echo "Installing Node dependencies (pnpm)..."
	$(COMPOSE) exec -T -e CI=true $(SERVICE_PHP) sh -c "pnpm install --frozen-lockfile || pnpm install"
	@echo "✅ Container ready!"

down:
	$(COMPOSE) down

ensure-up:
	@if ! $(COMPOSE) exec -T $(SERVICE_PHP) true 2>/dev/null; then \
		echo "Starting container..."; \
		$(COMPOSE) up -d; \
		sleep 3; \
		$(COMPOSE) exec -T $(SERVICE_PHP) sh -c "composer install --no-interaction || composer update --no-interaction"; \
		$(COMPOSE) exec -T -e CI=true $(SERVICE_PHP) sh -c "pnpm install --frozen-lockfile || pnpm install"; \
	fi

shell:
	$(COMPOSE) exec $(SERVICE_PHP) sh

install: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer install
	$(COMPOSE) exec -T -e CI=true $(SERVICE_PHP) sh -c "pnpm install --frozen-lockfile || pnpm install"

test: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) composer test

test-coverage: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) composer test-coverage | tee coverage-php.txt
	sh ./.scripts/php-coverage-percent.sh coverage-php.txt

coverage-check: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer coverage-check

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

release-check: check-no-cursor-coauthor check-open-prs ensure-up composer-sync cs-fix cs-check rector-dry phpstan coverage-check release-check-demos assets

release-check-demos:
	@if [ -f demo/Makefile ]; then $(MAKE) -C demo release-check; else true; fi

demo-smoke:
	@if [ -f demo/Makefile ]; then $(MAKE) -C demo release-verify; else echo "No demo/Makefile"; exit 1; fi

assets: ensure-up
	$(COMPOSE) exec -T -e CI=true $(SERVICE_PHP) sh -c "pnpm install --frozen-lockfile || pnpm install"
	$(COMPOSE) exec -T $(SERVICE_PHP) pnpm run build
	@echo "✅ Assets built: src/Resources/public/js/dashboard.js, js/stimulus-live.js"

clean:
	rm -rf vendor node_modules .phpunit.cache coverage coverage.xml .php-cs-fixer.cache coverage-php.txt coverage-output.txt



setup-hooks:
	@chmod +x .githooks/pre-commit 2>/dev/null || true
	@chmod +x .githooks/commit-msg 2>/dev/null || true
	@git config core.hooksPath .githooks
	@echo "✅ Git hooks installed (.githooks — includes commit-msg for REQ-GIT-001)."

# REQ-MAKE-008: update-deps (REQ-MAKE-008)
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST))))
# Optional: monorepo helper absent on standalone GitHub Actions checkout (REQ-MAKE-009).
-include $(BUNDLE_ROOT)/../.scripts/Makefile.update-deps.mk
check-no-cursor-coauthor:
	@chmod +x .scripts/check-no-cursor-coauthor.sh
	@./.scripts/check-no-cursor-coauthor.sh HEAD

check-open-prs:
	@chmod +x .scripts/check-open-prs.sh
	@./.scripts/check-open-prs.sh

strip-cursor-coauthor-from-history:
	@chmod +x .scripts/strip-cursor-coauthor-from-history.sh
	@./.scripts/strip-cursor-coauthor-from-history.sh main
