FROM php:8.5.7-fpm-alpine3.22 AS base

ARG USER=laravel
ARG UID=1000
ARG GID=1000

RUN apk add --no-cache \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    libxml2-dev \
    sqlite-dev \
    icu-dev \
    zip \
    unzip \
    git \
    nodejs=~22 \
    npm

RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install pdo_mysql pdo_sqlite bcmath gd pcntl zip intl   

# Run PHP-FPM with the project user so bind-mounted files remain editable on
# the host without making storage world-writable.
RUN sed -i "s/^user = www-data/user = $USER/; s/^group = www-data/group = $USER/" \
    /usr/local/etc/php-fpm.d/www.conf

COPY --from=composer:2.8.12 /usr/bin/composer /usr/bin/composer

RUN deluser node || true && \
    addgroup -g $GID $USER && \
    adduser -u $UID -G $USER -h /home/$USER -D $USER

WORKDIR /var/www

FROM base AS frontend-builder

WORKDIR /build

COPY --chown=$UID:$GID . .

RUN composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader && \
    npm install && \
    npm run test:frontend && \
    npm run build && \
    test -f public/build/manifest.json && \
    test -f public/build/.production

FROM base AS runtime

WORKDIR /var/www

COPY .env.example /usr/local/share/nossa-casa.env.example
COPY --chown=$UID:$GID ./docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY ./docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY --from=frontend-builder --chown=$UID:$GID \
    /build/public/build /opt/nossa-casa/public-build

RUN chmod +x /usr/local/bin/entrypoint.sh && \
    mkdir -p /var/run/php && \
    chown -R $UID:$GID /var/run/php /opt/nossa-casa/public-build

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

CMD ["php-fpm"]
