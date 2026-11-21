<?php
declare(strict_types=1);
namespace Pena\Service;
use Pena\Support\Db;

/** Dismissal is an account preference, not evidence that a user has learned the workflow. */
final class UserGuide
{
    public function __construct(private readonly Db $db){}
    public function needsIntroduction(int $userId):bool
    {
        return (bool)$this->db->value("SELECT id FROM users WHERE id=? AND status='active' AND email_verified_at IS NOT NULL AND guide_dismissed_at IS NULL",[$userId]);
    }
    public function dismiss(int $userId):void
    {
        $user=$this->db->one("SELECT id FROM users WHERE id=? AND status='active' AND email_verified_at IS NOT NULL",[$userId]);
        if(!$user)throw new PenaException('Masuk dengan akun aktif dan email terverifikasi untuk mengatur panduan.');
        $this->db->run('UPDATE users SET guide_dismissed_at=COALESCE(guide_dismissed_at,UTC_TIMESTAMP()) WHERE id=?',[$userId]);
    }
}
