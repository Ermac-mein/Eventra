FROM php:8.2-apache

# Install dependencies and extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    tesseract-ocr \
    git \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install gd zip pdo_mysql mysqli \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/*

# Enable common Apache modules
RUN a2enmod rewrite headers deflate expires

# Set working directory
WORKDIR /var/www/html

# Copy composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . /var/www/html/

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader || true

# Change owner to www-data
RUN chown -R www-data:www-data /var/www/html

# Expose port (default is 80, but can be configured)
EXPOSE 80
