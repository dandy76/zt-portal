FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libzip-dev \
    zip \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    openssl \
    cron \
    default-mysql-client \
    && docker-php-ext-install pdo pdo_mysql mbstring zip gd bcmath \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache modules
RUN a2enmod rewrite ssl headers

# Generate self-signed SSL certificate
RUN mkdir -p /etc/apache2/ssl && \
    openssl req -x509 -nodes -days 3650 \
    -newkey rsa:2048 \
    -keyout /etc/apache2/ssl/portal.key \
    -out /etc/apache2/ssl/portal.crt \
    -subj "/C=GR/ST=Attica/L=Athens/O=ZeroTrust/CN=zt-portal.local"

# Apache SSL configuration
COPY docker/apache-ssl.conf /etc/apache2/sites-available/default-ssl.conf
RUN a2ensite default-ssl

# Disable default HTTP site (redirect to HTTPS)
COPY docker/apache-redirect.conf /etc/apache2/sites-available/000-default.conf

# PHP configuration
RUN echo "display_errors = Off" >> /usr/local/etc/php/conf.d/zt-portal.ini && \
    echo "error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT" >> /usr/local/etc/php/conf.d/zt-portal.ini && \
    echo "date.timezone = Europe/Athens" >> /usr/local/etc/php/conf.d/zt-portal.ini && \
    echo "session.cookie_httponly = On" >> /usr/local/etc/php/conf.d/zt-portal.ini && \
    echo "session.cookie_secure = On" >> /usr/local/etc/php/conf.d/zt-portal.ini && \
    echo "session.cookie_samesite = Strict" >> /usr/local/etc/php/conf.d/zt-portal.ini && \
    echo "session.gc_maxlifetime = 1800" >> /usr/local/etc/php/conf.d/zt-portal.ini && \
    echo "max_execution_time = 30" >> /usr/local/etc/php/conf.d/zt-portal.ini && \
    echo "memory_limit = 128M" >> /usr/local/etc/php/conf.d/zt-portal.ini && \
    echo "upload_max_filesize = 2M" >> /usr/local/etc/php/conf.d/zt-portal.ini && \
    echo "post_max_size = 2M" >> /usr/local/etc/php/conf.d/zt-portal.ini

# Setup cron for cleanup (every minute)
RUN echo "* * * * * www-data php /var/www/html/scripts/cleanup.php >> /var/log/cleanup.log 2>&1" > /etc/cron.d/zt-cleanup && \
    chmod 0644 /etc/cron.d/zt-cleanup && \
    crontab /etc/cron.d/zt-cleanup

# Set working directory
WORKDIR /var/www/html

# Copy application code
COPY . /var/www/html/

# Install PHP dependencies (TOTP library)
RUN if [ -f composer.json ]; then composer install --no-dev --optimize-autoloader; fi

# Set permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 443

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
