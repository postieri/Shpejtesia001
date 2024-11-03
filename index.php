<?php
// Environment configuration
ini_set('memory_limit', getenv('PHP_MEMORY_LIMIT') ?: '256M');
ini_set('upload_max_filesize', getenv('MAX_UPLOAD_SIZE') ?: '100M');
ini_set('post_max_size', getenv('MAX_UPLOAD_SIZE') ?: '100M');

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['action'])) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: *');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }

    // Directory for temporary files
    $tempDir = sys_get_temp_dir() . '/speed_test/';

    // Create directory if it doesn't exist
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0777, true);
    }

    // Handle file upload
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $tempfile = $tempDir . uniqid('speed_test_') . '.tmp';
        file_put_contents($tempfile, file_get_contents('php://input'));
        echo json_encode(['success' => true]);
        exit;
    }

    // Handle ping request
    if (isset($_GET['action']) && $_GET['action'] === 'ping') {
        echo json_encode(['time' => time()]);
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
    <title>Internet Speed Test</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, 
                        "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: #f0f2f5;
            color: #333;
        }

        .container {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }

        h1 {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            color: #1a1a1a;
        }

        .speed-display {
            font-size: 3.5rem;
            font-weight: bold;
            margin: 1.5rem 0;
            color: #2196F3;
            font-feature-settings: "tnum";
            font-variant-numeric: tabular-nums;
        }

        .unit {
            font-size: 1.2rem;
            color: #666;
            margin-left: 0.5rem;
        }

        .test-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin: 1.5rem 0;
        }

        button {
            background: #2196F3;
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            min-width: 140px;
        }

        button:hover {
            background: #1976D2;
            transform: translateY(-1px);
        }

        button:active {
            transform: translateY(1px);
        }

        button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: #e0e0e0;
            margin: 1.5rem 0;
            border-radius: 3px;
            overflow: hidden;
        }

        .progress {
            width: 0%;
            height: 100%;
            background: #2196F3;
            transition: width 0.3s ease;
        }

        .status {
            color: #666;
            margin-bottom: 1rem;
            min-height: 20px;
            font-size: 1.1rem;
        }

        .results {
            margin-top: 1.5rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 8px;
            text-align: left;
            border: 1px solid #e9ecef;
        }

        .results div {
            margin: 0.75rem 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1.1rem;
        }

        .results span {
            font-weight: 600;
            color: #2196F3;
        }

        @media (max-width: 480px) {
            .container {
                padding: 1.5rem;
            }

            .test-buttons {
                flex-direction: column;
            }

            button {
                width: 100%;
            }

            .speed-display {
                font-size: 2.5rem;
            }
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
            <button onclick="startDownloadTest()">Test Download</button>
            <button onclick="startUploadTest()">Test Upload</button>
        </div>
        <div class="results">
            <div>Download: <span id="downloadResult">Not tested</span></div>
            <div>Upload: <span id="uploadResult">Not tested</span></div>
            <div>Latency: <span id="latencyResult">Not tested</span></div>
        </div>
    </div>

    <script>
        const CHUNK_SIZES = [1048576, 2097152, 5242880]; // 1MB, 2MB, 5MB chunks
        const status = document.querySelector('.status');
        const progress = document.querySelector('.progress');
        const speedDisplay = document.querySelector('.speed-display');
        const buttons = document.querySelectorAll('button');

        function generateChunk(size) {
            return new Blob([new ArrayBuffer(size)]);
        }

        function formatSpeed(speed) {
            if (speed < 1) return speed.toFixed(2);
            return speed.toFixed(1);
        }

        function updateProgress(percent) {
            progress.style.width = `${percent}%`;
        }

        function disableButtons(disable) {
            buttons.forEach(button => button.disabled = disable);
        }

        async function measureLatency() {
            const start = performance.now();
            try {
                await fetch('?action=ping', { 
                    cache: 'no-store',
                    headers: {
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache'
                    }
                });
                const end = performance.now();
                return end - start;
            } catch (error) {
                console.error('Latency measurement error:', error);
                return 0;
            }
        }

        async function downloadTest(chunkSize) {
            return new Promise((resolve) => {
                const startTime = performance.now();
                const blob = generateChunk(chunkSize);
                const url = URL.createObjectURL(blob);

                fetch(url, {
                    cache: 'no-store',
                    headers: {
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache'
                    }
                })
                    .then(response => response.blob())
                    .then(() => {
                        URL.revokeObjectURL(url);
                        const endTime = performance.now();
                        const duration = (endTime - startTime) / 1000;
                        const bitsLoaded = chunkSize * 8;
                        const speedMbps = (bitsLoaded / duration) / 1000000;
                        resolve(speedMbps);
                    })
                    .catch((error) => {
                        console.error('Download test error:', error);
                        resolve(0);
                    });
            });
        }

        async function uploadTest(chunkSize) {
            return new Promise((resolve) => {
                const blob = generateChunk(chunkSize);
                const startTime = performance.now();

                fetch(window.location.href, {
                    method: 'POST',
                    body: blob,
                    headers: {
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache'
                    }
                })
                    .then(() => {
                        const endTime = performance.now();
                        const duration = (endTime - startTime) / 1000;
                        const bitsLoaded = chunkSize * 8;
                        const speedMbps = (bitsLoaded / duration) / 1000000;
                        resolve(speedMbps);
                    })
                    .catch((error) => {
                        console.error('Upload test error:', error);
                        resolve(0);
                    });
            });
        }

        async function startDownloadTest() {
            disableButtons(true);
            status.textContent = 'Testing download speed...';
            speedDisplay.innerHTML = '...<span class="unit">Mbps</span>';
            
            let totalSpeed = 0;
            
            try {
                for (let i = 0; i < CHUNK_SIZES.length; i++) {
                    updateProgress((i / CHUNK_SIZES.length) * 100);
                    const speed = await downloadTest(CHUNK_SIZES[i]);
                    totalSpeed += speed;
                }

                const averageSpeed = totalSpeed / CHUNK_SIZES.length;
                updateProgress(100);
                speedDisplay.innerHTML = `${formatSpeed(averageSpeed)}<span class="unit">Mbps</span>`;
                document.getElementById('downloadResult').textContent = `${formatSpeed(averageSpeed)} Mbps`;
                status.textContent = 'Download test completed';
            } catch (error) {
                console.error('Download test error:', error);
                status.textContent = 'Error during download test';
            }

            disableButtons(false);
        }

        async function startUploadTest() {
            disableButtons(true);
            status.textContent = 'Testing upload speed...';
            speedDisplay.innerHTML = '...<span class="unit">Mbps</span>';
            
            let totalSpeed = 0;
            
            try {
                for (let i = 0; i < CHUNK_SIZES.length; i++) {
                    updateProgress((i / CHUNK_SIZES.length) * 100);
                    const speed = await uploadTest(CHUNK_SIZES[i]);
                    totalSpeed += speed;
                }

                const averageSpeed = totalSpeed / CHUNK_SIZES.length;
                updateProgress(100);
                speedDisplay.innerHTML = `${formatSpeed(averageSpeed)}<span class="unit">Mbps</span>`;
                document.getElementById('uploadResult').textContent = `${formatSpeed(averageSpeed)} Mbps`;
                status.textContent = 'Upload test completed';

                // Measure latency after upload test
                const latency = await measureLatency();
                document.getElementById('latencyResult').textContent = `${latency.toFixed(0)} ms`;
            } catch (error) {
                console.error('Upload test error:', error);
                status.textContent = 'Error during upload test';
            }

            disableButtons(false);
        }
    </script>
</body>
</html>