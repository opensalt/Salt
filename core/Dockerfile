# the different stages of this Dockerfile are meant to be built into separate images
# https://docs.docker.com/develop/develop-images/multistage-build/#stop-at-a-specific-build-stage
# https://docs.docker.com/compose/compose-file/#target

# Version and build of OpenSALT
ARG VERSION=3.3.1
ARG BUILD_NUMBER=x
ARG BUILD_DATE=0
ARG COMMIT=unknown

ARG PHP_VERSION=8.3
ARG FRANKENPHP_VERSION=1
ARG NODE_VERSION=20

# https://docs.docker.com/engine/reference/builder/#understand-how-arg-and-from-interact
#========================================================================
# Versions
FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION} AS frankenphp_upstream

#========================================================================
# Base FrankenPHP image
FROM frankenphp_upstream AS frankenphp_base

WORKDIR /app

# persistent / runtime deps
# hadolint ignore=DL3008
RUN apt-get update && apt-get install --no-install-recommends -y \
	acl \
	bash \
	file \
	gettext \
	git \
	supervisor \
	procps \
	vim \
	&& rm -rf /var/lib/apt/lists/*

COPY docker/scheduler/supervisord /etc/supervisor/conf.d/
COPY docker/scheduler/prefix-log /usr/local/bin/

RUN set -eux; \
	install-php-extensions \
		@composer \
		apcu \
		gd \
		intl \
		opcache \
		zip \
		xsl \
		bcmath \
		bz2 \
		mysqli \
		pcntl \
		pdo_mysql \
		pdo_pgsql \
	;

ARG DOCKERIZE_VERSION=v0.6.1
RUN curl -LsS https://github.com/jwilder/dockerize/releases/download/${DOCKERIZE_VERSION}/dockerize-alpine-linux-amd64-${DOCKERIZE_VERSION}.tar.gz \
	  | tar -C /usr/local/bin -xzvf -

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

###> recipes ###
###> doctrine/doctrine-bundle ###
RUN set -eux; \
	install-php-extensions pdo_pgsql
###< doctrine/doctrine-bundle ###
###< recipes ###

COPY --link frankenphp/conf.d/app.ini $PHP_INI_DIR/conf.d/
COPY --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link frankenphp/Caddyfile /etc/caddy/Caddyfile

ENTRYPOINT ["docker-entrypoint"]

HEALTHCHECK --start-period=30s CMD curl -f http://localhost:2019/metrics || exit 1
CMD [ "frankenphp", "run", "--config", "/etc/caddy/Caddyfile" ]


#=======================================================================
# "php" stage
FROM frankenphp_base AS salt_core_php

WORKDIR /srv/core

#=======================================================================
# "php" stage
FROM salt_core_php AS salt_core_vendors

# prevent the reinstallation of vendors at every changes in the source code
COPY composer.json composer.lock symfony.lock ./
RUN set -eux; \
	composer install --no-interaction --prefer-dist --no-autoloader --no-scripts --no-progress; \
	composer clear-cache

#=======================================================================
FROM node:${NODE_VERSION} AS salt_core_js_build

USER node
WORKDIR /srv/core

COPY package.json yarn.lock ./
COPY --link --from=salt_core_vendors /srv/core/vendor/symfony/ux-vue/assets ./vendor/symfony/ux-vue/assets/
RUN yarn install --non-interactive

COPY vite.config.js ./
COPY assets ./assets/

RUN mkdir -p public/build \
  && yarn build

#=======================================================================
# Squash the COPY layers into 1 layer
FROM salt_core_php AS salt_code_layer

# build for production
ARG APP_ENV=prod

# copy only specifically what we need
COPY .env \
	composer.json composer.lock symfony.lock \
	package.json vite.config.js yarn.lock \
	codeception.dist.yml \
	 ./
COPY assets/ assets/
COPY bin/ bin/
COPY config/ config/
COPY migrations/ migrations/
COPY public/ public/
COPY src/ src/
COPY templates/ templates/
COPY translations/ translations/
COPY tests/ tests/

COPY --link --from=salt_core_vendors /srv/core/vendor/ ./vendor/
# hack to avoid "Service 'php' failed to build : failed to export image: failed to create image: failed to get layer sha256:38c02bf8b1bff96084338f7e6835b713185e091467e11f08001d41375f078e0e: layer does not exist"
# as mentioned in https://github.com/moby/moby/issues/37965#issuecomment-426853382
RUN true
COPY --link --from=salt_core_js_build /srv/core/public/ ./public/

RUN set -eux; \
	mkdir -p var/cache var/log; \
	chmod 777 var/cache var/log; \
	mkdir -p public/attachments; \
	chmod 777 public/attachments; \
	chmod 777 tests/_output tests/_data tests/_support/_generated; \
	chmod +x bin/console-real; sync; \
	composer dump-autoload --no-interaction --classmap-authoritative; \
	composer dump-env prod; \
	composer run-script post-install-cmd; \
	chown -R www-data:www-data var public/attachments

ARG VERSION
ARG BUILD_NUMBER
ARG COMMIT

RUN echo ${VERSION}.${BUILD_NUMBER} > public/version.txt ; \
    echo ${VERSION}.${BUILD_NUMBER}+$(date -u '+%Y%m%d%H%M%S').${COMMIT} > public/revision.txt

#=======================================================================
FROM salt_core_php AS salt_core_app

ENV SYMFONY_PHPUNIT_VERSION=9

ARG VERSION
ARG BUILD_NUMBER
ARG BUILD_DATE
ARG COMMIT

LABEL org.opencontainers.title="OpenSALT PHP Application" \
      org.opencontainers.source="https://github.com/opensalt/opensalt" \
      org.opencontainers.version=${VERSION}.${BUILD_NUMBER}+${BUILD_DATE}.${COMMIT}

COPY --link --from=salt_code_layer /srv/core/ ./

VOLUME /srv/core/var
VOLUME /srv/core/public/attachments

#=======================================================================
FROM salt_core_app AS salt_core_dev

ENV APP_ENV=dev XDEBUG_MODE=off
VOLUME /srv/core/var/

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

RUN set -eux; \
	install-php-extensions \
		xdebug \
	;

COPY --link frankenphp/conf.d/app.dev.ini $PHP_INI_DIR/conf.d/

CMD [ "frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--watch" ]

#=======================================================================
# Prod consumer image
FROM salt_core_app AS salt_core_consumer

ENV APP_ENV=prod
#ENV FRANKENPHP_CONFIG="import worker.Caddyfile"

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link frankenphp/conf.d/app.prod.ini $PHP_INI_DIR/conf.d/
#COPY --link frankenphp/worker.Caddyfile /etc/caddy/worker.Caddyfile

RUN rm -Rf frankenphp/

CMD ["/usr/bin/supervisord"]

#=======================================================================
# Prod FrankenPHP image
FROM salt_core_app AS salt_core_prod

ENV APP_ENV=prod
#ENV FRANKENPHP_CONFIG="import worker.Caddyfile"

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link frankenphp/conf.d/app.prod.ini $PHP_INI_DIR/conf.d/
#COPY --link frankenphp/worker.Caddyfile /etc/caddy/worker.Caddyfile

RUN rm -Rf frankenphp/

