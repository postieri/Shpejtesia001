#!/bin/bash

# Configuration
APP_DIR="/var/www/html"
LOG_DIR="/var/log/speedtest"
SECURITY_LOG="${LOG_DIR}/security-check.log"

# Logging function
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$SECURITY_LOG"
}

# Check file permissions
check_permissions() {
    log "Checking file permissions..."
    
    # Check application files
    find "$APP_DIR" -type f -not -perm 644 -exec chmod 644 {} \;
    find "$APP_DIR" -type d -not -perm 755 -exec chmod 755 {} \;
    
    # Check sensitive files
    if [ -f "$APP_DIR/.env" ]; then
        chmod 600 "$APP_DIR/.env"
    fi
    
    # Check script permissions
    chmod 750 "$APP_DIR/scripts"
}

# Check Apache configuration
check_apache() {
    log "Checking Apache configuration..."
    
    # Verify Apache modules
    required_modules=("headers" "rewrite" "security2" "remoteip")
    for module in "${required_modules[@]}"; do
        if ! a2query -m "$module" > /dev/null 2>&1; then
            log "WARNING: Apache module '$module' is not enabled"
            a2enmod "$module"
        fi
    done
    
    # Test Apache configuration
    apache2ctl -t >> "$SECURITY_LOG" 2>&1
}

# Check ModSecurity
check_modsecurity() {
    log "Checking ModSecurity..."
    
    if [ ! -f "/etc/modsecurity/modsecurity.conf" ]; then
        log "ERROR: ModSecurity configuration not found"
        return 1
    fi
    
    # Verify ModSecurity is enabled
    if ! grep -q "SecRuleEngine On" /etc/modsecurity/modsecurity.conf; then
        log "WARNING: ModSecurity is not enabled"
    fi
}

# Check sensitive files
check_sensitive_files() {
    log "Checking for sensitive files..."
    
    sensitive_files=(".git" ".env" "composer.json" "composer.lock")
    for file in "${sensitive_files[@]}"; do
        if [ -e "$APP_DIR/$file" ]; then
            log "WARNING: Sensitive file found: $file"
        fi
    done
}

# Check PHP configuration
check_php() {
    log "Checking PHP configuration..."
    
    # Check PHP version
    php -v >> "$SECURITY_LOG" 2>&1
    
    # Check PHP modules
    php -m >> "$SECURITY_LOG" 2>&1
    
    # Check PHP configuration
    php -i | grep -E "expose_php|display_errors|allow_url_fopen" >> "$SECURITY_LOG" 2>&1
}

# Main security check
main() {
    log "=== Starting Security Check ==="
    
    check_permissions
    check_apache
    check_modsecurity
    check_sensitive_files
    check_php
    
    log "=== Security Check Complete ==="
}

# Rotate log if too large
if [ -f "$SECURITY_LOG" ] && [ $(stat -f%z "$SECURITY_LOG") -gt 10485760 ]; then
    mv "$SECURITY_LOG" "${SECURITY_LOG}.old"
    touch "$SECURITY_LOG"
fi

# Run security check
main