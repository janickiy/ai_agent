#!/usr/bin/env bash

set -Eeuo pipefail

readonly DOMAIN="news.awi.one"
readonly EXPECTED_IPV4="78.17.198.175"
readonly BOOTSTRAP_CRON="/etc/cron.d/news-awi-one-letsencrypt-bootstrap"

if [[ "${EUID}" -ne 0 ]]; then
    echo "Скрипт необходимо запускать от root." >&2
    exit 1
fi

if [[ -d "/etc/letsencrypt/live/${DOMAIN}" ]]; then
    rm -f "${BOOTSTRAP_CRON}"
    exit 0
fi

if ! getent ahostsv4 "${DOMAIN}" \
    | awk '$2 == "STREAM" { print $1 }' \
    | sort -u \
    | grep -Fxq "${EXPECTED_IPV4}"; then
    echo "A-запись ${DOMAIN} ещё не указывает на ${EXPECTED_IPV4}; выпуск отложен."
    exit 0
fi

/usr/bin/certbot --nginx \
    --non-interactive \
    --agree-tos \
    --register-unsafely-without-email \
    --redirect \
    --domain "${DOMAIN}"

/usr/sbin/nginx -t
/usr/bin/systemctl reload nginx
rm -f "${BOOTSTRAP_CRON}"

echo "Сертификат ${DOMAIN} выпущен, HTTPS включён."
