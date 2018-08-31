FROM php:fpm

RUN apt-get update &&\
    apt-get install -y git zip unzip &&\
    docker-php-ext-install mbstring pdo pdo_mysql

RUN pecl install mongodb &&\
    echo "extension=mongodb.so" >> /usr/local/etc/php/conf.d/mongodb.ini

EXPOSE 9000