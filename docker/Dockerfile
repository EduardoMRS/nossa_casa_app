FROM php:8.5-fpm-alpine

ARG USER=laravel
ARG UID=1000
ARG GID=1000

RUN apk add --no-cache \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    nodejs=~24 \
    npm

RUN docker-php-ext-install pdo_mysql bcmath gd

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN deluser node || true && \
    addgroup -g $GID $USER && \
    adduser -u $UID -G $USER -h /home/$USER -D $USER

WORKDIR /var/www

COPY --chown=$UID:$GID ./docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN mkdir -p /var/run/php && chown -R $UID:$GID /var/run/php

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

CMD ["php-fpm"]
