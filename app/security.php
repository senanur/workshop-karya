<?php

declare(strict_types=1);

if (!defined('KARYA_APP')) {
    http_response_code(404);
    exit;
}

function karya_slug_is_valid(string $slug): bool
{
    if (!preg_match('/^[a-z0-9-]{2,20}$/', $slug)) {
        return false;
    }

    if (str_starts_with($slug, 'smp-')) {
        return false; // whole namespace belongs to the school walls, §4
    }

    return !in_array($slug, KARYA_RESERVED_SLUGS, true);
}

// 6 chars, letters+digits only, with visually-confusable characters removed
// (0 O 1 l I) — kids type this from a printed card, not copy-paste.
function karya_kode_is_valid(string $kode): bool
{
    return (bool) preg_match('/^[2-9A-HJ-KM-NP-Za-km-np-z]{6}$/', $kode);
}

function karya_json_body(): array
{
    $raw = file_get_contents('php://input', false, null, 0, KARYA_MAX_BODY_BYTES + 1);
    if ($raw === false || strlen($raw) > KARYA_MAX_BODY_BYTES) {
        karya_json_error(413, 'Body terlalu besar.');
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        karya_json_error(400, 'Body harus JSON valid.');
    }

    return $data;
}

function karya_json_error(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function karya_json_ok(array $payload): never
{
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// Headers for a rendered child page. v2 assembles child-submitted HTML/CSS
// (sanitized, but still their markup) into this page, so the pagers moves
// from "never execute as script" (v1, no markup accepted at all) to
// "sanitized on the way in, and CSP as a second line of defense": script-src
// 'self' lets /aset/blok.js run, but any <script> a child's HTML somehow
// still contained cannot execute because it isn't same-origin script src.
function karya_send_child_page_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex');
    header(
        "Content-Security-Policy: default-src 'none'; script-src 'self'; "
        . "style-src 'self' 'unsafe-inline'; img-src 'self'; base-uri 'self'; "
        . "form-action 'none'; frame-ancestors 'self'"
    );
    header('Content-Type: text/html; charset=utf-8');
}

// Headers for the app's own UI pages (/masuk, /<slug>/edit) — not
// child-submitted content, so these are allowed their own inline script/style.
function karya_send_app_page_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-src 'self'");
    header('Content-Type: text/html; charset=utf-8');
}
