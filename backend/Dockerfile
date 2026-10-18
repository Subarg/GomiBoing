FROM php:8.3-cli

RUN apt-get update && apt-get install -y git unzip libpq-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APP_TIMEZONE=America/Mexico_City
# 1. Da permiso a Composer para correr como root
ENV COMPOSER_ALLOW_SUPERUSER=1 

WORKDIR /app
COPY . .

# 2. Agrega --no-scripts para evitar que Laravel intente arrancar prematuramente
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

COPY docker/start.sh /start.sh
RUN chmod +x /start.sh
EXPOSE 10000
CMD ["/start.sh"]