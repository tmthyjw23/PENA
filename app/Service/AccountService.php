<?php
declare(strict_types=1);
namespace Pena\Service;

use Closure;
use DateTimeImmutable;
use Pena\Support\Db;

final class AccountService
{
    private readonly Closure $clock;

    public function __construct(private readonly Db $db, private readonly ?ResendMailer $mailer,
        private readonly string $appUrl, ?Closure $clock = null)
    {
        // Links must use configured application origin, never an inbound Host header.
        $parts = parse_url($appUrl);
        $local = ($_ENV['APP_ENV'] ?? 'production') === 'local';
        if (empty($parts['host']) || isset($parts['user']) || isset($parts['query']) || isset($parts['fragment'])
            || !in_array($parts['scheme'] ?? '', $local ? ['http','https'] : ['https'], true)) {
            throw new PenaException('Alamat aplikasi untuk email belum diatur dengan benar.');
        }
        $this->clock = $clock ?? static fn() => new DateTimeImmutable();
    }

    public function sendVerification(int $userId): void
    {
        $this->issue($userId, 'verify_email');
    }

    /** A committed, unused link means the provider accepted a request, not inbox delivery. */
    public function hasPendingVerification(int $userId): bool
    {
        return (bool)$this->db->value("SELECT t.id FROM account_tokens t JOIN users u ON u.id=t.user_id WHERE u.id=? AND u.status='active' AND u.email_verified_at IS NULL AND t.purpose='verify_email' AND t.used_at IS NULL AND t.expires_at>? LIMIT 1",
            [$userId,($this->clock)()->format('Y-m-d H:i:s')]);
    }

    /** The controller always presents the same response for known and unknown email addresses. */
    public function requestPasswordReset(string $email): void
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) return;
        $user = $this->db->one("SELECT id FROM users WHERE email=? AND status='active'", [$email]);
        if ($user) $this->issue((int)$user['id'], 'reset_password');
    }

    private function issue(int $userId, string $purpose): void
    {
        $mailer = $this->mailer ?? ResendMailer::fromEnv();
        $limiter = new RateLimiter($this->db);
        if (!$limiter->allow('account-email:'.$purpose.':'.$userId, 3, 3600)
            || !$limiter->allow('account-email:platform', 90, 86400)) {
            throw new PenaException('Batas permintaan email tercapai. Silakan coba lagi nanti.');
        }
        $token = bin2hex(random_bytes(32));
        $now = ($this->clock)();
        $expiry = $now->modify($purpose === 'verify_email' ? '+24 hours' : '+30 minutes');
        // One user lock serializes issuance and consumption, including concurrent resends.
        $this->db->transaction(function(Db $db) use ($userId, $purpose, $token, $expiry, $mailer) {
            $user = $db->one("SELECT email, email_verified_at FROM users WHERE id=? AND status='active' FOR UPDATE", [$userId]);
            if (!$user || ($purpose === 'verify_email' && $user['email_verified_at'] !== null)) return;
            $db->run('DELETE FROM account_tokens WHERE user_id=? AND purpose=?', [$userId, $purpose]);
            $db->insert('INSERT INTO account_tokens (user_id,purpose,token_hash,expires_at) VALUES (?,?,?,?)',
                [$userId,$purpose,hash('sha256',$token),$expiry->format('Y-m-d H:i:s')]);
            $verify = $purpose === 'verify_email';
            // Fragment is not sent in HTTP requests or proxy access logs. Browser moves it to the POST form.
            $url = rtrim($this->appUrl,'/').'/akun/'.($verify ? 'konfirmasi' : 'reset').'/#'.$token;
            $message = AccountEmail::compose($verify, $url);
            $mailer->send($user['email'], $message['subject'], $message['text'], $message['html']);
        });
    }

    public function verifyEmail(string $token): void
    {
        $this->consume($token, 'verify_email', null);
    }

    public function resetPassword(string $token, string $password, string $confirmation): void
    {
        if (mb_strlen($password) < 8 || strlen($password) > 72) {
            throw new PenaException('Kata sandi harus berisi minimal 8 karakter dan tidak terlalu panjang.');
        }
        if (!hash_equals($password, $confirmation)) throw new PenaException('Kata sandi yang diulang belum sama.');
        $this->consume($token, 'reset_password', password_hash($password, PASSWORD_DEFAULT));
    }

    private function consume(string $token, string $purpose, ?string $hash): void
    {
        $invalid = 'Tautan tidak berlaku, kedaluwarsa, atau sudah digunakan. Minta tautan baru.';
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) throw new PenaException($invalid);
        $tokenHash = hash('sha256', $token);
        $owner = $this->db->value('SELECT user_id FROM account_tokens WHERE token_hash=? AND purpose=?', [$tokenHash,$purpose]);
        if ($owner === null) throw new PenaException($invalid);
        $now = ($this->clock)()->format('Y-m-d H:i:s');
        $this->db->transaction(function(Db $db) use ($owner,$tokenHash,$purpose,$hash,$now,$invalid) {
            $user = $db->one("SELECT id FROM users WHERE id=? AND status='active' FOR UPDATE", [$owner]);
            $row = $db->one('SELECT id FROM account_tokens WHERE user_id=? AND token_hash=? AND purpose=? AND used_at IS NULL AND expires_at>? FOR UPDATE',
                [$owner,$tokenHash,$purpose,$now]);
            if (!$user || !$row) throw new PenaException($invalid);
            if ($purpose === 'verify_email') {
                $db->run('UPDATE users SET email_verified_at=? WHERE id=?', [$now,$owner]);
                $db->run('UPDATE account_tokens SET used_at=? WHERE user_id=? AND purpose=? AND used_at IS NULL', [$now,$owner,$purpose]);
            } else {
                $db->run('UPDATE users SET password=?,auth_version=auth_version+1 WHERE id=?', [$hash,$owner]);
                $db->run('UPDATE account_tokens SET used_at=? WHERE user_id=? AND used_at IS NULL', [$now,$owner]);
            }
        });
    }

    public static function sessionIsCurrent(?array $user, mixed $version): bool
    {
        return $user !== null && $user['status'] === 'active' && is_int($version)
            && (int)$user['auth_version'] === $version;
    }
}
