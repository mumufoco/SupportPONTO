class CameraManager {
    static instance = null;
    stream = null;
    videoElement = null;
    isActive = false;

    static getInstance() {
        if (!CameraManager.instance) {
            CameraManager.instance = new CameraManager();
        }
        return CameraManager.instance;
    }

    async checkSupport() {
        if (!navigator.mediaDevices?.getUserMedia) {
            throw new Error('Camera not supported');
        }
        if (!location.protocol.startsWith('https') && location.hostname !== 'localhost') {
            throw new Error('HTTPS required for camera access');
        }
    }

    async checkPermission() {
        try {
            const result = await navigator.permissions.query({ name: 'camera' });
            return result.state;
        } catch {
            return 'unknown';
        }
    }

    async start(videoElement, constraints = {}) {
        if (this.isActive) {
            throw new Error('Camera already in use by another module');
        }

        await this.checkSupport();
        const permission = await this.checkPermission();
        if (permission === 'denied') {
            throw new Error('Camera permission denied');
        }

        const defaultConstraints = {
            video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' },
            audio: false
        };
        const mergedConstraints = { ...defaultConstraints, ...constraints };

        this.stream = await navigator.mediaDevices.getUserMedia(mergedConstraints);
        this.videoElement = videoElement;
        this.videoElement.srcObject = this.stream;
        this.isActive = true;

        // Stabilize focus
        await new Promise(resolve => setTimeout(resolve, 1000));

        return this.stream;
    }

    stop() {
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
            if (this.videoElement) {
                this.videoElement.srcObject = null;
            }
        }
        this.isActive = false;
    }

    captureFrame(canvas, quality = 0.8) {
        if (!this.isActive || !canvas) return null;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(this.videoElement, 0, 0, canvas.width, canvas.height);
        return canvas.toDataURL('image/jpeg', quality);
    }
}

window.CameraManager = CameraManager;
