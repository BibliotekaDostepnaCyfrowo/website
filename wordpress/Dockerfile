# Production image of the library site. Code, translations and tools come from
# the image; only wp-content/uploads and the database are persistent. The official
# entrypoint copies /usr/src/wordpress into /var/www/html on every start, so a new
# image (or a restart) always runs exactly what was built here. See README.md.
#
# Keep the base in step with compose.yml.
ARG WORDPRESS_IMAGE=wordpress:7.1.3-php8.3-apache@sha256:d42f63cb89ad0eab272c4b393164e63830d0df270009907919f7dcce52b1a20b

# Downloads are verified against checksums pinned here. WordPress.org publishes
# none for language packs or plugins, so those were recorded on first download:
# when translations for this version are refreshed upstream, the build fails on
# purpose and the new checksum has to be reviewed and pinned.
FROM ${WORDPRESS_IMAGE} AS fetch
ARG WP_VERSION=7.1.3
ARG PL_PL_SHA256=71a91628f63611eab9dff6b9f7472ce2ecacb20bb82471462e09f6accbc16247
ARG WP_CLI_VERSION=2.12.0
ARG WP_CLI_SHA512=be928f6b8ca1e8dfb9d2f4b75a13aa4aee0896f8a9a0a1c45cd5d2c98605e6172e6d014dda2e27f88c98befc16c040cbb2bd1bfa121510ea5cdf5f6a30fe8832
ARG IMPORTER_VERSION=0.9.7
ARG IMPORTER_SHA256=d1b4d5c516351a5433fd98a58e1ff965c7822048d63de38840b6c7c506f422a6
RUN apt-get update && apt-get install -y --no-install-recommends unzip && rm -rf /var/lib/apt/lists/*
WORKDIR /fetch
RUN set -eux; \
	curl -fsSL -o pl_PL.zip "https://downloads.wordpress.org/translation/core/${WP_VERSION}/pl_PL.zip"; \
	echo "${PL_PL_SHA256}  pl_PL.zip" | sha256sum -c -; \
	mkdir -p out/languages; \
	unzip -q pl_PL.zip -d out/languages; \
	curl -fsSL -o importer.zip "https://downloads.wordpress.org/plugin/wordpress-importer.${IMPORTER_VERSION}.zip"; \
	echo "${IMPORTER_SHA256}  importer.zip" | sha256sum -c -; \
	mkdir -p out/plugins; \
	unzip -q importer.zip -d out/plugins; \
	curl -fsSL -o out/wp-cli.phar "https://github.com/wp-cli/wp-cli/releases/download/v${WP_CLI_VERSION}/wp-cli-${WP_CLI_VERSION}.phar"; \
	echo "${WP_CLI_SHA512}  out/wp-cli.phar" | sha512sum -c -

FROM ${WORDPRESS_IMAGE}

# `wp db query`/`wp db export` shell out to the mysql client, which the base lacks.
RUN apt-get update \
	&& apt-get install -y --no-install-recommends mariadb-client \
	&& rm -rf /var/lib/apt/lists/*

COPY --from=fetch /fetch/out/wp-cli.phar /usr/local/lib/wp-cli.phar
COPY docker/wp /usr/local/bin/wp
ENV WP_CLI_CACHE_DIR=/tmp/wp-cli-cache

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-biblioteka.ini
COPY docker/apache-robots.conf /etc/apache2/conf-available/biblioteka-robots.conf
RUN a2enmod headers && a2enconf biblioteka-robots

# Only our own code and the importer. Akismet and Hello Dolly ship with core.
RUN rm -rf /usr/src/wordpress/wp-content/plugins/akismet /usr/src/wordpress/wp-content/plugins/hello.php
COPY --from=fetch /fetch/out/plugins/ /usr/src/wordpress/wp-content/plugins/
COPY --from=fetch /fetch/out/languages/ /usr/src/wordpress/wp-content/languages/
COPY mu-plugins/ /usr/src/wordpress/wp-content/mu-plugins/
COPY theme/biblioteka-zukowo/ /usr/src/wordpress/wp-content/themes/biblioteka-zukowo/

# The entrypoint would generate this from wp-config-docker.php anyway (salts and DB
# settings come from env). Shipping it lets WP-CLI run straight from /usr/src/wordpress
# in pods that never start Apache, like the cron job.
RUN cp /usr/src/wordpress/wp-config-docker.php /usr/src/wordpress/wp-config.php

# Setup and cleanup scripts for `wp eval-file`, outside the web root.
COPY setup/ /opt/bz-setup/
