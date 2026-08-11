#!/bin/bash
set -e

# 确保 Typecho 需要写入的目录存在
mkdir -p /var/www/html/usr/uploads /var/www/html/usr/themes /var/www/html/usr/plugins

exec docker-php-entrypoint apache2-foreground
