#!/bin/bash

# ========================================
# DeepFace API Installation Script
# Sistema de Ponto Eletrônico
# ========================================

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

clear
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}DeepFace API - Installation Script${NC}"
echo -e "${BLUE}Sistema de Ponto Eletrônico${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# ========================================
# Check if running as root for system install
# ========================================

INSTALL_MODE="local"

if [ "$1" == "--system" ]; then
    INSTALL_MODE="system"

    if [ "$EUID" -ne 0 ]; then
        echo -e "${RED}ERROR: System installation requires root privileges${NC}"
        echo -e "Please run: sudo $0 --system"
        exit 1
    fi

    echo -e "${YELLOW}Installation mode: SYSTEM (systemd service)${NC}"
    INSTALL_DIR="/var/www/deepface-api"
else
    echo -e "${YELLOW}Installation mode: LOCAL (development)${NC}"
    echo -e "${BLUE}For system installation: sudo $0 --system${NC}"
    INSTALL_DIR="$(pwd)"
fi

echo -e "Installation directory: ${GREEN}$INSTALL_DIR${NC}"
echo ""

# ========================================
# 1. Check Prerequisites
# ========================================

echo -e "${BLUE}[1/10] Checking prerequisites...${NC}"

# Check Python 3.8+
if ! command -v python3 &> /dev/null; then
    echo -e "${RED}ERROR: Python 3 not found${NC}"
    echo -e "Install Python 3.8+:"
    echo -e "  Ubuntu/Debian: sudo apt install python3 python3-venv python3-pip"
    echo -e "  CentOS/RHEL: sudo yum install python3 python3-pip"
    exit 1
fi

PYTHON_VERSION=$(python3 --version | cut -d' ' -f2)
PYTHON_MAJOR=$(echo $PYTHON_VERSION | cut -d'.' -f1)
PYTHON_MINOR=$(echo $PYTHON_VERSION | cut -d'.' -f2)

if [ "$PYTHON_MAJOR" -lt 3 ] || ([ "$PYTHON_MAJOR" -eq 3 ] && [ "$PYTHON_MINOR" -lt 8 ]); then
    echo -e "${RED}ERROR: Python 3.8+ required (found $PYTHON_VERSION)${NC}"
    exit 1
fi

echo -e "${GREEN}✓${NC} Python $PYTHON_VERSION"

# Check pip
if ! command -v pip3 &> /dev/null; then
    echo -e "${YELLOW}WARNING: pip3 not found, installing...${NC}"
    sudo apt install python3-pip -y || true
fi

echo -e "${GREEN}✓${NC} pip3 available"

# Check git (optional)
if command -v git &> /dev/null; then
    echo -e "${GREEN}✓${NC} git available"
fi

# ========================================
# 2. Create Installation Directory (System Mode)
# ========================================

if [ "$INSTALL_MODE" == "system" ]; then
    echo -e "${BLUE}[2/10] Creating system directories...${NC}"

    # Create main directory
    if [ ! -d "$INSTALL_DIR" ]; then
        mkdir -p "$INSTALL_DIR"
        echo -e "${GREEN}✓${NC} Created $INSTALL_DIR"
    else
        echo -e "${YELLOW}⚠${NC} Directory already exists: $INSTALL_DIR"
    fi

    # Copy files
    echo -e "${YELLOW}Copying application files...${NC}"
    cp -r ./* "$INSTALL_DIR/" 2>/dev/null || true

    # Change to install directory
    cd "$INSTALL_DIR"
else
    echo -e "${BLUE}[2/10] Using current directory...${NC}"
    echo -e "${GREEN}✓${NC} Local installation mode"
fi

# ========================================
# 3. Create Virtual Environment
# ========================================

echo -e "${BLUE}[3/10] Creating Python virtual environment...${NC}"

VENV_DIR="venv"

if [ -d "$VENV_DIR" ]; then
    echo -e "${YELLOW}⚠ Virtual environment already exists${NC}"
    read -p "Recreate? (y/N): " RECREATE
    if [ "$RECREATE" == "y" ] || [ "$RECREATE" == "Y" ]; then
        echo -e "${YELLOW}Removing old environment...${NC}"
        rm -rf "$VENV_DIR"
    fi
fi

if [ ! -d "$VENV_DIR" ]; then
    python3 -m venv "$VENV_DIR"
    echo -e "${GREEN}✓${NC} Virtual environment created"
else
    echo -e "${GREEN}✓${NC} Using existing virtual environment"
fi

# ========================================
# 4. Activate Virtual Environment
# ========================================

echo -e "${BLUE}[4/10] Activating virtual environment...${NC}"

source "$VENV_DIR/bin/activate"

if [ -z "$VIRTUAL_ENV" ]; then
    echo -e "${RED}ERROR: Failed to activate virtual environment${NC}"
    exit 1
fi

echo -e "${GREEN}✓${NC} Virtual environment activated"

# ========================================
# 5. Upgrade pip
# ========================================

echo -e "${BLUE}[5/10] Upgrading pip...${NC}"

pip install --upgrade pip setuptools wheel --quiet
echo -e "${GREEN}✓${NC} pip upgraded"

# ========================================
# 6. Install Python Dependencies
# ========================================

echo -e "${BLUE}[6/10] Installing Python dependencies...${NC}"
echo -e "${YELLOW}This may take 5-10 minutes (downloading ML models)${NC}"

if [ ! -f "requirements.txt" ]; then
    echo -e "${RED}ERROR: requirements.txt not found${NC}"
    exit 1
fi

pip install -r requirements.txt --no-cache-dir

echo -e "${GREEN}✓${NC} Dependencies installed"

# ========================================
# 7. Create Directories
# ========================================

echo -e "${BLUE}[7/10] Creating application directories...${NC}"

mkdir -p logs
mkdir -p faces_db
mkdir -p storage/temp
mkdir -p storage/cache

echo -e "${GREEN}✓${NC} Directories created"

# ========================================
# 8. Configure Environment
# ========================================

echo -e "${BLUE}[8/10] Configuring environment...${NC}"

if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        cp .env.example .env
        echo -e "${GREEN}✓${NC} Created .env from .env.example"
        echo -e "${YELLOW}⚠ IMPORTANT: Edit .env with production secrets before subir o serviço${NC}"
    else
        echo -e "${YELLOW}WARNING: .env.example not found${NC}"
        echo -e "${YELLOW}Creating minimal .env file...${NC}"

        cat > .env << 'EOF'
# DeepFace API Configuration
HOST=127.0.0.1
PORT=5000
FLASK_ENV=production

# DeepFace Settings
MODEL_NAME=VGG-Face
DETECTOR_BACKEND=opencv
DISTANCE_METRIC=cosine
THRESHOLD=0.40

# Paths
FACES_DB_PATH=./faces_db

# Security
SECRET_KEY=replace-with-a-random-64-char-secret

# CORS
CORS_ORIGINS=http://localhost:8080,http://localhost:8000

# Logging
LOG_LEVEL=INFO
LOG_FILE=logs/deepface_api.log

# Rate Limiting
RATELIMIT_ENABLED=True
RATELIMIT_DEFAULT=100 per minute
RATELIMIT_STORAGE_URL=redis://127.0.0.1:6379/5

# Hardening
HEALTH_DETAILS_ENABLED=False
ALLOW_INSECURE_PRODUCTION_DEFAULTS=False

# Gunicorn
GUNICORN_WORKERS=2
GUNICORN_TIMEOUT=120
EOF

        echo -e "${GREEN}✓${NC} Created minimal .env file"
    fi
else
    echo -e "${GREEN}✓${NC} .env file already exists"
fi

# ========================================
# 9. Set Permissions (System Mode)
# ========================================

if [ "$INSTALL_MODE" == "system" ]; then
    echo -e "${BLUE}[9/10] Setting permissions...${NC}"

    # Create www-data user if doesn't exist
    if ! id -u www-data &>/dev/null; then
        echo -e "${YELLOW}Creating www-data user...${NC}"
        useradd -r -s /bin/false www-data
    fi

    # Set ownership
    chown -R www-data:www-data "$INSTALL_DIR"
    chmod -R 755 "$INSTALL_DIR"

    # Make scripts executable
    chmod +x "$INSTALL_DIR/deepface_start.sh" 2>/dev/null || true
    chmod +x "$INSTALL_DIR/setup_deepface_api.sh" 2>/dev/null || true

    # Set special permissions for writable directories
    chmod 775 "$INSTALL_DIR/logs"
    chmod 775 "$INSTALL_DIR/faces_db"
    chmod 775 "$INSTALL_DIR/storage"

    echo -e "${GREEN}✓${NC} Permissions set"
else
    echo -e "${BLUE}[9/10] Setting local permissions...${NC}"

    chmod +x deepface_start.sh 2>/dev/null || true
    chmod +x setup_deepface_api.sh 2>/dev/null || true

    echo -e "${GREEN}✓${NC} Scripts made executable"
fi

# ========================================
# 10. Install Systemd Service (System Mode)
# ========================================

if [ "$INSTALL_MODE" == "system" ]; then
    echo -e "${BLUE}[10/10] Installing systemd service...${NC}"

    if [ -f "deepface-api.service" ]; then
        # Copy service file
        cp deepface-api.service /etc/systemd/system/

        # Reload systemd
        systemctl daemon-reload

        echo -e "${GREEN}✓${NC} Systemd service installed"
        echo ""
        echo -e "${YELLOW}To manage the service:${NC}"
        echo -e "  Start:   ${GREEN}sudo systemctl start deepface-api${NC}"
        echo -e "  Stop:    ${GREEN}sudo systemctl stop deepface-api${NC}"
        echo -e "  Status:  ${GREEN}sudo systemctl status deepface-api${NC}"
        echo -e "  Enable:  ${GREEN}sudo systemctl enable deepface-api${NC} (start on boot)"
        echo -e "  Logs:    ${GREEN}sudo journalctl -u deepface-api -f${NC}"
    else
        echo -e "${YELLOW}WARNING: deepface-api.service not found${NC}"
    fi
else
    echo -e "${BLUE}[10/10] Local installation complete${NC}"
fi

# ========================================
# Installation Complete
# ========================================

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Installation Complete! 🎉${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

if [ "$INSTALL_MODE" == "system" ]; then
    echo -e "${BLUE}System Installation Summary:${NC}"
    echo -e "  Directory: ${GREEN}$INSTALL_DIR${NC}"
    echo -e "  Service: ${GREEN}deepface-api.service${NC}"
    echo -e "  User: ${GREEN}www-data${NC}"
    echo ""
    echo -e "${YELLOW}Next Steps:${NC}"
    echo -e "  1. Edit configuration:"
    echo -e "     ${GREEN}sudo nano $INSTALL_DIR/.env${NC}"
    echo ""
    echo -e "  2. Start the service:"
    echo -e "     ${GREEN}sudo systemctl start deepface-api${NC}"
    echo ""
    echo -e "  3. Enable on boot (optional):"
    echo -e "     ${GREEN}sudo systemctl enable deepface-api${NC}"
    echo ""
    echo -e "  4. Check status:"
    echo -e "     ${GREEN}sudo systemctl status deepface-api${NC}"
    echo ""
    echo -e "  5. Test the API:"
    echo -e "     ${GREEN}curl http://localhost:5000/health${NC}"
else
    echo -e "${BLUE}Local Installation Summary:${NC}"
    echo -e "  Directory: ${GREEN}$INSTALL_DIR${NC}"
    echo -e "  Virtual env: ${GREEN}$VENV_DIR${NC}"
    echo ""
    echo -e "${YELLOW}Next Steps:${NC}"
    echo -e "  1. Edit configuration (if needed):"
    echo -e "     ${GREEN}nano .env${NC}"
    echo ""
    echo -e "  2. Start the development server:"
    echo -e "     ${GREEN}./deepface_start.sh${NC}"
    echo ""
    echo -e "  Or manually:"
    echo -e "     ${GREEN}source venv/bin/activate${NC}"
    echo -e "     ${GREEN}python app.py${NC}"
    echo ""
    echo -e "  3. Test the API:"
    echo -e "     ${GREEN}curl http://localhost:5000/health${NC}"
fi

echo ""
echo -e "${BLUE}Documentation:${NC}"
echo -e "  README: ${GREEN}cat README.md${NC}"
echo -e "  DeepFace: ${GREEN}https://github.com/serengil/deepface${NC}"
echo ""
echo -e "${GREEN}========================================${NC}"

# Deactivate virtual environment
deactivate 2>/dev/null || true
