# Dashboard Menu Bundle — demo aggregator (REQ-MAKE-003).
# Delegates to the in-repo demo (symfony8).

DEMOS := symfony8

.PHONY: help clean test-all test-coverage-all update-bundle-all verify-all release-check release-verify demo-smoke
.PHONY: $(foreach d,$(DEMOS),up-$d down-$d restart-$d build-$d install-$d update-bundle-$d shell-$d logs-$d test-$d test-coverage-$d verify-$d)
.PHONY: up down restart build install update-bundle test shell logs test-coverage verify

help:
	@echo "Dashboard Menu Bundle - Demo Commands"
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@echo "Per demo (symfony8):"
	@echo "  up-symfony8            Start demo"
	@echo "  down-symfony8          Stop demo"
	@echo "  restart-symfony8       Restart demo containers"
	@echo "  build-symfony8         Rebuild image (no cache)"
	@echo "  install-symfony8       Install dependencies"
	@echo "  update-bundle-symfony8 Update bundle from path and clear cache"
	@echo "  shell-symfony8         Open shell in container"
	@echo "  logs-symfony8          Show logs"
	@echo "  test-symfony8          Run tests"
	@echo "  test-coverage-symfony8 Run tests with coverage"
	@echo "  verify-symfony8        Start demo and check HTTP 200"
	@echo ""
	@echo "Generic (use DEMO=symfony8): up, down, install, test, shell, logs, update-bundle"
	@echo "  make up DEMO=symfony8"
	@echo "  make up-symfony8"
	@echo ""
	@echo "Other:"
	@echo "  update-bundle-all    Sync path repo in all demos"
	@echo "  test-all             Run tests in all demos"
	@echo "  test-coverage-all    Run tests with coverage in all demos"
	@echo "  verify-all           Verify all demos (up + HTTP 200)"
	@echo "  release-check        update-bundle-all + release-verify"
	@echo "  release-verify       For release-check: verify each demo"
	@echo "  demo-smoke           REQ-TEST-011: boot primary demo (symfony8) + HTTP 200"
	@echo "  clean                Remove vendor and var from all demos"
	@echo ""
	@echo "Access (after make up-symfony8): see demo/symfony8/.env PORT (default 8011)."

up-symfony8:
	@$(MAKE) -C symfony8 up

down-symfony8:
	@$(MAKE) -C symfony8 down

restart-symfony8:
	@$(MAKE) -C symfony8 restart

build-symfony8:
	@$(MAKE) -C symfony8 build

install-symfony8:
	@$(MAKE) -C symfony8 install

update-bundle-symfony8:
	@$(MAKE) -C symfony8 update-bundle

shell-symfony8:
	@$(MAKE) -C symfony8 shell

logs-symfony8:
	@$(MAKE) -C symfony8 logs

test-symfony8:
	@$(MAKE) -C symfony8 test

test-coverage-symfony8:
	@$(MAKE) -C symfony8 test-coverage

verify-symfony8:
	@$(MAKE) -C symfony8 verify

# Generic with DEMO= (REQ-MAKE-003: up / down / update-bundle)
up:
	@[ -n "$(DEMO)" ] || { echo "Usage: make up DEMO=symfony8"; exit 1; }
	@$(MAKE) up-$(DEMO)
down:
	@[ -n "$(DEMO)" ] || { echo "Usage: make down DEMO=symfony8"; exit 1; }
	@$(MAKE) down-$(DEMO)
restart:
	@[ -n "$(DEMO)" ] || { echo "Usage: make restart DEMO=symfony8"; exit 1; }
	@$(MAKE) restart-$(DEMO)
build:
	@[ -n "$(DEMO)" ] || { echo "Usage: make build DEMO=symfony8"; exit 1; }
	@$(MAKE) build-$(DEMO)
install:
	@[ -n "$(DEMO)" ] || { echo "Usage: make install DEMO=symfony8"; exit 1; }
	@$(MAKE) install-$(DEMO)
update-bundle:
	@[ -n "$(DEMO)" ] || { echo "Usage: make update-bundle DEMO=symfony8"; exit 1; }
	@$(MAKE) update-bundle-$(DEMO)
shell:
	@[ -n "$(DEMO)" ] || { echo "Usage: make shell DEMO=symfony8"; exit 1; }
	@$(MAKE) shell-$(DEMO)
logs:
	@[ -n "$(DEMO)" ] || { echo "Usage: make logs DEMO=symfony8"; exit 1; }
	@$(MAKE) logs-$(DEMO)
test:
	@[ -n "$(DEMO)" ] || { echo "Usage: make test DEMO=symfony8"; exit 1; }
	@$(MAKE) test-$(DEMO)
test-coverage:
	@[ -n "$(DEMO)" ] || { echo "Usage: make test-coverage DEMO=symfony8"; exit 1; }
	@$(MAKE) test-coverage-$(DEMO)
verify:
	@[ -n "$(DEMO)" ] || { echo "Usage: make verify DEMO=symfony8"; exit 1; }
	@$(MAKE) verify-$(DEMO)

update-bundle-all:
	@for d in $(DEMOS); do $(MAKE) -C $$d update-bundle || exit 1; done

test-all:
	@$(MAKE) test-symfony8
	@echo "All demo tests passed."

test-coverage-all:
	@for d in $(DEMOS); do $(MAKE) -C $$d test-coverage || exit 1; done
	@echo "test-coverage-all done."

verify-all:
	@$(MAKE) verify-symfony8

release-check: update-bundle-all release-verify
	@echo "All demos passed release-check."

release-verify:
	@for d in $(DEMOS); do $(MAKE) -C $$d verify; done

# REQ-TEST-011: boot primary demo + one HTTP 200
demo-smoke:
	@PORT=$$(grep "^PORT=" symfony8/.env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" symfony8/.env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=8011; \
	echo "Demo smoke: symfony8 (http://127.0.0.1:$$PORT)"; \
	$(MAKE) -C symfony8 up || exit 1; \
	sleep 12; \
	code=$$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:$$PORT" || true); \
	[ -z "$$code" ] && code=000; \
	$(MAKE) -C symfony8 down 2>/dev/null || true; \
	if [ "$$code" != "200" ]; then echo "symfony8 failed with HTTP $$code"; exit 1; fi; \
	echo "symfony8 OK (HTTP $$code)"

clean:
	@rm -rf $(addsuffix /vendor,$(DEMOS)) $(addsuffix /var,$(DEMOS)) $(addsuffix /.phpunit.cache,$(DEMOS))
	@echo "Cleaned demos."


# REQ-MAKE-008: update-deps-all (optional monorepo helper; REQ-MAKE-009 soft include)
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/..)
-include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-aggregate-update-deps.mk
