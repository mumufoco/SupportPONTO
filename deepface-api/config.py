"""
DeepFace API Configuration
"""

import os
import secrets
import hashlib
from dotenv import load_dotenv

load_dotenv()


def _load_face_encryption_key():
    """
    Chave AES-256 usada para criptografar em repouso as fotos faciais cadastradas
    (ver auditoria CRIT-07). Aceita FACE_STORAGE_ENCRYPTION_KEY como 64 caracteres hex
    (32 bytes); qualquer outro valor não vazio é normalizado via SHA-256 para 32 bytes.
    Sem a variável definida, gera uma chave efêmera por processo — aceitável apenas em
    desenvolvimento, pois Config.validate_runtime() bloqueia o boot em produção sem ela
    (mesmo padrão já usado para SECRET_KEY/API_KEY neste arquivo).
    """
    raw = os.getenv('FACE_STORAGE_ENCRYPTION_KEY', '').strip()
    if not raw:
        return secrets.token_bytes(32)

    try:
        key = bytes.fromhex(raw)
    except ValueError:
        key = raw.encode('utf-8')

    return key if len(key) == 32 else hashlib.sha256(key).digest()


def _get_bool(name, default=False):
    value = os.getenv(name)
    if value is None:
        return bool(default)
    return value.lower() == 'true'


def _get_list(name, default=''):
    value = os.getenv(name, default)
    return [item.strip() for item in value.split(',') if item.strip()]


class Config:
    """Base configuration"""

    SECRET_KEY = os.getenv('SECRET_KEY') or secrets.token_urlsafe(32)
    DEBUG = _get_bool('DEBUG', False)

    API_KEY = os.getenv('API_KEY') or os.getenv('DEEPFACE_API_KEY')
    INTERNAL_TOKEN = os.getenv('INTERNAL_TOKEN') or os.getenv('DEEPFACE_INTERNAL_TOKEN')
    REQUIRE_API_KEY_IN_PRODUCTION = _get_bool('REQUIRE_API_KEY_IN_PRODUCTION', True)

    HOST = os.getenv('HOST', '127.0.0.1')
    PORT = int(os.getenv('PORT', 5000))

    MODEL_NAME = os.getenv('MODEL_NAME', 'VGG-Face')
    DETECTOR_BACKEND = os.getenv('DETECTOR_BACKEND', 'opencv')
    DISTANCE_METRIC = os.getenv('DISTANCE_METRIC', 'cosine')
    ENFORCE_DETECTION = _get_bool('ENFORCE_DETECTION', True)
    ALIGN = _get_bool('ALIGN', True)

    THRESHOLD = float(os.getenv('THRESHOLD', 0.40))

    THRESHOLDS = {
        'VGG-Face': {'cosine': 0.40, 'euclidean': 0.60, 'euclidean_l2': 0.86},
        'Facenet': {'cosine': 0.40, 'euclidean': 10, 'euclidean_l2': 0.80},
        'Facenet512': {'cosine': 0.30, 'euclidean': 23.56, 'euclidean_l2': 1.04},
        'ArcFace': {'cosine': 0.68, 'euclidean': 4.15, 'euclidean_l2': 1.13},
        'Dlib': {'cosine': 0.07, 'euclidean': 0.6, 'euclidean_l2': 0.4},
        'SFace': {'cosine': 0.593, 'euclidean': 10.734, 'euclidean_l2': 1.055},
        'OpenFace': {'cosine': 0.10, 'euclidean': 0.55, 'euclidean_l2': 0.55},
        'DeepFace': {'cosine': 0.23, 'euclidean': 64, 'euclidean_l2': 0.64},
        'DeepID': {'cosine': 0.015, 'euclidean': 45, 'euclidean_l2': 0.17},
    }

    FACES_DB_PATH = os.getenv('FACES_DB_PATH', '../storage/faces')
    FACE_ENCRYPTION_KEY = _load_face_encryption_key()

    MAX_FILE_SIZE = int(os.getenv('MAX_FILE_SIZE', os.getenv('BIOMETRIC_FACE_MAX_BYTES', 3 * 1024 * 1024)))
    ALLOWED_EXTENSIONS = {'jpg', 'jpeg', 'png'}

    ANTI_SPOOFING_ENABLED = _get_bool('ANTI_SPOOFING_ENABLED', True)
    MIN_FACE_SIZE = int(os.getenv('MIN_FACE_SIZE', 80))

    CORS_ORIGINS = _get_list('CORS_ORIGINS', 'http://localhost:8000,http://localhost:8080')

    HEALTH_DETAILS_ENABLED = _get_bool('HEALTH_DETAILS_ENABLED', False)
    TEMP_DIR = os.getenv('TEMP_DIR', os.path.join(FACES_DB_PATH, 'temp'))
    TEMP_FILE_MAX_AGE_SECONDS = int(os.getenv('TEMP_FILE_MAX_AGE_SECONDS', 3600))

    RATELIMIT_ENABLED = _get_bool('RATELIMIT_ENABLED', True)
    RATELIMIT_DEFAULT = os.getenv('RATELIMIT_DEFAULT', '100 per minute')
    RATELIMIT_STORAGE_URL = os.getenv('RATELIMIT_STORAGE_URL', 'memory://')
    ALLOW_INSECURE_PRODUCTION_DEFAULTS = _get_bool('ALLOW_INSECURE_PRODUCTION_DEFAULTS', False)

    LOG_LEVEL = os.getenv('LOG_LEVEL', 'INFO')
    LOG_FILE = os.getenv('LOG_FILE', 'logs/deepface_api.log')

    CACHE_ENABLED = _get_bool('CACHE_ENABLED', True)
    CACHE_TTL = int(os.getenv('CACHE_TTL', 300))

    @staticmethod
    def get_threshold():
        model = Config.MODEL_NAME
        metric = Config.DISTANCE_METRIC
        if model in Config.THRESHOLDS and metric in Config.THRESHOLDS[model]:
            return Config.THRESHOLDS[model][metric]
        return Config.THRESHOLD

    @staticmethod
    def allowed_file(filename):
        return '.' in filename and filename.rsplit('.', 1)[1].lower() in Config.ALLOWED_EXTENSIONS

    @staticmethod
    def validate_runtime(environment=None):
        """Validate runtime configuration and fail closed in production."""
        env_name = (environment or os.getenv('FLASK_ENV', 'development')).lower()
        issues = []

        secret_key = os.getenv('SECRET_KEY', '').strip()
        api_key = os.getenv('API_KEY', '').strip() or os.getenv('DEEPFACE_API_KEY', '').strip()
        internal_token = os.getenv('INTERNAL_TOKEN', '').strip() or os.getenv('DEEPFACE_INTERNAL_TOKEN', '').strip()
        host = os.getenv('HOST', '127.0.0.1').strip()
        rate_storage = os.getenv('RATELIMIT_STORAGE_URL', 'memory://').strip()
        cors_origins = _get_list('CORS_ORIGINS', '')

        if env_name == 'production':
            if not secret_key or secret_key in {'change-this-secret-key-in-production', 'dev-secret-key-change-in-production', 'replace-with-a-random-64-char-secret'}:
                issues.append('SECRET_KEY ausente ou insegura para produção')

            if Config.REQUIRE_API_KEY_IN_PRODUCTION and not (api_key or internal_token):
                issues.append('API_KEY ou INTERNAL_TOKEN é obrigatório em produção')

            if rate_storage == 'memory://' and not Config.ALLOW_INSECURE_PRODUCTION_DEFAULTS:
                issues.append('RATELIMIT_STORAGE_URL não pode usar memory:// em produção')

            if not cors_origins:
                issues.append('CORS_ORIGINS deve ser definido explicitamente em produção')

            if host == '0.0.0.0' and not Config.ALLOW_INSECURE_PRODUCTION_DEFAULTS:
                issues.append('HOST=0.0.0.0 exige ALLOW_INSECURE_PRODUCTION_DEFAULTS=true para produção')

            if not os.getenv('FACE_STORAGE_ENCRYPTION_KEY', '').strip():
                issues.append('FACE_STORAGE_ENCRYPTION_KEY é obrigatório em produção (criptografia das fotos faciais em repouso)')

        return issues


class DevelopmentConfig(Config):
    DEBUG = True
    LOG_LEVEL = 'DEBUG'


class ProductionConfig(Config):
    DEBUG = False
    LOG_LEVEL = 'WARNING'


config = {
    'development': DevelopmentConfig,
    'production': ProductionConfig,
    'default': DevelopmentConfig,
}
