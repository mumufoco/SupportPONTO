#!/bin/bash

#==============================================================================
# DeepFace API Start Script
# Sistema de Ponto Eletrônico Brasileiro
#==============================================================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
DEEPFACE_DIR="$PROJECT_ROOT/deepface-api"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  DeepFace API - Startup Script${NC}"
echo -e "${BLUE}========================================${NC}\n"

# Check if Python 3 is installed
if ! command -v python3 &> /dev/null; then
    echo -e "${RED}❌ Python 3 is not installed${NC}"
    echo -e "${YELLOW}Please install Python 3.8 or higher${NC}"
    exit 1
fi

PYTHON_VERSION=$(python3 --version | awk '{print $2}')
echo -e "${GREEN}✓${NC} Python version: $PYTHON_VERSION"

# Navigate to deepface-api directory
cd "$DEEPFACE_DIR"

# Check if virtual environment exists
if [ ! -d "venv" ]; then
    echo -e "\n${YELLOW}⚠${NC}  Virtual environment not found. Creating..."
    python3 -m venv venv
    echo -e "${GREEN}✓${NC} Virtual environment created"
fi

# Activate virtual environment
echo -e "\n${BLUE}→${NC} Activating virtual environment..."
source venv/bin/activate

# Check if requirements need to be installed/updated
REQUIREMENTS_HASH_FILE=".requirements_hash"
CURRENT_HASH=$(md5sum requirements.txt 2>/dev/null | awk '{print $1}')

if [ -f "$REQUIREMENTS_HASH_FILE" ]; then
    STORED_HASH=$(cat "$REQUIREMENTS_HASH_FILE")
else
    STORED_HASH=""
fi

if [ "$CURRENT_HASH" != "$STORED_HASH" ]; then
    echo -e "${YELLOW}⚠${NC}  Requirements changed. Installing/updating dependencies..."
    pip install --upgrade pip
    pip install -r requirements.txt
    echo "$CURRENT_HASH" > "$REQUIREMENTS_HASH_FILE"
    echo -e "${GREEN}✓${NC} Dependencies installed/updated"
else
    echo -e "${GREEN}✓${NC} Dependencies up to date"
fi

# Check if .env exists
if [ ! -f ".env" ]; then
    echo -e "\n${YELLOW}⚠${NC}  .env file not found. Copying from .env.example..."
    cp .env.example .env
    echo -e "${GREEN}✓${NC} .env file created"
    echo -e "${YELLOW}⚠${NC}  Please configure your .env file before starting in production!"
fi

# Create logs directory if it doesn't exist
mkdir -p logs

# Check if running in development or production mode
if [ "${1}" == "--production" ] || [ "${FLASK_ENV}" == "production" ]; then
    echo -e "\n${BLUE}→${NC} Starting DeepFace API in ${GREEN}PRODUCTION${NC} mode with Gunicorn..."

    # Number of workers (2 * CPU cores + 1)
    WORKERS=$(( $(nproc) * 2 + 1 ))

    # Start with gunicorn
    exec gunicorn \
        --bind 0.0.0.0:5000 \
        --workers $WORKERS \
        --timeout 120 \
        --access-logfile logs/access.log \
        --error-logfile logs/error.log \
        --log-level info \
        app:app

else
    echo -e "\n${BLUE}→${NC} Starting DeepFace API in ${YELLOW}DEVELOPMENT${NC} mode..."
    echo -e "${YELLOW}⚠${NC}  For production use: $0 --production\n"

    # Start with Flask development server
    exec python app.py
fi
