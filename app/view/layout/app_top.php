<?php
/**
 * Secretary app shell: goldenrod rail on desktop, goldenrod top bar + bottom tab bar on phones.
 * Expects $data['title'], optional $data['active_menu'] (dashboard|surat|buat), $data['org'] (current workspace),
 * $data['workspaces'] (all of the user's workspaces, for the switcher), $data['mobile_title'].
 */
require_once dirname(__DIR__) . '/surat/_helpers.php';
$active = $data['active_menu'] ?? 'dashboard';
$userName = trim(preg_replace('/\(.*?\)/', '', (string)($_SESSION['user-name'] ?? 'Pengguna')));
$words = preg_split('/\s+/', $userName) ?: ['P'];
$initials = strtoupper(mb_substr($words[0], 0, 1) . (isset($words[1]) ? mb_substr($words[1], 0, 1) : ''));
$org = $data['org'] ?? null;
$nav = [
    'dashboard' => ['Beranda', APP_PATH . '/dashboard/'],
    'surat' => ['Surat', APP_PATH . '/surat/'],
    'buat' => ['Buat surat', APP_PATH . '/surat/buat/'],
];
$cur = static fn(string $k) => $active === $k ? ' aria-current="page"' : '';
$workspaces = $data['workspaces'] ?? [];
$wsLabel = static fn(array $w) => $w['kind'] === 'personal' ? 'Ruang pribadi' : $w['name'];
// Workspace switcher: each entry is a small POST form (switching changes session state).
$switcher = static function (string $tone) use ($workspaces, $org, $wsLabel, $data): void {
    if (!$workspaces) return; ?>
    <ul class="space-y-1" aria-label="Ruang kerja">
      <?php foreach ($workspaces as $w): $on = $org && (int)$org['id'] === (int)$w['id']; ?>
        <li>
          <?php if ($on): ?>
            <p class="flex min-h-9 items-center gap-2 rounded-control px-2 font-semibold" aria-current="true">
              <?= pena_strip($w['kind'] === 'personal' ? 'Pribadi' : $w['short_name'], 'carbon'); ?><span class="truncate"><?= e($wsLabel($w)); ?></span></p>
          <?php else: ?>
            <form method="POST" action="<?= APP_PATH; ?>/ruang/pilih/<?= (int)$w['id']; ?>">
              <input type="hidden" name="csrf_token" value="<?= e($data['csrf_token'] ?? ''); ?>">
              <button class="flex min-h-9 w-full items-center gap-2 rounded-control px-2 text-left <?= $tone; ?>">
                <?= pena_strip($w['kind'] === 'personal' ? 'Pribadi' : $w['short_name'], 'outline'); ?><span class="truncate"><?= e($wsLabel($w)); ?></span></button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
      <li><a href="<?= APP_PATH; ?>/ruang/baru/" class="flex min-h-9 items-center px-2 text-[13.5px] font-semibold text-action underline">+ Ruang organisasi</a></li>
    </ul>
<?php };
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php require __DIR__ . '/head.php'; ?>
  <title><?= e($data['title'] ?? 'PENA'); ?></title>
</head>
<body class="min-h-dvh pb-20 lg:pb-0">
<a href="#konten" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-control focus:bg-surface focus:px-4 focus:py-2">Lompat ke konten</a>

<aside class="fixed inset-y-0 left-0 z-30 hidden w-[220px] flex-col bg-rail px-4 py-5 lg:flex" aria-label="Navigasi">
  <a href="<?= APP_PATH; ?>/dashboard/" class="flex min-h-11 items-center gap-3 font-semibold no-underline">
    <span class="strip strip-carbon">PENA</span><span>Surat organisasi</span>
  </a>
  <nav class="mt-8" aria-label="Menu utama">
    <ul class="space-y-1">
      <?php foreach ($nav as $k => [$label, $href]): ?>
        <li><a href="<?= $href; ?>"<?= $cur($k); ?>
          class="flex min-h-11 items-center rounded-control px-3 no-underline hover:bg-ink/10 aria-[current=page]:bg-plate aria-[current=page]:font-semibold aria-[current=page]:text-on-plate"><?= $label; ?></a></li>
      <?php endforeach; ?>
    </ul>
  </nav>
  <div class="mt-auto">
    <p class="colhead">Ruang kerja</p>
    <?php $switcher('hover:bg-ink/10'); ?>
    <p class="mt-4 font-semibold"><?= e($userName); ?><?= $org ? ', ' . e(pena_role($org, (int)($_SESSION['user-id'] ?? 0))) : ''; ?></p>
    <a href="<?= APP_PATH; ?>/login/logout/" class="mt-2 inline-flex min-h-11 items-center underline decoration-ink/40 hover:decoration-ink">Keluar</a>
  </div>
</aside>

<header class="sticky top-0 z-30 flex h-14 items-center gap-3 bg-rail px-4 lg:hidden">
  <a href="<?= APP_PATH; ?>/dashboard/" class="flex min-h-11 items-center no-underline" aria-label="PENA, ke Beranda"><span class="strip strip-carbon">PENA</span></a>
  <span class="font-semibold"><?= e($data['mobile_title'] ?? $nav[$active][0] ?? 'PENA'); ?></span>
  <details class="relative ml-auto">
    <summary class="flex size-11 cursor-pointer list-none items-center justify-center font-cond font-bold [&::-webkit-details-marker]:hidden" aria-label="Menu akun <?= e($userName); ?>"><?= e($initials); ?></summary>
    <div class="absolute right-0 top-12 w-64 rounded-panel bg-surface p-4 shadow-paper">
      <p class="font-semibold"><?= e($userName); ?></p>
      <?php if ($workspaces): ?>
        <p class="colhead mt-3">Ruang kerja</p>
        <div class="mt-1"><?php $switcher('hover:bg-raised'); ?></div>
      <?php endif; ?>
      <a href="<?= APP_PATH; ?>/login/logout/" class="mt-3 inline-flex min-h-11 items-center text-rev underline">Keluar</a>
    </div>
  </details>
</header>

<nav class="fixed inset-x-0 bottom-0 z-30 grid h-16 grid-cols-3 gap-2 bg-rail px-4 py-2 lg:hidden" aria-label="Menu utama">
  <?php foreach ($nav as $k => [$label, $href]): ?>
    <a href="<?= $href; ?>"<?= $cur($k); ?> class="flex items-center justify-center rounded-control no-underline aria-[current=page]:bg-plate aria-[current=page]:font-semibold aria-[current=page]:text-on-plate"><?= $label; ?></a>
  <?php endforeach; ?>
</nav>

<main id="konten" class="px-4 py-5 lg:ml-[220px] lg:px-8 lg:py-6">
  <div class="mx-auto max-w-[1160px]">
