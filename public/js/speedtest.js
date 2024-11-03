class SpeedTest {
    constructor() {
        this.CHUNK_SIZES = [
            1024 * 1024,     // 1MB
            5 * 1024 * 1024, // 5MB
            10 * 1024 * 1024 // 10MB
        ];
        this.CONCURRENT_REQUESTS = 3;
        this.TEST_DURATION = 10000; // 10 seconds per test
        this.WARMUP_SIZE = 1024 * 256; // 256KB warmup
        
        // Initialize UI elements
        this.status = document.querySelector('.status');
        this.progress = document.querySelector('.progress');
        this.speedDisplay = document.querySelector('.speed-display');
        this.buttons = document.querySelectorAll('button');
        this.downloadResult = document.getElementById('downloadResult');
        this.uploadResult = document.getElementById('uploadResult');
        this.latencyResult = document.getElementById('latencyResult');
    }

    generateChunk(size) {
        const buffer = new ArrayBuffer(size);
        const view = new Uint8Array(buffer);
        for (let i = 0; i < view.length; i++) {
            view[i] = Math.random() * 256;
        }
        return new Blob([buffer]);
    }

    formatSpeed(speed, useBits = true) {
        const bits = useBits ? speed * 8 : speed;
        if (bits >= 1000) {
            return `${(bits / 1000).toFixed(2)} Gbps`;
        }
        return `${bits.toFixed(2)} Mbps`;
    }

    updateUI(progress, status, speed = null) {
        this.progress.style.width = `${progress}%`;
        this.status.textContent = status;
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
                        'Cache-Control': 'no-cache, no-store, must-revalidate',
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
        measurements.shift(); // Remove lowest
        measurements.pop();   // Remove highest
        return measurements.reduce((a, b) => a + b, 0) / measurements.length;
    }

    async performTransfer(type, chunkSize) {
        return new Promise(async (resolve) => {
            const startTime = performance.now();
            let totalBytes = 0;
            let activeTransfers = 0;
            let completed = false;

            const checkCompletion = () => {
                if (completed) return;
                const endTime = performance.now();
                const duration = (endTime - startTime) / 1000;
                if (duration >= this.TEST_DURATION / 1000) {
                    completed = true;
                    const speedMbps = (totalBytes / duration) / (1024 * 1024);
                    resolve(speedMbps);
                }
            };

            const startNewTransfer = async () => {
                if (completed) return;
                activeTransfers++;

                try {
                    if (type === 'download') {
                        await this.downloadChunk(chunkSize);
                    } else {
                        await this.uploadChunk(chunkSize);
                    }
                    totalBytes += chunkSize;
                } catch (error) {
                    console.error(`${type} error:`, error);
                }

                activeTransfers--;
                checkCompletion();
                if (!completed) startNewTransfer();
            };

            // Start concurrent transfers
            for (let i = 0; i < this.CONCURRENT_REQUESTS; i++) {
                startNewTransfer();
            }

            // Ensure test completion
            setTimeout(() => {
                if (!completed) {
                    completed = true;
                    const duration = this.TEST_DURATION / 1000;
                    const speedMbps = (totalBytes / duration) / (1024 * 1024);
                    resolve(speedMbps);
                }
            }, this.TEST_DURATION);
        });
    }

    async downloadChunk(size) {
        const blob = this.generateChunk(size);
        const url = URL.createObjectURL(blob);
        try {
            await fetch(url, {
                cache: 'no-store',
                headers: {
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache'
                }
            }).then(response => response.blob());
        } finally {
            URL.revokeObjectURL(url);
        }
    }

    async uploadChunk(size) {
        const blob = this.generateChunk(size);
        await fetch(window.location.href, {
            method: 'POST',
            body: blob,
            headers: {
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache'
            }
        });
    }

    async warmup() {
        this.updateUI(0, 'Warming up connection...');
        try {
            await this.downloadChunk(this.WARMUP_SIZE);
            await this.uploadChunk(this.WARMUP_SIZE);
        } catch (error) {
            console.error('Warmup error:', error);
        }
    }

    async startTest(type) {
        this.buttons.forEach(button => button.disabled = true);
        await this.warmup();

        let totalSpeed = 0;
        let validTests = 0;

        try {
            for (let i = 0; i < this.CHUNK_SIZES.length; i++) {
                const progress = (i / this.CHUNK_SIZES.length) * 100;
                this.updateUI(progress, `Testing ${type} speed...`);
                
                const speed = await this.performTransfer(type, this.CHUNK_SIZES[i]);
                if (speed > 0) {
                    totalSpeed += speed;
                    validTests++;
                    this.updateUI(progress, `Testing ${type} speed...`, speed);
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
        }

        this.buttons.forEach(button => button.disabled = false);
    }
}

// Initialize SpeedTest and expose test methods
const speedTest = new SpeedTest();
window.startDownloadTest = () => speedTest.startTest('download');
window.startUploadTest = () => speedTest.startTest('upload');