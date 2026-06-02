.PHONY: help up down restart logs ps ai-test frontend-build

help:
	@echo "ExpertBrain developer commands"
	@echo "  make up              Start local services"
	@echo "  make down            Stop local services"
	@echo "  make restart         Restart local services"
	@echo "  make logs            Tail docker logs"
	@echo "  make ps              Show service status"
	@echo "  make ai-test         Run AI service tests in container"
	@echo "  make frontend-build  Build frontend in container"

up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose down
	docker compose up -d

logs:
	docker compose logs -f --tail=200

ps:
	docker compose ps

ai-test:
	docker compose run --rm ai-service pytest

frontend-build:
	docker compose run --rm frontend sh -c "npm install && npm run build"
