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
$operatorArea=!empty($data['operator_area']);
$nav = [
    'dashboard' => ['Beranda', APP_PATH . '/dashboard/'],
    'surat' => ['Surat', APP_PATH . '/surat/'],
    'buat' => ['Buat surat', APP_PATH . '/surat/buat/'],
];
if($operatorArea)$nav=[
    'operator'=>['Ringkasan',APP_PATH.'/operator/'],
    'accounts'=>['Akun',APP_PATH.'/operator/akun/'],
    'organizations'=>['Organisasi',APP_PATH.'/operator/organisasi/'],
    'services'=>['Layanan',APP_PATH.'/operator/layanan/'],
    'logs'=>['Log',APP_PATH.'/operator/log/'],
];
$cur = static fn(string $k) => $active === $k ? ' aria-current="page"' : '';
$workspaces = $data['workspaces'] ?? [];
$wsLabel = static fn(array $w) => $w['kind'] === 'personal' ? 'Ruang pribadi' : $w['name'];
$curOrgLabel = $org ? $wsLabel($org) : 'Pilih ruang kerja';
$curOrgStrip = $org ? ($org['kind'] === 'personal' ? 'Pribadi' : $org['short_name']) : 'PENA';

// Workspace switcher: stable dropdown pinned at the top of the navigation
$renderSwitcher = static function (string $id, string $placement = 'desktop') use ($workspaces, $org, $wsLabel, $curOrgLabel, $curOrgStrip, $data): void {
    if (!$workspaces) return;
    $isMobile = $placement === 'mobile';
    $panelPos = $isMobile ? 'fixed inset-x-4 top-[60px] max-h-[calc(100dvh-140px)] overflow-y-auto' : 'absolute left-0 top-full mt-1.5 w-full';
    $summaryClass = $isMobile
        ? 'flex min-h-9 max-w-[200px] sm:max-w-[260px] cursor-pointer list-none items-center justify-between gap-1.5 rounded-control bg-ink/10 px-2 py-1 text-left font-semibold hover:bg-ink/15 focus-visible:outline-2 focus-visible:outline-ink [&::-webkit-details-marker]:hidden'
        : 'flex min-h-11 w-full cursor-pointer list-none items-center justify-between gap-2 rounded-control bg-ink/10 px-2.5 py-1.5 text-left font-semibold hover:bg-ink/15 focus-visible:outline-2 focus-visible:outline-ink [&::-webkit-details-marker]:hidden';
    ?>
    <details data-menu class="relative" id="<?= e($id); ?>">
      <summary data-tour="workspace" class="<?= $summaryClass; ?>" aria-label="Ruang kerja aktif: <?= e($curOrgLabel); ?>. Buka untuk beralih ruang kerja.">
        <span class="flex min-w-0 items-center gap-2">
          <?= pena_strip($curOrgStrip, 'carbon'); ?>
          <span class="truncate text-[13.5px]"><?= e($curOrgLabel); ?></span>
        </span>
        <span class="shrink-0 text-xs text-secondary" aria-hidden="true">▾</span>
      </summary>
      <div class="<?= $panelPos; ?> z-50 rounded-panel border border-line bg-surface p-2 shadow-paper">
        <p class="colhead px-2 py-1 text-xs text-secondary">Ruang kerja</p>
        <ul class="mt-1 space-y-1" role="list">
          <?php foreach ($workspaces as $w): $on = $org && (int)$org['id'] === (int)$w['id']; ?>
            <li>
              <?php if ($on): ?>
                <div class="flex min-h-9 items-center justify-between gap-2 rounded-control bg-raised px-2 font-semibold">
                  <span class="flex min-w-0 items-center gap-2">
                    <?= pena_strip($w['kind'] === 'personal' ? 'Pribadi' : $w['short_name'], 'carbon'); ?>
                    <span class="truncate text-[13.5px]"><?= e($wsLabel($w)); ?></span>
                  </span>
                  <span class="text-xs font-bold text-ok" title="Sedang aktif" aria-label="Sedang aktif">✓</span>
                </div>
              <?php else: ?>
                <form method="POST" action="<?= APP_PATH; ?>/ruang/pilih/<?= (int)$w['id']; ?>">
                  <input type="hidden" name="csrf_token" value="<?= e($data['csrf_token'] ?? ''); ?>">
                  <button type="submit" class="flex min-h-9 w-full items-center gap-2 rounded-control px-2 text-left text-[13.5px] hover:bg-raised">
                    <?= pena_strip($w['kind'] === 'personal' ? 'Pribadi' : $w['short_name'], 'outline'); ?>
                    <span class="truncate"><?= e($wsLabel($w)); ?></span>
                  </button>
                </form>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <div class="mt-2 border-t border-line pt-2">
          <a href="<?= APP_PATH; ?>/ruang/baru/" class="flex min-h-9 items-center px-2 text-[13px] font-semibold text-action underline">+ Ruang organisasi baru</a>
        </div>
      </div>
    </details>
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

<aside class="fixed inset-y-0 left-0 z-30 hidden w-[220px] flex-col overflow-y-auto bg-rail px-4 py-5 lg:flex" aria-label="Navigasi">
  <div class="flex shrink-0 flex-col gap-3">
    <a href="<?= APP_PATH; ?>/dashboard/" class="flex min-h-11 items-center gap-3 font-semibold no-underline">
      <span class="strip strip-carbon">PENA</span><span><?= $operatorArea?'Operator':'Ruang surat'; ?></span>
    </a>

    <?php if ($workspaces): ?>
      <div class="border-t border-ink/15 pt-3">
        <p class="colhead mb-1.5 text-xs text-ink/75">Ruang kerja</p>
        <?php $renderSwitcher('ws-switcher-desktop', 'desktop'); ?>
      </div>
    <?php endif; ?>
  </div>

  <nav class="mt-6 shrink-0" aria-label="Menu utama">
    <ul class="space-y-1">
      <?php foreach ($nav as $k => [$label, $href]): ?>
        <li><a data-tour="<?= e($k); ?>" href="<?= $href; ?>"<?= $cur($k); ?>
          class="flex min-h-11 items-center rounded-control px-3 no-underline hover:bg-ink/10 aria-[current=page]:bg-plate aria-[current=page]:font-semibold aria-[current=page]:text-on-plate"><?= $label; ?></a></li>
      <?php endforeach; ?>
    </ul>
  </nav>

  <div class="mt-auto shrink-0 border-t border-ink/15 pt-4">
    <p class="truncate text-[14.5px] font-semibold"><?= e($userName); ?></p>
    <p class="truncate text-xs text-ink/75"><?= $operatorArea?'Operator platform':($org ? e(pena_role($org, (int)($_SESSION['user-id'] ?? 0))) : 'Pengguna'); ?></p>
    <?php if(!empty($_SESSION['is-operator'])): ?><a href="<?= APP_PATH; ?>/<?= $operatorArea?'dashboard/':'operator/'; ?>" class="mt-2 flex min-h-9 items-center text-[13.5px] underline"><?= $operatorArea?'Ke ruang surat':'Operator platform'; ?></a><?php endif; ?>
    <a data-tour="help" href="<?= APP_PATH; ?>/bantuan/" class="mt-2 flex min-h-9 items-center text-[13.5px] underline">Bantuan</a>
    <a href="<?= APP_PATH; ?>/preferensi/" class="mt-2 flex min-h-9 items-center text-[13.5px] underline">Pengaturan AI</a>
    <a href="<?= APP_PATH; ?>/login/logout/" class="mt-2 inline-flex min-h-9 items-center text-[13.5px] underline decoration-ink/40 hover:decoration-ink">Keluar</a>
  </div>
</aside>

<header class="sticky top-0 z-30 flex h-14 items-center justify-between gap-2 bg-rail px-4 lg:hidden">
  <div class="flex min-w-0 items-center gap-2">
    <a href="<?= APP_PATH; ?>/dashboard/" class="flex min-h-11 shrink-0 items-center no-underline" aria-label="PENA, ke Beranda">
      <span class="strip strip-carbon">PENA</span>
    </a>
    <?php if ($workspaces): ?>
      <?php $renderSwitcher('ws-switcher-mobile', 'mobile'); ?>
    <?php endif; ?>
  </div>

  <details data-menu class="relative shrink-0" id="user-menu-mobile">
    <summary data-tour="help" class="flex size-10 cursor-pointer list-none items-center justify-center rounded-control bg-ink/10 font-cond font-bold hover:bg-ink/15 [&::-webkit-details-marker]:hidden" aria-label="Menu akun <?= e($userName); ?>"><?= e($initials); ?></summary>
    <div class="absolute right-0 top-12 w-56 rounded-panel border border-line bg-surface p-4 shadow-paper">
      <p class="font-semibold"><?= e($userName); ?></p>
      <?php if ($org): ?>
        <p class="mt-0.5 text-xs text-secondary"><?= e(pena_role($org, (int)($_SESSION['user-id'] ?? 0))); ?></p>
      <?php endif; ?>
      <div class="mt-3 border-t border-line pt-2">
        <?php if(!empty($_SESSION['is-operator'])): ?><a href="<?= APP_PATH; ?>/<?= $operatorArea?'dashboard/':'operator/'; ?>" class="flex min-h-11 items-center underline"><?= $operatorArea?'Ke ruang surat':'Operator platform'; ?></a><?php endif; ?>
        <a href="<?= APP_PATH; ?>/bantuan/" class="flex min-h-11 items-center underline">Bantuan</a>
        <a href="<?= APP_PATH; ?>/preferensi/" class="flex min-h-11 items-center underline">Pengaturan AI</a>
        <a href="<?= APP_PATH; ?>/login/logout/" class="flex min-h-9 items-center text-rev underline">Keluar</a>
      </div>
    </div>
  </details>
</header>

<nav class="fixed inset-x-0 bottom-0 z-30 grid h-16 <?= $operatorArea?'grid-cols-5 text-[13px]':'grid-cols-3'; ?> gap-2 bg-rail px-4 py-2 lg:hidden" aria-label="Menu utama">
  <?php foreach ($nav as $k => [$label, $href]): ?>
    <a data-tour="<?= e($k); ?>" href="<?= $href; ?>"<?= $cur($k); ?> class="flex items-center justify-center rounded-control no-underline aria-[current=page]:bg-plate aria-[current=page]:font-semibold aria-[current=page]:text-on-plate"><?= $label; ?></a>
  <?php endforeach; ?>
</nav>

<main id="konten" class="app-content px-4 py-5 lg:ml-[220px] lg:px-8 lg:py-6">
  <div class="mx-auto max-w-[1160px]">
