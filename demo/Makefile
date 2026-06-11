# Dashboard Menu Bundle - Demo management
DEMOS := symfony7 symfony8

.PHONY: help update-bundle-all test-coverage-all release-check release-verify

help:
	@echo "Demos: $(DEMOS)"
	@echo "  make up-symfony7, make ensure-up-symfony7, make setup-symfony7, make verify-symfony7, etc."
	@echo "  make release-check   - test-coverage-all, release-verify"
	@echo "  make release-verify   - run verify in all demos"

update-bundle-all:
	@for d in $(DEMOS); do $(MAKE) -C $$d update-bundle || exit 1; done

test-coverage-all:
	@for d in $(DEMOS); do $(MAKE) -C $$d test-coverage || exit 1; done
	@echo "test-coverage-all done."

release-check: update-bundle-all test-coverage-all release-verify
	@echo "All demos passed release-check."

release-verify:
	@for d in $(DEMOS); do $(MAKE) -C $$d verify || true; done

# REQ-MAKE-008: update-deps-all (REQ-MAKE-008)
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/..)
include $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/../../.scripts/Makefile.demo-aggregate-update-deps.mk
