FROM php:8.4-cli-alpine

# gd is what re-encodes uploaded photos from pixels only, which is how EXIF
# (including GPS coordinates) gets dropped — see PRD v2 §9 and app/foto.php.
# jpeg/png/webp are the three formats the uploader accepts.
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libjpeg-turbo-dev libpng-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd \
    && apk add --no-cache libjpeg-turbo libpng libwebp \
    && apk del .build-deps

WORKDIR /app
COPY index.php ./
COPY app/ ./app/
COPY aset/ ./aset/

# Lets php -S handle several requests concurrently instead of one at a time —
# no need for nginx/php-fpm in front for this app's traffic (~28 kids, mostly
# short file reads/writes). See docs/PRD-karyaweb.md §8.1.
ENV PHP_CLI_SERVER_WORKERS=4

EXPOSE 3000

# A container that never reports healthy is the #1 confusing failure per
# docs/panduan-infra (Bagian 10, §3) — Traefik silently skips routing to it,
# with no error message anywhere. Ship a healthcheck that actually passes.
# /bikin was v1's editor route; v2 dropped it in favor of /masuk (PRD v2 §4).
HEALTHCHECK --interval=15s --timeout=3s --start-period=5s --retries=3 \
    CMD php -r "exit(@file_get_contents('http://127.0.0.1:3000/masuk') === false ? 1 : 0);"

CMD ["php", "-S", "0.0.0.0:3000", "index.php"]
