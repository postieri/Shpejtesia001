#!/bin/bash

# Exit on error
set -e

# Configuration
APP_DIR="/var/www/html"
TEMP_DIR="/tmp/speed_test"
LOG_DIR="/var/log/speedtest"
BACKUP_DIR="/var/backups/speedtest"

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[$(date '+%Y-%m-%d %H:%M:%S')] ERROR:${NC} $1"
}

warn() {
    echo -e "${YELLOW}[$(date '+%Y-%m-%d %H:%M:%S')] WARNING:${NC} $1"
}

# Create necessary directories
setup_directories() {
    log "Setting up directories..."
    
    for DIR in "$TEMP_DIR" "$LOG_DIR" "$BACKUP_DIR"; do
        if [ ! -d "$DIR" ]; then
            mkdir -p "$DIR"
            log "Created directory: $DIR"
        fi
    done

    # Set proper permissions
    chown -R www-data:www-data "$TEMP_DIR" "$LOG_DIR" "$BACKUP_DIR"
    chmod -R 750 "$TEMP_DIR" "$LOG_DIR" "$BACKUP_DIR"
}

# Security checks
security_checks() {
    log "Performing security checks..."

    # Check PHP configuration
    if [ "$(php -r 'echo ini_get("expose_php");')" == "1" ]; then
        warn "PHP version exposure is enabled"
    fi

    # Check file permissions
    find "$APP_DIR" -type f -exec chmod 644 {} \;
    find "$APP_DIR" -type d -exec chmod 755 {} \;
    chmod 750 "$APP_DIR/scripts"

    # Check sensitive files
    if [ -f "$APP_DIR/.env" ]; then
        chmod 600 "$APP_DIR/.env"
    fi

    # Verify Apache modules
    required_modules=("headers" "rewrite" "security2" "remoteip")
    for module in "${required_modules[@]}"; do
        if ! a2query -m "$module" > /dev/null 2>&1; then
            error "Required Apache module '$module' is not enabled"
            a2enmod "$module"
        fi
    done
}

# Setup cron jobs
setup_cron() {
    log "Setting up cron jobs..."
    
    # Install crontab
    if [ -f "/etc/cron.d/speedtest-cron" ]; then
        crontab /etc/cron.d/speedtest-cron
        log "Installed crontab"
    else
        error "Crontab file not found"
    fi

    # Start cron service
    service cron start
    log "Started cron service"
}

# Backup existing data
backup_data() {
    log "Creating backup..."
    
    BACKUP_FILE="$BACKUP_DIR/backup-$(date +%Y%m%d-%H%M%S).tar.gz"
    
    # Create backup of important directories
    tar -czf "$BACKUP_FILE" \
        -C "$TEMP_DIR" . \
        -C "$LOG_DIR" . \
        2>/dev/null || true

    log "Backup created: $BACKUP_FILE"
}

# Cleanup old backups and logs
cleanup_old_files() {
    log "Cleaning up old files..."
    
    # Remove backups older than 7 days
    find "$BACKUP_DIR" -type f -name "backup-*.tar.gz" -mtime +7 -delete
    
    # Rotate large log files
    find "$LOG_DIR" -type f -name "*.log" -size +10M | while read -r file; do
        mv "$file" "${file}.old"
        touch "$file"
        chown www-data:www-data "$file"
        chmod 640 "$file"
    done
}

# Configure Apache
configure_apache() {
    log "Configuring Apache..."
    
    # Enable necessary modules
    a2enmod headers rewrite security2 remoteip

    # Set proper permissions for Apache logs
    chown -R root:adm /var/log/apache2
    chmod -R 640 /var/log/apache2
}

# Main deployment process
main() {
    log "Starting deployment process..."

    # Run all setup functions
    setup_directories
    security_checks
    backup_data
    cleanup_old_files
    setup_cron
    configure_apache

    # Start Apache
    log "Starting Apache..."
    apache2-foreground
}

# Error handling
handle_error() {
    error "An error occurred on line $1"
    cleanup_and_exit
}

cleanup_and_exit() {
    warn "Performing emergency cleanup..."
    # Add any necessary cleanup steps here
    exit 1
}

# Set error handler
trap 'handle_error $LINENO' ERR

# Run main process
main