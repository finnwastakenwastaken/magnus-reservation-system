#!/usr/bin/env bash
set -euo pipefail

export DEBIAN_FRONTEND=noninteractive

apt-get update
apt-get install -y software-properties-common ca-certificates curl unzip git ufw
add-apt-repository -y ppa:ondrej/php
apt-get update
apt-get install -y nginx mariadb-server php8.3 php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-intl

systemctl enable nginx
systemctl enable php8.3-fpm
systemctl enable mariadb
systemctl start nginx
systemctl start php8.3-fpm
systemctl start mariadb

ufw allow OpenSSH || true
ufw allow 'Nginx Full' || true

mkdir -p /var/www/living-room
chown -R "$SUDO_USER":"$SUDO_USER" /var/www/living-room 2>/dev/null || true

echo "Base VPS stack installed. Upload or clone the project into /var/www/living-room next."
