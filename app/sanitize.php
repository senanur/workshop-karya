<?php

declare(strict_types=1);

if (!defined('KARYA_APP')) {
    http_response_code(404);
    exit;
}

// Allow-list sanitizer for a child's Isi tab (HTML) and Gaya tab (CSS).
// "Daftar izin, bukan daftar buang" (PRD v2 §9): everything not explicitly
// allowed is stripped, not the other way around.

const KARYA_HTML_ALLOWED_TAGS = [
    'header', 'section', 'footer', 'div', 'span', 'p', 'h1', 'h2', 'h3',
    'ul', 'ol', 'li', 'img', 'a', 'b', 'i', 'strong', 'em', 'small', 'br',
    'hr', 'blockquote', 'cite', 'button',
];

// Tags whose entire subtree is dangerous and must be dropped outright
// (never unwrapped — unwrapping <script>text</script> would still let the
// text back onto the page).
const KARYA_HTML_DROP_ENTIRE_TAGS = [
    'script', 'iframe', 'object', 'embed', 'form', 'input', 'style',
    'link', 'meta', 'base',
];

const KARYA_HTML_ALLOWED_ATTRS = ['class', 'id', 'alt', 'title', 'src', 'href'];

/**
 * @return array{html: string, peringatan: string[]}
 */
function karya_sanitize_html(string $html): array
{
    $peringatan = [];

    if (trim($html) === '') {
        return ['html' => '', 'peringatan' => $peringatan];
    }

    // createEmpty()'s $body is documented as nullable and — confirmed against
    // a real PHP 8.4 build, not just the docs — actually comes back null in
    // practice, so it can't be relied on to already have a <body>.
    // createFromString() on a minimal document parses real HTML text, which
    // guarantees a populated $body per the HTML5 parsing algorithm.
    $doc = Dom\HTMLDocument::createFromString(
        '<!doctype html><html><body></body></html>',
        LIBXML_NOERROR | LIBXML_NOWARNING
    );
    $body = $doc->body;
    // Fragment-context parsing (same as assigning innerHTML in a browser),
    // rather than wrapping in a full document string: content that would
    // only be legal inside e.g. <table> is handled per the HTML5 fragment
    // parsing algorithm instead of silently relocated by a document parse.
    $body->innerHTML = $html;

    karya_sanitize_walk($doc, $body, $peringatan);

    $out = '';
    foreach ($body->childNodes as $child) {
        $out .= $doc->saveHtml($child);
    }

    return ['html' => trim($out), 'peringatan' => array_values(array_unique($peringatan))];
}

function karya_sanitize_walk(Dom\HTMLDocument $doc, Dom\Node $parent, array &$peringatan): void
{
    // Snapshot children first: we mutate the tree (replace/remove nodes)
    // while walking, which would otherwise skip or re-visit siblings.
    $children = [];
    foreach ($parent->childNodes as $child) {
        $children[] = $child;
    }

    foreach ($children as $node) {
        if ($node instanceof Dom\Text) {
            continue; // kept as-is; serialized back out already-escaped
        }

        if (!($node instanceof Dom\Element)) {
            // Comments, doctypes, processing instructions, CDATA — none of
            // these are on the allow-list, so they're simply not markup.
            $parent->removeChild($node);
            continue;
        }

        $tag = strtolower($node->tagName);

        if (in_array($tag, KARYA_HTML_DROP_ENTIRE_TAGS, true)) {
            $peringatan[] = "Tag <{$tag}> dihapus (tidak diizinkan).";
            $parent->removeChild($node);
            continue;
        }

        if (!in_array($tag, KARYA_HTML_ALLOWED_TAGS, true)) {
            $peringatan[] = "Tag <{$tag}> dihapus, isinya dipertahankan.";
            karya_sanitize_walk($doc, $node, $peringatan); // clean its children first
            karya_unwrap($node);
            continue;
        }

        karya_sanitize_attrs($node, $peringatan);
        karya_sanitize_walk($doc, $node, $peringatan);
    }
}

function karya_sanitize_attrs(Dom\Element $node, array &$peringatan): void
{
    $tag = strtolower($node->tagName);

    // Snapshot names first — removing attributes while iterating the live
    // attribute map would skip entries, same reasoning as the node walk.
    $names = [];
    foreach ($node->attributes as $attr) {
        $names[] = $attr->name;
    }

    foreach ($names as $name) {
        $lower = strtolower($name);
        $keep = in_array($lower, KARYA_HTML_ALLOWED_ATTRS, true) || str_starts_with($lower, 'data-');

        if (!$keep) {
            if (str_starts_with($lower, 'on')) {
                $peringatan[] = 'Atribut event (on...) dihapus.';
            } elseif ($lower === 'style') {
                $peringatan[] = 'Atribut style dihapus — pakai tab Gaya.';
            } else {
                $peringatan[] = "Atribut {$lower} dihapus.";
            }
            $node->removeAttribute($name);
            continue;
        }
    }

    if ($tag === 'img' && $node->hasAttribute('src')) {
        $src = (string) $node->getAttribute('src');
        if (!preg_match('#^foto/[A-Za-z0-9_.-]+$#', $src)) {
            $node->setAttribute('src', 'foto/contoh.svg');
            $peringatan[] = 'Gambar tidak valid, diganti gambar contoh.';
        }
    } elseif ($node->hasAttribute('src')) {
        // src only means something on <img> in this allow-list.
        $node->removeAttribute('src');
    }

    if ($tag === 'a' && $node->hasAttribute('href')) {
        $href = (string) $node->getAttribute('href');
        if (!preg_match('#^https://#i', $href)) {
            $node->removeAttribute('href');
            $peringatan[] = 'Tautan yang bukan https:// dihapus.';
        } else {
            $node->setAttribute('rel', 'noopener nofollow');
        }
    } elseif ($node->hasAttribute('href')) {
        $node->removeAttribute('href');
    }
}

// Replaces $node with its children, in place, in its parent.
function karya_unwrap(Dom\Element $node): void
{
    $parent = $node->parentNode;
    if ($parent === null) {
        return;
    }

    while ($node->firstChild !== null) {
        $parent->insertBefore($node->firstChild, $node);
    }
    $parent->removeChild($node);
}

/**
 * @return array{css: string, peringatan: string[]}
 */
function karya_sanitize_css(string $css): array
{
    $peringatan = [];

    $out = preg_replace('/@import[^;]*;?/i', '', $css, -1, $count);
    if ($count > 0) {
        $peringatan[] = '@import dihapus.';
    }

    // [^;{}]* (not [^)]*) so a value with a nested paren, e.g.
    // url(javascript:alert(1)), is matched up to its outermost ')' instead
    // of leaving the inner ')' dangling in the output.
    $out = preg_replace('/url\s*\([^;{}]*\)/i', 'none', $out, -1, $count);
    if ($count > 0) {
        $peringatan[] = 'url(...) dihapus.';
    }

    $out = preg_replace('/expression\s*\([^;{}]*\)/i', '', $out, -1, $count);
    if ($count > 0) {
        $peringatan[] = 'expression(...) dihapus.';
    }

    $out = preg_replace('#</style#i', '', $out, -1, $count);
    if ($count > 0) {
        $peringatan[] = "'</style' dihapus.";
    }

    $openCount = substr_count($out, '/*');
    $closeCount = substr_count($out, '*/');
    if ($openCount > $closeCount) {
        $cutAt = strrpos($out, '/*');
        if ($cutAt !== false) {
            $out = substr($out, 0, $cutAt);
            $peringatan[] = 'Komentar CSS yang tidak tertutup dipotong.';
        }
    }

    return ['css' => trim((string) $out), 'peringatan' => $peringatan];
}
