# Makefile for Doctrine Encrypt Bundle demos (symfony7, symfony8)
# Run from demo/: make up-symfony7, make up-symfony8, etc.

.PHONY: help up-symfony7 up-symfony8 down-symfony7 down-symfony8 install-symfony7 install-symfony8 setup-symfony7 setup-symfony8 shell-symfony7 shell-symfony8 update-bundle-symfony7 update-bundle-symfony8

help:
	@echo "Doctrine Encrypt Bundle - Demos"
	@echo ""
	@echo "  make up-symfony7      Start Symfony 7 demo (http://localhost:8007)"
	@echo "  make up-symfony8      Start Symfony 8 demo (http://localhost:8008)"
	@echo "  make down-symfony7    Stop Symfony 7 demo"
	@echo "  make down-symfony8    Stop Symfony 8 demo"
	@echo "  make install-symfony7 Install deps in Symfony 7 demo"
	@echo "  make install-symfony8 Install deps in Symfony 8 demo"
	@echo "  make setup-symfony7   install + db + schema + key + fixtures (Symfony 7)"
	@echo "  make setup-symfony8   install + db + schema + key + fixtures (Symfony 8)"
	@echo "  make shell-symfony7   Shell in Symfony 7 container"
	@echo "  make shell-symfony8   Shell in Symfony 8 container"
	@echo "  make update-bundle-symfony7  Update bundle from path repo (Symfony 7)"
	@echo "  make update-bundle-symfony8  Update bundle from path repo (Symfony 8)"
	@echo ""

up-symfony7:
	$(MAKE) -C symfony7 up

up-symfony8:
	$(MAKE) -C symfony8 up

down-symfony7:
	$(MAKE) -C symfony7 down

down-symfony8:
	$(MAKE) -C symfony8 down

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
