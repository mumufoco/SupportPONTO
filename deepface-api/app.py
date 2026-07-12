"""
DeepFace API - Facial Recognition Service
Sistema de Ponto Eletrônico Brasileiro
"""

import os
import base64
import hashlib
import logging
import shutil
import tempfile
from datetime import datetime
from io import BytesIO
from functools import wraps

from flask import Flask, request, jsonify
from flask_cors import CORS
from flask_limiter import Limiter
from flask_limiter.util import get_remote_address
from PIL import Image
from deepface import DeepFace
from cryptography.hazmat.primitives.ciphers.aead import AESGCM
import numpy as np

from config import config, Config

# Fotos faciais cadastradas ficam sempre criptografadas em repouso (AES-256-GCM) sob
# este sufixo — nunca gravamos JPEG em claro em FACES_DB_PATH a partir desta versão
# (ver auditoria CRIT-07). AES-GCM foi escolhido por já vir com autenticação (evita
# adulteração silenciosa do arquivo cifrado) e por ser suportado nativamente pelo
# pacote `cryptography` sem dependências extras de sistema.
FACE_FILE_SUFFIX = '.enc'
FACE_NONCE_SIZE = 12

# Initialize Flask app
app = Flask(__name__)

# Load configuration
env = os.getenv('FLASK_ENV', 'development').lower()
app.config.from_object(config.get(env, config['default']))
app.config['MAX_CONTENT_LENGTH'] = Config.MAX_FILE_SIZE * 2

# Setup CORS
CORS(app, resources={r"/*": {"origins": Config.CORS_ORIGINS}}, supports_credentials=False)

# Setup rate limiting
limiter = Limiter(
    app=app,
    key_func=get_remote_address,
    default_limits=[Config.RATELIMIT_DEFAULT] if Config.RATELIMIT_ENABLED else [],
    storage_uri=Config.RATELIMIT_STORAGE_URL
)

# Ensure directories exist before logging handlers are initialized
os.makedirs(Config.FACES_DB_PATH, exist_ok=True)
os.makedirs(Config.TEMP_DIR, exist_ok=True)
log_dir = os.path.dirname(Config.LOG_FILE)
if log_dir:
    os.makedirs(log_dir, exist_ok=True)

# Setup logging
logging.basicConfig(
    level=getattr(logging, Config.LOG_LEVEL),
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler(Config.LOG_FILE),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

runtime_issues = Config.validate_runtime(env)
if runtime_issues:
    raise RuntimeError('DeepFace API bootstrap blocked: ' + '; '.join(runtime_issues))


def is_internal_request():
    internal_token = request.headers.get('X-Internal-Token')
    return bool(Config.INTERNAL_TOKEN and internal_token and internal_token == Config.INTERNAL_TOKEN)


def is_api_key_request():
    api_key = request.headers.get('X-API-Key')
    return bool(Config.API_KEY and api_key and api_key == Config.API_KEY)


def require_api_key(f):
    """
    Decorator to require service authentication for endpoints.
    Accepts X-API-Key and/or X-Internal-Token.
    """
    @wraps(f)
    def decorated_function(*args, **kwargs):
        flask_env = os.getenv('FLASK_ENV', 'production')
        if flask_env == 'development' and not Config.API_KEY and not Config.INTERNAL_TOKEN:
            logger.warning('DeepFace API auth disabled in development mode')
            return f(*args, **kwargs)

        if is_api_key_request() or is_internal_request():
            return f(*args, **kwargs)

        logger.warning('Unauthorized DeepFace request from %s', get_remote_address())
        return jsonify({
            'success': False,
            'error': 'Unauthorized request'
        }), 401

    return decorated_function


def cleanup_stale_temp_files():
    now = datetime.now().timestamp()
    removed = 0
    for filename in os.listdir(Config.TEMP_DIR):
        path = os.path.join(Config.TEMP_DIR, filename)
        if not os.path.isfile(path):
            continue
        try:
            if now - os.path.getmtime(path) > Config.TEMP_FILE_MAX_AGE_SECONDS:
                os.remove(path)
                removed += 1
        except OSError:
            logger.warning('Unable to cleanup temp file %s', path)
    return removed


def decode_base64_image(base64_string):
    """
    Decode base64 image string to PIL Image
    """
    try:
        # Remove data:image prefix if present
        if 'base64,' in base64_string:
            base64_string = base64_string.split('base64,')[1]

        # Decode base64
        image_data = base64.b64decode(base64_string, validate=True)

        # Check file size
        if len(image_data) > Config.MAX_FILE_SIZE:
            raise ValueError(f'Image size exceeds maximum allowed ({Config.MAX_FILE_SIZE} bytes)')

        # Open image
        image = Image.open(BytesIO(image_data))

        return image

    except Exception as e:
        logger.error(f'Error decoding base64 image: {str(e)}')
        raise


def save_temp_image(image, prefix='temp'):
    """
    Save PIL Image to temporary file
    """
    try:
        os.makedirs(Config.TEMP_DIR, exist_ok=True)

        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S_%f')
        filename = f'{prefix}_{timestamp}.jpg'
        filepath = os.path.join(Config.TEMP_DIR, filename)

        # Convert to RGB if necessary
        if image.mode != 'RGB':
            image = image.convert('RGB')

        image.save(filepath, 'JPEG', quality=95)

        return filepath

    except Exception as e:
        logger.error(f'Error saving temp image: {str(e)}')
        raise


def cleanup_temp_file(filepath):
    """
    Remove temporary file
    """
    try:
        if os.path.exists(filepath):
            os.remove(filepath)
    except Exception as e:
        logger.warning(f'Error removing temp file {filepath}: {str(e)}')




def normalize_similarity(distance, metric):
    """Normalize distance into a 0..1 similarity score."""
    try:
        distance = float(distance)
    except (TypeError, ValueError):
        return 0.0

    metric = (metric or '').lower()
    if metric == 'cosine':
        similarity = 1.0 - (distance / 2.0)
    else:
        similarity = 1.0 / (1.0 + max(distance, 0.0))

    return max(0.0, min(1.0, float(similarity)))


def list_face_images(db_path):
    supported = {'.jpg', '.jpeg', '.png'}
    images = []
    for current_root, _, filenames in os.walk(db_path):
        for filename in filenames:
            if os.path.splitext(filename)[1].lower() in supported:
                images.append(os.path.join(current_root, filename))
    images.sort()
    return images


def purge_representation_cache(db_path):
    removed = 0
    for filename in os.listdir(db_path):
        if filename.startswith('representations_') and filename.endswith('.pkl'):
            try:
                os.remove(os.path.join(db_path, filename))
                removed += 1
            except OSError:
                logger.warning('Unable to remove DeepFace representation cache %s', filename)
    return removed

def calculate_image_hash(filepath):
    """
    Calculate SHA-256 hash of image file
    """
    try:
        with open(filepath, 'rb') as f:
            return hashlib.sha256(f.read()).hexdigest()
    except Exception as e:
        logger.error(f'Error calculating image hash: {str(e)}')
        return None


def encrypt_face_bytes(plaintext):
    """AES-256-GCM: retorna nonce (12 bytes) || ciphertext+tag."""
    aesgcm = AESGCM(Config.FACE_ENCRYPTION_KEY)
    nonce = os.urandom(FACE_NONCE_SIZE)
    return nonce + aesgcm.encrypt(nonce, plaintext, None)


def decrypt_face_bytes(blob):
    aesgcm = AESGCM(Config.FACE_ENCRYPTION_KEY)
    nonce, ciphertext = blob[:FACE_NONCE_SIZE], blob[FACE_NONCE_SIZE:]
    return aesgcm.decrypt(nonce, ciphertext, None)


def calculate_encrypted_image_hash(encrypted_filepath):
    """Hash SHA-256 do conteúdo em claro de um arquivo .enc (decripta em memória)."""
    try:
        with open(encrypted_filepath, 'rb') as f:
            plaintext = decrypt_face_bytes(f.read())
        return hashlib.sha256(plaintext).hexdigest()
    except Exception as e:
        logger.error(f'Error calculating encrypted image hash: {str(e)}')
        return None


def materialize_decrypted_faces_db():
    """
    Descriptografa cada rosto cadastrado em um diretório temporário exclusivo desta
    chamada, para que o DeepFace possa operar sobre arquivos JPEG reais. O texto puro
    nunca é persistido fora dessa janela: o chamador é responsável por apagar o
    diretório retornado (shutil.rmtree) assim que terminar — é exatamente esse
    apagamento que garante que FACES_DB_PATH permanece só com arquivos .enc em repouso.

    Compatibilidade retroativa: se encontrar uma foto antiga ainda em texto puro
    (.jpg/.jpeg/.png, de antes desta correção), ela é criptografada em
    FACES_DB_PATH nesse mesmo momento e o arquivo original é removido — migração
    automática e transparente, sem exigir script separado nem interromper o
    reconhecimento em andamento (ver auditoria CRIT-07).

    Returns:
        tuple[str, int]: (diretório temporário, quantidade de rostos materializados)
    """
    scratch_dir = tempfile.mkdtemp(dir=Config.TEMP_DIR, prefix='decrypted_faces_')
    temp_dir_abs = os.path.abspath(Config.TEMP_DIR)
    count = 0

    for current_root, dirnames, filenames in os.walk(Config.FACES_DB_PATH):
        current_root_abs = os.path.abspath(current_root)
        # TEMP_DIR normalmente vive dentro de FACES_DB_PATH (padrão: FACES_DB_PATH/temp)
        # — nunca deve ser tratado como rosto cadastrado, tanto para não vazar imagens
        # de processamento em trânsito quanto para não entrar no próprio scratch_dir.
        if current_root_abs == temp_dir_abs or current_root_abs.startswith(temp_dir_abs + os.sep):
            dirnames[:] = []
            continue

        for filename in filenames:
            lower = filename.lower()
            is_encrypted = lower.endswith(FACE_FILE_SUFFIX)
            is_legacy_plain = (not is_encrypted) and os.path.splitext(lower)[1] in {'.jpg', '.jpeg', '.png'}
            if not is_encrypted and not is_legacy_plain:
                continue

            source_path = os.path.join(current_root, filename)
            relative_dir = os.path.relpath(current_root, Config.FACES_DB_PATH)
            target_dir = os.path.join(scratch_dir, relative_dir) if relative_dir != '.' else scratch_dir
            os.makedirs(target_dir, exist_ok=True)

            try:
                if is_encrypted:
                    with open(source_path, 'rb') as f:
                        plaintext = decrypt_face_bytes(f.read())
                    target_name = filename[: -len(FACE_FILE_SUFFIX)]
                else:
                    with open(source_path, 'rb') as f:
                        plaintext = f.read()
                    target_name = filename

                    encrypted_target = source_path + FACE_FILE_SUFFIX
                    with open(encrypted_target, 'wb') as f:
                        f.write(encrypt_face_bytes(plaintext))
                    os.remove(source_path)
                    logger.info('Migrated legacy plaintext face to encrypted storage: %s', source_path)

                with open(os.path.join(target_dir, target_name), 'wb') as f:
                    f.write(plaintext)
                count += 1
            except Exception as e:
                logger.error('Failed to materialize enrolled face %s: %s', source_path, str(e))

    return scratch_dir, count


@app.route('/health', methods=['GET'])
def health():
    """Public liveness probe without sensitive details."""
    return jsonify({
        'status': 'ok',
        'service': 'deepface-api',
        'version': '1.1.498',
        'timestamp': datetime.now().isoformat()
    }), 200


@app.route('/health/details', methods=['GET'])
@require_api_key
def health_details():
    """Protected health probe for internal diagnostics."""
    if not (Config.HEALTH_DETAILS_ENABLED or env == 'development'):
        return jsonify({
            'success': False,
            'error': 'Detailed health endpoint disabled'
        }), 404

    return jsonify({
        'status': 'ok',
        'service': 'deepface-api',
        'version': '1.1.498',
        'model': Config.MODEL_NAME,
        'detector': Config.DETECTOR_BACKEND,
        'distance_metric': Config.DISTANCE_METRIC,
        'threshold': Config.get_threshold(),
        'temp_dir': Config.TEMP_DIR,
        'temp_cleanup_seconds': Config.TEMP_FILE_MAX_AGE_SECONDS,
        'timestamp': datetime.now().isoformat()
    }), 200


@app.route('/enroll', methods=['POST'])
@limiter.limit("20 per minute")
@require_api_key
def enroll():
    """
    Enroll a new face
    Expected JSON:
    {
        "employee_id": "123",
        "photo": "base64_encoded_image"
    }
    """
    try:
        data = request.get_json()

        # Validate input
        if not data or 'employee_id' not in data or 'photo' not in data:
            return jsonify({
                'success': False,
                'error': 'Missing required fields: employee_id, photo'
            }), 400

        employee_id = str(data['employee_id'])
        photo_base64 = data['photo']
        force = bool(data.get('force', False))
        requester_ip = data.get('requester_ip') or request.headers.get('X-Forwarded-For', request.remote_addr)
        requester_id = data.get('requester_id') or request.headers.get('X-Requester-Id')
        requester_role = data.get('requester_role') or request.headers.get('X-Requester-Role')

        logger.info(f'Enrolling face for employee {employee_id} (force={force})')

        # Decode image
        image = decode_base64_image(photo_base64)

        # Save temporary image
        temp_path = save_temp_image(image, f'enroll_{employee_id}')

        try:
            # Extract faces using DeepFace
            # ALTO-06 (auditoria): ANTI_SPOOFING_ENABLED existia na config mas nunca era
            # passado a nenhuma chamada do DeepFace — a flag não tinha efeito real.
            faces = DeepFace.extract_faces(
                img_path=temp_path,
                detector_backend=Config.DETECTOR_BACKEND,
                enforce_detection=Config.ENFORCE_DETECTION,
                align=Config.ALIGN,
                anti_spoofing=Config.ANTI_SPOOFING_ENABLED
            )

            # Validate face count
            if len(faces) == 0:
                cleanup_temp_file(temp_path)
                return jsonify({
                    'success': False,
                    'error': 'No face detected in the image'
                }), 400

            if len(faces) > 1:
                cleanup_temp_file(temp_path)
                return jsonify({
                    'success': False,
                    'error': f'Multiple faces detected ({len(faces)}). Please use a photo with only one face'
                }), 400

            face = faces[0]

            # extract_faces() com anti_spoofing=True anota is_real/antispoof_score no
            # dict retornado mas não levanta exceção por si só — a checagem explícita
            # abaixo é o que de fato rejeita uma foto impressa/tela usada para cadastro.
            if Config.ANTI_SPOOFING_ENABLED and face.get('is_real') is False:
                cleanup_temp_file(temp_path)
                return jsonify({
                    'success': False,
                    'error': 'Possível tentativa de fraude detectada (foto impressa ou em tela). Use uma captura ao vivo.',
                    'code': 'spoof_detected'
                }), 400

            # Check face size
            face_region = face['facial_area']
            face_width = face_region['w']
            face_height = face_region['h']

            if face_width < Config.MIN_FACE_SIZE or face_height < Config.MIN_FACE_SIZE:
                cleanup_temp_file(temp_path)
                return jsonify({
                    'success': False,
                    'error': f'Face too small. Minimum size: {Config.MIN_FACE_SIZE}x{Config.MIN_FACE_SIZE} pixels'
                }), 400

            # Create employee directory
            employee_dir = os.path.join(Config.FACES_DB_PATH, employee_id)
            os.makedirs(employee_dir, exist_ok=True)

            # Save face image — sempre criptografada em repouso (ver auditoria CRIT-07).
            # Aceita tanto o layout legado (.jpg em claro, de antes desta correção) quanto
            # o atual (.jpg.enc) ao decidir se já existe cadastro para este funcionário.
            face_filename = f'{employee_id}_face.jpg{FACE_FILE_SUFFIX}'
            face_path = os.path.join(employee_dir, face_filename)
            legacy_face_path = os.path.join(employee_dir, f'{employee_id}_face.jpg')

            already_enrolled = os.path.exists(face_path) or os.path.exists(legacy_face_path)
            previous_image_hash = None
            if os.path.exists(face_path):
                previous_image_hash = calculate_encrypted_image_hash(face_path)
            elif os.path.exists(legacy_face_path):
                previous_image_hash = calculate_image_hash(legacy_face_path)

            if already_enrolled and not force:
                cleanup_temp_file(temp_path)
                return jsonify({
                    'success': False,
                    'error': 'Face already enrolled for this employee. Delete or force re-enroll to replace it.',
                    'code': 'face_already_enrolled'
                }), 409

            if already_enrolled and force:
                logger.info(
                    'Forced re-enroll requested',
                    extra={
                        'action': 'FORCED_REENROLL_PENDING',
                        'employee_id': employee_id,
                        'previous_image_hash': previous_image_hash,
                        'requester_ip': requester_ip,
                        'requester_id': requester_id,
                        'requester_role': requester_role,
                        'timestamp': datetime.now().isoformat(),
                    },
                )
                if os.path.exists(legacy_face_path):
                    os.remove(legacy_face_path)

            # Serializa o JPEG em memória, calcula o hash sobre o conteúdo em claro
            # (mesma semântica de antes, usada por deleteFaceByHash no lado PHP) e só
            # então grava a versão criptografada em disco — o texto puro nunca chega a
            # tocar o disco.
            rgb_image = image.convert('RGB') if image.mode != 'RGB' else image
            buffer = BytesIO()
            rgb_image.save(buffer, 'JPEG', quality=95)
            plaintext_bytes = buffer.getvalue()

            image_hash = hashlib.sha256(plaintext_bytes).hexdigest()

            with open(face_path, 'wb') as f:
                f.write(encrypt_face_bytes(plaintext_bytes))

            if already_enrolled and force:
                logger.info(
                    'Forced re-enroll completed',
                    extra={
                        'action': 'FORCED_REENROLL',
                        'employee_id': employee_id,
                        'previous_image_hash': previous_image_hash,
                        'new_image_hash': image_hash,
                        'requester_ip': requester_ip,
                        'requester_id': requester_id,
                        'requester_role': requester_role,
                        'timestamp': datetime.now().isoformat(),
                    },
                )

            # Get face confidence
            confidence = face.get('confidence', 0)

            logger.info(f'Face enrolled successfully for employee {employee_id}')

            return jsonify({
                'success': True,
                'employee_id': employee_id,
                'image_hash': image_hash,
                'previous_image_hash': previous_image_hash if already_enrolled and force else None,
                'confidence': float(confidence),
                'facial_area': face_region,
                'message': 'Face enrolled successfully'
            }), 200

        finally:
            cleanup_temp_file(temp_path)

    except ValueError as e:
        logger.error(f'Validation error in enroll: {str(e)}')
        return jsonify({
            'success': False,
            'error': str(e)
        }), 400

    except Exception as e:
        logger.error(f'Error in enroll: {str(e)}')
        return jsonify({
            'success': False,
            'error': 'Internal server error'
        }), 500


@app.route('/recognize', methods=['POST'])
@limiter.limit("10 per minute")
@require_api_key
def recognize():
    """
    Recognize a face from the database
    Expected JSON:
    {
        "photo": "base64_encoded_image",
        "threshold": 0.40 (optional)
    }
    """
    try:
        data = request.get_json()

        # Validate input
        if not data or 'photo' not in data:
            return jsonify({
                'success': False,
                'error': 'Missing required field: photo'
            }), 400

        photo_base64 = data['photo']
        threshold = float(data.get('threshold', Config.get_threshold()))

        logger.info(f'Recognizing face with threshold {threshold}')

        # Decode image
        image = decode_base64_image(photo_base64)

        # Save temporary image
        temp_path = save_temp_image(image, 'recognize')

        scratch_dir = None
        try:
            # Rostos cadastrados ficam criptografados em repouso (ver auditoria CRIT-07) —
            # materializa um diretório temporário exclusivo desta chamada com os arquivos
            # decifrados para o DeepFace poder buscar, e apaga tudo no finally, esteja lá
            # embaixo qual for o resultado.
            scratch_dir, enrolled_count = materialize_decrypted_faces_db()

            if enrolled_count == 0:
                logger.info('No enrolled faces available for recognition')
                return jsonify({
                    'success': True,
                    'recognized': False,
                    'message': 'No matching face found'
                }), 200

            # Find matching faces
            # ALTO-06 (auditoria): anti_spoofing habilitado no reconhecimento ao vivo —
            # é aqui, não no enroll, que um ataque de apresentação (foto impressa, tela
            # de celular) contra o ponto facial realmente importa.
            result = DeepFace.find(
                img_path=temp_path,
                db_path=scratch_dir,
                model_name=Config.MODEL_NAME,
                detector_backend=Config.DETECTOR_BACKEND,
                distance_metric=Config.DISTANCE_METRIC,
                enforce_detection=Config.ENFORCE_DETECTION,
                align=Config.ALIGN,
                anti_spoofing=Config.ANTI_SPOOFING_ENABLED,
                silent=True,
                refresh_database=True
            )

            # Check if any matches found
            if result is None or len(result) == 0 or len(result[0]) == 0:
                logger.info('No matching face found')
                return jsonify({
                    'success': True,
                    'recognized': False,
                    'message': 'No matching face found'
                }), 200

            # Get the best match (first result)
            matches = result[0]
            best_match = matches.iloc[0]

            distance = float(best_match['distance'])
            identity = best_match['identity']

            # Check if distance is below threshold
            if distance > threshold:
                logger.info(f'Match found but distance ({distance}) exceeds threshold ({threshold})')
                return jsonify({
                    'success': True,
                    'recognized': False,
                    'message': 'Face found but similarity too low',
                    'distance': distance,
                    'threshold': threshold
                }), 200

            # Extract employee_id from identity path
            # Format: {scratch_dir}/employee_id/employee_id_face.jpg
            employee_id = os.path.basename(os.path.dirname(identity))

            # Calculate similarity percentage (inverse of distance)
            similarity = normalize_similarity(distance, Config.DISTANCE_METRIC)

            logger.info(f'Face recognized: employee {employee_id}, distance {distance}, similarity {similarity}')

            return jsonify({
                'success': True,
                'recognized': True,
                'employee_id': employee_id,
                'distance': distance,
                'similarity': float(similarity),
                'threshold': threshold,
                'model': Config.MODEL_NAME,
                'detector': Config.DETECTOR_BACKEND,
                'message': 'Face recognized successfully'
            }), 200

        finally:
            cleanup_temp_file(temp_path)
            if scratch_dir:
                shutil.rmtree(scratch_dir, ignore_errors=True)

    except ValueError as e:
        logger.error(f'Validation error in recognize: {str(e)}')
        return jsonify({
            'success': False,
            'error': str(e)
        }), 400

    except Exception as e:
        logger.error(f'Error in recognize: {str(e)}')
        return jsonify({
            'success': False,
            'error': 'Internal server error'
        }), 500


@app.route('/verify', methods=['POST'])
@limiter.limit("20 per minute")
@require_api_key
def verify():
    """
    Verify if two faces are the same person
    Expected JSON:
    {
        "photo1": "base64_encoded_image",
        "photo2": "base64_encoded_image"
    }
    """
    try:
        data = request.get_json()

        # Validate input
        if not data or 'photo1' not in data or 'photo2' not in data:
            return jsonify({
                'success': False,
                'error': 'Missing required fields: photo1, photo2'
            }), 400

        photo1_base64 = data['photo1']
        photo2_base64 = data['photo2']

        logger.info('Verifying two faces')

        # Decode images
        image1 = decode_base64_image(photo1_base64)
        image2 = decode_base64_image(photo2_base64)

        # Save temporary images
        temp_path1 = save_temp_image(image1, 'verify1')
        temp_path2 = save_temp_image(image2, 'verify2')

        try:
            # Verify faces
            result = DeepFace.verify(
                img1_path=temp_path1,
                img2_path=temp_path2,
                model_name=Config.MODEL_NAME,
                detector_backend=Config.DETECTOR_BACKEND,
                distance_metric=Config.DISTANCE_METRIC,
                enforce_detection=Config.ENFORCE_DETECTION,
                align=Config.ALIGN,
                anti_spoofing=Config.ANTI_SPOOFING_ENABLED
            )

            verified = result['verified']
            distance = result['distance']
            threshold = result['threshold']

            similarity = normalize_similarity(distance, Config.DISTANCE_METRIC)

            logger.info(f'Verification result: {verified}, distance: {distance}')

            return jsonify({
                'success': True,
                'verified': bool(verified),
                'distance': float(distance),
                'similarity': float(similarity),
                'threshold': float(threshold),
                'model': Config.MODEL_NAME,
                'message': 'Faces verified successfully'
            }), 200

        finally:
            cleanup_temp_file(temp_path1)
            cleanup_temp_file(temp_path2)

    except ValueError as e:
        logger.error(f'Validation error in verify: {str(e)}')
        return jsonify({
            'success': False,
            'error': str(e)
        }), 400

    except Exception as e:
        logger.error(f'Error in verify: {str(e)}')
        return jsonify({
            'success': False,
            'error': 'Internal server error'
        }), 500




@app.route('/delete', methods=['POST'])
@require_api_key
def delete():
    """
    Delete an enrolled face by employee_id or by image_hash (ver auditoria CRIT-06 — este
    endpoint não existia antes, então nenhum fluxo de exclusão/revogação de consentimento/
    LGPD do lado PHP conseguia de fato apagar a foto cadastrada aqui).

    Expected JSON (um dos dois):
    { "employee_id": "123" }
    { "image_hash": "sha256hex" }
    """
    try:
        data = request.get_json(silent=True) or {}
        employee_id = data.get('employee_id')
        image_hash = data.get('image_hash')

        if not employee_id and not image_hash:
            return jsonify({
                'success': False,
                'error': 'Missing required field: employee_id or image_hash'
            }), 400

        deleted_paths = []

        if employee_id:
            employee_id = str(employee_id)
            employee_dir = os.path.join(Config.FACES_DB_PATH, employee_id)
            if os.path.isdir(employee_dir):
                for filename in os.listdir(employee_dir):
                    file_path = os.path.join(employee_dir, filename)
                    if os.path.isfile(file_path):
                        os.remove(file_path)
                        deleted_paths.append(file_path)
                try:
                    os.rmdir(employee_dir)
                except OSError:
                    pass

        if image_hash and not deleted_paths:
            temp_dir_abs = os.path.abspath(Config.TEMP_DIR)
            for current_root, dirnames, filenames in os.walk(Config.FACES_DB_PATH):
                current_root_abs = os.path.abspath(current_root)
                if current_root_abs == temp_dir_abs or current_root_abs.startswith(temp_dir_abs + os.sep):
                    dirnames[:] = []
                    continue

                for filename in filenames:
                    lower = filename.lower()
                    candidate_path = os.path.join(current_root, filename)

                    if lower.endswith(FACE_FILE_SUFFIX):
                        candidate_hash = calculate_encrypted_image_hash(candidate_path)
                    elif os.path.splitext(lower)[1] in {'.jpg', '.jpeg', '.png'}:
                        candidate_hash = calculate_image_hash(candidate_path)
                    else:
                        continue

                    if candidate_hash and candidate_hash == image_hash:
                        os.remove(candidate_path)
                        deleted_paths.append(candidate_path)
                        if not os.listdir(current_root):
                            try:
                                os.rmdir(current_root)
                            except OSError:
                                pass
                        break
                if deleted_paths:
                    break

        removed_cache = purge_representation_cache(Config.FACES_DB_PATH)

        logger.info(
            'Face deleted',
            extra={
                'action': 'FACE_DELETED',
                'employee_id': employee_id,
                'image_hash': image_hash,
                'deleted_paths': deleted_paths,
                'removed_cache_files': removed_cache,
                'timestamp': datetime.now().isoformat(),
            },
        )

        return jsonify({
            'success': True,
            'deleted': len(deleted_paths) > 0,
            'deleted_files': len(deleted_paths),
            'removed_cache_files': removed_cache,
            'message': 'Face deleted successfully' if deleted_paths else 'No matching enrolled face found'
        }), 200

    except Exception as e:
        logger.error(f'Error in delete: {str(e)}')
        return jsonify({
            'success': False,
            'error': 'Internal server error'
        }), 500


@app.route('/admin/rebuild-db', methods=['POST'])
@require_api_key
def rebuild_db():
    """
    Não há mais cache de representações persistido em FACES_DB_PATH — cada /recognize
    materializa e descarta seu próprio diretório temporário decifrado (ver
    materialize_decrypted_faces_db, auditoria CRIT-07). Este endpoint agora serve para
    validar que todos os rostos cadastrados ainda podem ser descriptografados com a
    chave atual (útil após rotação de FACE_STORAGE_ENCRYPTION_KEY ou para diagnosticar
    corrupção), além de limpar qualquer cache de representações remanescente de
    instalações anteriores a esta correção.
    """
    try:
        cleanup_stale_temp_files()
        removed = purge_representation_cache(Config.FACES_DB_PATH)

        scratch_dir, enrolled_count = materialize_decrypted_faces_db()
        try:
            if enrolled_count == 0:
                return jsonify({
                    'success': True,
                    'rebuilt': False,
                    'message': 'No enrolled faces available to build representations',
                    'removed_cache_files': removed
                }), 200

            images = list_face_images(scratch_dir)
            sample_image = images[0]
            DeepFace.find(
                img_path=sample_image,
                db_path=scratch_dir,
                model_name=Config.MODEL_NAME,
                detector_backend=Config.DETECTOR_BACKEND,
                distance_metric=Config.DISTANCE_METRIC,
                enforce_detection=Config.ENFORCE_DETECTION,
                align=Config.ALIGN,
                silent=True,
                refresh_database=True
            )

            return jsonify({
                'success': True,
                'rebuilt': True,
                'indexed_faces': enrolled_count,
                'removed_cache_files': removed,
                'model': Config.MODEL_NAME,
                'distance_metric': Config.DISTANCE_METRIC,
                'message': 'Todos os rostos cadastrados foram descriptografados e validados com sucesso'
            }), 200
        finally:
            shutil.rmtree(scratch_dir, ignore_errors=True)
    except Exception as e:
        logger.error(f'Error rebuilding DeepFace database: {str(e)}')
        return jsonify({
            'success': False,
            'error': 'Failed to rebuild DeepFace database'
        }), 500


@app.route('/analyze', methods=['POST'])
@limiter.limit("20 per minute")
@require_api_key
def analyze():
    """
    Analyze face attributes (age, gender, emotion, race)
    Expected JSON:
    {
        "photo": "base64_encoded_image"
    }
    """
    try:
        data = request.get_json()

        # Validate input
        if not data or 'photo' not in data:
            return jsonify({
                'success': False,
                'error': 'Missing required field: photo'
            }), 400

        photo_base64 = data['photo']

        logger.info('Analyzing face attributes')

        # Decode image
        image = decode_base64_image(photo_base64)

        # Save temporary image
        temp_path = save_temp_image(image, 'analyze')

        try:
            # Analyze face
            analysis = DeepFace.analyze(
                img_path=temp_path,
                actions=['age', 'gender', 'emotion', 'race'],
                detector_backend=Config.DETECTOR_BACKEND,
                enforce_detection=Config.ENFORCE_DETECTION,
                silent=True
            )

            # Extract results (first face)
            if isinstance(analysis, list):
                analysis = analysis[0]

            result = {
                'age': int(analysis['age']),
                'gender': analysis['dominant_gender'],
                'emotion': analysis['dominant_emotion'],
                'race': analysis['dominant_race'],
                'facial_area': analysis['region']
            }

            logger.info(f'Face analyzed: {result}')

            return jsonify({
                'success': True,
                **result,
                'message': 'Face analyzed successfully'
            }), 200

        finally:
            cleanup_temp_file(temp_path)

    except ValueError as e:
        logger.error(f'Validation error in analyze: {str(e)}')
        return jsonify({
            'success': False,
            'error': str(e)
        }), 400

    except Exception as e:
        logger.error(f'Error in analyze: {str(e)}')
        return jsonify({
            'success': False,
            'error': 'Internal server error'
        }), 500


@app.errorhandler(429)
def ratelimit_error(e):
    """Rate limit exceeded handler"""
    return jsonify({
        'success': False,
        'error': 'Rate limit exceeded. Please try again later.'
    }), 429


@app.errorhandler(404)
def not_found(e):
    """404 handler"""
    return jsonify({
        'success': False,
        'error': 'Endpoint not found'
    }), 404


@app.errorhandler(500)
def internal_error(e):
    """500 handler"""
    logger.error(f'Internal server error: {str(e)}')
    return jsonify({
        'success': False,
        'error': 'Internal server error'
    }), 500


if __name__ == '__main__':
    logger.info(f'Starting DeepFace API on {Config.HOST}:{Config.PORT}')
    logger.info(f'Model: {Config.MODEL_NAME}, Detector: {Config.DETECTOR_BACKEND}')
    logger.info(f'Faces DB: {Config.FACES_DB_PATH}')

    app.run(
        host=Config.HOST,
        port=Config.PORT,
        debug=Config.DEBUG
    )


cleanup_stale_temp_files()
