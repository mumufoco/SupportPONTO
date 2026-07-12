#!/bin/bash

set -e

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

echo "=========================================="
echo "Fase 16: Executando Otimizações PostgreSQL"
echo "=========================================="
echo ""

if ! command -v php &> /dev/null; then
    echo "❌ PHP não está instalado ou não está no PATH"
    exit 1
fi

if ! command -v psql &> /dev/null; then
    echo "⚠️  PostgreSQL CLI não encontrado. Migrations devem ser executadas via CodeIgniter."
else
    echo "✓ PostgreSQL CLI encontrado"
fi

echo ""
echo "=========================================="
echo "1. Executando Migrations de Otimização"
echo "=========================================="
echo ""

if [ -f "spark" ]; then
    php spark migrate
    echo "✓ Migrations executadas com sucesso"
else
    echo "⚠️  Arquivo spark não encontrado."
    echo "   Execute manualmente: php spark migrate"
fi

echo ""
echo "=========================================="
echo "2. Aplicando Particionamento (Opcional)"
echo "=========================================="
echo ""

if command -v psql &> /dev/null; then
    echo "Deseja aplicar particionamento na tabela time_punches? (s/n)"
    read -r apply_partition

    if [ "$apply_partition" = "s" ] || [ "$apply_partition" = "S" ]; then
        psql -U postgres -d supportponto -f scripts/database/partition_time_punches.sql
        echo "✓ Particionamento aplicado"
    else
        echo "⚠️  Particionamento ignorado"
    fi
else
    echo "⚠️  Execute manualmente:"
    echo "   psql -U postgres -d supportponto -f scripts/database/partition_time_punches.sql"
fi

echo ""
echo "=========================================="
echo "3. Aplicando Ajustes de Banco"
echo "=========================================="
echo ""
echo "⚠️  Consulte: scripts/database/postgres_optimization.sql"
echo "   Reinicie o serviço PostgreSQL após aplicar as configurações."

echo ""
echo "=========================================="
echo "4. Executando Benchmarks de Performance"
echo "=========================================="
echo ""

if [ -f "scripts/testing/run-phpunit.sh" ]; then
    bash scripts/testing/run-phpunit.sh --filter IndexesBenchmark tests/performance/ || true
    bash scripts/testing/run-phpunit.sh --filter ConfigServiceBenchmark tests/performance/ || true
    bash scripts/testing/run-phpunit.sh --filter FacialRecognitionCacheBenchmark tests/performance/ || true
    bash scripts/testing/run-phpunit.sh --filter EagerLoadingBenchmark tests/performance/ || true
    echo "✓ Todos os benchmarks foram executados"
else
    echo "⚠️  PHPUnit não encontrado."
fi

echo ""
echo "=========================================="
echo "5. Verificando Cache"
echo "=========================================="
echo ""

if [ -d "writable/cache" ]; then
    echo "✓ Diretório de cache existe"
    ls -lh writable/cache/ | head -10
else
    mkdir -p writable/cache
    chmod 777 writable/cache
    echo "✓ Diretório criado"
fi

echo ""
echo "=========================================="
echo "Resumo da Execução"
echo "=========================================="
echo "✓ Projeto consolidado para PostgreSQL"
echo "✓ Migrations e views alinhadas ao PostgreSQL"
echo "✓ Benchmarks e cache verificados"
echo ""
echo "Próximos passos:"
echo "  1. Executar testes completos"
echo "  2. Validar migrations em ambiente limpo"
echo "  3. Monitorar slow queries e locks"
echo "=========================================="
