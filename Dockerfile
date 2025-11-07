FROM hyperf/hyperf:8.3-alpine-v3.20-swoole

WORKDIR /opt/www

RUN apk upgrade --no-cache --available && \
    apk add --no-cache php83-pecl-grpc=1.64.1-r0 && \
    printf "extension=grpc.so\ngrpc.enable_fork_support=1\n" > /etc/php83/conf.d/50_grpc.ini

COPY composer.* /opt/www
RUN composer install --prefer-dist
COPY . /opt/www

ENTRYPOINT ["sh"]