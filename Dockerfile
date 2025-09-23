FROM hyperf/hyperf:8.3-alpine-v3.20-swoole

WORKDIR /opt/www

COPY ./composer.* /opt/www
RUN composer install --prefer-dist
COPY . /opt/www

ENTRYPOINT [ "sh" ]