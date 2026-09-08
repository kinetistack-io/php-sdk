.DEFAULT_GOAL := help

export USER_ID := $(shell id -u)
export GROUP_ID := $(shell id -g)
DC := docker compose run --rm php

help: ## Show this help message
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-18s\033[0m %s\n", $$1, $$2}'

build: ## Build the default PHP Docker image
	docker compose build php

build-all: ## Build all PHP matrix images (8.1, 8.2, 8.3, 8.4)
	docker compose build

install: ## Install composer dependencies
	$(DC) composer install

update: ## Update composer dependencies
	$(DC) composer update

composer: ## Run arbitrary composer commands (e.g. make composer cmd="require symfony/yaml")
	$(DC) composer $(cmd)

test: ## Run PHPUnit tests
	$(DC) vendor/bin/phpunit

test-all: ## Run PHPUnit tests across all PHP versions (8.1, 8.2, 8.3, 8.4)
	docker compose run --rm php81 vendor/bin/phpunit
	docker compose run --rm php82 vendor/bin/phpunit
	docker compose run --rm php vendor/bin/phpunit
	docker compose run --rm php84 vendor/bin/phpunit

phpstan: ## Run PHPStan static analysis
	$(DC) vendor/bin/phpstan analyse --memory-limit=512M

cs-check: ## Check code style with PHP CS Fixer
	$(DC) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Fix code style with PHP CS Fixer
	$(DC) vendor/bin/php-cs-fixer fix

check: cs-check phpstan test ## Run all verification checks (cs-check, phpstan, test)

shell: ## Open an interactive bash shell in the container
	$(DC) bash

release: ## Prepare and push a new release (e.g. make release VERSION=1.2.4)
	@if [ -z "$(VERSION)" ]; then \
		printf "\033[31mError: VERSION is required. Example: make release VERSION=1.2.4\033[0m\n"; \
		exit 1; \
	fi; \
	CLEAN_VERSION=$$(echo "$(VERSION)" | sed -e 's/^v//'); \
	if ! echo "$$CLEAN_VERSION" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?$$'; then \
		printf "\033[31mError: Invalid VERSION '$$CLEAN_VERSION'. Must follow SemVer (e.g. 1.2.4 or 1.2.4-rc.1)\033[0m\n"; \
		exit 1; \
	fi; \
	TAG="v$$CLEAN_VERSION"; \
	if [ -n "$$(git status --porcelain)" ]; then \
		printf "\033[31mError: Working directory has uncommitted changes. Stash or commit before releasing.\033[0m\n"; \
		exit 1; \
	fi; \
	CURRENT_BRANCH=$$(git rev-parse --abbrev-ref HEAD); \
	if [ "$$CURRENT_BRANCH" != "main" ] && [ "$(ALLOW_BRANCH)" != "1" ]; then \
		printf "\033[31mError: Releases must be cut from 'main' (current branch: $$CURRENT_BRANCH). Use ALLOW_BRANCH=1 to override.\033[0m\n"; \
		exit 1; \
	fi; \
	if git rev-parse "$$TAG" >/dev/null 2>&1; then \
		printf "\033[31mError: Tag $$TAG already exists locally.\033[0m\n"; \
		exit 1; \
	fi; \
	printf "\033[34m==>\033[0m Running verification checks (make check)...\n"; \
	$(MAKE) check || { printf "\033[31mError: Verification checks failed. Aborting release without changes.\033[0m\n"; exit 1; }; \
	printf "\033[34m==>\033[0m Bumping composer.json version to %s...\n" "$$CLEAN_VERSION"; \
	$(DC) composer config version "$$CLEAN_VERSION" || exit 1; \
	$(DC) composer update --lock || exit 1; \
	$(DC) composer validate --no-check-version || exit 1; \
	if git diff --quiet composer.json composer.lock; then \
		printf "\033[34m==>\033[0m composer.json is already at version %s, skipping commit...\n" "$$CLEAN_VERSION"; \
	else \
		printf "\033[34m==>\033[0m Committing release %s...\n" "$$TAG"; \
		git add composer.json composer.lock || exit 1; \
		git commit -m "Release $$TAG" || exit 1; \
	fi; \
	printf "\033[34m==>\033[0m Creating annotated tag %s...\n" "$$TAG"; \
	git tag -a "$$TAG" -m "Release $$TAG" || exit 1; \
	if [ "$(DRY_RUN)" = "1" ]; then \
		printf "\033[33m[DRY RUN] Skipping push to origin. Created local commit and tag %s.\033[0m\n" "$$TAG"; \
	else \
		if [ -n "$$(git log origin/$$CURRENT_BRANCH..$$CURRENT_BRANCH 2>/dev/null)" ]; then \
			printf "\033[34m==>\033[0m Pushing commit to origin...\n"; \
			git push origin "$$CURRENT_BRANCH" || exit 1; \
		fi; \
		printf "\033[34m==>\033[0m Pushing tag %s to origin...\n" "$$TAG"; \
		git push origin "$$TAG" || exit 1; \
		printf "\033[32mSuccessfully released and pushed %s!\033[0m\n" "$$TAG"; \
	fi

.PHONY: help build build-all install update composer test test-all phpstan cs-check cs-fix check shell release

