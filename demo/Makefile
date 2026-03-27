# Makefile for Doctrine Encrypt Bundle demos (symfony7, symfony8)
# Run from demo/: make up-symfony7, make up-symfony8, etc.

.PHONY: help up-symfony7 up-symfony8 down-symfony7 down-symfony8 restart-symfony7 restart-symfony8 build-symfony7 build-symfony8 install-symfony7 install-symfony8 setup-symfony7 setup-symfony8 shell-symfony7 shell-symfony8 update-bundle-symfony7 update-bundle-symfony8 update-bundle-all release-check release-verify

help:
	@echo "Doctrine Encrypt Bundle - Demos"
	@echo ""
	@echo "  make up-symfony7      Start Symfony 7 demo (http://localhost:8007)"
	@echo "  make up-symfony8      Start Symfony 8 demo (http://localhost:8008)"
	@echo "  make down-symfony7    Stop Symfony 7 demo"
	@echo "  make down-symfony8    Stop Symfony 8 demo"
	@echo "  make restart-symfony7 Restart Symfony 7 demo containers"
	@echo "  make restart-symfony8 Restart Symfony 8 demo containers"
	@echo "  make build-symfony7   Rebuild Symfony 7 image (no cache)"
	@echo "  make build-symfony8   Rebuild Symfony 8 image (no cache)"
	@echo "  make install-symfony7 Install deps in Symfony 7 demo"
	@echo "  make install-symfony8 Install deps in Symfony 8 demo"
	@echo "  make setup-symfony7   install + db + schema + key + fixtures (Symfony 7)"
	@echo "  make setup-symfony8   install + db + schema + key + fixtures (Symfony 8)"
	@echo "  make shell-symfony7   Shell in Symfony 7 container"
	@echo "  make shell-symfony8   Shell in Symfony 8 container"
	@echo "  make update-bundle-symfony7  Update bundle from path repo (Symfony 7)"
	@echo "  make update-bundle-symfony8  Update bundle from path repo (Symfony 8)"
	@echo "  make release-check   Pre-release: test-coverage-all, release-verify"
	@echo "  make release-verify  Start each demo, healthcheck (HTTP 200), then stop"
	@echo ""

up-symfony7:
	$(MAKE) -C symfony7 up

up-symfony8:
	$(MAKE) -C symfony8 up

down-symfony7:
	$(MAKE) -C symfony7 down

down-symfony8:
	$(MAKE) -C symfony8 down

restart-symfony7:
	$(MAKE) -C symfony7 restart

restart-symfony8:
	$(MAKE) -C symfony8 restart

build-symfony7:
	$(MAKE) -C symfony7 build

build-symfony8:
	$(MAKE) -C symfony8 build

install-symfony7:
	$(MAKE) -C symfony7 install

install-symfony8:
	$(MAKE) -C symfony8 install

setup-symfony7:
	$(MAKE) -C symfony7 setup

setup-symfony8:
	$(MAKE) -C symfony8 setup

shell-symfony7:
	$(MAKE) -C symfony7 shell

shell-symfony8:
	$(MAKE) -C symfony8 shell

update-bundle-symfony7:
	$(MAKE) -C symfony7 update-bundle

update-bundle-symfony8:
	$(MAKE) -C symfony8 update-bundle

# update-bundle-all: refresh path-repo bundle before tests (REQ-DEMO-007)
update-bundle-all:
	$(MAKE) update-bundle-symfony7
	$(MAKE) update-bundle-symfony8
	@echo "✅ update-bundle-all done."

# release-check: test-coverage-all, then release-verify
test-coverage-all:
	@for demo in symfony7 symfony8; do $(MAKE) test-coverage-$$demo || exit 1; done
	@echo "✅ test-coverage-all done."
test-coverage-symfony7:
	$(MAKE) -C symfony7 test-coverage
test-coverage-symfony8:
	$(MAKE) -C symfony8 test-coverage
release-check: update-bundle-all test-coverage-all release-verify
	@echo "✅ All demos passed release-check."

# Release verify: for each demo, up → sleep → healthcheck (HTTP 200) → down
release-verify:
	@for demo in symfony7 symfony8; do \
		port=$$(case $$demo in symfony7) echo 8007;; symfony8) echo 8008;; *) echo 8000;; esac); \
		echo "🔍 Release verify: $$demo (port $$port)..."; \
		$(MAKE) up-$$demo; \
		echo "⏳ Waiting for $$demo to be ready..."; sleep 15; \
		code=$$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:$$port 2>/dev/null || echo "000"); \
		$(MAKE) down-$$demo 2>/dev/null || true; \
		if [ "$$code" != "200" ]; then echo "❌ $$demo healthcheck failed (HTTP $$code)"; exit 1; fi; \
		echo "✅ $$demo OK (HTTP $$code)"; \
	done
	@echo "✅ All demos passed release-verify."
