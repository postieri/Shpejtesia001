<?php
// Environment configuration
ini_set('memory_limit', '256M');
ini_set('max_execution_time', '300');
ini_set('zlib.output_compression', 'Off');
ini_set('output_buffering', 'Off');

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: no-referrer');

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['action'])) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: *');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    // Handle download test
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'download') {
        header('Content-Type: application/octet-stream');
        header('Content-Transfer-Encoding: binary');
        
        // Validate and sanitize size parameter
        $requestedSize = isset($_GET['size']) ? intval($_GET['size']) : 1048576;
        $maxChunkSize = 8 * 1024 * 1024; // 8MB maximum
        $size = min(max($requestedSize, 256 * 1024), $maxChunkSize);
        
        // Disable output buffering
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        try {
            $buffer = str_repeat('0', 8192); // 8KB buffer
            $remaining = $size;
            
            while ($remaining > 0 && !connection_aborted()) {
                $chunk = min($remaining, 8192);
                echo substr($buffer, 0, $chunk);
                $remaining -= $chunk;
                
                if ($remaining % (256 * 1024) === 0) { // Flush every 256KB
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
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'upload') {
        try {
            $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? intval($_SERVER['CONTENT_LENGTH']) : 0;
            
            if ($contentLength > 0) {
                $input = fopen('php://input', 'rb');
                $size = 0;
                $startTime = microtime(true);
                
                while (!feof($input)) {
                    $chunk = fread($input, 8192); // Read 8KB at a time
                    if ($chunk === false) break;
                    $size += strlen($chunk);
                    
                    if ($size % (256 * 1024) === 0) { // Flush every 256KB
                        flush();
                    }
                }
                
                $duration = microtime(true) - $startTime;
                fclose($input);
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'size' => $size,
                    'duration' => $duration
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
        echo json_encode(['timestamp' => microtime(true)]);
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
    <meta http-equiv="Permissions-Policy" content="interest-cohort=()">
    
    <!-- Prevent caching -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <!-- Dark theme styles -->
    <style>
        :root {
            --bg-color: #1a1a1a;
            --text-color: #ffffff;
            --primary-color: #2196F3;
            --secondary-color: #424242;
            --success-color: #4CAF50;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        h1 {
            text-align: center;
            margin-bottom: 2rem;
            color: var(--text-color);
        }
        
        .status {
            text-align: center;
            margin-bottom: 1rem;
            color: var(--text-color);
        }
        
        .speed-display {
            font-size: 3rem;
            text-align: center;
            margin: 1rem 0;
            color: var(--primary-color);
        }
        
        .unit {
            font-size: 1.5rem;
            margin-left: 0.5rem;
            color: var(--text-color);
            opacity: 0.8;
        }
        
        .progress-bar {
            background-color: var(--secondary-color);
            height: 4px;
            border-radius: 2px;
            margin: 2rem 0;
            overflow: hidden;
        }
        
        .progress {
            background-color: var(--primary-color);
            height: 100%;
            width: 0;
            transition: width 0.3s ease;
        }
        
        .test-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin: 2rem 0;
        }
        
        button {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 5px;
            background-color: var(--primary-color);
            color: white;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s ease;
        }
        
        button:hover {
            background-color: #1976D2;
        }
        
        button:disabled {
            background-color: var(--secondary-color);
            cursor: not-allowed;
        }
        
        .results {
            margin-top: 2rem;
            padding: 1rem;
            background-color: rgba(255, 255, 255, 0.05);
            border-radius: 5px;
        }
        
        .result-item {
            display: flex;
            justify-content: space-between;
            margin: 0.5rem 0;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--secondary-color);
        }
        
        .result-item:last-child {
            border-bottom: none;
        }
        
        .result-label {
            color: var(--text-color);
            opacity: 0.8;
        }
        
        .result-value {
            color: var(--primary-color);
            font-weight: 500;
        }
    </style>
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

    <script src="public/js/speedtest.js"></script>
</body>
</html>