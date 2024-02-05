SHELL := /bin/bash -eu -o pipefail -c

SRCDIR := src
DEFAULT_BRANCH := $(shell git remote show origin | grep 'HEAD branch' | cut -d" " -f5)
COMPOSER_DIR := vendor
COMPOSER_CACHE_SRCS := ./$(COMPOSER_DIR)/composer/installed.json
COMPOSER_AUTOLOAD_OBJ := ./$(COMPOSER_DIR)/composer/autoload_classmap.php
DIFF_PHP_SRCS := $(shell find src tests -name "*.php" -type f)
GIT_HEAD := ./.git/HEAD
FIXER_DIFF_STATUS := .fixer-diff.status
PHPSTAN_STATUS := .phpstan/resultCache.php
CS_FIXER_CACHE := .php-cs-fixer.cache
COVERAGE_OBJ := coverage.xml
CREDITS_OBJ := CREDITS

.DEFAULT_GOAL := help

.PHONY: help coverage install phpstan fixer fixer-all lint lint-all credits

$(COMPOSER_CACHE_SRCS):
	@composer install

$(COMPOSER_AUTOLOAD_OBJ): $(GIT_HEAD)
	@composer dump-autoload
	@touch $@

install: ## Run composer install(and dump-autoload)
	@composer install

$(PHPSTAN_STATUS): $(COMPOSER_CACHE_SRCS) $(COMPOSER_AUTOLOAD_OBJ) $(DIFF_PHP_SRCS)
	@php -d memory_limit=-1 ./$(COMPOSER_DIR)/bin/phpstan analyse -c phpstan.neon

$(CS_FIXER_CACHE): $(COMPOSER_CACHE_SRCS) $(COMPOSER_AUTOLOAD_OBJ)
	@$(COMPOSER_DIR)/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php

$(FIXER_DIFF_STATUS): $(COMPOSER_CACHE_SRCS) $(COMPOSER_AUTOLOAD_OBJ)
	@git diff --name-only --diff-filter=d origin/$(DEFAULT_BRANCH) '*.php' | \
		xargs -r $(COMPOSER_DIR)/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php;
	@echo $(FIXER_DIFF_STATUS) > $@

$(COVERAGE_OBJ): $(COMPOSER_CACHE_SRCS) $(COMPOSER_AUTOLOAD_OBJ)
	@$(COMPOSER_DIR)/bin/phpunit --configuration phpunit.xml --coverage-clover $@

$(CREDITS_OBJ): composer.lock
	@vendor/bin/php-vendor-credits . > $@

help:
	@grep -E '^[a-zA-Z_/%-]+:.*?## .*$$' Makefile | \
		sort | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

coverage: $(COVERAGE_OBJ) ## Run coverage tests

test: $(COMPOSER_CACHE_SRCS) $(COMPOSER_AUTOLOAD_OBJ) ## Run PHPUnit
	@composer test

lint-all: $(CS_FIXER_CACHE) $(PHPSTAN_STATUS) ## Run Linter with current project

lint: $(FIXER_DIFF_STATUS) $(PHPSTAN_STATUS) ## Run Linter diff of default branch

fixer-all: $(CS_FIXER_CACHE) ## Run CS-Fixer with current project

fixer: $(FIXER_DIFF_STATUS) ## Run CS-Fixer with diff of default branch

phpstan: $(PHPSTAN_STATUS) ## Run PHPStan with current project

credits: $(CREDITS_OBJ)	## Create CREDITS

prerelease_for_tagpr:	## Change files just before release
	@composer config version $(TAGPR_NEXT_VERSION)
	@composer update
	@vendor/bin/php-vendor-credits . > $(CREDITS_OBJ)
	@git add CHANGELOG.md composer.json composer.lock $(CREDITS_OBJ)

release:	## Run composer archive
	@composer archive --format zip --file composer
