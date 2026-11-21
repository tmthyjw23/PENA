<?php
declare(strict_types=1);
namespace Pena\Service;
use Pena\Support\Db;

final class RateLimiter
{
    public function __construct(private readonly Db $db) {}

    /** Atomic fixed-window budget shared by all PHP workers. */
    public function allow(string $key,int $limit,int $seconds):bool
    {
        if($limit<1 || $seconds<1)return false;
        $window=intdiv(time(),$seconds)*$seconds;
        $hash=hash('sha256',$key);
        return $this->db->transaction(function(Db $db)use($hash,$window,$limit,$seconds){
            $db->run('INSERT INTO request_limits (bucket,window_start,hits,expires_at) VALUES (?,?,1,?) ON DUPLICATE KEY UPDATE hits=hits+1',[$hash,$window,$window+$seconds+86400]);
            $hits=(int)$db->value('SELECT hits FROM request_limits WHERE bucket=? AND window_start=?',[$hash,$window]);
            $db->run('DELETE FROM request_limits WHERE expires_at < ? LIMIT 100',[time()]);
            return $hits<=$limit;
        });
    }
}
