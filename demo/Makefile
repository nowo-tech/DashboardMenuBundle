# Dashboard Menu Bundle - Demo management
DEMOS := symfony7 symfony8

help:
	@echo "Demos: $(DEMOS)"
	@echo "  make up-symfony7, make ensure-up-symfony7, make setup-symfony7, make verify-symfony7, etc."
	@echo "  make release-verify  - run verify in all demos"

release-verify:
	@for d in $(DEMOS); do $(MAKE) -C $$d verify || true; done
