# Makefile for Doctrine Encrypt Bundle demos (symfony8)
# Run from demo/: make up-symfony8, etc.

.PHONY: help up-symfony8 down-symfony8 restart-symfony8 build-symfony8 install-symfony8 setup-symfony8 shell-symfony8 update-bundle-symfony8 update-bundle-all release-check release-verify

help:
	@echo "Doctrine Encrypt Bundle - Demos"
	@echo ""
	@echo "  make up-symfony8      Start Symfony 8 demo (http://localhost:8008)"
	@echo "  make down-symfony8    Stop Symfony 8 demo"
	@echo "  make restart-symfony8 Restart Symfony 8 demo containers"
	@echo "  make build-symfony8   Rebuild Symfony 8 image (no cache)"
	@echo "  make install-symfony8 Install deps in Symfony 8 demo"
	@echo "  make setup-symfony8   install + db + schema + key + fixtures (Symfony 8)"
	@echo "  make shell-symfony8   Shell in Symfony 8 container"
	@echo "  make update-bundle-symfony8  Update bundle from path repo (Symfony 8)"
	@echo "  make release-check   Pre-release: test-coverage-all, release-verify"
	@echo "  make release-verify  Start each demo, healthcheck (HTTP 200), then stop"
	@echo ""

up-symfony8:
	$(MAKE) -C symfony8 up

down-symfony8:
	$(MAKE) -C symfony8 down

restart-symfony8:
	$(MAKE) -C symfony8 restart

build-symfony8:
	$(MAKE) -C symfony8 build

install-symfony8:
	$(MAKE) -C symfony8 install

setup-symfony8:
	$(MAKE) -C symfony8 setup

shell-symfony8:
	$(MAKE) -C symfony8 shell

update-bundle-symfony8:
	$(MAKE) -C symfony8 update-bundle

# update-bundle-all: refresh path-repo bundle before tests (REQ-DEMO-007)
update-bundle-all:
	$(MAKE) update-bundle-symfony8
	@echo "✅ update-bundle-all done."

# release-check: test-coverage-all, then release-verify
test-coverage-all:
	@for demo in symfony8; do $(MAKE) test-coverage-$$demo || exit 1; done
	@echo "✅ test-coverage-all done."
test-coverage-symfony8:
	$(MAKE) -C symfony8 test-coverage
release-check: update-bundle-all test-coverage-all release-verify
	@echo "✅ All demos passed release-check."

# Release verify: for each demo, up → sleep → healthcheck (HTTP 200) → down
release-verify:
	@for demo in symfony8; do \
		port=$$(case $$demo in symfony8) echo 8008;; *) echo 8000;; esac); \
		echo "🔍 Release verify: $$demo (port $$port)..."; \
		$(MAKE) up-$$demo; \
		echo "⏳ Waiting for $$demo to be ready..."; sleep 15; \
		code=$$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:$$port 2>/dev/null || echo "000"); \
		$(MAKE) down-$$demo 2>/dev/null || true; \
		if [ "$$code" != "200" ]; then echo "❌ $$demo healthcheck failed (HTTP $$code)"; exit 1; fi; \
		echo "✅ $$demo OK (HTTP $$code)"; \
	done
	@echo "✅ All demos passed release-verify."


# REQ-MAKE-008: update-deps-all (REQ-MAKE-008)
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/..)
include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-aggregate-update-deps.mk
