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

// Headers for a rendered child page: the whole point is that nothing the
// child submitted can ever execute as script on labpplg.web.id.
function karya_send_child_page_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    // style-src allows inline styles because the <style> block is a static,
    // server-authored template — user text is only ever interpolated into
    // escaped text nodes, never into CSS. script-src stays 'none': that's the
    // rule that actually matters, since it's what stops any child-submitted
    // content from ever executing as script.
    header("Content-Security-Policy: default-src 'self'; script-src 'none'; style-src 'self' 'unsafe-inline'");
    header('Content-Type: text/html; charset=utf-8');
}

// Truncates to a max length in a way that's safe for multi-byte UTF-8 input.
function karya_clamp_text(string $value, int $maxLen): string
{
    $value = trim($value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLen);
    }

    return substr($value, 0, $maxLen);
}
