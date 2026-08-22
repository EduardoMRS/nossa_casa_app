FROM php:8.5.5-fpm-alpine3.22

ARG USER=laravel
ARG UID=1000
ARG GID=1000

RUN apk add --no-cache \
    curl \
    libpng-dev \
    libzip-dev \
    libxml2-dev \
    sqlite-dev \
    zip \
    unzip \
    git \
    nodejs=~22 \
    npm

RUN docker-php-ext-install pdo_mysql pdo_sqlite bcmath gd pcntl zip

# Run PHP-FPM with the project user so bind-mounted files remain editable on
# the host without making storage world-writable.
RUN sed -i "s/^user = www-data/user = $USER/; s/^group = www-data/group = $USER/" \
    /usr/local/etc/php-fpm.d/www.conf

# Composer
COPY --from=composer:2.8.12 /usr/bin/composer /usr/bin/composer

RUN deluser node || true && \
    addgroup -g $GID $USER && \
    adduser -u $UID -G $USER -h /home/$USER -D $USER

WORKDIR /var/www

COPY .env.example /usr/local/share/nossa-casa.env.example
COPY --chown=$UID:$GID ./docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY ./docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN mkdir -p /var/run/php && chown -R $UID:$GID /var/run/php

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

CMD ["php-fpm"]
