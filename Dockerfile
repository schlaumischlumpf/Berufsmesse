# syntax=docker/dockerfile:1
FROM php:8.2-apache

# Install system dependencies and PHP extensions required by the app
RUN apt-get update && apt-get install -y \
        libfreetype-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libzip-dev \
        libonig-dev \
        zip \
        unzip \
        curl \
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

# Write all PHP tunables in a single step – uses a grouped redirect so
# every setting ends up in the file regardless of build-cache state.
RUN { \
        echo 'upload_max_filesize = 10M'; \
        echo 'post_max_size = 12M'; \
        echo 'max_execution_time = 300'; \
        echo 'opcache.enable = 1'; \
        echo 'opcache.memory_consumption = 128'; \
        echo 'opcache.interned_strings_buffer = 8'; \
        echo 'opcache.max_accelerated_files = 4000'; \
        echo 'opcache.revalidate_freq = 60'; \
        echo 'opcache.enable_cli = 0'; \
    } > "$PHP_INI_DIR/conf.d/berufsmesse.ini"

# Allow .htaccess overrides specifically for the web root.
# A dedicated conf file is more reliable than patching apache2.conf with sed.
RUN { \
        echo '<Directory /var/www/html>'; \
        echo '    AllowOverride All'; \
        echo '    Options -Indexes +FollowSymLinks'; \
        echo '    Require all granted'; \
        echo '</Directory>'; \
    } > /etc/apache2/conf-available/berufsmesse.conf \
 && a2enconf berufsmesse

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

# Declare the port Apache listens on inside the container
EXPOSE 80

# Report healthy once the login page returns HTTP 200
HEALTHCHECK --interval=30s --timeout=5s --start-period=90s --retries=3 \
    CMD curl -fsS http://localhost/login.php -o /dev/null || exit 1

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
