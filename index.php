<?php
// Environment configuration
ini_set('memory_limit', getenv('PHP_MEMORY_LIMIT') ?: '256M');
ini_set('upload_max_filesize', getenv('MAX_UPLOAD_SIZE') ?: '100M');
ini_set('post_max_size', getenv('MAX_UPLOAD_SIZE') ?: '100M');
ini_set('max_execution_time', '300');

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['action'])) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: *');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }

    // Directory for temporary files
    $tempDir = sys_get_temp_dir() . '/speed_test/';

    // Create directory if it doesn't exist
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0750, true);
    }

    // Handle file upload
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $tempfile = $tempDir . uniqid('speed_test_') . '.tmp';
        $success = file_put_contents($tempfile, file_get_contents('php://input'));
        echo json_encode(['success' => $success !== false]);
        @unlink($tempfile); // Clean up immediately after upload
        exit;
    }

    // Handle ping request
    if (isset($_GET['action']) && $_GET['action'] === 'ping') {
        echo json_encode([
            'time' => microtime(true),
            'server_time' => time(),
            'memory_usage' => memory_get_usage(true)
        ]);
        exit;
    }

    // Cleanup old files (older than 1 hour)
    $files = glob($tempDir . '*');
    foreach ($files as $file) {
        if (filemtime($file) < time() - 3600) {
            @unlink($file);
        }
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Internet Speed Test</title>
    <meta name="description" content="Test your internet connection speed with our fast and accurate speed test tool">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Security headers -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    
    <!-- Styles -->
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Internet Speed Test</h1>
        <div class="status">Ready to test</div>
        <div class="speed-display">0<span class="unit">Mbps</span></div>
        <div class="progress-bar">
            <div class="progress"></div>
        </div>
        <div class="test-buttons">
            <button onclick="startDownloadTest()">Test Download</button>
            <button onclick="startUploadTest()">Test Upload</button>
        </div>
        <div class="results">
            <div>Download: <span id="downloadResult">Not tested</span></div>
            <div>Upload: <span id="uploadResult">Not tested</span></div>
            <div>Latency: <span id="latencyResult">Not tested</span></div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="public/js/speedtest.js"></script>
</body>
</html>