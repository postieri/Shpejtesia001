<?php
// Environment configuration
ini_set('memory_limit', getenv('PHP_MEMORY_LIMIT') ?: '256M');
ini_set('upload_max_filesize', getenv('MAX_UPLOAD_SIZE') ?: '100M');
ini_set('post_max_size', getenv('MAX_UPLOAD_SIZE') ?: '100M');
ini_set('max_execution_time', '300');
ini_set('zlib.output_compression', 'Off');
ini_set('output_buffering', 'Off');

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['action'])) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: *');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }

    // Handle download test
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'download') {
        header('Content-Type: application/octet-stream');
        header('Content-Transfer-Encoding: binary');
        
        // Validate and sanitize size parameter
        $requestedSize = isset($_GET['size']) ? intval($_GET['size']) : 1048576;
        $maxChunkSize = 8 * 1024 * 1024; // 8MB maximum
        $size = min(max($requestedSize, 256 * 1024), $maxChunkSize); // Between 256KB and 8MB
        
        // Determine optimal chunk size based on requested size
        $chunkSize = min(65536, max(8192, intval($size / 100))); // Between 8KB and 64KB
        $totalSent = 0;
        
        // Disable output buffering
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set time limit based on expected transfer time
        $timeLimit = max(30, ceil($size / (1024 * 1024) * 5)); // 5 seconds per MB, minimum 30 seconds
        set_time_limit($timeLimit);
        
        try {
            while ($totalSent < $size) {
                if (connection_aborted()) {
                    break;
                }
                
                $sendSize = min($chunkSize, $size - $totalSent);
                $data = random_bytes($sendSize);
                $written = fwrite(fopen('php://output', 'wb'), $data);
                
                if ($written === false || $written !== $sendSize) {
                    throw new Exception('Failed to write data');
                }
                
                $totalSent += $written;
                
                // Flush every few chunks
                if ($totalSent % ($chunkSize * 8) === 0) {
                    flush();
                }
            }
        } catch (Exception $e) {
            error_log("Download test error: " . $e->getMessage());
            http_response_code(500);
        }
        exit;
    }

    // Handle upload test
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? intval($_SERVER['CONTENT_LENGTH']) : 0;
            
            if ($contentLength > 0) {
                $input = fopen('php://input', 'rb');
                $temp = fopen('php://temp', 'w+b');
                $size = 0;
                $chunkSize = 65536; // 64KB chunks for processing uploads
                
                while (!feof($input)) {
                    $chunk = fread($input, $chunkSize);
                    if ($chunk === false) {
                        throw new Exception('Failed to read upload data');
                    }
                    
                    $written = fwrite($temp, $chunk);
                    if ($written === false) {
                        throw new Exception('Failed to process upload data');
                    }
                    
                    $size += $written;
                    
                    // Flush output buffer periodically
                    if ($size % (1024 * 1024) === 0) {
                        flush();
                    }
                }
                
                fclose($input);
                fclose($temp);
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'size' => $size
                ]);
            } else {
                throw new Exception('No content received');
            }
        } catch (Exception $e) {
            error_log("Upload test error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Upload failed: ' . $e->getMessage()
            ]);
        }
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
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="Permissions-Policy" content="interest-cohort=()">
    
    <!-- Preload key resources -->
    <link rel="preload" href="public/js/speedtest.js" as="script">
    <link rel="preload" href="public/css/style.css" as="style">
    
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
            <button onclick="startDownloadTest()" class="download-btn">Test Download</button>
            <button onclick="startUploadTest()" class="upload-btn">Test Upload</button>
        </div>
        <div class="results">
            <div class="result-item">
                <span class="result-label">Download:</span>
                <span id="downloadResult" class="result-value">Not tested</span>
            </div>
            <div class="result-item">
                <span class="result-label">Upload:</span>
                <span id="uploadResult" class="result-value">Not tested</span>
            </div>
            <div class="result-item">
                <span class="result-label">Latency:</span>
                <span id="latencyResult" class="result-value">Not tested</span>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="public/js/speedtest.js"></script>
</body>
</html>