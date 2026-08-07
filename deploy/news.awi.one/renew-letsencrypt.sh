#!/usr/bin/env bash

set -Eeuo pipefail

readonly DOMAIN="news.awi.one"

if [[ "${EUID}" -ne 0 ]]; then
    echo "Скрипт необходимо запускать от root." >&2
    exit 1
fi

if [[ ! -d "/etc/letsencrypt/live/${DOMAIN}" ]]; then
    echo "Сертификат ${DOMAIN} ещё не выпущен; продление пропущено." >&2
    exit 0
fi

/usr/bin/certbot renew \
    --cert-name "${DOMAIN}" \
    --force-renewal \
    --quiet \
    --deploy-hook "/usr/bin/systemctl reload nginx"
