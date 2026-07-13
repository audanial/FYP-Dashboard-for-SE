# syntax=docker/dockerfile:1

############################################
# Stage 1: PHP dependencies (composer)
############################################
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --ignore-platform-reqs

############################################
# Stage 2: Frontend assets (Vite + Tailwind)
############################################
FROM node:20-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

# resources/css/app.css imports Flux UI's CSS straight out of the Composer
# package (../../vendor/livewire/flux/dist/flux.css), so vendor/ has to be
# present before Vite can resolve it.
COPY --from=vendor /app/vendor ./vendor

RUN npm run build

############################################
# Stage 3: Runtime image (php-fpm + nginx)
############################################
FROM php:8.4-fpm AS runtime

# System packages: nginx/supervisor to serve the app, gettext-base for
# envsubst (used to inject $PORT into the nginx config at startup), and
# the -dev headers needed to compile the PHP extensions below.
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        gettext-base \
        libpq-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libfreetype6-dev \
        libzip-dev \
        libonig-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip

WORKDIR /var/www/html

# Application source
COPY . .

# Composer-installed vendor/ and built frontend assets from earlier stages
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

# Writable directories Laravel needs at runtime (regenerated at checkout,
# not committed to git — see storage/framework/.gitignore).
RUN mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Nginx + php-fpm + supervisord config
COPY docker/nginx.conf.template /etc/nginx/templates/nginx.conf.template
COPY docker/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && rm -f /etc/nginx/sites-enabled/default \
    && mkdir -p /etc/nginx/sites-enabled

# Render sets $PORT dynamically; default only matters for local `docker run`.
ENV PORT=8080
EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
