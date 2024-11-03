<?php
header('Content-Type: application/json');

class StorageCleanup {
    private $tempDir;
    private $maxAge;
    private $maxSize;
    private $log = [];

    public function __construct($tempDir, $maxAge = 3600, $maxSize = 1073741824) {
        $this->tempDir = $tempDir;
        $this->maxAge = $maxAge; // Default 1 hour
        $this->maxSize = $maxSize; // Default 1GB
    }

    public function cleanup() {
        try {
            $this->checkDirectory();
            $this->removeOldFiles();
            $this->enforceStorageLimit();
            $this->logCleanup();
            return [
                'status' => 'success',
                'log' => $this->log,
                'stats' => $this->getStats()
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'log' => $this->log
            ];
        }
    }

    private function checkDirectory() {
        if (!file_exists($this->tempDir)) {
            mkdir($this->tempDir, 0750, true);
            $this->log[] = "Created directory: {$this->tempDir}";
        }
        if (!is_writable($this->tempDir)) {
            throw new Exception("Directory not writable: {$this->tempDir}");
        }
    }

    private function removeOldFiles() {
        $now = time();
        $files = glob($this->tempDir . '/*');
        $removed = 0;

        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) > $this->maxAge) {
                    unlink($file);
                    $removed++;
                }
            }
        }

        $this->log[] = "Removed {$removed} old files";
    }

    private function enforceStorageLimit() {
        $files = glob($this->tempDir . '/*');
        $totalSize = 0;
        $fileInfo = [];

        foreach ($files as $file) {
            if (is_file($file)) {
                $size = filesize($file);
                $totalSize += $size;
                $fileInfo[] = [
                    'path' => $file,
                    'size' => $size,
                    'time' => filemtime($file)
                ];
            }
        }

        if ($totalSize > $this->maxSize) {
            usort($fileInfo, function($a, $b) {
                return $a['time'] - $b['time'];
            });

            while ($totalSize > $this->maxSize && !empty($fileInfo)) {
                $file = array_shift($fileInfo);
                unlink($file['path']);
                $totalSize -= $file['size'];
                $this->log[] = "Removed file to free space: {$file['path']}";
            }
        }
    }

    private function getStats() {
        $files = glob($this->tempDir . '/*');
        $totalSize = 0;
        $fileCount = 0;

        foreach ($files as $file) {
            if (is_file($file)) {
                $totalSize += filesize($file);
                $fileCount++;
            }
        }

        return [
            'total_size' => $totalSize,
            'file_count' => $fileCount,
            'disk_free' => disk_free_space($this->tempDir),
            'disk_total' => disk_total_space($this->tempDir)
        ];
    }

    private function logCleanup() {
        $logFile = $this->tempDir . '/cleanup.log';
        $logEntry = date('Y-m-d H:i:s') . ' - ' . implode('; ', $this->log) . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
}

// Run cleanup
$cleanup = new StorageCleanup('/tmp/speed_test');
echo json_encode($cleanup->cleanup(), JSON_PRETTY_PRINT);