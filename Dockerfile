FROM php:8.2-apache

# Build arguments
ARG APP_ENV=production
ARG PHP_MEMORY_LIMIT=256M
ARG MAX_UPLOAD_SIZE=100M

# Environment variables
ENV APP_ENV=${APP_ENV} \
    PHP_MEMORY_LIMIT=${PHP_MEMORY_LIMIT} \
    MAX_UPLOAD_SIZE=${MAX_UPLOAD_SIZE} \
    APACHE_DOCUMENT_ROOT=/var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    zip \
    unzip \
    git \
    libzip-dev \
    curl \
    cron \
    supervisor \
    libapache2-mod-security2 \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    zip \
    opcache \
    && pecl install apcu \
    && docker-php-ext-enable apcu

# Enable Apache modules
RUN a2enmod \
    rewrite \
    headers \
    expires \
    security2 \
    remoteip \
    ratelimit

# Configure PHP
COPY php.ini-production ${PHP_INI_DIR}/php.ini
RUN sed -i \
    -e "s/memory_limit = .*/memory_limit = ${PHP_MEMORY_LIMIT}/" \
    -e "s/upload_max_filesize = .*/upload_max_filesize = ${MAX_UPLOAD_SIZE}/" \
    -e "s/post_max_size = .*/post_max_size = ${MAX_UPLOAD_SIZE}/" \
    -e "s/expose_php = .*/expose_php = Off/" \
    -e "s/allow_url_fopen = .*/allow_url_fopen = Off/" \
    ${PHP_INI_DIR}/php.ini

# Configure ModSecurity
COPY modsecurity.conf /etc/modsecurity/modsecurity.conf
RUN sed -i 's/SecRuleEngine DetectionOnly/SecRuleEngine On/' /etc/modsecurity/modsecurity.conf

# Configure Apache
COPY apache.conf /etc/apache2/sites-available/000-default.conf
COPY security.conf /etc/apache2/conf-enabled/security.conf

# Setup cron jobs
COPY crontab /etc/cron.d/speedtest-cron
RUN chmod 0644 /etc/cron.d/speedtest-cron && \
    crontab /etc/cron.d/speedtest-cron

# Configure Supervisor
COPY supervisor.conf /etc/supervisor/conf.d/supervisord.conf

# Set working directory
WORKDIR /var/www/html

# Create necessary directories
RUN mkdir -p \
    /tmp/speed_test \
    /tmp/speed_test/logs \
    /var/log/speedtest

# Copy application files
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chown -R www-data:www-data /tmp/speed_test \
    && chmod -R 750 /tmp/speed_test \
    && chown -R www-data:www-data /var/log/speedtest \
    && chmod -R 750 /var/log/speedtest

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/healthcheck.php || exit 1

# Expose port
EXPOSE 80

# Start services
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]