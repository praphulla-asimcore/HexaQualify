# Production image: nginx + php-fpm under supervisord. The worker service reuses it (see docker-compose.yml).
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json vite.config.js tailwind.config.js postcss.config.js ./
RUN npm ci
COPY resources ./resources
COPY public ./public
RUN npm run build

FROM php:8.3-fpm-alpine
RUN apk add --no-cache nginx postgresql-dev icu-dev libzip-dev supervisor \
 && docker-php-ext-install pdo_pgsql intl zip opcache pcntl \
 && apk add --no-cache --virtual .build $PHPIZE_DEPS && pecl install redis && docker-php-ext-enable redis && apk del .build
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer install --no-dev --optimize-autoloader --no-interaction \
 && chown -R www-data:www-data storage bootstrap/cache
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
EXPOSE 8080
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
