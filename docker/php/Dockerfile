FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    python3 \
    python3-pip \
    python3-venv \
    git \
    libzip-dev \
    zip \
    unzip \
    default-mysql-client \
    supervisor \
    procps \
    && docker-php-ext-install pdo pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

# Install Playwright and dependencies
RUN pip3 install --break-system-packages playwright openpyxl

# Copy custom PHP configuration
COPY custom.ini /usr/local/etc/php/conf.d/custom.ini

# Copy supervisor config
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Install Playwright browsers system-wide
ENV PLAYWRIGHT_BROWSERS_PATH=/usr/local/share/playwright
RUN python3 -m playwright install chromium
RUN python3 -m playwright install-deps chromium

# Create directories
RUN mkdir -p /var/www/downloads /var/www/scripts /var/log/supervisor && \
    chown -R www-data:www-data /var/www && \
    chmod -R 775 /var/www/downloads

# Set umask for future file creation
RUN echo "umask 0002" >> /etc/profile

WORKDIR /var/www/html

# Supervisor manages both php-fpm AND pbx-monitor daemon
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
