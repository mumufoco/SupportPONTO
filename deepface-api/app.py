"""
DeepFace API - Facial Recognition Service
Sistema de Ponto Eletrônico Brasileiro
"""

import os
import base64
import hashlib
import logging
from datetime import datetime
from io import BytesIO
from functools import wraps

from flask import Flask, request, jsonify
from flask_cors import CORS
from flask_limiter import Limiter
from flask_limiter.util import get_remote_address
from PIL import Image
from deepface import DeepFace
import numpy as np

from config import config, Config

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
            faces = DeepFace.extract_faces(
                img_path=temp_path,
                detector_backend=Config.DETECTOR_BACKEND,
                enforce_detection=Config.ENFORCE_DETECTION,
                align=Config.ALIGN
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

            # Save face image
            face_filename = f'{employee_id}_face.jpg'
            face_path = os.path.join(employee_dir, face_filename)
            already_enrolled = os.path.exists(face_path)
            previous_image_hash = calculate_image_hash(face_path) if already_enrolled else None
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

            # Copy temp file to employee directory
            image.save(face_path, 'JPEG', quality=95)

            # Calculate hash
            image_hash = calculate_image_hash(face_path)

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

        try:
            # Find matching faces
            result = DeepFace.find(
                img_path=temp_path,
                db_path=Config.FACES_DB_PATH,
                model_name=Config.MODEL_NAME,
                detector_backend=Config.DETECTOR_BACKEND,
                distance_metric=Config.DISTANCE_METRIC,
                enforce_detection=Config.ENFORCE_DETECTION,
                align=Config.ALIGN,
                silent=True,
                refresh_database=False
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
            # Format: ../storage/faces/employee_id/employee_id_face.jpg
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
                align=Config.ALIGN
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




@app.route('/admin/rebuild-db', methods=['POST'])
@require_api_key
def rebuild_db():
    """Rebuild DeepFace representations cache after enroll/delete operations."""
    try:
        cleanup_stale_temp_files()
        removed = purge_representation_cache(Config.FACES_DB_PATH)
        images = list_face_images(Config.FACES_DB_PATH)

        if not images:
            return jsonify({
                'success': True,
                'rebuilt': False,
                'message': 'No enrolled faces available to build representations',
                'removed_cache_files': removed
            }), 200

        sample_image = images[0]
        DeepFace.find(
            img_path=sample_image,
            db_path=Config.FACES_DB_PATH,
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
            'indexed_faces': len(images),
            'removed_cache_files': removed,
            'model': Config.MODEL_NAME,
            'distance_metric': Config.DISTANCE_METRIC,
            'message': 'DeepFace database rebuilt successfully'
        }), 200
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
