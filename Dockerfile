FROM hyperf/hyperf:8.3-alpine-v3.20-swoole

WORKDIR /opt/www

RUN apk add --no-cache php83-pecl-pcov=1.0.11-r0

COPY ./composer.* /opt/www
RUN composer install --prefer-dist
COPY . /opt/www

ENTRYPOINT [ "sh" ]