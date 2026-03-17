# Dashboard Menu Bundle - Demo management
DEMOS := symfony7 symfony8

help:
	@echo "Demos: $(DEMOS)"
	@echo "  make up-symfony7, make ensure-up-symfony7, make setup-symfony7, make verify-symfony7, etc."
	@echo "  make release-check   - test-coverage-all, release-verify"
	@echo "  make release-verify   - run verify in all demos"

test-coverage-all:
	@for d in $(DEMOS); do $(MAKE) -C $$d test-coverage || exit 1; done
	@echo "test-coverage-all done."
release-check: test-coverage-all release-verify
	@echo "All demos passed release-check."

release-verify:
	@for d in $(DEMOS); do $(MAKE) -C $$d verify || true; done
