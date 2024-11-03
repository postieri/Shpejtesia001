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

    // Handle download test
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'download') {
        header('Content-Type: application/octet-stream');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        
        // Limit chunk size and apply rate limiting
        $requestedSize = isset($_GET['size']) ? intval($_GET['size']) : 1048576; // default 1MB
        $maxChunkSize = 5 * 1024 * 1024; // 5MB maximum
        $size = min($requestedSize, $maxChunkSize);
        
        // Generate and send data in smaller chunks with controlled rate
        $chunkSize = 65536; // 64KB chunks
        $totalSent = 0;
        $rateLimit = 32768; // Bytes per iteration (32KB)
        
        while ($totalSent < $size) {
            if (connection_aborted()) {
                break;
            }

            $sendSize = min($chunkSize, $size - $totalSent);
            $data = random_bytes($sendSize);
            
            // Send chunk
            echo $data;
            $totalSent += $sendSize;
            
            // Flush output
            flush();
            ob_flush();
            
            // Rate limiting delay
            usleep(1000); // 1ms delay between chunks
        }
        exit;
    }

    // Handle upload test
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = fopen('php://input', 'rb');
        $temp = fopen('php://temp', 'w+b');
        $size = 0;
        
        // Read in chunks to prevent memory issues
        while (!feof($input)) {
            $chunk = fread($input, 8192); // 8KB chunks
            if ($chunk === false) break;
            $written = fwrite($temp, $chunk);
            if ($written === false) break;
            $size += $written;
        }
        
        fclose($input);
        fclose($temp);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'size' => $size
        ]);
        exit;
    }

    // Handle ping request
    if (isset($_GET['action']) && $_GET['action'] === 'ping') {
        header('Content-Type: application/json');
        echo json_encode([
            'timestamp' => microtime(true),
            'status' => 'ok'
        ]);
        exit;
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
    
    <!-- Styles -->
    <link rel="stylesheet" href="public/css/style.css">

    <!-- Preload key resources -->
    <link rel="preload" href="public/js/speedtest.js" as="script">
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