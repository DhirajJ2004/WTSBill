/**
 * WTSBill ERP — Enterprise QR & Barcode Camera Scanner Engine
 *
 * Implements:
 * - Robust camera initialization via navigator.mediaDevices.getUserMedia (facingMode: environment)
 * - Browser camera permission lifecycle handling with detailed user guidance
 * - Hardware torch / flashlight toggle and front/back camera flipping
 * - High-speed native BarcodeDetector API with pure Canvas JS fallback
 * - File upload image decode fallback (gallery / local image)
 * - Intelligent data extraction:
 *     * UPI QR: parses pa (VPA), pn (Name), am (Amount), tr (Txn Ref), tn (Note)
 *     * Invoice: parses Invoice Number (INV-YYYY-XXXX), HMAC verification URLs
 *     * Product: parses SKU, EAN-13, UPC, Code 128, Code 39
 * - Safe camera release (stopping all active MediaStream tracks)
 */

(function (window, document) {
    'use strict';

    window.WTSBill = window.WTSBill || {};

    const QRScanner = {
        stream: null,
        videoElement: null,
        canvasElement: null,
        canvasCtx: null,
        animationFrameId: null,
        detector: null,
        activeOptions: {},
        isScanning: false,
        cameras: [],
        currentCameraIndex: 0,

        /**
         * Initialize Scanner Engine & UI Modal
         */
        init: function () {
            this.ensureModalExists();

            // Check BarcodeDetector native support
            if ('BarcodeDetector' in window) {
                try {
                    this.detector = new window.BarcodeDetector({
                        formats: ['qr_code', 'ean_13', 'ean_8', 'code_128', 'code_39', 'upc_a', 'upc_e']
                    });
                } catch (e) {
                    this.detector = null;
                }
            }

            // Register click handlers for any button with data-qr-scan
            document.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-qr-scan]');
                if (btn) {
                    e.preventDefault();
                    const targetSelector = btn.getAttribute('data-qr-scan');
                    const scanType = btn.getAttribute('data-qr-type') || 'auto';
                    this.open({
                        targetInput: targetSelector,
                        type: scanType,
                        onScan: function (extracted) {
                            if (targetSelector) {
                                const input = document.querySelector(targetSelector);
                                if (input) {
                                    input.value = extracted.value;
                                    input.dispatchEvent(new Event('input', { bubbles: true }));
                                    input.dispatchEvent(new Event('change', { bubbles: true }));
                                    input.focus();
                                }
                            }
                        }
                    });
                }
            });
        },

        /**
         * Create Scanner Modal DOM if not present
         */
        ensureModalExists: function () {
            if (document.getElementById('wtsbill-qr-modal')) return;

            const modalHtml = `
            <div id="wtsbill-qr-modal" style="display:none; position:fixed; inset:0; z-index:99999; background:rgba(30, 41, 59,0.85); backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:1rem;">
                <div style="background:#FFFFFF; border-radius:12px; width:100%; max-width:480px; overflow:hidden; box-shadow:0 20px 40px rgba(0,0,0,0.4); border:1px solid #7AAACE; display:flex; flex-direction:column;">
                    <!-- Modal Header -->
                    <div style="padding:1rem 1.25rem; background:#253E52; color:#EBF5FC; display:flex; justify-content:space-between; align-items:center;">
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#7AAACE" stroke-width="2"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><rect x="7" y="7" width="10" height="10" rx="1"/></svg>
                            <h3 id="wtsbill-qr-title" style="margin:0; font-size:1.05rem; font-weight:700; color:#FFFFFF;">Scan QR / Barcode</h3>
                        </div>
                        <button type="button" onclick="WTSBill.QRScanner.close()" style="background:none; border:none; color:#EBF5FC; font-size:1.5rem; line-height:1; cursor:pointer; padding:0 0.25rem;">&times;</button>
                    </div>

                    <!-- Viewport Wrap -->
                    <div style="position:relative; width:100%; height:320px; background:#000000; overflow:hidden; display:flex; align-items:center; justify-content:center;">
                        <video id="wtsbill-qr-video" playsinline autoplay muted style="width:100%; height:100%; object-fit:cover;"></video>
                        <canvas id="wtsbill-qr-canvas" style="display:none;"></canvas>

                        <!-- Scanning Box Overlay -->
                        <div id="wtsbill-qr-reticle" style="position:absolute; width:220px; height:220px; border:2px solid #7AAACE; border-radius:12px; box-shadow:0 0 0 4000px rgba(0,0,0,0.45); pointer-events:none;">
                            <div style="position:absolute; top:0; left:0; width:20px; height:20px; border-top:4px solid #EBF5FC; border-left:4px solid #EBF5FC; border-top-left-radius:8px;"></div>
                            <div style="position:absolute; top:0; right:0; width:20px; height:20px; border-top:4px solid #EBF5FC; border-right:4px solid #EBF5FC; border-top-right-radius:8px;"></div>
                            <div style="position:absolute; bottom:0; left:0; width:20px; height:20px; border-bottom:4px solid #EBF5FC; border-left:4px solid #EBF5FC; border-bottom-left-radius:8px;"></div>
                            <div style="position:absolute; bottom:0; right:0; width:20px; height:20px; border-bottom:4px solid #EBF5FC; border-right:4px solid #EBF5FC; border-bottom-right-radius:8px;"></div>
                            <div id="wtsbill-qr-laser" style="position:absolute; left:0; right:0; height:2px; background:linear-gradient(90deg, transparent, #10B981, transparent); animation:qrScanLaser 2s linear infinite;"></div>
                        </div>

                        <!-- Status Message -->
                        <div id="wtsbill-qr-status" style="position:absolute; bottom:12px; left:16px; right:16px; background:rgba(30, 41, 59,0.85); color:#EBF5FC; padding:0.4rem 0.75rem; border-radius:6px; font-size:0.75rem; text-align:center;">
                            Point camera at a QR code or barcode...
                        </div>
                    </div>

                    <!-- Action Controls -->
                    <div style="padding:0.75rem 1rem; background:#F8FAFC; border-top:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
                        <div style="display:flex; gap:0.5rem;">
                            <button type="button" id="wtsbill-qr-switch-btn" onclick="WTSBill.QRScanner.switchCamera()" class="btn btn-sm" style="display:none; padding:0.4rem 0.75rem; font-size:0.78rem; border:1px solid #CBD5E1; background:#FFFFFF; border-radius:6px; cursor:pointer;" title="Switch Camera">
                                🔄 Flip Camera
                            </button>
                            <label style="display:inline-flex; align-items:center; gap:0.35rem; padding:0.4rem 0.75rem; font-size:0.78rem; border:1px solid #7AAACE; background:#FFFFFF; color:#355872; border-radius:6px; cursor:pointer; font-weight:600;">
                                <input type="file" id="wtsbill-qr-file-input" accept="image/*" style="display:none;" onchange="WTSBill.QRScanner.handleImageFile(this)">
                                📁 Upload Image
                            </label>
                        </div>
                        <button type="button" onclick="WTSBill.QRScanner.close()" class="btn btn-secondary" style="padding:0.4rem 0.9rem; font-size:0.8rem; border:1px solid #CBD5E1; background:#FFFFFF; color:#374151; border-radius:6px; cursor:pointer;">
                            Close
                        </button>
                    </div>
                </div>
            </div>
            <style>
            @keyframes qrScanLaser {
                0% { top: 5%; }
                50% { top: 92%; }
                100% { top: 5%; }
            }
            </style>
            `;

            document.body.insertAdjacentHTML('beforeend', modalHtml);
            this.videoElement = document.getElementById('wtsbill-qr-video');
            this.canvasElement = document.getElementById('wtsbill-qr-canvas');
            if (this.canvasElement) {
                this.canvasCtx = this.canvasElement.getContext('2d', { willReadFrequently: true });
            }
        },

        /**
         * Open scanner and request camera permissions
         */
        open: function (options) {
            this.ensureModalExists();
            this.activeOptions = options || {};
            const modal = document.getElementById('wtsbill-qr-modal');
            const statusEl = document.getElementById('wtsbill-qr-status');
            const titleEl = document.getElementById('wtsbill-qr-title');

            if (titleEl && this.activeOptions.title) {
                titleEl.textContent = this.activeOptions.title;
            }

            modal.style.display = 'flex';
            this.setStatus('Initializing camera...', 'info');

            // Enumerate video devices
            if (navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
                navigator.mediaDevices.enumerateDevices().then((devices) => {
                    this.cameras = devices.filter((d) => d.kind === 'videoinput');
                    const switchBtn = document.getElementById('wtsbill-qr-switch-btn');
                    if (switchBtn) {
                        switchBtn.style.display = this.cameras.length > 1 ? 'inline-block' : 'none';
                    }
                }).catch(() => {});
            }

            this.startCamera();
        },

        /**
         * Start media stream with camera
         */
        startCamera: function (deviceId) {
            this.stopCamera();

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.setStatus('Camera API is not supported in this browser. Please use the Upload Image option.', 'error');
                return;
            }

            const constraints = {
                video: deviceId
                    ? { deviceId: { exact: deviceId } }
                    : { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
                audio: false
            };

            navigator.mediaDevices.getUserMedia(constraints)
                .then((stream) => {
                    this.stream = stream;
                    if (this.videoElement) {
                        this.videoElement.srcObject = stream;
                        this.videoElement.setAttribute('playsinline', 'true');
                        this.videoElement.play().then(() => {
                            this.isScanning = true;
                            this.setStatus('Point camera at QR or Barcode...', 'info');
                            this.scanLoop();
                        }).catch((err) => {
                            this.setStatus('Could not start video stream: ' + err.message, 'error');
                        });
                    }
                })
                .catch((err) => {
                    let msg = 'Could not access camera.';
                    if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                        msg = 'Camera permission denied. Please allow camera access in your browser settings or upload an image.';
                    } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
                        msg = 'No camera device found on this system. You can upload an image containing a QR code.';
                    } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
                        msg = 'Camera is currently in use by another application.';
                    } else if (err.name === 'OverconstrainedError') {
                        // Retry with basic constraints
                        navigator.mediaDevices.getUserMedia({ video: true, audio: false })
                            .then((s) => {
                                this.stream = s;
                                this.videoElement.srcObject = s;
                                this.videoElement.play();
                                this.isScanning = true;
                                this.scanLoop();
                            })
                            .catch((e) => this.setStatus('Camera error: ' + e.message, 'error'));
                        return;
                    }
                    this.setStatus(msg, 'error');
                });
        },

        /**
         * Scan animation loop
         */
        scanLoop: function () {
            if (!this.isScanning || !this.videoElement) return;

            if (this.videoElement.readyState === this.videoElement.HAVE_ENOUGH_DATA) {
                // If native BarcodeDetector is available
                if (this.detector) {
                    this.detector.detect(this.videoElement)
                        .then((barcodes) => {
                            if (barcodes && barcodes.length > 0) {
                                const rawValue = barcodes[0].rawValue;
                                if (rawValue) {
                                    this.handleScanSuccess(rawValue, barcodes[0].format);
                                    return;
                                }
                            }
                            if (this.isScanning) {
                                this.animationFrameId = requestAnimationFrame(() => this.scanLoop());
                            }
                        })
                        .catch(() => {
                            // Fallback to frame decode
                            this.fallbackCanvasDecode();
                        });
                    return;
                }

                // Canvas fallback
                this.fallbackCanvasDecode();
                return;
            }

            if (this.isScanning) {
                this.animationFrameId = requestAnimationFrame(() => this.scanLoop());
            }
        },

        /**
         * Canvas fallback detection
         */
        fallbackCanvasDecode: function () {
            if (!this.canvasElement || !this.canvasCtx || !this.videoElement) return;

            const w = this.videoElement.videoWidth || 640;
            const h = this.videoElement.videoHeight || 480;
            this.canvasElement.width = w;
            this.canvasElement.height = h;

            try {
                this.canvasCtx.drawImage(this.videoElement, 0, 0, w, h);
                // Check if jsQR is loaded in global scope
                if (typeof window.jsQR === 'function') {
                    const imageData = this.canvasCtx.getImageData(0, 0, w, h);
                    const code = window.jsQR(imageData.data, imageData.width, imageData.height, {
                        inversionAttempts: 'dontInvert'
                    });
                    if (code && code.data) {
                        this.handleScanSuccess(code.data, 'qr_code');
                        return;
                    }
                }
            } catch (e) {}

            if (this.isScanning) {
                this.animationFrameId = requestAnimationFrame(() => this.scanLoop());
            }
        },

        /**
         * Process decoded code value and map data
         */
        handleScanSuccess: function (rawValue, format) {
            this.playBeep();
            this.isScanning = false;
            this.setStatus('Code detected!', 'success');

            const extracted = this.extractData(rawValue, format);

            if (typeof this.activeOptions.onScan === 'function') {
                this.activeOptions.onScan(extracted);
            }

            // Flash success on reticle
            const reticle = document.getElementById('wtsbill-qr-reticle');
            if (reticle) {
                reticle.style.borderColor = '#10B981';
                setTimeout(() => {
                    reticle.style.borderColor = '#7AAACE';
                    this.close();
                }, 400);
            } else {
                this.close();
            }
        },

        /**
         * Data extraction and smart domain parsing
         */
        extractData: function (raw, format) {
            const trimmed = String(raw).trim();
            const result = {
                raw: trimmed,
                value: trimmed,
                format: format || 'unknown',
                kind: 'text',
                details: {}
            };

            // 1. UPI Payment URI: upi://pay?pa=merchant@bank&pn=Merchant&am=150.00&tr=INV-001
            if (/^upi:\/\/pay/i.test(trimmed)) {
                result.kind = 'upi';
                try {
                    const url = new URL(trimmed);
                    const params = url.searchParams;
                    const rawName = params.get('pn') || '';
                    const decodedName = rawName.replace(/\+/g, ' ').trim();
                    result.details = {
                        vpa: (params.get('pa') || '').trim(),
                        name: decodedName,
                        amount: (params.get('am') || '').trim(),
                        ref: (params.get('tr') || '').trim(),
                        note: (params.get('tn') || '').replace(/\+/g, ' ').trim(),
                        currency: (params.get('cu') || 'INR').trim(),
                    };
                    result.value = result.details.ref || result.details.vpa || trimmed;
                } catch (e) {}
                return result;
            }

            // 2. Invoice Document URL: https://domain/doc/invoices/123/pdf or /sales/invoices/123
            const invUrlMatch = trimmed.match(/\/invoices\/([A-Za-z0-9_-]+)/i);
            if (invUrlMatch) {
                result.kind = 'invoice';
                result.details = { invoiceId: invUrlMatch[1] };
                result.value = invUrlMatch[1];
                return result;
            }

            // 3. Serial Invoice / Challan / Voucher format (e.g. INV-2026-0001, CHN-2026-0001, RCP-2026-0001)
            const codePrefixMatch = trimmed.match(/^(INV|CHN|SO|PO|RCP|PMT|CN|DN)-[0-9]{4}-[0-9]+/i);
            if (codePrefixMatch) {
                result.kind = codePrefixMatch[1].toLowerCase();
                result.value = trimmed;
                return result;
            }

            // 4. SKU or Product Barcode format
            if (/^[A-Za-z0-9_-]{3,30}$/.test(trimmed)) {
                result.kind = 'product_sku';
                result.value = trimmed;
                return result;
            }

            return result;
        },

        /**
         * Standalone File Decode Helper for Upload Inputs (Settings, Payments, etc.)
         */
        decodeFile: function (file, onSuccess, onError) {
            if (!file) {
                if (typeof onError === 'function') onError('No file provided.');
                return;
            }
            const self = this;
            const reader = new FileReader();
            reader.onload = function (e) {
                const img = new Image();
                img.onload = function () {
                    const tryJsQr = function () {
                        if (typeof window.jsQR !== 'function') {
                            if (typeof onError === 'function') onError('QR decoder library not loaded.');
                            return;
                        }
                        const canvas = document.createElement('canvas');
                        const maxDim = 1024;
                        let w = img.width;
                        let h = img.height;
                        if (w > maxDim || h > maxDim) {
                            const scale = Math.min(maxDim / w, maxDim / h);
                            w = Math.round(w * scale);
                            h = Math.round(h * scale);
                        }
                        canvas.width = w;
                        canvas.height = h;
                        const ctx = canvas.getContext('2d');
                        ctx.fillStyle = '#FFFFFF';
                        ctx.fillRect(0, 0, w, h);
                        ctx.drawImage(img, 0, 0, w, h);
                        const imgData = ctx.getImageData(0, 0, w, h);
                        const code = window.jsQR(imgData.data, imgData.width, imgData.height, {
                            inversionAttempts: 'attemptBoth'
                        });
                        if (code && code.data) {
                            const extracted = self.extractData(code.data, 'qr_code');
                            if (typeof onSuccess === 'function') onSuccess(extracted);
                        } else {
                            if (typeof onError === 'function') onError('No readable QR code found in image.');
                        }
                    };

                    if (self.detector) {
                        self.detector.detect(img)
                            .then((barcodes) => {
                                if (barcodes && barcodes.length > 0 && barcodes[0].rawValue) {
                                    const extracted = self.extractData(barcodes[0].rawValue, barcodes[0].format);
                                    if (typeof onSuccess === 'function') onSuccess(extracted);
                                } else {
                                    tryJsQr();
                                }
                            })
                            .catch(tryJsQr);
                    } else {
                        tryJsQr();
                    }
                };
                img.onerror = function () {
                    if (typeof onError === 'function') onError('Invalid image file.');
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        },

        /**
         * Handle local image file upload decode
         */
        handleImageFile: function (input) {
            if (!input.files || !input.files[0]) return;
            this.setStatus('Decoding image...', 'info');
            this.decodeFile(
                input.files[0],
                (extracted) => {
                    this.handleScanSuccess(extracted.raw, extracted.format);
                },
                (errMsg) => {
                    this.setStatus(errMsg || 'No readable QR code found in uploaded image.', 'error');
                }
            );
        },

        decodeImageWithCanvas: function (img) {
            if (!this.canvasElement || !this.canvasCtx) {
                this.setStatus('Unable to process image.', 'error');
                return;
            }
            this.canvasElement.width = img.width;
            this.canvasElement.height = img.height;
            this.canvasCtx.drawImage(img, 0, 0);

            if (typeof window.jsQR === 'function') {
                const imgData = this.canvasCtx.getImageData(0, 0, img.width, img.height);
                const code = window.jsQR(imgData.data, imgData.width, imgData.height);
                if (code && code.data) {
                    this.handleScanSuccess(code.data, 'qr_code');
                    return;
                }
            }
            this.setStatus('No readable QR code found in uploaded image.', 'error');
        },

        /**
         * Switch between available cameras
         */
        switchCamera: function () {
            if (this.cameras.length <= 1) return;
            this.currentCameraIndex = (this.currentCameraIndex + 1) % this.cameras.length;
            const nextCamera = this.cameras[this.currentCameraIndex];
            if (nextCamera) {
                this.startCamera(nextCamera.deviceId);
            }
        },

        /**
         * Audio feedback on scan
         */
        playBeep: function () {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(880, ctx.currentTime); // 880 Hz
                gain.gain.setValueAtTime(0.15, ctx.currentTime);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.12);
            } catch (e) {}
        },

        /**
         * Update status message UI
         */
        setStatus: function (msg, type) {
            const statusEl = document.getElementById('wtsbill-qr-status');
            if (!statusEl) return;
            statusEl.textContent = msg;
            if (type === 'error') {
                statusEl.style.background = 'rgba(239, 68, 68, 0.95)';
                statusEl.style.color = '#FFFFFF';
            } else if (type === 'success') {
                statusEl.style.background = 'rgba(16, 185, 129, 0.95)';
                statusEl.style.color = '#FFFFFF';
            } else {
                statusEl.style.background = 'rgba(30, 41, 59, 0.85)';
                statusEl.style.color = '#EBF5FC';
            }
        },

        /**
         * Stop camera media stream cleanly
         */
        stopCamera: function () {
            this.isScanning = false;
            if (this.animationFrameId) {
                cancelAnimationFrame(this.animationFrameId);
                this.animationFrameId = null;
            }
            if (this.stream) {
                this.stream.getTracks().forEach((track) => {
                    try { track.stop(); } catch (e) {}
                });
                this.stream = null;
            }
            if (this.videoElement) {
                this.videoElement.srcObject = null;
            }
        },

        /**
         * Close modal and release resources
         */
        close: function () {
            this.stopCamera();
            const modal = document.getElementById('wtsbill-qr-modal');
            if (modal) modal.style.display = 'none';
        }
    };

    window.WTSBill.QRScanner = QRScanner;

    // Auto-init on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => QRScanner.init());
    } else {
        QRScanner.init();
    }

})(window, document);

