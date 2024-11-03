FROM php:8.2-apache

# Enable Apache modules and PHP extensions
RUN a2enmod rewrite headers expires remoteip security2 && \
    docker-php-ext-install opcache zip

# Install required dependencies
RUN apt-get update && apt-get install -y \
    zip \
    unzip \
    git \
    libzip-dev \
    curl \
    libapache2-mod-security2 \
    && rm -rf /var/lib/apt/lists/*

# Security configurations
RUN echo "ServerTokens Prod" >> /etc/apache2/conf-enabled/security.conf && \
    echo "ServerSignature Off" >> /etc/apache2/conf-enabled/security.conf && \
    echo "TraceEnable Off" >> /etc/apache2/conf-enabled/security.conf

# PHP Security configurations
COPY php.ini-production /usr/local/etc/php/php.ini
RUN sed -i 's/expose_php = On/expose_php = Off/' /usr/local/etc/php/php.ini && \
    sed -i 's/allow_url_fopen = On/allow_url_fopen = Off/' /usr/local/etc/php/php.ini

# Configure Apache
COPY apache.conf /etc/apache2/sites-available/000-default.conf
COPY security.conf /etc/apache2/conf-enabled/security.conf

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Create temp directory and set permissions
RUN mkdir -p /tmp/speed_test && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    chmod -R 750 /tmp/speed_test && \
    chown -R www-data:www-data /tmp/speed_test

# Security headers
RUN echo 'Header set Content-Security-Policy "default-src '\''self'\''; script-src '\''self'\''"' >> /etc/apache2/conf-enabled/security.conf

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/healthcheck.php || exit 1

# Expose port
EXPOSE 80

# Drop privileges
USER www-data

# Start Apache in foreground
CMD ["apache2-foreground"]