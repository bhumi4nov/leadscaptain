FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpq-dev \
    && docker-php-ext-install \
    pdo_mysql \
    pdo_pgsql \
    pcntl \
    zip \
    && pecl install redis pcov \
    && docker-php-ext-enable redis pcov \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]