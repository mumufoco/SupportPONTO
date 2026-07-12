<script <?= csp_script_nonce_attr() ?>>
document.addEventListener('DOMContentLoaded', function () {
    const syncCatalogSelect = function (select) {
        const targetId = select.getAttribute('data-catalog-target');
        if (!targetId) {
            return;
        }

        const target = document.getElementById(targetId);
        if (!target) {
            return;
        }

        const option = select.options[select.selectedIndex];
        if (option && option.value && target.value.trim() === '') {
            target.value = option.textContent.trim();
        }
    };

    document.querySelectorAll('select[data-catalog-target]').forEach(function (select) {
        syncCatalogSelect(select);
        select.addEventListener('change', function () {
            const target = document.getElementById(select.getAttribute('data-catalog-target'));
            const option = select.options[select.selectedIndex];
            if (target && option && option.value) {
                target.value = option.textContent.trim();
            }
            syncAliases();
        });
    });

    const syncAliases = function () {
        const department = document.getElementById('department');
        const setor = document.getElementById('setor');
        const position = document.getElementById('position');
        const cargo = document.getElementById('cargo');
        const pisPasep = document.getElementById('pis_pasep');
        const pis = document.getElementById('pis');
        const start = document.getElementById('horario_entrada');
        const end = document.getElementById('horario_saida');
        const journey = document.getElementById('jornada_trabalho');

        if (department && setor) {
            setor.value = department.value;
        }
        if (position && cargo) {
            cargo.value = position.value;
        }
        if (pisPasep && pis) {
            pis.value = pisPasep.value;
        }
        if (start && end && journey && journey.value.trim() === '') {
            journey.value = `${start.value} às ${end.value}`;
        }
    };

    ['department', 'position', 'pis_pasep', 'horario_entrada', 'horario_saida'].forEach(function (id) {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('input', syncAliases);
            element.addEventListener('change', syncAliases);
        }
    });

    syncAliases();
});
</script>
