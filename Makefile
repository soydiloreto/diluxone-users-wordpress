# diluxone-users — developer task runner.
#
# All PHP-based commands run inside the official `composer:2` Docker
# image by default. That keeps the host clean of plugin-specific PHP
# extensions (dom, mbstring, xml, xmlwriter, etc.) which the standard
# WSL `php-cli` build tends to lack. The Composer + dev-tooling
# versions still come from composer.lock either way, so the runtime
# difference vs CI is just the PHP-extension surface area.
#
# Usage:
#   make            # = make help
#   make install    # composer install
#   make check      # everything CI runs
#   make release    # check + version-alignment dry-run
#
# Override DOCKER=0 to invoke local binaries instead. Only viable on
# hosts that already have a full PHP CLI with the required extensions
# (dom, mbstring, xml, xmlwriter, libxml, openssl, json, fileinfo,
# tokenizer) and `wp` and `npx` available on $PATH.

SHELL := /bin/bash

# -- Docker plumbing ---------------------------------------------------
# Mount the project read/write at /app, run as the host user so
# composer doesn't leave root-owned files in vendor/.
DOCKER ?= 1
DOCKER_USER := $(shell id -u):$(shell id -g)
DOCKER_RUN  := docker run --rm -u $(DOCKER_USER) -v $(CURDIR):/app -w /app

# Pin floating tags via env override for reproducibility:
#   make stan COMPOSER_IMAGE=composer:2.7
COMPOSER_IMAGE ?= composer:2
WP_CLI_IMAGE   ?= wordpress:cli
PHP_IMAGE      ?= php:8.3-cli

# `--network host` lets the wordpress:cli container reach the wp-env
# MySQL on the same host. It is unsupported on Docker Desktop for
# macOS/Windows; on those hosts override DOCKER_NET= to drop it (and
# arrange WP-CLI / integration tests another way, e.g. via wp-env).
DOCKER_NET ?= --network host

ifeq ($(DOCKER),1)
COMPOSER  := $(DOCKER_RUN) $(COMPOSER_IMAGE) composer
VENDOR    := $(DOCKER_RUN) $(COMPOSER_IMAGE)
PSALM_CMD := $(DOCKER_RUN) $(PHP_IMAGE) ./vendor/bin/psalm
WP_CLI    := $(DOCKER_RUN) $(DOCKER_NET) $(WP_CLI_IMAGE)
INTEG     := $(DOCKER_RUN) $(DOCKER_NET) $(COMPOSER_IMAGE)
else
COMPOSER  := composer
VENDOR    :=
PSALM_CMD := ./vendor/bin/psalm
WP_CLI    := wp
INTEG     :=
endif

# -- Default target ----------------------------------------------------
.DEFAULT_GOAL := help

.PHONY: help
help: ## Show this help.
	@awk 'BEGIN {FS = ":.*##"; printf "\nTargets:\n"} \
	  /^[a-zA-Z0-9_-]+:.*##/ {printf "  \033[1;32m%-18s\033[0m %s\n", $$1, $$2}' \
	  $(MAKEFILE_LIST)
	@echo
	@echo "Override the Docker mode with DOCKER=0 to use local binaries."

# -- Setup -------------------------------------------------------------
.PHONY: install
install: ## Install dev dependencies (composer install).
	$(COMPOSER) install --no-interaction --prefer-dist --no-progress

.PHONY: update
update: ## Update dev dependencies (composer update).
	$(COMPOSER) update --no-interaction --prefer-dist --no-progress

# -- Linting / static analysis ----------------------------------------
.PHONY: lint
lint: ## PHPCS + WordPress Coding Standards.
	# --no-cache on purpose: PHPCS caches per file and, when a file goes back
	# to a content it already analysed, answers with the stale result. That is
	# green locally while CI — which always starts clean — is red, and half an
	# hour goes into looking for the difference.
	$(VENDOR) ./vendor/bin/phpcs --no-cache

.PHONY: lint-fix
lint-fix: ## Auto-fix PHPCS violations where possible.
	$(VENDOR) ./vendor/bin/phpcbf --no-cache

.PHONY: stan
stan: ## PHPStan level 8 (no baseline).
	$(VENDOR) ./vendor/bin/phpstan analyse --memory-limit=2G --no-progress

.PHONY: psalm
psalm: ## Psalm taint analysis (XSS / SQLi / RCE).
	$(PSALM_CMD) --taint-analysis --no-cache --no-progress

.PHONY: i18n
i18n: ## Refresh languages/diluxone-users.pot from the source strings.
	$(WP_CLI) i18n make-pot . languages/diluxone-users.pot \
	    --slug=diluxone-users \
	    --domain=diluxone-users \
	    --exclude=tests,vendor,node_modules,.wordpress-org,assets,docs,build

.PHONY: i18n-update
i18n-update: i18n ## Merge the refreshed .pot into every shipped .po (keeps existing translations).
	@for po in languages/*.po; do \
	  $(WP_CLI) i18n update-po languages/diluxone-users.pot "$$po" >/dev/null && echo "  merged $$po"; \
	done

.PHONY: i18n-mo
i18n-mo: ## Compile every languages/*.po into the .mo WordPress actually reads.
	$(WP_CLI) i18n make-mo languages languages
	@echo "✔ $$(ls languages/*.mo | wc -l) .mo files built."

.PHONY: i18n-check
i18n-check: ## Fail if any shipped .po is malformed or has untranslated strings.
	@fail=0; \
	for po in languages/*.po; do \
	  out=$$(msgfmt --check --statistics -o /dev/null "$$po" 2>&1) || fail=1; \
	  printf "%-34s %s\n" "$$po" "$$out"; \
	  case "$$out" in *untranslated*) fail=1;; esac; \
	done; \
	if [ "$$fail" -ne 0 ]; then echo "✗ translations incomplete or malformed"; exit 1; fi; \
	echo "✔ every locale complete."

# -- Tests -------------------------------------------------------------
.PHONY: test
test: test-unit ## Run the unit-test suite (default — fast, no WP needed).

.PHONY: test-unit
test-unit: ## Run only the unit-test suite (no WordPress runtime).
	$(VENDOR) ./vendor/bin/phpunit --testsuite unit

.PHONY: test-integration
test-integration: ## Run integration tests against the wp-env stack (must be `make env` first).
	$(INTEG) ./vendor/bin/phpunit --testsuite integration

# -- Distribution build ------------------------------------------------
# The repo directory is diluxone-users-wordpress (GitHub), but the plugin
# folder wordpress.org receives must be named after the slug, diluxone-users:
# WordPress derives the text-domain check from the folder name. `make dist`
# materialises exactly what ships, under the right name, applying .distignore
# so the tree has no tests, tooling or repo metadata in it.
DIST_DIR := build/diluxone-users

.PHONY: dist
dist: ## Build build/diluxone-users/ — exactly what gets published.
	@mkdir -p "$(DIST_DIR)"
	@# --delete, never `rm -rf` the directory itself: wp-env bind-mounts it, and
	@# replacing the inode leaves the container looking at a mount that is gone.
	@# --delete-excluded too: a file that became excluded must leave the dist,
	@# --delete alone keeps it there from an earlier build.
	@rsync -a --delete --delete-excluded --exclude-from=.distignore --exclude='build' ./ "$(DIST_DIR)/"
	@echo "✔ Built $(DIST_DIR) ($$(find "$(DIST_DIR)" -type f | wc -l) files)"

.PHONY: zip
zip: dist ## Package build/diluxone-users.zip — the file you upload to wordpress.org.
	@cd build && rm -f diluxone-users.zip && zip -qr diluxone-users.zip diluxone-users
	@echo "✔ build/diluxone-users.zip ($$(du -h build/diluxone-users.zip | cut -f1))"

# -- Plugin Check (wordpress.org review gate) --------------------------
# This is the tool the plugin review team runs. PHPCS/WPCS overlaps with it
# but does not replace it: Plugin Check also enforces readme.txt structure,
# plugin headers, i18n and directory rules that WPCS knows nothing about.
#
# It runs in its own throwaway wp-env project under build/pcp, on ports
# 8894/8895 (8888-8891 belong to dev environments and 8896/8897 to the sibling
# DiluxOne plugin's own Plugin Check env),
# mounting only the built dist. Two reasons it cannot share the main
# environment: the plugin folder there is the repo name, and Plugin Check
# compares the text domain against the folder name; and wp-env activates every
# plugin it mounts, so mounting the repo and the dist together loads the plugin
# twice and fatals on redeclaration.
PCP_DIR := $(CURDIR)/build/pcp

.PHONY: pcp-env
pcp-env: dist
	@mkdir -p "$(PCP_DIR)"
	@printf '%s\n' \
	  '{' \
	  '  "core": null,' \
	  '  "phpVersion": "8.2",' \
	  '  "plugins": [ "../diluxone-users" ],' \
	  '  "port": 8894,' \
	  '  "testsPort": 8895' \
	  '}' > "$(PCP_DIR)/.wp-env.json"
	@cd "$(PCP_DIR)" && npx @wordpress/env start >/dev/null
	@cd "$(PCP_DIR)" && (npx @wordpress/env run cli wp plugin is-installed plugin-check >/dev/null 2>&1 \
	  || npx @wordpress/env run cli wp plugin install plugin-check --activate >/dev/null)

.PHONY: plugin-check
plugin-check: pcp-env ## Run wordpress.org's Plugin Check on the built dist.
	@cd "$(PCP_DIR)" && npx @wordpress/env run cli wp plugin check diluxone-users --format=table --severity=5

.PHONY: plugin-check-all
plugin-check-all: pcp-env ## Plugin Check on the built dist, including warnings and notices.
	@cd "$(PCP_DIR)" && npx @wordpress/env run cli wp plugin check diluxone-users --format=table

.PHONY: plugin-check-down
plugin-check-down: ## Stop the Plugin Check environment.
	@cd "$(PCP_DIR)" && npx @wordpress/env stop 2>/dev/null || true

# -- Aggregate ---------------------------------------------------------
.PHONY: check
check: lint stan psalm test ## Run every quality gate CI runs (lint, stan, psalm, unit tests).
	@echo "✔ All checks passed."

# -- Local dev environment (wp-env) ------------------------------------
.PHONY: env env-up
env: env-up ## Alias of env-up.
env-up: ## Start the local wp-env Docker stack.
	npx wp-env start

.PHONY: env-down
env-down: ## Stop the local wp-env Docker stack.
	npx wp-env stop

.PHONY: env-clean
env-clean: ## Destroy the local wp-env Docker stack and its volumes.
	npx wp-env destroy

# -- Deploy / release --------------------------------------------------
# The plugin is developed here and tried on a real site. `make deploy-test`
# copies the working tree into that site's plugins directory — only what
# ships, so no vendor/, no tests, no tooling — and leaves the site's own
# files alone. Override SITE= to try it somewhere else.
SITE ?= $(HOME)/repos/cst-website
SITE_PLUGIN := $(SITE)/wp-content/plugins/diluxone-users
SITE_LANGS  := $(SITE)/wp-content/languages/plugins

.PHONY: deploy-test
deploy-test: ## Copy the working tree into a real site for manual smoke-testing.
	@if [ ! -d "$(SITE)/wp-content/plugins" ]; then \
	  echo "no site at $(SITE). Override with SITE=/path/to/wordpress"; \
	  exit 1; \
	fi
	@mkdir -p "$(SITE_PLUGIN)"
	@# --delete-excluded as well: a file that became excluded — or that was
	@# there from an earlier layout — has to leave the site copy too, or the
	@# site ends up running something the repo no longer ships.
	rsync -a --delete --delete-excluded \
	  --exclude-from=.distignore \
	  --exclude='.git' \
	  ./ "$(SITE_PLUGIN)/"
	@# The bundled .mo files are inert on their own: with no
	@# load_plugin_textdomain() call — discouraged by Plugin Check since
	@# WordPress 4.6 — WordPress only reads plugin translations from
	@# wp-content/languages/plugins/, which is where wordpress.org installs
	@# its language packs. Until the plugin is published and those packs
	@# exist, this puts the same files in the same place by hand.
	@mkdir -p "$(SITE_LANGS)"
	@cp languages/*.mo "$(SITE_LANGS)/" 2>/dev/null || true
	@echo "✔ Copied to $(SITE_PLUGIN) (+ $$(ls languages/*.mo 2>/dev/null | wc -l) locales in $(SITE_LANGS))"

.PHONY: release
release: check ## Pre-release validation: full quality gate + version-alignment dry-run.
	@echo "── version alignment check ─────────────────────────────"
	@PHP_VERSION=$$(grep -E '^[[:space:]]*\*[[:space:]]*Version:' diluxone-users.php | head -1 | sed -E 's/.*Version:[[:space:]]*//'); \
	 STABLE_TAG=$$(grep -E '^Stable tag:' readme.txt | sed -E 's/Stable tag:[[:space:]]*//'); \
	 PHP_BASE=$$(echo $$PHP_VERSION | sed -E 's/-(dev|alpha|beta|rc).*$$//'); \
	 echo "  PHP header Version : $$PHP_VERSION"; \
	 echo "  PHP base (no -dev) : $$PHP_BASE"; \
	 echo "  readme Stable tag  : $$STABLE_TAG"; \
	 if [ "$$PHP_BASE" = "$$STABLE_TAG" ]; then \
	   echo "  → match ✔"; \
	 else \
	   echo "  → MISMATCH ✗ (PHP base must equal readme Stable tag at tag time)"; exit 1; \
	 fi
	@echo "✔ Ready to tag."

# -- Cleanup -----------------------------------------------------------
.PHONY: clean
clean: plugin-check-down ## Remove caches, build artefacts, and temporary files.
	rm -rf build .phpunit.result.cache .phpunit.cache .phpcs-cache .phpstan .psalm
	@echo "✔ Cleaned."
