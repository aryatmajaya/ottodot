SHELL := /bin/bash

.PHONY: up down dev build sail art

up:
	./vendor/bin/sail up -d

down:
	./vendor/bin/sail down

sail:
	./vendor/bin/sail $(CMD)

dev:
	./vendor/bin/sail npm run dev

build:
	./vendor/bin/sail npm run build

art:
	./vendor/bin/sail artisan $(ARGS)

# Usage examples:
# make up
# make dev
# make build
# make art ARGS="migrate"
# make art ARGS="make:model Post"
# make sail CMD="up"
