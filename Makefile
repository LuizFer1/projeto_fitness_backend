.PHONY: help up down build restart shell log artisan clear

APP_CONTAINER = laravel_app

help: ## Show this help
	@echo "Usage: make [target]"
	@echo ""
	@echo "Targets:"
	@find . -name 'Makefile' -exec grep -E '^[a-zA-Z_-]+:.*?## .*$$' {} + | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

up: ## Start all containers in background
	docker-compose up -d

down: ## Stop all containers
	docker-compose down

build: ## Build containers
	docker-compose up -d --build

restart: ## Restart containers
	docker-compose restart

shell: ## Access the bash shell of the App container
	docker exec -it $(APP_CONTAINER) bash

log: ## View logs of the App container
	docker logs -f $(APP_CONTAINER)

artisan: ## Run an artisan command, e.g. make artisan cmd="migrate"
	docker exec -it $(APP_CONTAINER) php artisan $(cmd)

composer: ## Run a composer command, e.g. make composer cmd="install"
	docker exec -it $(APP_CONTAINER) composer $(cmd)

clear: ## Clear caches using artisan
	docker exec -it $(APP_CONTAINER) php artisan optimize:clear
