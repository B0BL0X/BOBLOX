# BOBLOX: Apache + PHP 8.3 with the site in /var/www/html.
# Data (SQLite database, uploads, thumbnails, sessions) lives in /var/www/html/App_Data,
# which docker-compose.yml mounts from ./data on the host.
FROM php:8.3-apache

# GD for thumbnails and uploads (PNG, JPEG, GIF). PDO SQLite is built into the official image.
RUN apt-get update \
 && apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev \
 && docker-php-ext-configure gd --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" gd \
 && apt-get purge -y --auto-remove libpng-dev libjpeg62-turbo-dev \
 && apt-get install -y --no-install-recommends libpng16-16 libjpeg62-turbo \
 && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite remoteip headers \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php.ini "$PHP_INI_DIR/conf.d/boblox.ini"
COPY docker/apache-boblox.conf /etc/apache2/conf-available/zz-boblox.conf
RUN a2enconf zz-boblox

COPY --chown=root:root public_html/ /var/www/html/
COPY docker/entrypoint.sh /usr/local/bin/boblox-entrypoint
RUN chmod 755 /usr/local/bin/boblox-entrypoint

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD php -r 'exit(@file_get_contents("http://127.0.0.1/Login/Default.aspx") === false ? 1 : 0);'

ENTRYPOINT ["boblox-entrypoint"]
CMD ["apache2-foreground"]
