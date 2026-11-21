<?php
declare(strict_types=1);
namespace Pena\Service;

/** Session drafts are scoped to a person/workspace/process; revisions reject stale tabs. */
final class AiDraftStore
{
    private array $runs;

    public function __construct(array &$session, private readonly int $now = 0)
    {
        $session['pena_ai_runs'] ??= [];
        $this->runs =& $session['pena_ai_runs'];
        $cutoff = $this->time() - 7200;
        foreach ($this->runs as $id => $run) if ($run['updated_at'] < $cutoff) unset($this->runs[$id]);
    }

    public function create(int $userId, int $workspaceId): string
    {
        if (count($this->runs) >= 12) throw new PenaException('Terlalu banyak proses penyusunan terbuka. Simpan salah satu hasil sebelum membuat surat baru.');
        $id = bin2hex(random_bytes(16));
        $this->runs[$id] = ['user_id'=>$userId,'workspace_id'=>$workspaceId,'revision'=>0,
            'updated_at'=>$this->time(),'data'=>[]];
        return $id;
    }

    public function read(string $id, int $userId, int $workspaceId): array
    {
        $run = $this->runs[$id] ?? null;
        if (!$run || $run['user_id'] !== $userId || $run['workspace_id'] !== $workspaceId) {
            throw new PenaException('Proses penyusunan tidak tersedia di ruang ini. Buka kembali Buat surat pada ruang yang benar.');
        }
        return $run;
    }

    public function write(string $id, int $userId, int $workspaceId, int $revision, array $data): int
    {
        $run = $this->read($id,$userId,$workspaceId);
        if ($run['revision'] !== $revision) throw new PenaException('Hasil ini sudah berubah di tab lain. Muat ulang halaman sebelum melanjutkan.');
        $this->runs[$id] = array_replace($run,['revision'=>$revision+1,'data'=>$data,'updated_at'=>$this->time()]);
        return $revision+1;
    }

    public function remove(string $id, int $userId, int $workspaceId): void
    {
        $this->read($id,$userId,$workspaceId);
        unset($this->runs[$id]);
    }

    private function time(): int { return $this->now ?: time(); }
}
