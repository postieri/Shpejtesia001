class SpeedTest {
    constructor() {
        this.CHUNK_SIZES = [
            2 * 1024 * 1024,    // 2MB
            4 * 1024 * 1024,    // 4MB
            8 * 1024 * 1024     // 8MB
        ];
        this.CONCURRENT_REQUESTS = 1;     // Single request for accurate testing
        this.TEST_DURATION = 8000;        // 8 seconds per test
        this.WARMUP_SIZE = 256 * 1024;    // 256KB warmup
        
        // Initialize UI elements
        this.status = document.querySelector('.status');
        this.progress = document.querySelector('.progress');
        this.speedDisplay = document.querySelector('.speed-display');
        this.buttons = document.querySelectorAll('button');
        this.downloadResult = document.getElementById('downloadResult');
        this.uploadResult = document.getElementById('uploadResult');
        this.latencyResult = document.getElementById('latencyResult');

        // Initialize test state
        this.abortController = null;
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
        // Remove highest and lowest
        measurements.shift();
        measurements.pop();
        return measurements.reduce((a, b) => a + b, 0) / measurements.length;
    }

    downloadChunk(size) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            const startTime = performance.now();
            
            xhr.open('GET', `?action=download&size=${size}`, true);
            xhr.responseType = 'arraybuffer';
            
            let lastLoadedTime = startTime;
            let lastLoaded = 0;
            let progressSpeeds = [];

            xhr.onprogress = (event) => {
                if (event.lengthComputable) {
                    const currentTime = performance.now();
                    const timeDiff = (currentTime - lastLoadedTime) / 1000; // seconds
                    const loadedDiff = event.loaded - lastLoaded;
                    
                    if (timeDiff > 0) {
                        const currentSpeed = (loadedDiff / timeDiff) / (1024 * 1024) * 8; // Mbps
                        progressSpeeds.push(currentSpeed);
                        this.updateUI(null, 'Testing download speed...', currentSpeed);
                    }
                    
                    lastLoadedTime = currentTime;
                    lastLoaded = event.loaded;
                }
            };
            
            xhr.onload = () => {
                if (xhr.status === 200) {
                    const endTime = performance.now();
                    const duration = (endTime - startTime) / 1000; // seconds
                    const loaded = xhr.response.byteLength;
                    
                    // Calculate average speed from progress updates
                    let speed;
                    if (progressSpeeds.length > 0) {
                        // Remove outliers (top and bottom 10%)
                        progressSpeeds.sort((a, b) => a - b);
                        const cut = Math.floor(progressSpeeds.length * 0.1);
                        const filteredSpeeds = progressSpeeds.slice(cut, -cut);
                        speed = filteredSpeeds.length > 0 
                            ? filteredSpeeds.reduce((a, b) => a + b) / filteredSpeeds.length
                            : (loaded * 8) / duration / (1024 * 1024); // Fallback calculation
                    } else {
                        speed = (loaded * 8) / duration / (1024 * 1024);
                    }
                    
                    resolve({ loaded, duration, speed });
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
            
            let lastLoadedTime = startTime;
            let lastLoaded = 0;
            let progressSpeeds = [];

            xhr.upload.onprogress = (event) => {
                if (event.lengthComputable) {
                    const currentTime = performance.now();
                    const timeDiff = (currentTime - lastLoadedTime) / 1000;
                    const loadedDiff = event.loaded - lastLoaded;
                    
                    if (timeDiff > 0) {
                        const currentSpeed = (loadedDiff / timeDiff) / (1024 * 1024) * 8; // Mbps
                        progressSpeeds.push(currentSpeed);
                        this.updateUI(null, 'Testing upload speed...', currentSpeed);
                    }
                    
                    lastLoadedTime = currentTime;
                    lastLoaded = event.loaded;
                }
            };
            
            xhr.onload = () => {
                if (xhr.status === 200) {
                    const endTime = performance.now();
                    const duration = (endTime - startTime) / 1000;
                    
                    let speed;
                    if (progressSpeeds.length > 0) {
                        progressSpeeds.sort((a, b) => a - b);
                        const cut = Math.floor(progressSpeeds.length * 0.1);
                        const filteredSpeeds = progressSpeeds.slice(cut, -cut);
                        speed = filteredSpeeds.length > 0 
                            ? filteredSpeeds.reduce((a, b) => a + b) / filteredSpeeds.length
                            : (size * 8) / duration / (1024 * 1024);
                    } else {
                        speed = (size * 8) / duration / (1024 * 1024);
                    }
                    
                    resolve({ loaded: size, duration, speed });
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
            const startTime = performance.now();
            const speeds = [];
            let completed = false;

            const checkCompletion = () => {
                if (completed) return;
                const endTime = performance.now();
                const duration = (endTime - startTime) / 1000;
                
                if (duration >= this.TEST_DURATION / 1000) {
                    completed = true;
                    if (speeds.length > 0) {
                        speeds.sort((a, b) => a - b);
                        const cut = Math.floor(speeds.length * 0.1);
                        const filteredSpeeds = speeds.slice(cut, -cut);
                        const averageSpeed = filteredSpeeds.length > 0 
                            ? filteredSpeeds.reduce((a, b) => a + b) / filteredSpeeds.length
                            : 0;
                        resolve(averageSpeed);
                    } else {
                        resolve(0);
                    }
                }
            };

            const runTest = async () => {
                if (completed) return;

                try {
                    const result = await (type === 'download' ? 
                        this.downloadChunk(chunkSize) : 
                        this.uploadChunk(chunkSize));
                    
                    if (!completed) {
                        speeds.push(result.speed);
                        runTest(); // Start next test immediately
                    }
                } catch (error) {
                    console.error(`${type} error:`, error);
                    if (!completed) {
                        runTest(); // Retry on error
                    }
                }

                checkCompletion();
            };

            // Start the test
            runTest();

            // Ensure test completion
            setTimeout(() => {
                completed = true;
                if (speeds.length > 0) {
                    speeds.sort((a, b) => a - b);
                    const cut = Math.floor(speeds.length * 0.1);
                    const filteredSpeeds = speeds.slice(cut, -cut);
                    const averageSpeed = filteredSpeeds.length > 0 
                        ? filteredSpeeds.reduce((a, b) => a + b) / filteredSpeeds.length
                        : 0;
                    resolve(averageSpeed);
                } else {
                    resolve(0);
                }
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

// Initialize SpeedTest and expose test methods
const speedTest = new SpeedTest();
window.startDownloadTest = () => speedTest.startTest('download');
window.startUploadTest = () => speedTest.startTest('upload');