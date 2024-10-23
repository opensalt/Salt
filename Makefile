COMMIT ?= "$(shell git describe --always --match='x' --dirty=-x 2>/dev/null || date "+%Y%m%d%H%M")"
TAG ?= "$(shell git describe --always --match='[0-9].[0-9]*' --dirty=-x 2>/dev/null || date "+%Y%m%d%H%M")"
BUILD_NUMBER ?= "x"
VERSION ?= "$(shell cat core/VERSION)"
BUILD_DATE ?= "$(shell date -u +%Y%m%d.%H%M)"

default:
	@echo "You need to supply an argument to make"
.PHONY: default


# Docker commands
docker-start:
	docker compose up -d
up: docker-start
.PHONY: docker-start up

docker-stop:
	docker compose down -v
down: docker-stop
.PHONY: docker-stop down

docker-restart: docker-stop docker-start
restart: docker-restart
.PHONY: docker-restart restart

docker-build:
	VERSION=${VERSION} BUILD_NUMBER=${BUILD_NUMBER} BUILD_DATE=${BUILD_DATE} COMMIT=${COMMIT} COMPOSE_DOCKER_CLI_BUILD=1 DOCKER_BUILDKIT=1 docker compose build --pull
image: docker-build
.PHONY: docker-build image


# Cache commands
cache-clear:
	rm -rf core/var/cache/{dev,test,prod}/*
cc: cache-clear
.PHONY: cache-clear cc

cache-warmup: cache-clear
	core/bin/console cache:warmup --env=prod
.PHONY: cache-warmup

# Composer commands
vendor: core/composer.json core/composer.lock
	core/bin/composer install --no-interaction
composer-install: vendor
composer-update:
	core/bin/composer update --no-interaction
.PHONY: composer-install composer-update


# Encore commands
js: encore cache-clear
.PHONY: js

encore: yarn-install
	core/bin/yarn build
encore-dev: yarn-install
	core/bin/yarn build
encore-build: encore
.PHONY: encore encore-dev encore-build

node_modules: core/yarn.lock core/package.json
	core/bin/yarn install --non-interactive
	touch core/node_modules
yarn-install: node_modules
.PHONY: yarn-install

# Install and build commands
assets-install:
	core/bin/console assets:install public --symlink --relative
.PHONY: assets-install

build: core/vendor encore-build assets-install cache-clear
.PHONY: build

force-vendor:
	touch -c core/composer.lock
.PHONY: force-vendor

force-node-modules:
	touch -c core/yarn.lock
.PHONY: force-node-modules

force-build: force-vendor force-node-modules build
.PHONY: force-build

update: force-build migrate
.PHONY: update

install: cache-clear force-build
.PHONY: install

# DB commands
migrate:
	core/bin/console doctrine:migrations:migrate --no-interaction
.PHONY: migrate


# Clean
clean: cache-clear
	rm -rf core/build/* core/public/build/*
.PHONY: clean
