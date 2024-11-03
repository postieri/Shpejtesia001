class SpeedTest {
    constructor() {
        // Base settings
        this.INITIAL_CHUNK_SIZE = 256 * 1024;  // 256KB for initial speed detection
        this.TEST_DURATION = 8000;             // 8 seconds per test
        this.WARMUP_SIZE = 128 * 1024;         // 128KB warmup
        this.MIN_SAMPLES = 8;                  // Minimum samples needed
        this.TEST_RETRIES = 2;                 // Number of retries if test fails

        // UI elements
        this.status = document.querySelector('.status');
        this.progress = document.querySelector('.progress');
        this.speedDisplay = document.querySelector('.speed-display');
        this.buttons = document.querySelectorAll('button');
        this.downloadResult = document.getElementById('downloadResult');
        this.uploadResult = document.getElementById('uploadResult');
        this.latencyResult = document.getElementById('latencyResult');
    }

    getAdaptiveChunkSize(measuredSpeedMbps) {
        if (measuredSpeedMbps < 10) {          // Less than 10 Mbps
            return 512 * 1024;                  // 512KB chunks
        } else if (measuredSpeedMbps < 50) {   // Less than 50 Mbps
            return 1 * 1024 * 1024;            // 1MB chunks
        } else if (measuredSpeedMbps < 100) {  // Less than 100 Mbps
            return 2 * 1024 * 1024;            // 2MB chunks
        } else if (measuredSpeedMbps < 500) {  // Less than 500 Mbps
            return 4 * 1024 * 1024;            // 4MB chunks
        } else {                               // 500+ Mbps
            return 8 * 1024 * 1024;            // 8MB chunks
        }
    }

    formatSpeed(speed) {
        if (speed >= 1000) {
            return `${(speed / 1000).toFixed(2)} Gbps`;
        }
        return `${speed.toFixed(2)} Mbps`;
    }

    updateUI(progress, status, speed = null) {
        if (progress !== null) {
            this.progress.style.width = `${progress}%`;
        }
        if (status) {
            this.status.textContent = status;
        }
        if (speed !== null) {
            this.speedDisplay.innerHTML = `${this.formatSpeed(speed)}<span class="unit">Mbps</span>`;
        }
    }

    async measureLatency() {
        const measurements = [];
        for (let i = 0; i < 5; i++) {
            const start = performance.now();
            try {
                await fetch('?action=ping', {
                    cache: 'no-store',
                    headers: {
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache'
                    }
                });
                measurements.push(performance.now() - start);
                await new Promise(resolve => setTimeout(resolve, 100));
            } catch (error) {
                console.error('Latency measurement error:', error);
            }
        }
        measurements.sort((a, b) => a - b);
        // Remove highest and lowest values
        measurements.shift();
        measurements.pop();
        return measurements.reduce((a, b) => a + b, 0) / measurements.length;
    }

    calculateSpeed(bytes, duration) {
        const bits = bytes * 8;
        const megabits = bits / (1024 * 1024);
        const seconds = duration / 1000;
        return megabits / seconds;
    }

    downloadChunk(size) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            const startTime = performance.now();
            let totalBytes = 0;
            let speedSamples = [];
            let lastProgressTime = startTime;
            
            xhr.open('GET', `?action=download&size=${size}&_=${Date.now()}`, true);
            xhr.responseType = 'arraybuffer';
            
            xhr.onprogress = (event) => {
                const currentTime = performance.now();
                const timeDiff = (currentTime - lastProgressTime) / 1000;
                const byteDiff = event.loaded - totalBytes;
                
                if (timeDiff > 0.1) { // Sample every 100ms
                    const speed = this.calculateSpeed(byteDiff, timeDiff * 1000);
                    if (speed > 0) {
                        speedSamples.push(speed);
                        this.updateUI(null, 'Testing download speed...', speed);
                    }
                    
                    totalBytes = event.loaded;
                    lastProgressTime = currentTime;
                }
            };
            
            xhr.onload = () => {
                if (xhr.status === 200) {
                    const endTime = performance.now();
                    const duration = endTime - startTime;
                    
                    if (speedSamples.length >= this.MIN_SAMPLES) {
                        // Remove statistical outliers
                        speedSamples.sort((a, b) => a - b);
                        const q1 = speedSamples[Math.floor(speedSamples.length * 0.25)];
                        const q3 = speedSamples[Math.floor(speedSamples.length * 0.75)];
                        const iqr = q3 - q1;
                        const validSamples = speedSamples.filter(
                            speed => speed >= (q1 - 1.5 * iqr) && speed <= (q3 + 1.5 * iqr)
                        );
                        
                        const avgSpeed = validSamples.length > 0 
                            ? validSamples.reduce((a, b) => a + b) / validSamples.length
                            : this.calculateSpeed(totalBytes, duration);
                        
                        resolve({
                            success: true,
                            speed: avgSpeed,
                            samples: validSamples.length
                        });
                    } else {
                        const speed = this.calculateSpeed(totalBytes, duration);
                        resolve({
                            success: true,
                            speed: speed,
                            samples: speedSamples.length
                        });
                    }
                } else {
                    reject(new Error(`HTTP ${xhr.status}`));
                }
            };
            
            xhr.onerror = () => reject(new Error('Network error'));
            xhr.onabort = () => reject(new Error('Aborted'));
            xhr.ontimeout = () => reject(new Error('Timeout'));
            
            xhr.send();
        });
    }

    uploadChunk(size) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            const startTime = performance.now();
            const blob = new Blob([new ArrayBuffer(size)]);
            let speedSamples = [];
            let lastLoaded = 0;
            let lastTime = startTime;

            xhr.upload.onprogress = (event) => {
                const currentTime = performance.now();
                const timeDiff = (currentTime - lastTime) / 1000;
                const byteDiff = event.loaded - lastLoaded;

                if (timeDiff > 0.1) { // Sample every 100ms
                    const speed = this.calculateSpeed(byteDiff, timeDiff * 1000);
                    if (speed > 0) {
                        speedSamples.push(speed);
                        this.updateUI(null, 'Testing upload speed...', speed);
                    }

                    lastLoaded = event.loaded;
                    lastTime = currentTime;
                }
            };

            xhr.onload = () => {
                if (xhr.status === 200) {
                    if (speedSamples.length >= this.MIN_SAMPLES) {
                        speedSamples.sort((a, b) => a - b);
                        const q1 = speedSamples[Math.floor(speedSamples.length * 0.25)];
                        const q3 = speedSamples[Math.floor(speedSamples.length * 0.75)];
                        const iqr = q3 - q1;
                        const validSamples = speedSamples.filter(
                            speed => speed >= (q1 - 1.5 * iqr) && speed <= (q3 + 1.5 * iqr)
                        );
                        
                        const avgSpeed = validSamples.length > 0 
                            ? validSamples.reduce((a, b) => a + b) / validSamples.length
                            : this.calculateSpeed(size, performance.now() - startTime);

                        resolve({
                            success: true,
                            speed: avgSpeed,
                            samples: validSamples.length
                        });
                    } else {
                        const speed = this.calculateSpeed(size, performance.now() - startTime);
                        resolve({
                            success: true,
                            speed: speed,
                            samples: speedSamples.length
                        });
                    }
                } else {
                    reject(new Error(`HTTP ${xhr.status}`));
                }
            };

            xhr.onerror = () => reject(new Error('Network error'));
            xhr.onabort = () => reject(new Error('Aborted'));
            xhr.ontimeout = () => reject(new Error('Timeout'));

            xhr.open('POST', `?action=upload&_=${Date.now()}`, true);
            xhr.setRequestHeader('Content-Type', 'application/octet-stream');
            xhr.setRequestHeader('Cache-Control', 'no-cache');
            xhr.send(blob);
        });
    }

    async performInitialSpeedTest(type) {
        try {
            const result = await this.performTransfer(type, this.INITIAL_CHUNK_SIZE);
            return result.speed;
        } catch (error) {
            console.error('Initial speed test error:', error);
            return 10; // Default to 10 Mbps on error
        }
    }

    async performTransfer(type, chunkSize) {
        return new Promise((resolve) => {
            const speeds = [];
            let testCount = 0;
            const maxTests = 3;
            let completed = false;

            const runTest = async () => {
                if (completed || testCount >= maxTests) return;
                testCount++;

                try {
                    const result = await (type === 'download' ? 
                        this.downloadChunk(chunkSize) : 
                        this.uploadChunk(chunkSize));

                    if (!completed && result.success) {
                        speeds.push(result.speed);
                    }
                } catch (error) {
                    console.error(`${type} error:`, error);
                }

                if (!completed && testCount < maxTests) {
                    setTimeout(runTest, 1000);
                } else {
                    finishTest();
                }
            };

            const finishTest = () => {
                if (speeds.length > 0) {
                    // Use median speed
                    speeds.sort((a, b) => a - b);
                    const medianSpeed = speeds[Math.floor(speeds.length / 2)];
                    resolve({
                        success: true,
                        speed: medianSpeed
                    });
                } else {
                    resolve({
                        success: false,
                        speed: 0
                    });
                }
            };

            runTest();

            setTimeout(() => {
                completed = true;
                finishTest();
            }, this.TEST_DURATION);
        });
    }

    async warmup() {
        this.updateUI(0, 'Warming up connection...');
        try {
            await Promise.all([
                this.downloadChunk(this.WARMUP_SIZE),
                this.uploadChunk(this.WARMUP_SIZE)
            ]);
        } catch (error) {
            console.error('Warmup error:', error);
        }
    }

    async startTest(type) {
        this.buttons.forEach(button => button.disabled = true);
        this.updateUI(0, 'Initializing test...');
        
        try {
            await this.warmup();
            const initialSpeed = await this.performInitialSpeedTest(type);
            const chunkSize = this.getAdaptiveChunkSize(initialSpeed);
            
            let totalSpeed = 0;
            let validTests = 0;
            const numberOfTests = 3;

            for (let i = 0; i < numberOfTests; i++) {
                const progress = ((i + 1) / numberOfTests) * 100;
                this.updateUI(progress, `Testing ${type} speed...`);
                
                const result = await this.performTransfer(type, chunkSize);
                if (result.success) {
                    totalSpeed += result.speed;
                    validTests++;
                }
                
                await new Promise(resolve => setTimeout(resolve, 1000));
            }

            const finalSpeed = validTests > 0 ? totalSpeed / validTests : 0;
            
            this.updateUI(100, `${type.charAt(0).toUpperCase() + type.slice(1)} test completed`, finalSpeed);
            
            if (type === 'download') {
                this.downloadResult.textContent = this.formatSpeed(finalSpeed);
            } else {
                this.uploadResult.textContent = this.formatSpeed(finalSpeed);
                const latency = await this.measureLatency();
                this.latencyResult.textContent = `${latency.toFixed(0)} ms`;
            }
        } catch (error) {
            console.error(`${type} test error:`, error);
            this.updateUI(0, `Error during ${type} test`);
        } finally {
            this.buttons.forEach(button => button.disabled = false);
        }
    }
}

const speedTest = new SpeedTest();
window.startDownloadTest = () => speedTest.startTest('download');
window.startUploadTest = () => speedTest.startTest('upload');