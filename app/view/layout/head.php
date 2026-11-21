<?php
// Shared <head> for PENA pages (Tailwind build, Figma "Jadwal saku"). Template example pages keep template/_head.php.
// Fonts are self-hosted in public/assets/fonts (no request to Google), so the demo works offline.
$cssFile = dirname(__DIR__, 3) . '/public/assets/css/app.css';
?>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#E7B53B">
  <link rel="preload" href="<?= APP_PATH; ?>/assets/fonts/barlow-400.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="<?= APP_PATH; ?>/assets/fonts/barlow-600.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="<?= APP_PATH; ?>/assets/css/app.css?v=<?= @filemtime($cssFile) ?: 1; ?>">

  <link rel="icon" type="image/svg+xml" href="<?= APP_PATH; ?>/assets/favicon.svg?v=2">
  <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_PATH; ?>/assets/favicon.png?v=2">
  <script src="<?= APP_PATH; ?>/assets/js/draft-guard.js?v=<?= @filemtime(dirname(__DIR__,3).'/public/assets/js/draft-guard.js') ?: 1; ?>" defer></script>
  <script src="<?= APP_PATH; ?>/assets/js/feedback.js?v=<?= @filemtime(dirname(__DIR__,3).'/public/assets/js/feedback.js') ?: 1; ?>" defer></script>
