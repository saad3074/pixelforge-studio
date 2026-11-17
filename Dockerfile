FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-scripts --no-interaction --ignore-platform-reqs

FROM node:22-bookworm AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

FROM php:8.4-cli-bookworm
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo_pgsql bcmath pcntl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
RUN php artisan package:discover --ansi || true

ENV APP_ENV=production
ENV LOG_CHANNEL=stderr
EXPOSE 8000

CMD sh -c "php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=\${PORT:-8000}"
