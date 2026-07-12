/**
 * Geolocator.js
 * HTML5 Geolocation API wrapper for time punch system
 *
 * Features:
 * - High accuracy positioning
 * - Retry mechanism (max 3 attempts)
 * - Permission handling
 * - Error handling with user-friendly messages
 */

const Geolocator = {
    /**
     * Maximum number of retry attempts
     */
    maxRetries: 3,

    /**
     * Current retry count
     */
    retryCount: 0,

    /**
     * Geolocation options
     */
    options: {
        enableHighAccuracy: true,
        timeout: 10000, // 10 seconds
        maximumAge: 0    // Don't use cached position
    },

    /**
     * Request current location
     *
     * @param {Function} onSuccess - Callback(position)
     * @param {Function} onError - Callback(error)
     * @param {Boolean} showLoading - Show loading indicator
     * @returns {void}
     */
    requestLocation(onSuccess, onError, showLoading = true) {
        // Check if geolocation is supported
        if (!navigator.geolocation) {
            this.handleError({
                code: 'NOT_SUPPORTED',
                message: 'Geolocalização não é suportada pelo seu navegador.'
            }, onError);
            return;
        }

        // Show loading if enabled
        if (showLoading) {
            this.showLoading();
        }

        // Reset retry count for new request
        this.retryCount = 0;

        // Request position
        this._getCurrentPosition(onSuccess, onError);
    },

    /**
     * Internal method to get current position
     * @private
     */
    _getCurrentPosition(onSuccess, onError) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                this.hideLoading();
                this.handleSuccess(position, onSuccess);
            },
            (error) => {
                this.handleError(error, onError);
            },
            this.options
        );
    },

    /**
     * Handle successful position retrieval
     *
     * @param {Position} position - Geolocation position object
     * @param {Function} callback - Success callback
     */
    handleSuccess(position, callback) {
        const locationData = {
            lat: position.coords.latitude,
            lng: position.coords.longitude,
            accuracy: Math.round(position.coords.accuracy), // in meters
            altitude: position.coords.altitude,
            altitudeAccuracy: position.coords.altitudeAccuracy,
            heading: position.coords.heading,
            speed: position.coords.speed,
            timestamp: position.timestamp
        };

        // Check accuracy warning
        if (locationData.accuracy > 100) {
            this.showAccuracyWarning(locationData.accuracy);
        }

        // Call success callback
        if (typeof callback === 'function') {
            callback(locationData);
        }
    },

    /**
     * Handle geolocation errors
     *
     * @param {GeolocationPositionError} error - Error object
     * @param {Function} callback - Error callback
     */
    handleError(error, callback) {
        let errorMessage = 'Erro ao obter localização.';
        let shouldRetry = false;

        switch (error.code) {
            case error.PERMISSION_DENIED:
            case 1: // PERMISSION_DENIED
                this.hideLoading();
                this.showPermissionDeniedModal();
                errorMessage = 'Permissão de localização negada pelo usuário.';
                break;

            case error.POSITION_UNAVAILABLE:
            case 2: // POSITION_UNAVAILABLE
                this.hideLoading();
                this.showPositionUnavailableModal(callback);
                errorMessage = 'Localização indisponível no momento.';
                break;

            case error.TIMEOUT:
            case 3: // TIMEOUT
                shouldRetry = true;
                errorMessage = 'Timeout ao obter localização.';
                break;

            default:
                this.hideLoading();
                errorMessage = error.message || 'Erro desconhecido ao obter localização.';
                break;
        }

        // Retry logic for timeouts
        if (shouldRetry && this.retryCount < this.maxRetries) {
            this.retryCount++;
            console.log(`Tentativa ${this.retryCount} de ${this.maxRetries} para obter localização...`);

            // Wait 1 second before retry
            setTimeout(() => {
                this._getCurrentPosition(
                    (position) => callback(position),
                    (err) => this.handleError(err, callback)
                );
            }, 1000);
        } else {
            // Max retries reached or non-retryable error
            this.hideLoading();

            if (typeof callback === 'function') {
                callback({
                    error: true,
                    code: error.code,
                    message: errorMessage
                });
            }
        }
    },

    /**
     * Show loading indicator
     */
    showLoading() {
        // Check if loading element exists
        let loadingEl = document.getElementById('geolocation-loading');

        if (!loadingEl) {
            loadingEl = document.createElement('div');
            loadingEl.id = 'geolocation-loading';
            loadingEl.className = 'alert alert-info d-flex align-items-center';
            loadingEl.innerHTML = `
                <div class="spinner-border spinner-border-sm me-2" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
                <div>Obtendo localização... Aguarde.</div>
            `;

            // Insert at top of main content or body
            const mainContent = document.querySelector('main') || document.body;
            mainContent.insertBefore(loadingEl, mainContent.firstChild);
        }

        loadingEl.style.display = 'flex';
    },

    /**
     * Hide loading indicator
     */
    hideLoading() {
        const loadingEl = document.getElementById('geolocation-loading');
        if (loadingEl) {
            loadingEl.style.display = 'none';
        }
    },

    /**
     * Show accuracy warning
     *
     * @param {Number} accuracy - Accuracy in meters
     */
    showAccuracyWarning(accuracy) {
        // Check if warning already exists
        let warningEl = document.getElementById('geolocation-accuracy-warning');

        if (!warningEl) {
            warningEl = document.createElement('div');
            warningEl.id = 'geolocation-accuracy-warning';
            warningEl.className = 'alert alert-warning alert-dismissible fade show';

            const mainContent = document.querySelector('main') || document.body;
            mainContent.insertBefore(warningEl, mainContent.firstChild);
        }

        warningEl.innerHTML = `
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Precisão de GPS baixa (±${accuracy}m).</strong>
            A localização pode estar imprecisa.
        `;
    },

    /**
     * Show modal when permission is denied
     */
    showPermissionDeniedModal() {
        const modal = this._createModal(
            'Permissão de Localização Negada',
            `
                <p>Para registrar ponto com geolocalização, você precisa permitir o acesso à sua localização.</p>
                <p><strong>Como habilitar:</strong></p>
                <ol>
                    <li><strong>Chrome/Edge:</strong> Clique no ícone <i class="fas fa-lock"></i> na barra de endereço → Configurações do site → Localização → Permitir</li>
                    <li><strong>Firefox:</strong> Clique no ícone <i class="fas fa-shield-alt"></i> → Permissões → Localização → Permitir</li>
                    <li><strong>Safari:</strong> Safari → Preferências → Sites → Localização → Permitir</li>
                </ol>
                <p class="text-muted small">Após habilitar, recarregue a página e tente novamente.</p>
            `,
            'warning'
        );

        modal.show();
    },

    /**
     * Show modal when position is unavailable
     *
     * @param {Function} onContinue - Callback to continue without location
     */
    showPositionUnavailableModal(onContinue) {
        const modal = this._createModal(
            'Localização Indisponível',
            `
                <p>Não foi possível obter sua localização no momento.</p>
                <p><strong>Possíveis causas:</strong></p>
                <ul>
                    <li>GPS desativado no dispositivo</li>
                    <li>Sinal de GPS fraco (tente ao ar livre)</li>
                    <li>Serviços de localização desativados</li>
                </ul>
                <p>Você pode registrar ponto sem localização, mas isso será registrado nos logs.</p>
            `,
            'warning',
            [
                {
                    text: 'Cancelar',
                    class: 'btn-secondary',
                    dismiss: true
                },
                {
                    text: 'Continuar sem Localização',
                    class: 'btn-primary',
                    onClick: () => {
                        if (typeof onContinue === 'function') {
                            onContinue({
                                lat: null,
                                lng: null,
                                accuracy: null,
                                unavailable: true
                            });
                        }
                    }
                }
            ]
        );

        modal.show();
    },

    /**
     * Create Bootstrap modal
     * @private
     *
     * @param {String} title - Modal title
     * @param {String} body - Modal body HTML
     * @param {String} type - Alert type (info, warning, danger)
     * @param {Array} buttons - Array of button objects
     * @returns {Bootstrap.Modal}
     */
    _createModal(title, body, type = 'info', buttons = []) {
        // Remove existing modal if any
        const existingModal = document.getElementById('geolocator-modal');
        if (existingModal) {
            existingModal.remove();
        }

        const modalEl = document.createElement('div');
        modalEl.id = 'geolocator-modal';
        modalEl.className = 'modal fade';
        modalEl.setAttribute('tabindex', '-1');

        const iconClass = {
            'info': 'fa-info-circle text-info',
            'warning': 'fa-exclamation-triangle text-warning',
            'danger': 'fa-times-circle text-danger'
        }[type] || 'fa-info-circle';

        const footerButtons = buttons.length > 0
            ? buttons.map(btn => `
                <button type="button" class="btn ${btn.class}" ${btn.dismiss ? 'data-bs-dismiss="modal"' : ''} id="${btn.id || ''}">
                    ${btn.text}
                </button>
            `).join('')
            : '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>';

        modalEl.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas ${iconClass} me-2"></i>
                            ${title}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        ${body}
                    </div>
                    <div class="modal-footer">
                        ${footerButtons}
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modalEl);

        const bsModal = new bootstrap.Modal(modalEl);

        // Attach button click handlers
        buttons.forEach((btn, index) => {
            if (btn.onClick) {
                const buttonEl = modalEl.querySelectorAll('.modal-footer button')[index];
                if (buttonEl) {
                    buttonEl.addEventListener('click', () => {
                        btn.onClick();
                        bsModal.hide();
                    });
                }
            }
        });

        // Cleanup on hide
        modalEl.addEventListener('hidden.bs.modal', () => {
            modalEl.remove();
        });

        return bsModal;
    },

    /**
     * Format coordinates to display string
     *
     * @param {Number} lat - Latitude
     * @param {Number} lng - Longitude
     * @returns {String}
     */
    formatCoordinates(lat, lng) {
        return `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
    },

    /**
     * Get human-readable accuracy description
     *
     * @param {Number} accuracy - Accuracy in meters
     * @returns {String}
     */
    getAccuracyDescription(accuracy) {
        if (accuracy < 10) return 'Excelente (±' + accuracy + 'm)';
        if (accuracy < 50) return 'Boa (±' + accuracy + 'm)';
        if (accuracy < 100) return 'Moderada (±' + accuracy + 'm)';
        return 'Baixa (±' + accuracy + 'm)';
    }
};

// Export for use in modules or global scope
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Geolocator;
}
