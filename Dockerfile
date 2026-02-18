# syntax=docker/dockerfile:1
FROM php:8.2-apache

# Install system dependencies and PHP extensions required by the app
RUN apt-get update && apt-get install -y \
        libfreetype-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libzip-dev \
        zip \
        unzip \
        default-mysql-client \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mysqli \
        gd \
        mbstring \
        zip \
        opcache

# Enable Apache mod_rewrite (required for .htaccess)
RUN a2enmod rewrite

# Use the production PHP configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Tune PHP for uploads and performance
RUN echo "upload_max_filesize = 10M" >> "$PHP_INI_DIR/conf.d/berufsmesse.ini" \
 && echo "post_max_size = 12M"       >> "$PHP_INI_DIR/conf.d/berufsmesse.ini" \
 && echo "max_execution_time = 300"  >> "$PHP_INI_DIR/conf.d/berufsmesse.ini" \
 && echo "opcache.enable = 1"        >> "$PHP_INI_DIR/conf.d/berufsmesse.ini" \
 && echo "opcache.revalidate_freq = 60" >> "$PHP_INI_DIR/conf.d/berufsmesse.ini"

# Allow .htaccess overrides in the web root
RUN sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf

# Copy application files
COPY --chown=www-data:www-data . /var/www/html

# Ensure the uploads directory exists and is writable by www-data
RUN mkdir -p /var/www/html/uploads \
 && chown -R www-data:www-data /var/www/html/uploads \
 && chmod 775 /var/www/html/uploads

# Copy and enable the entrypoint script
COPY --chown=root:root docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

VOLUME ["/var/www/html/uploads"]

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
