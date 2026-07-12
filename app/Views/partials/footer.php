<!-- Footer -->
<footer class="footer sp-footer-shell">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                <p class="mb-0 text-muted">
                    <i class="fas fa-clock me-1"></i>
                    Copyright <?= date('Y') ?> - SupportPONTO - SUᕈᕈORΓ SOLO E SOṈDΔGEṈS. Todos os direitos reservados.
                </p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <p class="mb-0 text-muted">
                    <i class="fas fa-shield-alt me-1"></i>
                    Sistema em conformidade com LGPD e Portaria MTE 671/2021
                </p>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12 text-center">
                <small class="text-muted">
                    Versão <?= esc(app_version(false)) ?> | 
                    <a href="<?= base_url('lgpd/privacy') ?>" class="text-decoration-none text-muted">
                        <i class="fas fa-user-shield me-1"></i>Política de Privacidade
                    </a> | 
                    <a href="<?= base_url('lgpd/terms') ?>" class="text-decoration-none text-muted">
                        <i class="fas fa-file-contract me-1"></i>Termos de Uso
                    </a>
                </small>
            </div>
        </div>
    </div>
</footer>
