#!/bin/bash

# Monitoring script
set -e

# Configuration
MONITOR_LOG="/var/log/speedtest/monitor.log"
ALERT_LOG="/var/log/speedtest/alerts.log"
METRICS_FILE="/tmp/speed_test/metrics.json"

# Check system resources
check_resources() {
    CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}')
    MEM_USAGE=$(free -m | awk 'NR==2{printf "%.2f", $3*100/$2}')
    DISK_USAGE=$(df -h / | awk 'NR==2{print $5}' | sed 's/%//')
    
    echo "{
        \"timestamp\": \"$(date -u +"%Y-%m-%dT%H:%M:%SZ")\",
        \"cpu_usage\": $CPU_USAGE,
        \"memory_usage\": $MEM_USAGE,
        \"disk_usage\": $DISK_USAGE
    }" > "$METRICS_FILE"

    # Check thresholds
    if [ "${CPU_USAGE%.*}" -gt 80 ]; then
        echo "[ALERT] High CPU usage: $CPU_USAGE%" >> "$ALERT_LOG"
    fi
    if [ "${MEM_USAGE%.*}" -gt 80 ]; then
        echo "[ALERT] High memory usage: $MEM_USAGE%" >> "$ALERT_LOG"
    fi
    if [ "$DISK_USAGE" -gt 80 ]; then
        echo "[ALERT] High disk usage: $DISK_USAGE%" >> "$ALERT_LOG"
    fi
}

# Main monitoring loop
main() {
    while true; do
        check_resources
        sleep 60
    done
}

main >> "$MONITOR_LOG" 2>&1