FROM php:8.4-cli-alpine

WORKDIR /app
COPY index.php ./
COPY app/ ./app/

# Lets php -S handle several requests concurrently instead of one at a time —
# no need for nginx/php-fpm in front for this app's traffic (~28 kids, mostly
# short file reads/writes). See docs/PRD-karyaweb.md §8.1.
ENV PHP_CLI_SERVER_WORKERS=4

EXPOSE 3000

# A container that never reports healthy is the #1 confusing failure per
# docs/panduan-infra (Bagian 10, §3) — Traefik silently skips routing to it,
# with no error message anywhere. Ship a healthcheck that actually passes.
HEALTHCHECK --interval=15s --timeout=3s --start-period=5s --retries=3 \
    CMD php -r "exit(@file_get_contents('http://127.0.0.1:3000/bikin') === false ? 1 : 0);"

CMD ["php", "-S", "0.0.0.0:3000", "index.php"]
