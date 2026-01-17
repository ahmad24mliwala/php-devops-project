# -------------------------------
# Base Image
# -------------------------------
FROM php:8.2-apache

# -------------------------------
# Install System Dependencies
# -------------------------------
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libonig-dev \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mysqli \
        zip \
    && rm -rf /var/lib/apt/lists/*

# -------------------------------
# Enable Apache Rewrite Module
# -------------------------------
RUN a2enmod rewrite

# Allow .htaccess overrides (IMPORTANT)
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# -------------------------------
# Install Composer
# -------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# -------------------------------
# Set Working Directory
# -------------------------------
WORKDIR /var/www/html

# -------------------------------
# Copy Application Code
# -------------------------------
COPY . .

# -------------------------------
# Install PHP Dependencies (if composer.json exists)
# -------------------------------
RUN if [ -f composer.json ]; then \
        composer install --no-dev --optimize-autoloader; \
    fi

# -------------------------------
# Permissions (Apache user)
# -------------------------------
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# -------------------------------
# Expose Port
# -------------------------------
EXPOSE 80

