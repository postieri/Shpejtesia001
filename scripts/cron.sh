#!/bin/bash

# Set paths
SCRIPT_DIR="$(dirname "$(readlink -f "$0")")"
LOG_DIR="/tmp/speed_test/logs"
CLEANUP_LOG="${LOG_DIR}/cleanup.log"
MONITOR_LOG="${LOG_DIR}/monitor.log"

# Create log directory
mkdir -p "${LOG_DIR}"

# Function to log messages
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$2"
}

# Run cleanup
log_message "Starting cleanup" "${CLEANUP_LOG}"
php "${SCRIPT_DIR}/cleanup.php" >> "${CLEANUP_LOG}" 2>&1
cleanup_status=$?

if [ $cleanup_status -eq 0 ]; then
    log_message "Cleanup completed successfully" "${CLEANUP_LOG}"
else
    log_message "Cleanup failed with status ${cleanup_status}" "${CLEANUP_LOG}"
fi

# Run monitoring
log_message "Starting monitoring" "${MONITOR_LOG}"
php "${SCRIPT_DIR}/monitor.php" >> "${MONITOR_LOG}" 2>&1
monitor_status=$?

if [ $monitor_status -eq 0 ]; then
    log_message "Monitoring completed successfully" "${MONITOR_LOG}"
else
    log_message "Monitoring failed with status ${monitor_status}" "${MONITOR_LOG}"
fi

# Rotate logs if they get too large (>10MB)
for log_file in "${CLEANUP_LOG}" "${MONITOR_LOG}"; do
    if [ -f "${log_file}" ] && [ $(stat -f%z "${log_file}") -gt 10485760 ]; then
        mv "${log_file}" "${log_file}.old"
        touch "${log_file}"
        log_message "Log rotated" "${log_file}"
    fi
done