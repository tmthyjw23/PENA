<?php require_once dirname(__DIR__) . '/surat/_helpers.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php require dirname(__DIR__) . '/layout/head.php'; ?>
  <meta name="robots" content="noindex">
  <title><?= e($data['title'] ?? 'Pemeriksaan surat'); ?> - PENA</title>
</head>
<body class="min-h-dvh <?= e($bodyClass ?? ''); ?>">
<header class="sticky top-0 z-30 flex h-14 items-center gap-3 bg-rail px-4 lg:px-8">
  <span class="strip strip-carbon">PENA</span><span class="font-semibold">Pemeriksaan surat</span>
</header>
<main id="konten" class="px-4 py-5 lg:px-8 lg:py-8">
  <div class="mx-auto max-w-[1160px]">
