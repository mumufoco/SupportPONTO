#!/usr/bin/env bash

bootstrap_dotenv_trim() {
  local value="$1"
  value="${value#${value%%[![:space:]]*}}"
  value="${value%${value##*[![:space:]]}}"
  if [[ ${#value} -ge 2 ]]; then
    if [[ ( ${value:0:1} == '"' && ${value: -1} == '"' ) || ( ${value:0:1} == "'" && ${value: -1} == "'" ) ]]; then
      value="${value:1:${#value}-2}"
    fi
  fi
  printf '%s' "$value"
}

bootstrap_dotenv_get() {
  local key="$1"
  local default="${2-}"
  local env_file="${3:-.env}"
  if [[ -n "${!key-}" ]]; then
    printf '%s' "${!key}"
    return 0
  fi
  if [[ ! -f "$env_file" ]]; then
    printf '%s' "$default"
    return 0
  fi
  local line
  line=$(grep -E "^[[:space:]]*${key//./\.}[[:space:]]*=" "$env_file" | tail -n 1 || true)
  if [[ -z "$line" ]]; then
    printf '%s' "$default"
    return 0
  fi
  local value="${line#*=}"
  bootstrap_dotenv_trim "$value"
}

bootstrap_normalize_environment() {
  local resolved="${APP_ENV:-${CI_ENVIRONMENT:-production}}"
  export APP_ENV="$resolved"
  export CI_ENVIRONMENT="$resolved"
  printf '%s' "$resolved"
}

bootstrap_is_https_url() {
  local url="$1"
  [[ "$url" =~ ^https:// ]]
}

bootstrap_cookie_secure_from_url() {
  local url="$1"
  if bootstrap_is_https_url "$url"; then
    printf 'true'
  else
    printf 'false'
  fi
}

bootstrap_session_save_path() {
  local root_dir="$1"
  local env_file="${2:-.env}"
  local configured="${SESSION_SAVE_PATH:-}"
  if [[ -z "$configured" ]]; then
    configured=$(bootstrap_dotenv_get 'session.savePath' '' "$env_file")
  fi
  if [[ -z "$configured" ]]; then
    configured='writable/session'
  fi
  if [[ "$configured" = /* || "$configured" =~ ^[A-Za-z]:[\\/] ]]; then
    printf '%s' "$configured"
  else
    printf '%s/%s' "$root_dir" "${configured#./}"
  fi
}

bootstrap_required_secrets_report() {
  local env_file="${1:-.env}"
  local key value missing=0
  for key in encryption.key JWT_SECRET_KEY QR_SECRET_KEY DEEPFACE_API_KEY; do
    value=$(bootstrap_dotenv_get "$key" '' "$env_file")
    if [[ -z "$value" ]]; then
      printf '%s\n' "$key"
      missing=1
    fi
  done
  return $missing
}
