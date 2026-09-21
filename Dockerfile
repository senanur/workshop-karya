FROM php:8.4-cli-alpine

# gd is what re-encodes uploaded photos from pixels only, which is how EXIF
# (including GPS coordinates) gets dropped — see PRD v2 §9 and app/foto.php.
# jpeg/png/webp are the three formats the uploader accepts.
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libjpeg-turbo-dev libpng-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd \
    && apk add --no-cache libjpeg-turbo libpng libwebp \
    && apk del .build-deps

# zip is what opens and reassembles .sb3 files (Jalur Scratch, PRD-jalur-scratch
# §5). KARYA_MAX_BODY_BYTES only bounds JSON bodies; multipart uploads are
# bounded by PHP's own upload_max_filesize/post_max_size instead — a .sb3 can be
# up to 20 MB, so upload_max_filesize=25M leaves headroom for the multipart
# overhead,and post_max_size=26M for the rest of the form fields.

RUN apk add --no-cache --virtual .build-deps-php $PHPIZE_DEPS libzip-dev \
    && docker-php-ext-install -j"$(nproc)" zip \
    && apk del .build-deps-php \
    && printf 'upload_max_filesize=25M\npost_max_size=26M\n' > /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /app
COPY index.php ./
COPY app/ ./app/
COPY aset/ ./aset/
# bin/seed.php and bin/uji-beban.php are meant to be run for real via
# `docker exec` on this container (M6) — they need to actually be in the
# image, unlike bin/dev-seed.php which ships too since excluding one file
# from the directory isn't worth the complexity and it's no more reachable
# than the others (nothing in bin/ is web-routed).
COPY bin/ ./bin/

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
