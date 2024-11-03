#!/bin/bash

# Exit on error
set -e

# Security checks
if [ "$(id -u)" = "0" ]; then
    echo "Warning: Running as root"
fi

# Create necessary directories with secure permissions
mkdir -p /tmp/speed_test
chmod 750 /tmp/speed_test
chown www-data:www-data /tmp/speed_test

# Security hardening
chmod 644 /var/www/html/*.php
chmod 755 /var/www/html
find /var/www/html -type f -exec chmod 644 {} \;
find /var/www/html -type d -exec chmod 755 {} \;

# Start the application
apache2-foreground