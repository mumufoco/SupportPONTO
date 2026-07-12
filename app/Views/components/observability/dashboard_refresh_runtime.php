<script <?= csp_script_nonce_attr() ?>>
(function () {
    if (window.SupportPontoDashboardRefreshRuntime) {
        return;
    }

    const runtime = {
        fetchJson: async function (url) {
            const fetcher = window.spFetch || window.fetch;
            const response = await fetcher(url, {headers: {'Accept': 'application/json'}});
            if (!response || typeof response.json !== 'function') {
                throw new Error('Resposta inválida do endpoint de observabilidade.');
            }
            return response.json();
        },
        updateWidget: function (widget, payload) {
            const mode = String(payload?.delivery?.mode || payload?.mode || 'ok').toLowerCase();
            widget.dataset.deliveryMode = ['ok', 'degraded', 'error'].includes(mode) ? mode : 'ok';
            const valueEl = widget.querySelector('[data-observability-value]');
            const descEl = widget.querySelector('[data-observability-description]');
            if (valueEl && payload?.value !== undefined) {
                valueEl.textContent = String(payload.value);
            }
            if (descEl && payload?.description !== undefined) {
                descEl.textContent = String(payload.description);
            }
            document.dispatchEvent(new CustomEvent('support:dashboard-widget-refresh', {
                detail: Object.assign({widgetId: widget.id || '', summaryUrl: widget.dataset.summaryUrl || ''}, payload || {})
            }));
        },
        refresh: async function (widget) {
            const url = widget.dataset.summaryUrl || '';
            if (url === '' || url === '#') {
                this.updateWidget(widget, {delivery: {mode: widget.dataset.deliveryMode || 'ok'}});
                return;
            }
            try {
                const payload = await this.fetchJson(url);
                this.updateWidget(widget, payload);
            } catch (error) {
                this.updateWidget(widget, {
                    delivery: {mode: 'degraded'},
                    description: 'Não foi possível atualizar automaticamente. Último estado local preservado.'
                });
            }
        },
        refreshAll: function () {
            document.querySelectorAll('[data-summary-url]').forEach((widget) => this.refresh(widget));
        }
    };

    window.SupportPontoDashboardRefreshRuntime = runtime;
    document.addEventListener('DOMContentLoaded', () => runtime.refreshAll());
})();
</script>
