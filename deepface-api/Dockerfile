# ==============================================================================
# Dockerfile - DeepFace API
# Serviço de Reconhecimento Facial para Sistema de Ponto Eletrônico
# ==============================================================================

FROM python:3.11-slim

LABEL maintainer="Support Solo Sondagens"
LABEL description="Deep Face Recognition API - Flask"
LABEL version="1.1.498"

# Variáveis de ambiente
ENV PYTHONUNBUFFERED=1 \
    PYTHONDONTWRITEBYTECODE=1 \
    PIP_NO_CACHE_DIR=1 \
    PIP_DISABLE_PIP_VERSION_CHECK=1 \
    FLASK_ENV=production \
    TZ=America/Sao_Paulo

# Instalar dependências do sistema
RUN apt-get update && apt-get install -y --no-install-recommends \
    # Compiladores necessários para algumas bibliotecas Python
    gcc \
    g++ \
    cmake \
    # Bibliotecas para OpenCV
    libglib2.0-0 \
    libsm6 \
    libxext6 \
    libxrender-dev \
    libgomp1 \
    libgl1-mesa-glx \
    libglib2.0-0 \
    # Utilitários
    curl \
    && rm -rf /var/lib/apt/lists/*

# Criar usuário não-root
RUN groupadd -r deepface && useradd -r -g deepface deepface

# Diretório de trabalho
WORKDIR /app

# Copiar requirements e instalar dependências Python
COPY requirements.txt .
RUN pip install --upgrade pip setuptools wheel \
    && pip install --no-cache-dir -r requirements.txt

# Copiar código da API
COPY --chown=deepface:deepface . .

# Criar diretórios necessários
RUN mkdir -p \
    /app/faces \
    /app/faces/temp \
    /app/logs \
    && chown -R deepface:deepface /app \
    && chmod -R 755 /app

# Mudar para usuário não-root
USER deepface

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://localhost:5000/health || exit 1

# Expor porta
EXPOSE 5000

# Comando para iniciar a API
CMD ["python", "app.py"]
