FROM php:8.3-fpm-alpine

ARG PUID=1000
ARG PGID=1000

# Install system dependencies and PHP extensions required for Laravel & XML processing
RUN apk add --no-cache \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    oniguruma-dev \
    linux-headers \
    shadow

RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd dom xml

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

RUN groupmod -g ${PGID} www-data && usermod -u ${PUID} -g ${PGID} www-data