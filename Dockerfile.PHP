FROM php:7.4-fpm-alpine
WORKDIR /code
COPY ./src/subtitulamos .
RUN apk add autoconf g++ libc-dev make gcc \
    && pecl install redis-5.3.1 \
    && docker-php-ext-install -j$(nproc) pdo_mysql \
    && docker-php-ext-enable redis \
    && curl -s https://getcomposer.org/installer | php \
    && rm -rf /tmp/pear \
    && apk del autoconf g++ libc-dev make gcc

COPY ./config/docker_phpsv_entrypoint.sh /usr/local/bin/docker-entrypoint.sh
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
