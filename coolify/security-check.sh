#!/bin/bash

# Security checking script
set -e

# Configuration
APP_DIR="/var/www/html"
SECURITY_LOG="/var/log/speedtest/security-check.log"

# Check file permissions
check_permissions() {
    echo "Checking file permissions..."
    find "$APP_DIR" -type f -not -perm 644 -exec chmod 644 {} \;
    find "$APP_DIR" -type d -not -perm 755 -exec chmod 755 {} \;
    chmod 600 "$APP_DIR/.env" 2>/dev/null || true
}

# Check sensitive files
check_sensitive_files() {
    echo "Checking for sensitive files..."
    sensitive_files=(".git" ".env" "composer.json" "composer.lock")
    for file in "${sensitive_files[@]}"; do
        if [ -e "$APP_DIR/$file" ]; then
            echo "Warning: Sensitive file found: $file"
        fi
    done
}

# Check Apache configuration
check_apache() {
    echo "Checking Apache configuration..."
    apache2ctl -t
}

# Check PHP configuration
check_php() {
    echo "Checking PHP configuration..."
    php -v
    php -m
}

# Main security check
main() {
    {
        echo "=== Security Check $(date) ==="
        check_permissions
        check_sensitive_files
        check_apache
        check_php
        echo "=== Check Complete ==="
    } >> "$SECURITY_LOG" 2>&1
}

main