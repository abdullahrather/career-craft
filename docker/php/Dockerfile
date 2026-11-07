# The development image: PHP 8.5 with the extensions Laravel, PostgreSQL and Redis need,
# and Composer. The app code is mounted in, not copied.
FROM php:8.5-cli-bookworm

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql redis intl zip bcmath pcntl gd exif opcache \
    && apt-get update && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Files the app writes belong to the host user, not root.
ARG UID=1000
ARG GID=1000
RUN groupadd -g ${GID} app && useradd -m -u ${UID} -g app app
USER app

WORKDIR /app
