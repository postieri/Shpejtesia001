#!/bin/bash

# Configuration
LOG_DIR="/var/log/speedtest"
MONITOR_LOG="${LOG_DIR}/monitor.log"
ALERT_LOG="${LOG_DIR}/alerts.log"
METRICS_FILE="/tmp/speed_test/metrics.json"

# Thresholds
DISK_THRESHOLD=90
MEMORY_THRESHOLD=90
LOAD_THRESHOLD=5.0

# Logging function
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$MONITOR_LOG"
}

alert() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ALERT: $1" >> "$ALERT_LOG"
}

# Check system resources
check_resources() {
    # CPU Load
    load_average=$(uptime | awk -F'load average:' '{ print $2 }' | awk -F, '{ print $1 }' | tr -d ' ')
    if (( $(echo "$load_average > $LOAD_THRESHOLD" | bc -l) )); then
        alert "High CPU load: $load_average"
    fi

    # Memory usage
    if [ -f /proc/meminfo ]; then
        total_mem=$(grep MemTotal /proc/meminfo | awk '{print $2}')
        free_mem=$(grep MemAvailable /proc/meminfo | awk '{print $2}')
        used_mem=$((total_mem - free_mem))
        mem_percent=$((used_mem * 100 / total_mem))

        if [ $mem_percent -gt $MEMORY_THRESHOLD ]; then
            alert "High memory usage: ${mem_percent}%"
        fi
    fi

    # Disk usage
    disk_usage=$(df -h / | awk 'NR==2 {print $5}' | tr -d '%')
    if [ "$disk_usage" -gt $DISK_THRESHOLD ]; then
        alert "High disk usage: ${disk_usage}%"
    fi
}

# Check application status
check_application() {
    # Check if Apache is running
    if ! pgrep apache2 > /dev/null; then
        alert "Apache is not running"
    fi

    # Check application health
    health_status=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/healthcheck.php)
    if [ "$health_status" != "200" ]; then
        alert "Health check failed with status: $health_status"
    fi
}

# Monitor log files
check_logs() {
    # Check for errors in Apache logs
    if [ -f /var/log/apache2/error.log ]; then
        errors=$(tail -n 100 /var/log/apache2/error.log | grep -i "error")
        if [ ! -z "$errors" ]; then
            alert "Apache errors detected: $errors"
        fi
    fi

    # Check ModSecurity logs
    if [ -f /var/log/modsecurity/audit.log ]; then
        attacks=$(tail -n 100 /var/log/modsecurity/audit.log | grep -i "attack")
        if [ ! -z "$attacks" ]; then
            alert "Security attacks detected: $attacks"
        fi
    fi
}

# Write metrics
write_metrics() {
    # Collect metrics
    metrics=$(cat << EOF
{
    "timestamp": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")",
    "system": {
        "load_average": "$load_average",
        "memory_usage": "$mem_percent",
        "disk_usage": "$disk_usage"
    },
    "application": {
        "health_status": "$health_status",
        "apache_status": "$(pgrep apache2 > /dev/null && echo "running" || echo "stopped")"
    }
}
EOF
)
    echo "$metrics" > "$METRICS_FILE"
}

# Rotate logs
rotate_logs() {
    for log_file in "$MONITOR_LOG" "$ALERT_LOG"; do
        if [ -f "$log_file" ] && [ $(stat -f%z "$log_file") -gt 10485760 ]; then
            mv "$log_file" "${log_file}.old"
            touch "$log_file"
            log "Log rotated: $log_file"
        fi
    done
}

# Main monitoring loop
main() {
    while true; do
        check_resources
        check_application
        check_logs
        write_metrics
        rotate_logs
        sleep 60
    done
}

# Start monitoring
log "Starting monitoring service"
main