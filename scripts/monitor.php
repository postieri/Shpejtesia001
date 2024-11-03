<?php
class SystemMonitor {
    private $tempDir;
    private $logFile;
    private $alertFile;
    private $metricsFile;
    private $thresholds;

    public function __construct() {
        $this->tempDir = sys_get_temp_dir() . '/speed_test/';
        $this->logFile = $this->tempDir . 'monitor.log';
        $this->alertFile = $this->tempDir . 'alerts.log';
        $this->metricsFile = $this->tempDir . 'metrics.json';
        $this->thresholds = [
            'disk_usage' => 90,    // 90%
            'memory_usage' => 90,  // 90%
            'load_average' => 5,   // 5.0
            'temp_files' => 1000   // max files
        ];
    }

    public function monitor() {
        try {
            $metrics = $this->collectMetrics();
            $this->checkThresholds($metrics);
            $this->writeMetrics($metrics);
            $this->rotateFiles();

            return [
                'status' => 'success',
                'timestamp' => time(),
                'metrics' => $metrics
            ];
        } catch (Exception $e) {
            $this->logError($e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    private function collectMetrics() {
        return [
            'system' => $this->getSystemMetrics(),
            'php' => $this->getPhpMetrics(),
            'disk' => $this->getDiskMetrics(),
            'temp_directory' => $this->getTempDirMetrics()
        ];
    }

    private function getSystemMetrics() {
        $load = sys_getloadavg();
        return [
            'load' => [
                '1m' => $load[0],
                '5m' => $load[1],
                '15m' => $load[2]
            ],
            'uptime' => $this->getUptime(),
            'memory' => $this->getMemoryInfo()
        ];
    }

    private function getPhpMetrics() {
        return [
            'version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'memory_usage' => memory_get_usage(true),
            'peak_memory_usage' => memory_get_peak_usage(true)
        ];
    }

    private function getDiskMetrics() {
        $free = disk_free_space($this->tempDir);
        $total = disk_total_space($this->tempDir);
        $used = $total - $free;
        
        return [
            'free' => $free,
            'total' => $total,
            'used' => $used,
            'used_percent' => ($used / $total) * 100
        ];
    }

    private function getTempDirMetrics() {
        $fileCount = 0;
        $totalSize = 0;

        foreach (new DirectoryIterator($this->tempDir) as $file) {
            if ($file->isDot() || !$file->isFile()) continue;
            $fileCount++;
            $totalSize += $file->getSize();
        }

        return [
            'file_count' => $fileCount,
            'total_size' => $totalSize,
            'is_writable' => is_writable($this->tempDir)
        ];
    }

    private function checkThresholds($metrics) {
        $alerts = [];

        if ($metrics['disk']['used_percent'] > $this->thresholds['disk_usage']) {
            $alerts[] = "High disk usage: {$metrics['disk']['used_percent']}%";
        }

        if ($metrics['system']['load']['5m'] > $this->thresholds['load_average']) {
            $alerts[] = "High system load: {$metrics['system']['load']['5m']}";
        }

        if ($metrics['temp_directory']['file_count'] > $this->thresholds['temp_files']) {
            $alerts[] = "Too many temp files: {$metrics['temp_directory']['file_count']}";
        }

        if (!empty($alerts)) {
            $this->writeAlerts($alerts);
        }
    }

    private function writeMetrics($metrics) {
        file_put_contents(
            $this->metricsFile,
            json_encode($metrics, JSON_PRETTY_PRINT)
        );
    }

    private function writeAlerts($alerts) {
        $logEntry = date('Y-m-d H:i:s') . " - " . implode('; ', $alerts) . PHP_EOL;
        file_put_contents($this->alertFile, $logEntry, FILE_APPEND);
    }

    private function rotateFiles() {
        $maxSize = 10485760; // 10MB

        foreach ([$this->logFile, $this->alertFile] as $file) {
            if (file_exists($file) && filesize($file) > $maxSize) {
                rename($file, $file . '.old');
            }
        }
    }

    private function getUptime() {
        if (file_exists('/proc/uptime')) {
            $uptime = file_get_contents('/proc/uptime');
            return (float)explode(' ', $uptime)[0];
        }
        return 0;
    }

    private function getMemoryInfo() {
        if (file_exists('/proc/meminfo')) {
            $meminfo = [];
            foreach (file('/proc/meminfo') as $line) {
                list($key, $val) = explode(':', $line);
                $meminfo[$key] = (int)filter_var($val, FILTER_SANITIZE_NUMBER_INT);
            }
            return $meminfo;
        }
        return [];
    }

    private function logError($message) {
        error_log("[SpeedTest Monitor] " . $message);
    }
}

// Run monitor if called directly
if (php_sapi_name() === 'cli') {
    $monitor = new SystemMonitor();
    $result = $monitor->monitor();
    echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
}