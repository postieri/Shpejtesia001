<?php
class StorageCleanup {
    private $tempDir;
    private $maxAge;
    private $maxSize;
    private $logFile;
    private $log = [];

    public function __construct() {
        $this->tempDir = sys_get_temp_dir() . '/speed_test/';
        $this->maxAge = 3600; // 1 hour
        $this->maxSize = 1073741824; // 1GB
        $this->logFile = $this->tempDir . 'cleanup.log';
    }

    public function cleanup() {
        try {
            $this->initializeDirectory();
            $this->removeOldFiles();
            $this->enforceStorageLimit();
            $this->cleanupLogs();
            $this->writeLog();

            return [
                'status' => 'success',
                'log' => $this->log,
                'stats' => $this->getStats()
            ];
        } catch (Exception $e) {
            $this->logError($e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'log' => $this->log
            ];
        }
    }

    private function initializeDirectory() {
        if (!file_exists($this->tempDir)) {
            if (!mkdir($this->tempDir, 0750, true)) {
                throw new Exception("Failed to create directory: {$this->tempDir}");
            }
            $this->log[] = "Created directory: {$this->tempDir}";
        }

        if (!is_writable($this->tempDir)) {
            throw new Exception("Directory not writable: {$this->tempDir}");
        }
    }

    private function removeOldFiles() {
        $now = time();
        $count = 0;
        $size = 0;

        foreach (new DirectoryIterator($this->tempDir) as $file) {
            if ($file->isDot() || !$file->isFile()) continue;

            if ($now - $file->getMTime() > $this->maxAge) {
                $size += $file->getSize();
                if (@unlink($file->getPathname())) {
                    $count++;
                }
            }
        }

        $this->log[] = "Removed {$count} old files (". $this->formatSize($size) .")";
    }

    private function enforceStorageLimit() {
        $files = [];
        $totalSize = 0;

        foreach (new DirectoryIterator($this->tempDir) as $file) {
            if ($file->isDot() || !$file->isFile()) continue;

            $files[] = [
                'path' => $file->getPathname(),
                'size' => $file->getSize(),
                'time' => $file->getMTime()
            ];
            $totalSize += $file->getSize();
        }

        if ($totalSize > $this->maxSize) {
            usort($files, function($a, $b) {
                return $a['time'] - $b['time'];
            });

            while ($totalSize > $this->maxSize && !empty($files)) {
                $file = array_shift($files);
                if (@unlink($file['path'])) {
                    $totalSize -= $file['size'];
                    $this->log[] = "Removed file to free space: " . basename($file['path']);
                }
            }
        }
    }

    private function cleanupLogs() {
        if (file_exists($this->logFile) && filesize($this->logFile) > 10485760) { // 10MB
            rename($this->logFile, $this->logFile . '.old');
            $this->log[] = "Rotated log file";
        }
    }

    private function getStats() {
        $totalSize = 0;
        $fileCount = 0;

        foreach (new DirectoryIterator($this->tempDir) as $file) {
            if ($file->isDot() || !$file->isFile()) continue;
            $totalSize += $file->getSize();
            $fileCount++;
        }

        return [
            'total_size' => $totalSize,
            'formatted_size' => $this->formatSize($totalSize),
            'file_count' => $fileCount,
            'disk_free' => disk_free_space($this->tempDir),
            'disk_total' => disk_total_space($this->tempDir)
        ];
    }

    private function formatSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    private function writeLog() {
        $logEntry = date('Y-m-d H:i:s') . " - " . implode('; ', $this->log) . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND);
    }

    private function logError($message) {
        error_log("[SpeedTest Cleanup] " . $message);
        $this->log[] = "ERROR: " . $message;
    }
}

// Run cleanup if called directly
if (php_sapi_name() === 'cli') {
    $cleanup = new StorageCleanup();
    $result = $cleanup->cleanup();
    echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
}