#!/usr/bin/env bash

set -Eeuo pipefail

readonly DOMAIN="news.awi.one"
readonly APP_ROOT="/var/html/${DOMAIN}/www"
readonly CREDENTIALS_FILE="/root/.news-awi-one-db-credentials"

if [[ "${EUID}" -ne 0 ]]; then
    echo "Скрипт необходимо запускать от root." >&2
    exit 1
fi

if [[ ! -f "${APP_ROOT}/.env" ]]; then
    echo "Не найден ${APP_ROOT}/.env." >&2
    exit 1
fi

if [[ -f "${CREDENTIALS_FILE}" ]]; then
    # shellcheck disable=SC1090
    source "${CREDENTIALS_FILE}"
else
    NEWS_DB_NAME="news_monitor"
    NEWS_DB_USER="news_monitor"
    NEWS_DB_PASSWORD="$(openssl rand -hex 32)"

    umask 077
    {
        printf 'NEWS_DB_NAME=%q\n' "${NEWS_DB_NAME}"
        printf 'NEWS_DB_USER=%q\n' "${NEWS_DB_USER}"
        printf 'NEWS_DB_PASSWORD=%q\n' "${NEWS_DB_PASSWORD}"
    } > "${CREDENTIALS_FILE}"
fi

mysql --protocol=socket --user=root <<SQL
CREATE DATABASE IF NOT EXISTS \`${NEWS_DB_NAME}\`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${NEWS_DB_USER}'@'localhost'
    IDENTIFIED BY '${NEWS_DB_PASSWORD}';
ALTER USER '${NEWS_DB_USER}'@'localhost'
    IDENTIFIED BY '${NEWS_DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${NEWS_DB_NAME}\`.*
    TO '${NEWS_DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

set_env() {
    local key="$1"
    local value="$2"

    sed -i "/^${key}=/d" "${APP_ROOT}/.env"
    printf '%s=%s\n' "${key}" "${value}" >> "${APP_ROOT}/.env"
}

set_env APP_ENV production
set_env APP_DEBUG false
set_env APP_URL "https://${DOMAIN}"
set_env APP_TIMEZONE Europe/Moscow
set_env DISPLAY_TIMEZONE Europe/Moscow
set_env LOG_LEVEL warning
set_env DB_CONNECTION mysql
set_env DB_HOST 127.0.0.1
set_env DB_PORT 3306
set_env DB_DATABASE "${NEWS_DB_NAME}"
set_env DB_USERNAME "${NEWS_DB_USER}"
set_env DB_PASSWORD "${NEWS_DB_PASSWORD}"
set_env CACHE_STORE memcached
set_env MEMCACHED_HOST 127.0.0.1
set_env QUEUE_CONNECTION redis
set_env REDIS_QUEUE_RETRY_AFTER 240
set_env REDIS_HOST 127.0.0.1
set_env REDIS_PORT 6379
set_env SESSION_DRIVER database
set_env SESSION_CONNECTION mysql
set_env SESSION_SECURE_COOKIE true
set_env SESSION_DOMAIN "${DOMAIN}"

chmod 0640 "${APP_ROOT}/.env"
chown root:www-data "${APP_ROOT}/.env"

install -d -o www-data -g www-data -m 0775 \
    "${APP_ROOT}/storage" \
    "${APP_ROOT}/storage/app" \
    "${APP_ROOT}/storage/framework/cache/data" \
    "${APP_ROOT}/storage/framework/sessions" \
    "${APP_ROOT}/storage/framework/views" \
    "${APP_ROOT}/storage/logs" \
    "${APP_ROOT}/bootstrap/cache"

chown -R www-data:www-data "${APP_ROOT}/storage" "${APP_ROOT}/bootstrap/cache"
find "${APP_ROOT}/storage" "${APP_ROOT}/bootstrap/cache" -type d -exec chmod 0775 {} +
find "${APP_ROOT}/storage" "${APP_ROOT}/bootstrap/cache" -type f -exec chmod 0664 {} +

echo "MySQL и production .env настроены."
