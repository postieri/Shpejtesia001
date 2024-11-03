class SpeedTest {
    constructor() {
        this.CHUNK_SIZES = [
            1 * 1024 * 1024,    // 1MB
            2 * 1024 * 1024,    // 2MB
            3 * 1024 * 1024     // 3MB
        ];
        this.CONCURRENT_REQUESTS = 1;
        this.TEST_DURATION = 8000;
        this.WARMUP_SIZE = 256 * 1024;
        
        this.status = document.querySelector('.status');
        this.progress = document.querySelector('.progress');
        this.speedDisplay = document.querySelector('.speed-display');
        this.buttons = document.querySelectorAll('button');
        this.downloadResult = document.getElementById('downloadResult');
        this.uploadResult = document.getElementById('uploadResult');
        this.latencyResult = document.getElementById('latencyResult');
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
        measurements.shift();
        measurements.pop();
        return measurements.reduce((a, b) => a + b, 0) / measurements.length;
    }

    downloadChunk(size) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            const startTime = performance.now();
            let totalBytes = 0;
            let speedSamples = [];
            let lastProgressTime = startTime;
            
            xhr.open('GET', `?action=download&size=${size}`, true);
            xhr.responseType = 'arraybuffer';
            
            xhr.onprogress = (event) => {
                const currentTime = performance.now();
                const timeDiff = (currentTime - lastProgressTime) / 1000;
                const byteDiff = event.loaded - totalBytes;
                
                if (timeDiff > 0) {
                    const speedMbps = (byteDiff * 8) / (timeDiff * 1024 * 1024);
                    if (speedMbps > 0 && speedMbps < 1000) {
                        speedSamples.push(speedMbps);
                        this.updateUI(null, 'Testing download speed...', speedMbps);
                    }
                }
                
                totalBytes = event.loaded;
                lastProgressTime = currentTime;
            };
            
            xhr.onload = () => {
                if (xhr.status === 200) {
                    const endTime = performance.now();
                    const duration = (endTime - startTime) / 1000;
                    
                    if (speedSamples.length > 0) {
                        speedSamples.sort((a, b) => a - b);
                        const cut = Math.floor(speedSamples.length * 0.2);
                        const validSamples = speedSamples.slice(cut, -cut);
                        
                        const avgSpeed = validSamples.length > 0 
                            ? validSamples.reduce((a, b) => a + b) / validSamples.length
                            : 0;
                            
                        resolve({
                            success: true,
                            speed: Math.min(avgSpeed, 1000),
                            samples: validSamples.length
                        });
                    } else {
                        const speed = Math.min((totalBytes * 8) / (duration * 1024 * 1024), 1000);
                        resolve({
                            success: true,
                            speed: speed,
                            samples: 0
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

                if (timeDiff > 0) {
                    const speedMbps = (byteDiff * 8) / (timeDiff * 1024 * 1024);
                    if (speedMbps > 0 && speedMbps < 1000) {
                        speedSamples.push(speedMbps);
                        this.updateUI(null, 'Testing upload speed...', speedMbps);
                    }
                }

                lastLoaded = event.loaded;
                lastTime = currentTime;
            };

            xhr.onload = () => {
                if (xhr.status === 200) {
                    const endTime = performance.now();
                    const duration = (endTime - startTime) / 1000;

                    if (speedSamples.length > 0) {
                        speedSamples.sort((a, b) => a - b);
                        const cut = Math.floor(speedSamples.length * 0.2);
                        const validSamples = speedSamples.slice(cut, -cut);
                        
                        const avgSpeed = validSamples.length > 0 
                            ? validSamples.reduce((a, b) => a + b) / validSamples.length
                            : 0;

                        resolve({
                            success: true,
                            speed: Math.min(avgSpeed, 1000),
                            samples: validSamples.length
                        });
                    } else {
                        const speed = Math.min((size * 8) / (duration * 1024 * 1024), 1000);
                        resolve({
                            success: true,
                            speed: speed,
                            samples: 0
                        });
                    }
                } else {
                    reject(new Error(`HTTP ${xhr.status}`));
                }
            };

            xhr.onerror = () => reject(new Error('Network error'));
            xhr.onabort = () => reject(new Error('Aborted'));
            xhr.ontimeout = () => reject(new Error('Timeout'));

            xhr.open('POST', window.location.href, true);
            xhr.setRequestHeader('Content-Type', 'application/octet-stream');
            xhr.setRequestHeader('Cache-Control', 'no-cache');
            xhr.send(blob);
        });
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
                    speeds.sort((a, b) => a - b);
                    const medianSpeed = speeds[Math.floor(speeds.length / 2)];
                    resolve(medianSpeed);
                } else {
                    resolve(0);
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
            let totalSpeed = 0;
            let validTests = 0;

            for (let i = 0; i < this.CHUNK_SIZES.length; i++) {
                const progress = (i / this.CHUNK_SIZES.length) * 100;
                this.updateUI(progress, `Testing ${type} speed...`);
                
                const speed = await this.performTransfer(type, this.CHUNK_SIZES[i]);
                if (speed > 0) {
                    totalSpeed += speed;
                    validTests++;
                }
            }

            const averageSpeed = validTests > 0 ? totalSpeed / validTests : 0;
            this.updateUI(100, `${type.charAt(0).toUpperCase() + type.slice(1)} test completed`, averageSpeed);
            
            if (type === 'download') {
                this.downloadResult.textContent = this.formatSpeed(averageSpeed);
            } else {
                this.uploadResult.textContent = this.formatSpeed(averageSpeed);
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