FROM php:7.4-fpm-alpine
WORKDIR /code
COPY ./src/subtitulamos .
RUN apk add autoconf g++ libc-dev make gcc git \
    && pecl install redis-5.3.2 \
    && docker-php-ext-install -j$(nproc) pdo_mysql \
    && docker-php-ext-enable redis \
    && curl -s https://raw.githubusercontent.com/composer/getcomposer.org/e3e43bde99447de1c13da5d1027545be81736b27/web/installer | php \
    && rm -rf /tmp/pear \
    && apk del autoconf g++ libc-dev make gcc

# Use our own PHP ini config
COPY ./config/php.ini "$PHP_INI_DIR/php.ini"

COPY ./config/docker_phpsv_entrypoint.sh /usr/local/bin/docker-entrypoint.sh
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
