<?php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

function checkDiskSpace($path) {
    $free = disk_free_space($path);
    $total = disk_total_space($path);
    return [
        'free' => $free,
        'total' => $total,
        'used' => $total - $free,
        'percent_free' => ($free / $total) * 100
    ];
}

function checkPermissions($path) {
    return [
        'exists' => file_exists($path),
        'writable' => is_writable($path),
        'owner' => posix_getpwuid(fileowner($path))['name'],
        'group' => posix_getgrgid(filegroup($path))['name'],
        'permissions' => substr(sprintf('%o', fileperms($path)), -4)
    ];
}

function checkLoadAverage() {
    $load = sys_getloadavg();
    return [
        '1min' => $load[0],
        '5min' => $load[1],
        '15min' => $load[2]
    ];
}

$tempDir = sys_get_temp_dir() . '/speed_test/';

$health = [
    'status' => 'healthy',
    'timestamp' => time(),
    'memory' => [
        'limit' => ini_get('memory_limit'),
        'usage' => memory_get_usage(true),
        'peak' => memory_get_peak_usage(true)
    ],
    'disk' => checkDiskSpace($tempDir),
    'temp_directory' => checkPermissions($tempDir),
    'system' => [
        'load' => checkLoadAverage(),
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown'
    ],
    'limits' => [
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size')
    ]
];

// Check if disk space is low
if ($health['disk']['percent_free'] < 10) {
    $health['status'] = 'warning';
    $health['warnings'][] = 'Low disk space';
}

// Check if load is high
if ($health['system']['load']['1min'] > 5) {
    $health['status'] = 'warning';
    $health['warnings'][] = 'High system load';
}

// Check temp directory
if (!$health['temp_directory']['writable']) {
    $health['status'] = 'critical';
    $health['errors'][] = 'Temp directory not writable';
}

// Set HTTP status code based on health status
http_response_code($health['status'] === 'healthy' ? 200 : 
                 ($health['status'] === 'warning' ? 429 : 503));

echo json_encode($health, JSON_PRETTY_PRINT);