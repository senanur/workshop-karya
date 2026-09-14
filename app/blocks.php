<?php

declare(strict_types=1);

if (!defined('KARYA_APP')) {
    http_response_code(404);
    exit;
}

// Blocks are server-side templates. A child only ever sends field VALUES
// (plain text) and a block ID from this catalog — never markup. Rendering
// always escapes every value; nothing here trusts client input as HTML.

function karya_block_catalog(): array
{
    return [
        'tiga-hal' => [
            'label' => '3 Hal yang Aku Suka',
            'fields' => [
                'item1' => ['label' => 'Hal pertama', 'maxlen' => 40],
                'item2' => ['label' => 'Hal kedua', 'maxlen' => 40],
                'item3' => ['label' => 'Hal ketiga', 'maxlen' => 40],
            ],
            'render' => function (array $v): string {
                $items = [$v['item1'] ?? '', $v['item2'] ?? '', $v['item3'] ?? ''];
                $lis = '';
                foreach ($items as $item) {
                    $item = trim($item);
                    if ($item === '') {
                        continue;
                    }
                    $lis .= '<li>' . htmlspecialchars($item, ENT_QUOTES, 'UTF-8') . '</li>';
                }
                if ($lis === '') {
                    return '';
                }

                return '<section class="blok blok-tiga-hal"><h2>3 Hal yang Aku Suka</h2><ul>' . $lis . '</ul></section>';
            },
        ],
        'lagu-favorit' => [
            'label' => 'Lagu Favorit',
            'fields' => [
                'judul_lagu' => ['label' => 'Judul lagu', 'maxlen' => 60],
                'penyanyi' => ['label' => 'Penyanyi', 'maxlen' => 60],
            ],
            'render' => function (array $v): string {
                $judul = trim($v['judul_lagu'] ?? '');
                $penyanyi = trim($v['penyanyi'] ?? '');
                if ($judul === '' && $penyanyi === '') {
                    return '';
                }
                $judulHtml = htmlspecialchars($judul, ENT_QUOTES, 'UTF-8');
                $penyanyiHtml = htmlspecialchars($penyanyi, ENT_QUOTES, 'UTF-8');

                return '<section class="blok blok-lagu"><h2>Lagu Favorit</h2>'
                    . '<p class="lagu-judul">' . $judulHtml . '</p>'
                    . '<p class="lagu-penyanyi">' . $penyanyiHtml . '</p></section>';
            },
        ],
        'sosial' => [
            'label' => 'Media Sosial',
            'fields' => [
                'instagram' => ['label' => 'Username Instagram (tanpa @)', 'maxlen' => 30, 'pattern' => '/^[A-Za-z0-9._]{0,30}$/'],
                'tiktok' => ['label' => 'Username TikTok (tanpa @)', 'maxlen' => 30, 'pattern' => '/^[A-Za-z0-9._]{0,30}$/'],
            ],
            'render' => function (array $v): string {
                $ig = trim($v['instagram'] ?? '');
                $tt = trim($v['tiktok'] ?? '');
                $out = '';
                if ($ig !== '' && preg_match('/^[A-Za-z0-9._]{1,30}$/', $ig)) {
                    $igHtml = htmlspecialchars($ig, ENT_QUOTES, 'UTF-8');
                    $out .= '<a class="tombol-sosial" href="https://instagram.com/' . $igHtml . '">Instagram: @' . $igHtml . '</a>';
                }
                if ($tt !== '' && preg_match('/^[A-Za-z0-9._]{1,30}$/', $tt)) {
                    $ttHtml = htmlspecialchars($tt, ENT_QUOTES, 'UTF-8');
                    $out .= '<a class="tombol-sosial" href="https://tiktok.com/@' . $ttHtml . '">TikTok: @' . $ttHtml . '</a>';
                }
                if ($out === '') {
                    return '';
                }

                return '<section class="blok blok-sosial"><h2>Media Sosial</h2><div class="tombol-sosial-wrap">' . $out . '</div></section>';
            },
        ],
    ];
}

function karya_block_field_values_from_input(array $blockDef, array $input): array
{
    $values = [];
    foreach ($blockDef['fields'] as $name => $def) {
        $raw = isset($input[$name]) && is_string($input[$name]) ? $input[$name] : '';
        $clamped = karya_clamp_text($raw, $def['maxlen']);
        if (isset($def['pattern']) && !preg_match($def['pattern'], $clamped)) {
            $clamped = '';
        }
        $values[$name] = $clamped;
    }

    return $values;
}
