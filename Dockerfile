# ============================================================
# StallFront image
#
# One container with the web app, the scheduler (cron) and, when no
# external database is configured, an embedded MariaDB server.
# Everything that must survive an update lives in /data.
# ============================================================

# ── Front-end assets ─────────────────────────────────────────
FROM node:22-bookworm-slim AS assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js postcss.config.js tailwind.config.js ./
COPY resources ./resources
RUN npm run build

# ── PHP dependencies ─────────────────────────────────────────
FROM composer:2 AS vendor
WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs

# ── Application ──────────────────────────────────────────────
FROM php:8.3-apache-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libicu-dev libonig-dev libpq-dev \
        unzip cron mariadb-server mariadb-client \
 && docker-php-ext-configure gd --with-jpeg --with-freetype \
 && docker-php-ext-install pdo_mysql pdo_pgsql gd zip intl mbstring bcmath exif opcache \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && { \
        echo "upload_max_filesize=25M"; \
        echo "post_max_size=60M"; \
        echo "memory_limit=512M"; \
        echo "max_file_uploads=20"; \
        echo "expose_php=Off"; \
        echo "opcache.validate_timestamps=0"; \
    } > /usr/local/etc/php/conf.d/stallfront.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY --from=vendor /build/vendor ./vendor
COPY . .
COPY --from=assets /build/public/build ./public/build
RUN composer dump-autoload --optimize --no-dev --no-interaction \
 && rm -rf storage/logs/* storage/framework/cache/* storage/framework/sessions/* storage/framework/views/* \
 && chown -R www-data:www-data bootstrap/cache public

COPY docker/entrypoint.sh /usr/local/bin/stallfront-entrypoint
RUN chmod +x /usr/local/bin/stallfront-entrypoint

ENV STALLFRONT_DATA_DIR=/data \
    SCHEDULER_MODE=cron \
    LOG_CHANNEL=daily
VOLUME /data
EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=90s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1/up") === false ? 1 : 0);'

ENTRYPOINT ["stallfront-entrypoint"]
CMD ["apache2-foreground"]
