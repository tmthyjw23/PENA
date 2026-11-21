<?php
// View helpers for PENA pages (Tailwind, arah "Jadwal saku"). Every output goes through e().
if (!function_exists('e')) {
    function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('pena_mark')) {
    /**
     * Geometric status mark (16px). Always rendered next to its text label: colour is never the only signal.
     * approved = filled + check, pending = ring, waiting = dashed ring, revision = slashed ring, cancelled = filled + bar.
     */
    function pena_mark(string $kind, string $class = ''): string {
        $c = 'mark ' . $class;
        return match ($kind) {
            'approved' => '<svg class="' . $c . ' text-ok" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="8" fill="currentColor"/><path d="M4.6 8.3l2.2 2.2 4.6-4.8" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'pending' => '<svg class="' . $c . ' text-action" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6.25" fill="none" stroke="currentColor" stroke-width="3.5"/></svg>',
            'revision' => '<svg class="' . $c . ' text-rev" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6.75" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M3.4 12.6l9.2-9.2" stroke="currentColor" stroke-width="1.6"/></svg>',
            'cancelled' => '<svg class="' . $c . ' text-muted" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="8" fill="currentColor"/><path d="M4.5 8h7" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>',
            default => '<svg class="' . $c . ' text-muted" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6.75" fill="none" stroke="currentColor" stroke-width="1.5" stroke-dasharray="2.4 2.2"/></svg>',
        };
    }
}

if (!function_exists('pena_status')) {
    /** Letter status: [label, mark kind, text colour class]. */
    function pena_status(string $status): array {
        return match ($status) {
            'draft' => ['Draf', 'waiting', 'text-secondary'],
            'in_review' => ['Proses pemeriksaan', 'pending', 'text-action'],
            'revision_requested' => ['Diminta revisi', 'revision', 'text-rev'],
            'approved' => ['Disetujui', 'approved', 'text-ok'],
            'cancelled' => ['Dibatalkan', 'cancelled', 'text-secondary'],
            default => [$status, 'waiting', 'text-secondary'],
        };
    }
}

if (!function_exists('pena_chip')) {
    function pena_chip(string $status): string {
        [$label, $mark, $cls] = pena_status($status);
        return '<span class="inline-flex items-center gap-2 font-semibold ' . $cls . '">' . pena_mark($mark) . e($label) . '</span>';
    }
}

if (!function_exists('pena_strip')) {
    /** Code strip: PMJ, V2, T1, REVISI. Tones: outline, carbon, ink, ok, rev. */
    function pena_strip(string $text, string $tone = 'outline', string $extra = ''): string {
        return '<span class="strip strip-' . e($tone) . ' ' . e($extra) . '">' . e($text) . '</span>';
    }
}

if (!function_exists('pena_flash')) {
    function pena_flash(?array $flash): void {
        if (!$flash || empty($flash['text'])) return;
        $bad = ($flash['type'] ?? '') === 'danger';
        $warn = ($flash['type'] ?? '') === 'warning';
        $cls = $bad ? 'bg-rev-soft' : ($warn ? 'bg-hl' : 'bg-ok-soft');
        $mark = $bad ? 'revision' : ($warn ? 'waiting' : 'approved');
        echo '<div class="mb-6 flex items-start gap-3 rounded-panel px-4 py-3 ' . $cls . '" role="' . ($bad ? 'alert' : 'status') . '">'
           . pena_mark($mark, 'mt-1') . '<p>' . e($flash['text']) . '</p></div>';
    }
}

if (!function_exists('pena_date')) {
    /** "17 Nov 2026, 09.02" with Indonesian month names. */
    function pena_date(?string $value, bool $withTime = true): string {
        if ($value === null || $value === '') return '';
        $t = strtotime($value);
        $m = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][(int)date('n', $t) - 1];
        return date('j', $t) . ' ' . $m . ' ' . date('Y', $t) . ($withTime ? ', ' . date('H.i', $t) : '');
    }
}

if (!function_exists('pena_short')) {
    /** Timetable time "17.11 09.01". */
    function pena_short(?string $value): string {
        if ($value === null || $value === '') return '';
        $t = strtotime($value);
        return date('d.m H.i', $t);
    }
}

if (!function_exists('pena_nonce')) {
    /** nonce="..." attribute for an inline script tag; the Content-Security-Policy only runs inline code that carries it. */
    function pena_nonce(): string {
        return ' nonce="' . htmlspecialchars(Controller::csp_nonce(), ENT_QUOTES, 'UTF-8') . '"';
    }
}

if (!function_exists('pena_role')) {
    /** Label of the user in a workspace. There is no admin role; the creator is marked as owner. */
    function pena_role(array $workspace, int $userId): string {
        if (($workspace['kind'] ?? '') === 'personal') return 'pribadi';
        return ($workspace['owner_user_id'] ?? null) === $userId ? 'pemilik' : 'sekretaris';
    }
}
