<?php
declare(strict_types=1);

namespace Pena\Service;

use Pena\Support\Db;

/**
 * Accounts and workspaces. There is no admin role: every member of a workspace is a secretary.
 * The setup work an admin used to do happens inside the normal flow instead:
 *  - signing up creates the account and its personal workspace in one step;
 *  - anyone can create an organization workspace and becomes its owner (a marker, not a role);
 *  - the letterhead is entered with the first letter; reviewers are added while drafting.
 */
final class WorkspaceService
{
    public const PROFILE_KEYS = ['nama_mahasiswa', 'nim', 'program_studi'];

    public function __construct(private readonly Db $db) {}

    /** @return int new user id */
    public function register(string $name, string $email, string $password): int
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        $email = mb_strtolower(trim($email));
        $errors = [];
        if ($name === '' || mb_strlen($name) > 100) $errors['nama'] = 'Nama wajib diisi (maksimal 100 karakter).';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) $errors['email'] = 'Email tidak valid.';
        if (mb_strlen($password) < 8) $errors['password'] = 'Kata sandi minimal 8 karakter.';
        elseif (strlen($password) > 72) $errors['password'] = 'Kata sandi terlalu panjang. Gunakan kata sandi yang lebih singkat.';
        if ($errors !== []) throw new PenaException('Periksa kembali isian pendaftaran.', $errors);

        return $this->db->transaction(function (Db $db) use ($name, $email, $password) {
            if ($db->one('SELECT id FROM users WHERE email = ? FOR UPDATE', [$email])) {
                throw new PenaException('Periksa kembali isian pendaftaran.', ['email' => 'Email ini sudah terdaftar. Masuk dengan email tersebut.']);
            }
            $userId = $db->insert('INSERT INTO users (username, password, nama_lengkap, email) VALUES (?, ?, ?, ?)',
                                  [$this->freeUsername($email), password_hash($password, PASSWORD_DEFAULT), $name, $email]);
            $this->ensurePersonal($userId);
            return $userId;
        });
    }

    /** Every account has exactly one personal workspace. */
    public function ensurePersonal(int $userId): int
    {
        return $this->db->transaction(function (Db $db) use ($userId) {
            $id = $db->value("SELECT id FROM organizations WHERE kind = 'personal' AND owner_user_id = ? FOR UPDATE", [$userId]);
            if ($id === null) {
                $name = (string)$db->value('SELECT nama_lengkap FROM users WHERE id = ?', [$userId]);
                $id = $db->insert("INSERT INTO organizations (kind, name, short_name, owner_user_id, profile) VALUES ('personal', ?, ?, ?, ?)",
                                  [$name, 'pribadi-' . $userId, $userId, LetterService::json(['nama_mahasiswa' => $name])]);
            }
            $db->run("INSERT IGNORE INTO organization_members (organization_id, user_id, role) VALUES (?, ?, 'secretary')", [$id, $userId]);
            return (int)$id;
        });
    }

    /** @return int new workspace id; the creator becomes its owner and first secretary */
    public function createOrganization(int $userId, string $name, string $shortName): int
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        $shortName = strtoupper(trim($shortName));
        $errors = [];
        if (mb_strlen($name) < 3 || mb_strlen($name) > 150) $errors['nama'] = 'Nama organisasi 3 sampai 150 karakter.';
        if (!preg_match('/^[A-Z0-9][A-Z0-9-]{1,19}$/', $shortName)) {
            $errors['singkatan'] = 'Singkatan 2 sampai 20 huruf, angka, atau tanda hubung. Dipakai di nomor surat, contoh BEM-FILKOM.';
        } elseif (str_starts_with($shortName, 'PRIBADI-')) {
            $errors['singkatan'] = 'Singkatan ini dipakai sistem. Pilih singkatan lain.';
        }
        if ($errors !== []) throw new PenaException('Periksa kembali data organisasi.', $errors);

        return $this->db->transaction(function (Db $db) use ($userId, $name, $shortName) {
            if ($db->one('SELECT id FROM organizations WHERE short_name = ?', [$shortName])) {
                throw new PenaException('Periksa kembali data organisasi.', ['singkatan' => "Singkatan $shortName sudah dipakai organisasi lain."]);
            }
            $id = $db->insert("INSERT INTO organizations (kind, name, short_name, owner_user_id) VALUES ('organization', ?, ?, ?)",
                              [$name, $shortName, $userId]);
            $db->run("INSERT INTO organization_members (organization_id, user_id, role) VALUES (?, ?, 'secretary')", [$id, $userId]);
            return $id;
        });
    }

    /** Workspaces the user belongs to: organizations first (by name), then the personal one. */
    public function forUser(int $userId): array
    {
        return array_map([$this, 'shape'], $this->db->all(
            "SELECT o.id, o.kind, o.name, o.short_name, o.owner_user_id, o.profile, o.verified_at
               FROM organization_members m JOIN organizations o ON o.id = m.organization_id
              WHERE m.user_id = ? ORDER BY o.kind = 'personal', o.name", [$userId]));
    }

    /** The workspace to work in: the preferred one if the user belongs to it, else an organization, else personal. */
    public function current(int $userId, ?int $preferredId): ?array
    {
        $all = $this->forUser($userId);
        if ($all === []) {
            $this->ensurePersonal($userId); // accounts created before workspaces existed
            $all = $this->forUser($userId);
        }
        foreach ($all as $w) if ($preferredId !== null && $w['id'] === $preferredId) return $w;
        return $all[0] ?? null;
    }

    public function requireMember(int $workspaceId, int $userId): array
    {
        $row = $this->db->one('SELECT o.id, o.kind, o.name, o.short_name, o.owner_user_id, o.profile, o.verified_at
                                 FROM organization_members m JOIN organizations o ON o.id = m.organization_id
                                WHERE m.organization_id = ? AND m.user_id = ?', [$workspaceId, $userId]);
        if (!$row) throw new PenaException('Anda bukan anggota ruang kerja ini.');
        return $this->shape($row);
    }

    /** Adds a reviewer to the workspace directory while drafting; an identical active entry is reused. */
    public function addSigner(int $workspaceId, int $userId, string $name, string $position): int
    {
        $this->requireMember($workspaceId, $userId);
        $name = trim(preg_replace('/\s+/', ' ', $name));
        $position = trim(preg_replace('/\s+/', ' ', $position));
        if ($name === '' || $position === '' || mb_strlen($name) > 150 || mb_strlen($position) > 150) {
            throw new PenaException('Pemeriksa baru perlu nama dan jabatan (masing-masing maksimal 150 karakter).');
        }
        $existing = $this->db->value('SELECT id FROM signers WHERE organization_id = ? AND name = ? AND position = ? AND is_active = 1',
                                     [$workspaceId, $name, $position]);
        return $existing !== null ? (int)$existing
            : $this->db->insert('INSERT INTO signers (organization_id, name, position) VALUES (?, ?, ?)', [$workspaceId, $name, $position]);
    }

    /** The letterhead an organization enters with its first letter. */
    public function addLetterhead(int $workspaceId, int $userId, string $name, string $address, string $contact): int
    {
        $workspace = $this->requireMember($workspaceId, $userId);
        if ($workspace['kind'] !== 'organization') throw new PenaException('Kop surat hanya untuk ruang organisasi.');
        $name = trim($name); $address = trim($address); $contact = trim($contact);
        $errors = [];
        if ($name === '' || mb_strlen($name) > 150) $errors['kop.name'] = 'Nama di kop wajib diisi (maksimal 150 karakter).';
        if ($address === '' || mb_strlen($address) > 255) $errors['kop.address'] = 'Alamat wajib diisi (maksimal 255 karakter).';
        if (mb_strlen($contact) > 255) $errors['kop.contact'] = 'Kontak maksimal 255 karakter.';
        if ($errors !== []) throw new PenaException('Lengkapi kop surat organisasi.', $errors);
        // The first kop of an organization becomes its default kop.
        $default = !$this->db->value('SELECT COUNT(*) FROM letterheads WHERE organization_id = ? AND is_active = 1 AND is_default = 1', [$workspaceId]);
        return $this->db->insert('INSERT INTO letterheads (organization_id, name, address, contact, is_default, created_by) VALUES (?, ?, ?, ?, ?, ?)',
                                 [$workspaceId, $name, $address, $contact === '' ? null : $contact, $default ? 1 : 0, $userId]);
    }

    /** Remembers identity fields (name, NIM, study program) typed into a personal letter for the next one. */
    public function rememberProfile(int $workspaceId, int $userId, array $fields): void
    {
        $workspace = $this->requireMember($workspaceId, $userId);
        if ($workspace['kind'] !== 'personal') return;
        $profile = $workspace['profile'];
        foreach (self::PROFILE_KEYS as $key) {
            if (isset($fields[$key]) && is_string($fields[$key]) && trim($fields[$key]) !== '') $profile[$key] = trim($fields[$key]);
        }
        if ($profile !== $workspace['profile']) {
            $this->db->run('UPDATE organizations SET profile = ? WHERE id = ?', [LetterService::json($profile), $workspaceId]);
        }
    }

    /** Fills empty identity fields of a template from the personal profile. */
    public static function prefill(array $workspace, array $definition, array $values): array
    {
        if ($workspace['kind'] !== 'personal') return $values;
        foreach ($definition['fields'] ?? [] as $spec) {
            $key = $spec['key'];
            if (!empty($spec['profile']) && ($values[$key] ?? null) === null && isset($workspace['profile'][$key])) {
                $values[$key] = $workspace['profile'][$key];
            }
        }
        return $values;
    }

    private function freeUsername(string $email): string
    {
        $base = substr(preg_replace('/[^a-z0-9._]/', '', strtolower(strstr($email, '@', true) ?: 'pengguna')) ?: 'pengguna', 0, 40);
        $candidate = $base;
        for ($i = 2; $this->db->one('SELECT id FROM users WHERE username = ?', [$candidate]); $i++) $candidate = $base . $i;
        return $candidate;
    }

    private function shape(array $row): array
    {
        $row['id'] = (int)$row['id'];
        $row['owner_user_id'] = $row['owner_user_id'] === null ? null : (int)$row['owner_user_id'];
        $row['profile'] = $row['profile'] ? (json_decode($row['profile'], true) ?: []) : [];
        return $row;
    }
}
