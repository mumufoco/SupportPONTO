#!/bin/bash

# ========================================
# DeepFace API Startup Script
# Sistema de Ponto Eletrônico
# ========================================

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}DeepFace API - Starting Service${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

# ========================================
# 1. Check if virtual environment exists
# ========================================

VENV_DIR="venv"

if [ ! -d "$VENV_DIR" ]; then
    echo -e "${RED}ERROR: Virtual environment not found at $VENV_DIR${NC}"
    echo -e "${YELLOW}Please run setup_deepface_api.sh first${NC}"
    exit 1
fi

echo -e "${GREEN}✓${NC} Virtual environment found: $VENV_DIR"

# ========================================
# 2. Activate virtual environment
# ========================================

echo -e "${YELLOW}Activating virtual environment...${NC}"
source "$VENV_DIR/bin/activate"

# Verify activation
if [ -z "$VIRTUAL_ENV" ]; then
    echo -e "${RED}ERROR: Failed to activate virtual environment${NC}"
    exit 1
fi

echo -e "${GREEN}✓${NC} Virtual environment activated"

# ========================================
# 3. Check if requirements changed
# ========================================

REQUIREMENTS_FILE="requirements.txt"
REQUIREMENTS_HASH_FILE=".requirements.hash"

if [ -f "$REQUIREMENTS_FILE" ]; then
    CURRENT_HASH=$(md5sum "$REQUIREMENTS_FILE" | cut -d' ' -f1)

    if [ -f "$REQUIREMENTS_HASH_FILE" ]; then
        STORED_HASH=$(cat "$REQUIREMENTS_HASH_FILE")
    else
        STORED_HASH=""
    fi

    if [ "$CURRENT_HASH" != "$STORED_HASH" ]; then
        echo -e "${YELLOW}Requirements file changed, updating dependencies...${NC}"
        pip install --upgrade pip
        pip install -r "$REQUIREMENTS_FILE" --no-cache-dir

        # Save new hash
        echo "$CURRENT_HASH" > "$REQUIREMENTS_HASH_FILE"
        echo -e "${GREEN}✓${NC} Dependencies updated"
    else
        echo -e "${GREEN}✓${NC} Dependencies up to date"
    fi
else
    echo -e "${RED}ERROR: requirements.txt not found${NC}"
    exit 1
fi

# ========================================
# 4. Check .env file
# ========================================

if [ ! -f ".env" ]; then
    echo -e "${YELLOW}WARNING: .env file not found${NC}"
    if [ -f ".env.example" ]; then
        echo -e "${YELLOW}Copying .env.example to .env${NC}"
        cp .env.example .env
        echo -e "${GREEN}✓${NC} Created .env from .env.example"
        echo -e "${YELLOW}⚠ Please configure .env file before running in production${NC}"
    else
        echo -e "${RED}ERROR: No .env or .env.example found${NC}"
        exit 1
    fi
else
    echo -e "${GREEN}✓${NC} .env file found"
fi

# ========================================
# 5. Create necessary directories
# ========================================

mkdir -p logs
mkdir -p faces_db
mkdir -p storage

echo -e "${GREEN}✓${NC} Directories created"

# ========================================
# 6. Verify Python version
# ========================================

PYTHON_VERSION=$(python --version 2>&1 | cut -d' ' -f2)
echo -e "${GREEN}✓${NC} Python version: $PYTHON_VERSION"

# ========================================
# 7. Load environment variables
# ========================================

set -a  # Export all variables
source .env
set +a

# Get configuration
# MED-07 (auditoria): o padrão era 0.0.0.0 (todas as interfaces de rede). O serviço
# systemd (deepface-api.service) já força --bind 127.0.0.1, mas quem sobe a API via
# este script (setup manual/rápido) ficava exposto em toda a rede sem perceber, caso
# não configurasse HOST explicitamente no .env. Padrão agora é loopback, como no
# systemd; produção exige opt-in explícito para 0.0.0.0 (ver checagem abaixo).
HOST=${HOST:-127.0.0.1}
PORT=${PORT:-5000}
WORKERS=${GUNICORN_WORKERS:-2}
TIMEOUT=${GUNICORN_TIMEOUT:-120}

echo -e "${GREEN}✓${NC} Configuration loaded"
echo -e "   Host: $HOST"
echo -e "   Port: $PORT"
echo -e "   Workers: $WORKERS"
echo -e "   Timeout: ${TIMEOUT}s"

if [ "${FLASK_ENV:-development}" = "production" ]; then
    if [ -z "${SECRET_KEY:-}" ] || [ "$SECRET_KEY" = "change-this-secret-key-in-production" ] || [ "$SECRET_KEY" = "dev-secret-key-change-in-production" ] || [ "$SECRET_KEY" = "replace-with-a-random-64-char-secret" ]; then
        echo -e "${RED}ERROR: SECRET_KEY insegura ou ausente para produção${NC}"
        exit 1
    fi

    if [ "${REQUIRE_API_KEY_IN_PRODUCTION:-True}" = "True" ] && [ -z "${API_KEY:-${DEEPFACE_API_KEY:-}}" ] && [ -z "${INTERNAL_TOKEN:-${DEEPFACE_INTERNAL_TOKEN:-}}" ]; then
        echo -e "${RED}ERROR: API_KEY ou INTERNAL_TOKEN obrigatório em produção${NC}"
        exit 1
    fi

    if [ "${RATELIMIT_STORAGE_URL:-memory://}" = "memory://" ] && [ "${ALLOW_INSECURE_PRODUCTION_DEFAULTS:-False}" != "True" ]; then
        echo -e "${RED}ERROR: RATELIMIT_STORAGE_URL não pode usar memory:// em produção${NC}"
        exit 1
    fi

    if [ -z "${CORS_ORIGINS:-}" ]; then
        echo -e "${RED}ERROR: CORS_ORIGINS deve ser definido explicitamente em produção${NC}"
        exit 1
    fi

    # MED-07 (auditoria): esta checagem só existia dentro do Config.validate_runtime()
    # em Python, mas HOST nunca era exportado para o ambiente do processo Python quando
    # vinha só do default deste script (não do .env) — gunicorn se ligava em 0.0.0.0 de
    # verdade via --bind, mas o validador Python via os.getenv('HOST', '127.0.0.1') não
    # enxergava isso e nunca disparava. A checagem precisa estar aqui, no shell, que é
    # quem de fato controla o endereço de bind do gunicorn.
    if [ "$HOST" = "0.0.0.0" ] && [ "${ALLOW_INSECURE_PRODUCTION_DEFAULTS:-False}" != "True" ]; then
        echo -e "${RED}ERROR: HOST=0.0.0.0 exige ALLOW_INSECURE_PRODUCTION_DEFAULTS=true para produção${NC}"
        exit 1
    fi

    if [ -z "${FACE_STORAGE_ENCRYPTION_KEY:-}" ]; then
        echo -e "${RED}ERROR: FACE_STORAGE_ENCRYPTION_KEY é obrigatório em produção (criptografia das fotos faciais em repouso)${NC}"
        exit 1
    fi
fi

# ========================================
# 8. Run health check on startup (optional)
# ========================================

# Pre-load DeepFace models for faster first request
echo -e "${YELLOW}Pre-loading DeepFace models (this may take a minute)...${NC}"

python -c "
try:
    from deepface import DeepFace
    import os

    # Pre-build models
    model_name = os.getenv('MODEL_NAME', 'VGG-Face')
    detector = os.getenv('DETECTOR_BACKEND', 'opencv')

    print(f'Loading model: {model_name}')
    print(f'Loading detector: {detector}')

    # This will download and cache models
    DeepFace.build_model(model_name)

    print('✓ Models loaded successfully')
except Exception as e:
    print(f'WARNING: Failed to pre-load models: {e}')
    print('Models will be loaded on first request')
" || echo -e "${YELLOW}⚠ Model pre-loading skipped${NC}"

echo -e "${GREEN}✓${NC} Pre-loading complete"

# ========================================
# 9. Start Gunicorn
# ========================================

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Starting DeepFace API Server${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "Access API at: ${GREEN}http://$HOST:$PORT${NC}"
echo -e "Health check: ${GREEN}http://$HOST:$PORT/health${NC}"
echo ""
echo -e "Press ${RED}Ctrl+C${NC} to stop the server"
echo ""

# Start gunicorn
exec gunicorn \
    --bind "$HOST:$PORT" \
    --workers "$WORKERS" \
    --worker-class sync \
    --timeout "$TIMEOUT" \
    --access-logfile logs/access.log \
    --error-logfile logs/error.log \
    --log-level info \
    --capture-output \
    --enable-stdio-inheritance \
    app:app
