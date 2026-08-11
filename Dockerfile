FROM php:8.2-apache

ARG PUID=1000
ARG PGID=1000

RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd mysqli pdo pdo_mysql \
    && a2enmod rewrite \
    && groupmod -o -g ${PGID} www-data \
    && usermod -o -u ${PUID} -g www-data www-data \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
