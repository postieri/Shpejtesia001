<?php
header('Content-Type: application/json');

class SystemMonitor {
    private $tempDir;
    private $thresholds;
    private $alerts = [];
    private $stats = [];

    public function __construct($tempDir) {
        $this->tempDir = $tempDir;
        $this->thresholds = [
            'disk_usage' => 90, // 90% threshold
            'file_count' => 1000, // max files
            'load_average' => 5, // max load
            'memory_usage' => 90 // 90% threshold
        ];
    }

    public function monitor() {
        try {
            $this->checkDiskUsage();
            $this->checkFileCount();
            $this->checkLoadAverage();
            $this->checkMemoryUsage();
            $this->checkPermissions();
            $this->logStatus();

            return [
                'status' => empty($this->alerts) ? 'healthy' : 'warning',
                'timestamp' => time(),
                'stats' => $this->stats,
                'alerts' => $this->alerts
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'timestamp' => time()
            ];
        }
    }

    private function checkDiskUsage() {
        $free = disk_free_space($this->tempDir);
        $total = disk_total_space($this->tempDir);
        $used = $total - $free;
        $usagePercent = ($used / $total) * 100;

        $this->stats['disk'] = [
            'free' => $free,
            'total' => $total,
            'used' => $used,
            'usage_percent' => $usagePercent
        ];

        if ($usagePercent > $this->thresholds['disk_usage']) {
            $this->alerts[] = "High disk usage: {$usagePercent}%";
        }
    }

    private function checkFileCount() {
        $files = glob($this->tempDir . '/*');
        $count = count($files);

        $this->stats['files'] = [
            'count' => $count,
            'threshold' => $this->thresholds['file_count']
        ];

        if ($count > $this->thresholds['file_count']) {
            $this->alerts[] = "Too many files: {$count}";
        }
    }

    private function checkLoadAverage() {
        $load = sys_getloadavg();
        
        $this->stats['load'] = [
            '1min' => $load[0],
            '5min' => $load[1],
            '15min' => $load[2]
        ];

        if ($load[0] > $this->thresholds['load_average']) {
            $this->alerts[] = "High system load: {$load[0]}";
        }
    }

    private function checkMemoryUsage() {
        $memInfo = [];
        if (file_exists('/proc/meminfo')) {
            $data = explode("\n", file_get_contents('/proc/meminfo'));
            foreach ($data as $line) {
                if (preg_match('/^(\w+):\s+(\d+)\s/', $line, $matches)) {
                    $memInfo[$matches[1]] = $matches[2];
                }
            }
        }

        $total = isset($memInfo['MemTotal']) ? $memInfo['MemTotal'] : 0;
        $free = isset($memInfo['MemAvailable']) ? $memInfo['MemAvailable'] : 0;
        $used = $total - $free;
        $usagePercent = $total > 0 ? ($used / $total) * 100 : 0;

        $this->stats['memory'] = [
            'total' => $total,
            'free' => $free,
            'used' => $used,
            'usage_percent' => $usagePercent
        ];

        if ($usagePercent > $this->thresholds['memory_usage']) {
            $this->alerts[] = "High memory usage: {$usagePercent}%";
        }
    }

    private function checkPermissions() {
        $this->stats['permissions'] = [
            'exists' => file_exists($this->tempDir),
            'writable' => is_writable($this->tempDir),
            'owner' => posix_getpwuid(fileowner($this->tempDir))['name'],
            'group' => posix_getgrgid(filegroup($this->tempDir))['name'],
            'mode' => substr(sprintf('%o', fileperms($this->tempDir)), -4)
        ];

        if (!$this->stats['permissions']['writable']) {
            $this->alerts[] = "Directory not writable: {$this->tempDir}";
        }
    }

    private function logStatus() {
        $logFile = $this->tempDir . '/monitor.log';
        $logEntry = date('Y-m-d H:i:s') . ' - ' . 
                    json_encode(['stats' => $this->stats, 'alerts' => $this->alerts]) . 
                    PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
}

// Run monitoring
$monitor = new SystemMonitor('/tmp/speed_test');
echo json_encode($monitor->monitor(), JSON_PRETTY_PRINT);