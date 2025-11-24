FROM php:8.3-cli-alpine

# Install system dependencies
RUN apk add --no-cache \
    autoconf \
    g++ \
    make \
    openssl-dev \
    pcre-dev

# Install MongoDB extension
RUN pecl install mongodb && \
    docker-php-ext-enable mongodb

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files
COPY composer.json ./

# Install PHP dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

CMD ["sh"]
