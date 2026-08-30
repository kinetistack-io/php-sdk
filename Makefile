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
