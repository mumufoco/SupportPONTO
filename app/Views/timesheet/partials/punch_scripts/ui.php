<script <?= csp_script_nonce_attr() ?>>
window.SupportPontoPunchUI = (() => {
    // Safe HTML escape for API/config values inserted via innerHTML
    function escHtml(str) {
        const div = document.createElement('div');
        div.textContent = String(str ?? '');
        return div.innerHTML;
    }

    const config = window.SupportPontoPunchConfig || {};
    const methodReadiness = config.methodReadiness || {};
    const endpointMap = config.endpoints || {};

    let selectedPunchType = 'entrada';
    let selectedMethod = null;
    let qrScanner = null;
    let faceStream = null;
    let fpWs = null;
    // Segunda camada de segurança contra fraude: quando definido, a próxima
    // captura no painel facial não é o método "facial" em si, e sim a
    // confirmação de identidade exigida após código/CPF/QR/biometria digital.
    // Só é limpo quando o registro é efetuado com sucesso (ou o usuário troca
    // de método) -- assim, se a foto não bater, o usuário pode tentar de novo
    // sem precisar redigitar o código/CPF.
    let pendingSecondFactor = null;

    const methodAliases = {
        codigo: 'codigo',
        cpf: 'cpf',
        qr: 'qrcode',
        qrcode: 'qrcode',
        face: 'facial',
        facial: 'facial',
        fingerprint: 'biometria',
        biometria: 'biometria',
    };

    const typeLabels = {
        entrada: 'Entrada',
        saida: 'Saída',
        intervalo_inicio: 'Início do intervalo',
        intervalo_fim: 'Fim do intervalo',
    };

    const methodLabels = {
        codigo: 'Código único',
        cpf: 'CPF',
        qr: 'QR Code',
        face: 'Reconhecimento facial',
        fingerprint: 'Biometria digital',
    };

    function normalizeMethod(method) {
        return methodAliases[method] || method;
    }

    function isMethodEnabled(method) {
        const normalized = normalizeMethod(method);
        const meta = methodReadiness[normalized] || {};
        return !!meta.enabled;
    }

    function getResultContainer() {
        return document.getElementById('punchResult');
    }

    function setResult(kind, message, extraHtml = '') {
        const box = getResultContainer();
        if (!box) return;
        box.innerHTML = `<div class="alert alert-${kind}">${message}${extraHtml}</div>`;
    }

    function selectionSummary() {
        const box = document.getElementById('punchSelectionSummary');
        if (!box) return;
        const methodText = selectedMethod ? (methodLabels[selectedMethod] || selectedMethod) : 'aguardando método';
        box.innerHTML = `<strong>Seleção atual:</strong> ${typeLabels[selectedPunchType] || selectedPunchType} · ${methodText}`;
    }

    function updateWarnings() {
        const warnings = [];
        if (!config.supportsCamera) {
            warnings.push('Este dispositivo não oferece câmera para QR Code e reconhecimento facial.');
        }
        if (!config.supportsWebAuthn) {
            warnings.push('A biometria do navegador não está disponível neste dispositivo.');
        }

        Object.entries(methodReadiness).forEach(([key, meta]) => {
            if (!meta.enabled) {
                warnings.push(`${escHtml(meta.label || key)} desabilitado nas configurações atuais.`);
            }
        });

        const box = document.getElementById('punchCapabilityAlert');
        if (!box) return;
        box.innerHTML = warnings.length
            ? `<div class="alert alert-warning small">${warnings.join('<br>')}</div>`
            : '';
    }

    function activateTypeButtons() {
        const buttons = Array.from(document.querySelectorAll('[data-punch-type]'));
        buttons.forEach((btn) => {
            btn.addEventListener('click', () => {
                buttons.forEach((x) => x.classList.remove('active'));
                btn.classList.add('active');
                selectedPunchType = btn.dataset.punchType || 'entrada';
                selectionSummary();
            });
        });
        if (buttons.length > 0) {
            buttons[0].classList.add('active');
            selectedPunchType = buttons[0].dataset.punchType || 'entrada';
        }
        selectionSummary();
    }

    function activateMethodButtons() {
        const buttons = Array.from(document.querySelectorAll('[data-method]'));
        buttons.forEach((btn) => {
            btn.addEventListener('click', async () => {
                if (btn.disabled || !isMethodEnabled(btn.dataset.method || '')) {
                    setResult('warning', 'Este método está desabilitado nas configurações atuais.');
                    return;
                }

                buttons.forEach((x) => x.classList.remove('active'));
                btn.classList.add('active');
                selectedMethod = btn.dataset.method || null;
                selectionSummary();
                await showMethodPanel(selectedMethod);
            });
        });

        const firstEnabled = buttons.find((btn) => !btn.disabled && isMethodEnabled(btn.dataset.method || ''));
        if (firstEnabled) {
            firstEnabled.classList.add('active');
            selectedMethod = firstEnabled.dataset.method || null;
            selectionSummary();
            void showMethodPanel(selectedMethod);
        }
    }

    async function showMethodPanel(method) {
        pendingSecondFactor = null;
        await stopHardware();
        document.querySelectorAll('.sp-method-panel').forEach((panel) => panel.classList.add('d-none'));

        const panelIdMap = {
            codigo: 'form-codigo',
            cpf: 'form-cpf',
            qr: 'form-qr',
            qrcode: 'form-qr',
            face: 'form-face',
            facial: 'form-face',
            fingerprint: 'form-fingerprint',
            biometria: 'form-fingerprint',
        };

        const panel = document.getElementById(panelIdMap[method] || '');
        if (!panel) return;
        panel.classList.remove('d-none');

        if (method === 'face' || method === 'facial') {
            await startFace();
        } else if (method === 'qr' || method === 'qrcode') {
            await startQR();
        } else if (method === 'fingerprint' || method === 'biometria') {
            startFingerprintWS();
        }
    }

    function getHolidayOverride() {
        const cb = document.getElementById('sp-holiday-override');
        return cb ? (cb.checked ? 1 : 0) : 0;
    }

    // Métodos capturáveis offline: código/CPF/QR/facial funcionam só com
    // câmera/formulário do navegador. Biometria digital fica de fora porque
    // depende do leitor físico via WebSocket local (fp_bridge) — um fluxo de
    // hardware separado, com sua própria mensagem de indisponibilidade.
    function methodForEndpoint(endpoint) {
        if (endpoint === endpointMap.codigo) return 'codigo';
        if (endpoint === endpointMap.cpf) return 'cpf';
        if (endpoint === endpointMap.qr) return 'qrcode';
        if (endpoint === endpointMap.face) return 'facial';
        return null;
    }

    function renderOfflineReceipt(item) {
        const when = new Date(item.client_captured_at).toLocaleString('pt-BR');
        return `<div class="small mt-2">Salvo em ${escHtml(when)} · será sincronizado automaticamente. Acompanhe o status na lista de pendências offline abaixo.</div>`;
    }

    async function queueOfflinePunch(method, payload) {
        if (!window.SupportPontoOffline) {
            setResult('danger', 'Sem conexão e o módulo de fila offline não carregou. Tente recarregar a página assim que possível.');
            return false;
        }
        try {
            const item = await window.SupportPontoOffline.queuePunch(method, payload);
            setResult('warning', 'Sem conexão: seu ponto foi salvo neste dispositivo e será sincronizado automaticamente quando a internet voltar.', renderOfflineReceipt(item));
            clearMethodInputs();
            return true;
        } catch (error) {
            setResult('danger', 'Não foi possível salvar o ponto localmente neste dispositivo. Tente novamente.');
            return false;
        }
    }

    async function sendPunch(endpoint, payload) {
        const override = getHolidayOverride();
        if (override) {
            payload = Object.assign({}, payload, { holiday_override: 1 });
        }

        const offlineMethod = methodForEndpoint(endpoint);
        const canQueueOffline = !!(window.SupportPontoOffline && offlineMethod);

        if (canQueueOffline && !navigator.onLine) {
            return await queueOfflinePunch(offlineMethod, payload);
        }

        setResult('secondary', 'Processando registro de ponto...');
        try {
            const response = await spFetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });
            const result = await response.json();
            const pendingPunch = result?.data?.pending_punch || null;
            const faceSecondFactor = result?.errors?.face_second_factor || null;
            let extra = '';
            if (pendingPunch?.eligible && pendingPunch?.justify_url) {
                // Validate URL to prevent javascript: injection; only allow relative or http/https URLs
                const rawUrl = String(pendingPunch.justify_url);
                const safeUrl = /^(https?:\/\/|\/)/.test(rawUrl) ? escHtml(rawUrl) : '#';
                extra = `<div class="mt-3"><a class="btn btn-sm btn-warning" href="${safeUrl}">Abrir justificativa automática</a></div>`;
            } else if (pendingPunch?.block_reason) {
                extra = `<div class="small mt-2">${escHtml(pendingPunch.block_reason)}</div>`;
            } else if (faceSecondFactor === 'no_enrollment' && result?.errors?.enroll_url) {
                const rawUrl = String(result.errors.enroll_url);
                const safeUrl = /^(https?:\/\/|\/)/.test(rawUrl) ? escHtml(rawUrl) : '#';
                extra = `<div class="mt-3"><a class="btn btn-sm btn-warning" href="${safeUrl}" target="_blank" rel="noopener">Cadastrar minha biometria facial</a></div>`;
            }
            setResult(response.ok ? 'success' : 'danger', escHtml(result.message) || (response.ok ? 'Registro efetuado com sucesso.' : 'Falha ao registrar ponto.'), extra);
            if (response.ok) {
                clearMethodInputs();
            }
            return response.ok;
        } catch (error) {
            // navigator.onLine pode reportar "online" incorretamente (falso
            // positivo comum) -- se o fetch falhar por rede mesmo assim, cai
            // para a fila offline como último recurso antes de desistir.
            if (canQueueOffline) {
                return await queueOfflinePunch(offlineMethod, payload);
            }
            setResult('danger', 'Falha de comunicação com o servidor.');
            return false;
        }
    }

    /**
     * Segunda camada de segurança contra fraude: código/CPF/QR/biometria digital
     * já identificaram o colaborador, mas antes de efetivar o registro exigimos
     * uma foto confirmando que é a própria pessoa (comparação 1:1 contra o
     * cadastro biométrico facial dela — não é o método "facial" livre).
     */
    async function requireFaceSecondFactorThenSend(endpoint, payload) {
        await stopHardware();
        document.querySelectorAll('.sp-method-panel').forEach((panel) => panel.classList.add('d-none'));
        const panel = document.getElementById('form-face');
        if (!panel || !config.supportsCamera) {
            // Sem câmera disponível: não há como cumprir a segunda camada de
            // segurança neste dispositivo -- não envia o registro sem ela.
            setResult('warning', 'Este dispositivo não possui câmera disponível para a verificação de segurança exigida neste método.');
            return;
        }
        panel.classList.remove('d-none');
        pendingSecondFactor = { endpoint, payload };
        setResult('info', 'Identificação confirmada. Posicione seu rosto no oval e clique em "Capturar e registrar" para concluir o registro com segurança.');
        await startFace();
    }

    function clearMethodInputs() {
        const codeInput = document.getElementById('punch_unique_code');
        const cpfInput = document.getElementById('punch_cpf');
        if (codeInput) codeInput.value = '';
        if (cpfInput) cpfInput.value = '';
    }

    function stopFingerprint() {
        if (fpWs) { try { fpWs.close(); } catch(_) {} fpWs = null; }
    }

    function startFingerprintWS() {
        stopFingerprint();
        var box = document.getElementById('punchFingerprintMessage');
        var btn = document.getElementById('submitFingerprintPunch');
        if (btn) btn.style.display = 'none';
        function setFpStatus(cls, msg, spin) {
            if (!box) return;
            box.className = 'alert alert-' + cls + ' d-flex align-items-center gap-2';
            var icon = spin
                ? '<div class="spinner-border spinner-border-sm flex-shrink-0" role="status"></div>'
                : '<i class="bi bi-fingerprint fs-5 flex-shrink-0"></i>';
            box.innerHTML = icon + '<span>' + msg + '</span>';
        }
        setFpStatus('secondary', 'Detectando leitor de impressao digital...', true);
        fpWs = new WebSocket('ws://localhost:8765');
        fpWs.onopen = function() {
            setFpStatus('secondary', 'Verificando hardware...', true);
        };
        fpWs.onmessage = function(ev) {
            try {
                var msg = JSON.parse(ev.data);
                if (msg.status === 'ready') {
                    setFpStatus('info', 'Apoie o dedo no leitor de impressao digital...', true);
                } else if (msg.status === 'captured' && msg.data) {
                    setFpStatus('success', 'Digital capturada! Confirme sua identidade com uma foto para concluir.', true);
                    var ws = fpWs; fpWs = null;
                    try { ws.close(); } catch(_) {}
                    requireFaceSecondFactorThenSend(endpointMap.fingerprint, {
                        fingerprint_data: msg.data,
                        punch_type: selectedPunchType,
                    });
                } else if (msg.status === 'timeout') {
                    setFpStatus('warning', 'Tempo esgotado. Selecione Biometria novamente.', false);
                } else if (msg.status === 'error') {
                    setFpStatus('danger', msg.message || 'Erro no leitor de impressao digital.', false);
                }
            } catch(_) {}
        };
        fpWs.onerror = function() {
            setFpStatus('warning',
                'Servico fp_bridge nao encontrado. Verifique se esta em execucao.', false);
        };
    }

    function bindForms() {
        const codeForm = document.getElementById('form-codigo');
        const cpfForm = document.getElementById('form-cpf');
        const cpfInput = document.getElementById('punch_cpf');
        const faceButton = document.getElementById('captureFacePunch');
        const fingerprintButton = document.getElementById('submitFingerprintPunch');

        if (codeForm) {
            codeForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (!selectedMethod || selectedMethod !== 'codigo') {
                    setResult('warning', 'Selecione o método código para registrar.');
                    return;
                }
                const formData = new FormData(codeForm);
                await requireFaceSecondFactorThenSend(endpointMap.codigo, {
                    unique_code: String(formData.get('unique_code') || '').trim(),
                    punch_type: selectedPunchType,
                });
            });
        }

        if (cpfInput) {
            SupportPontoValidation.bindCpfField(cpfInput);
        }

        if (cpfForm) {
            cpfForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (!selectedMethod || selectedMethod !== 'cpf') {
                    setResult('warning', 'Selecione o método CPF para registrar.');
                    return;
                }
                const formData = new FormData(cpfForm);
                await requireFaceSecondFactorThenSend(endpointMap.cpf, {
                    cpf: String(formData.get('cpf') || '').trim(),
                    punch_type: selectedPunchType,
                });
            });
        }

        if (faceButton) {
            faceButton.addEventListener('click', async () => {
                if (!pendingSecondFactor && selectedMethod !== 'face') {
                    setResult('warning', 'Selecione o método facial para registrar.');
                    return;
                }
                const video = document.getElementById('punchFaceVideo');
                if (!video || !(video.videoWidth > 0) || !(video.videoHeight > 0)) {
                    setResult('warning', 'A câmera ainda não está pronta para captura.');
                    return;
                }
                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth || 640;
                canvas.height = video.videoHeight || 480;
                const context = canvas.getContext('2d');
                if (!context) {
                    setResult('danger', 'Não foi possível preparar a captura da imagem.');
                    return;
                }
                context.drawImage(video, 0, 0);
                const photo = canvas.toDataURL('image/jpeg').split(',')[1];

                if (pendingSecondFactor) {
                    const ctx = pendingSecondFactor;
                    const ok = await sendPunch(ctx.endpoint, Object.assign({}, ctx.payload, { photo }));
                    // Só limpa em caso de sucesso -- assim, se a foto não bater (ou faltar
                    // cadastro), o usuário pode tentar de novo sem redigitar código/CPF.
                    if (ok) pendingSecondFactor = null;
                    return;
                }

                await sendPunch(endpointMap.face, {
                    photo,
                    punch_type: selectedPunchType,
                });
            });
        }

        // fingerprint: auto via WebSocket fp_bridge (sem click necessario)
    }

    async function startFace() {
        if (!config.supportsCamera) {
            setResult('warning', 'Câmera indisponível para reconhecimento facial.');
            return;
        }
        try {
            faceStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
            const video = document.getElementById('punchFaceVideo');
            if (video) {
                video.srcObject = faceStream;
            }
        } catch (error) {
            setResult('warning', 'Não foi possível acessar a câmera para reconhecimento facial.');
        }
    }

    async function startQR() {
        if (!config.supportsCamera) {
            setResult('warning', 'Câmera indisponível para leitura de QR Code.');
            return;
        }
        if (typeof Html5Qrcode === 'undefined') {
            setResult('warning', 'Leitor de QR Code indisponível no navegador atual.');
            return;
        }
        try {
            qrScanner = new Html5Qrcode('punch-qr-reader');
            await qrScanner.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: 240 },
                async (text) => {
                    // requireFaceSecondFactorThenSend() já para o leitor de QR (via
                    // stopHardware()) ao trocar para o painel de câmera facial.
                    await requireFaceSecondFactorThenSend(endpointMap.qr, {
                        token: text,
                        punch_type: selectedPunchType,
                    });
                }
            );
        } catch (error) {
            setResult('warning', 'Não foi possível iniciar o leitor de QR Code.');
        }
    }

    async function stopQR() {
        if (!qrScanner) return;
        try {
            await qrScanner.stop();
        } catch (_) {
        }
        try {
            await qrScanner.clear();
        } catch (_) {
        }
        qrScanner = null;
    }

    async function stopFace() {
        if (!faceStream) return;
        faceStream.getTracks().forEach((track) => track.stop());
        faceStream = null;
        const video = document.getElementById('punchFaceVideo');
        if (video) {
            video.srcObject = null;
        }
    }

    async function stopHardware() {
        stopFingerprint();
        await stopQR();
        await stopFace();
    }

    function initialize() {
        updateWarnings();
        activateTypeButtons();
        activateMethodButtons();
        bindForms();
    }

    return {
        initialize,
        activateTypeButtons,
        activateMethodButtons,
        showMethodPanel,
    };
})();
</script>
