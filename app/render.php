<?php

declare(strict_types=1);

if (!defined('KARYA_APP')) {
    http_response_code(404);
    exit;
}

// $fields = ['judul' => string, 'tentang' => string]
// $selectedBlocks = ['tiga-hal' => ['item1'=>..], 'lagu-favorit' => [...]]
function karya_render_child_page(string $slug, array $fields, array $selectedBlocks): string
{
    $judul = htmlspecialchars(karya_clamp_text($fields['judul'] ?? $slug, 60), ENT_QUOTES, 'UTF-8');
    $tentang = htmlspecialchars(karya_clamp_text($fields['tentang'] ?? '', 280), ENT_QUOTES, 'UTF-8');
    $slugHtml = htmlspecialchars($slug, ENT_QUOTES, 'UTF-8');

    $catalog = karya_block_catalog();
    $blocksHtml = '';
    foreach ($selectedBlocks as $blockId => $values) {
        if (!isset($catalog[$blockId]) || !is_array($values)) {
            continue;
        }
        $blocksHtml .= $catalog[$blockId]['render']($values);
    }

    return <<<HTML
<!doctype html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$judul} — {$slugHtml}</title>
<style>
  :root { color-scheme: light; }
  body { font-family: system-ui, sans-serif; margin:0; padding:2rem 1.25rem;
         background:linear-gradient(160deg,#fef6ff,#eef3ff); color:#232323; }
  main { max-width:480px; margin:0 auto; }
  h1 { font-size:1.75rem; margin:0 0 .5rem; }
  .tentang { color:#555; line-height:1.5; }
  section.blok { margin-top:1.75rem; padding:1rem 1.25rem; background:#fff;
                 border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,.06); }
  section.blok h2 { font-size:1rem; margin:0 0 .6rem; color:#6b3fa0; }
  .blok-tiga-hal ul { margin:0; padding-left:1.1rem; }
  .lagu-judul { font-weight:600; margin:.2rem 0; }
  .lagu-penyanyi { color:#666; margin:.2rem 0; }
  .tombol-sosial-wrap { display:flex; flex-wrap:wrap; gap:.5rem; }
  .tombol-sosial { display:inline-block; padding:.5rem .9rem; border-radius:999px;
                   background:#6b3fa0; color:#fff; text-decoration:none; font-size:.9rem; }
  footer { margin-top:2.5rem; text-align:center; color:#999; font-size:.75rem; }
</style>
</head>
<body>
<main>
  <h1>{$judul}</h1>
  <p class="tentang">{$tentang}</p>
  {$blocksHtml}
  <footer>Dibuat di karya.labpplg.web.id/{$slugHtml}</footer>
</main>
</body>
</html>
HTML;
}
