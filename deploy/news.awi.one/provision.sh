#!/usr/bin/env bash

set -Eeuo pipefail

readonly DOMAIN="news.awi.one"
readonly APP_ROOT="/var/html/${DOMAIN}/www"
readonly PHP_VERSION="8.4"
readonly MYSQL_APT_PACKAGE="mysql-apt-config_0.8.39-1_all.deb"
readonly MYSQL_APT_MD5="8f722bb35fc6f510a2154a9466f5e2f7"

export DEBIAN_FRONTEND=noninteractive

if [[ "${EUID}" -ne 0 ]]; then
    echo "Скрипт необходимо запускать от root." >&2
    exit 1
fi

if [[ ! -f /etc/os-release ]] || ! grep -q '^VERSION_ID="24.04"' /etc/os-release; then
    echo "Поддерживается Ubuntu 24.04 LTS." >&2
    exit 1
fi

apt-get update
apt-get install -y \
    ca-certificates \
    curl \
    gnupg \
    lsb-release \
    software-properties-common \
    unzip \
    rsync \
    acl

if ! apt-cache show php8.4-fpm >/dev/null 2>&1; then
    add-apt-repository -y ppa:ondrej/php
    apt-get update
fi

apt-get install -y \
    nginx \
    redis-server \
    memcached \
    git \
    certbot \
    python3-certbot-nginx \
    php8.4-cli \
    php8.4-fpm \
    php8.4-bcmath \
    php8.4-curl \
    php8.4-gd \
    php8.4-intl \
    php8.4-mbstring \
    php8.4-mysql \
    php8.4-opcache \
    php8.4-readline \
    php8.4-redis \
    php8.4-memcached \
    php8.4-xml \
    php8.4-zip

if ! command -v mysql >/dev/null 2>&1 || [[ "$(mysql --version 2>/dev/null)" != *"Ver 8.4"* ]]; then
    mysql_apt_path="/tmp/${MYSQL_APT_PACKAGE}"
    curl --fail --location --silent --show-error \
        "https://repo.mysql.com/${MYSQL_APT_PACKAGE}" \
        --output "${mysql_apt_path}"
    echo "${MYSQL_APT_MD5}  ${mysql_apt_path}" | md5sum --check --strict

    echo "mysql-apt-config mysql-apt-config/select-server select mysql-8.4-lts" | debconf-set-selections
    echo "mysql-apt-config mysql-apt-config/select-product select Ok" | debconf-set-selections
    dpkg -i "${mysql_apt_path}"
    apt-get update
    apt-get install -y mysql-server
    rm -f "${mysql_apt_path}"
fi

if ! command -v composer >/dev/null 2>&1; then
    composer_installer="/tmp/composer-setup.php"
    composer_signature="$(curl --fail --silent --show-error https://composer.github.io/installer.sig)"
    curl --fail --silent --show-error https://getcomposer.org/installer --output "${composer_installer}"
    actual_signature="$(php -r "echo hash_file('sha384', '${composer_installer}');")"

    if [[ "${actual_signature}" != "${composer_signature}" ]]; then
        rm -f "${composer_installer}"
        echo "Проверка подписи установщика Composer не пройдена." >&2
        exit 1
    fi

    php "${composer_installer}" --quiet --install-dir=/usr/local/bin --filename=composer
    rm -f "${composer_installer}"
fi

install -d -o root -g www-data -m 0755 "/var/html/${DOMAIN}" "${APP_ROOT}"

install -d -m 0755 /etc/php/8.4/fpm/conf.d
cat > /etc/php/8.4/fpm/conf.d/99-news-awi-one.ini <<'PHP_INI'
memory_limit = 512M
max_execution_time = 120
upload_max_filesize = 20M
post_max_size = 25M
date.timezone = Europe/Moscow
expose_php = Off
opcache.enable = 1
opcache.enable_cli = 1
opcache.memory_consumption = 192
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 1
opcache.revalidate_freq = 2
PHP_INI

sed -i 's/^-l .*/-l 127.0.0.1/' /etc/memcached.conf

cat > /etc/mysql/mysql.conf.d/99-news-awi-one.cnf <<'MYSQL_INI'
[mysqld]
bind-address = 127.0.0.1
mysqlx-bind-address = 127.0.0.1
MYSQL_INI

systemctl enable nginx php8.4-fpm mysql redis-server memcached
systemctl restart php8.4-fpm redis-server memcached
systemctl restart mysql
systemctl start nginx

php_version="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
mysql_version="$(mysql --version)"

if [[ "${php_version}" != "8.4" ]]; then
    echo "Ожидался PHP 8.4, установлен ${php_version}." >&2
    exit 1
fi

if [[ "${mysql_version}" != *"Ver 8.4"* ]]; then
    echo "Ожидался MySQL 8.4: ${mysql_version}" >&2
    exit 1
fi

echo "Базовый стек для ${DOMAIN} установлен."
