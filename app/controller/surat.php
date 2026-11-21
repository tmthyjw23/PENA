<?php
/**
 * Controller Surat - halaman sekretaris: daftar, buat, ubah, ajukan, revisi, batalkan.
 * Aturan bisnis ada di Pena\Service; controller ini hanya membaca form dan menampilkan hasil.
 * Metode bantu diawali "_" agar router template tidak membukanya sebagai URL.
 */

use Pena\Service\AiClient;
use Pena\Service\AiException;
use Pena\Service\LetterAiService;
use Pena\Service\LetterQuery;
use Pena\Service\LetterRenderer;
use Pena\Service\LetterService;
use Pena\Service\PenaException;
use Pena\Service\WorkspaceService;
use Pena\Support\Db;

class surat extends Controller {

    /** Daftar surat. ?status=semua|tindakan|draft|in_review|approved|cancelled, ?q=perihal atau nomor. */
    public function index() {
        [$org] = $this->_context();
        $query = $this->_query();
        $all = $query->letters((int)$org['id']);
        $needsLink = $query->lettersNeedingLink((int)$org['id']);
        $tabs = ['semua' => ['Semua', static fn($l) => true],
                 'tindakan' => ['Perlu tindakan', static fn($l) => $l['status'] === 'revision_requested' || isset($needsLink[(int)$l['id']])],
                 'draft' => ['Draf', static fn($l) => $l['status'] === 'draft'],
                 'in_review' => ['Sedang direview', static fn($l) => $l['status'] === 'in_review'],
                 'approved' => ['Disetujui', static fn($l) => $l['status'] === 'approved'],
                 'cancelled' => ['Dibatalkan', static fn($l) => $l['status'] === 'cancelled']];
        $tab = isset($tabs[$_GET['status'] ?? '']) ? $_GET['status'] : 'semua';
        $q = trim((string)($_GET['q'] ?? ''));
        $counts = array_map(static fn($t) => count(array_filter($all, $t[1])), $tabs);
        $letters = array_values(array_filter($all, static fn($l) => $tabs[$tab][1]($l)
            && ($q === '' || mb_stripos($l['title'], $q) !== false || mb_stripos((string)$l['letter_number'], $q) !== false)));
        $this->display('surat/index', $this->_page('Surat', [
            'org' => $org,
            'letters' => $letters,
            'total' => count($all),
            'tabs' => array_map(static fn($t) => $t[0], $tabs),
            'counts' => $counts,
            'tab' => $tab,
            'q' => $q,
        ]));
    }

    /** Buat surat: ceritakan kebutuhan, PENA menyusun lewat AI (9router). */
    public function buat() {
        [$org, $userId] = $this->_context();
        if (empty($_GET['proses'])) {
            try { $id = $this->_aiRuns()->create($userId, (int)$org['id']); }
            catch (PenaException $e) { $this->abort(429, $e->getMessage()); }
            $this->_go('/surat/buat/?proses='.$id);
        }
        [$runId, $run] = $this->_aiLoad($org, $userId);
        $state = $run['data'];
        if (!empty($state['clarify'])) {
            $options=$state['clarify']['options']??[];
            if (array_filter($options,'is_array')!==$options) $state['clarify']=null;
        }
        $this->display('surat/generate', $this->_page('Buat Surat', [
            'org' => $org, 'active_menu' => 'buat', 'ai_run' => $runId, 'ai_revision' => $run['revision'],
            'prompt' => (string)($state['prompt'] ?? ''), 'clarify' => ($org['kind']==='personal' && ($state['clarify']['mode']??'')!=='personal')?null:($state['clarify']??null),
            'ai_connection' => $this->_aiSettings()->metadata($userId),
            'ai_error' => $state['error'] ?? null, 'ai_ready' => $this->_aiClient() !== null,
            'has_result' => !empty($state['blocks']), 'unsupported'=>$org['kind']==='personal'?null:($state['unsupported']??null),
            'supported_templates'=>$this->_query()->templates((int)$org['id']),
        ]));
    }

    /** Tanpa JavaScript: tiga langkah AI berurutan dalam satu permintaan, lalu ke halaman hasil. */
    public function susun() {
        [$org, $userId] = $this->_context(true);
        @set_time_limit(0); // three model calls in one request; each is bounded by AI_TIMEOUT
        foreach (['kerangka', 'data', 'paragraf'] as $step) {
            $result = $this->_aiStep($step, $org, $userId, false);
            if ($result['status'] !== 'ok') $this->_go('/surat/buat/?proses='.(string)$_POST['ai_run']);
            $_POST['ai_revision'] = $result['revision'];
        }
        $this->_go('/surat/hasil/?proses='.(string)$_POST['ai_run']);
    }

    /** Langkah AI untuk tampilan "Sedang menyusun" (fetch). Jawaban JSON. */
    public function ai_kerangka() { [$org, $userId] = $this->_context(true, true); $this->json_response($this->_aiStep('kerangka', $org, $userId)); }
    public function ai_data() { [$org, $userId] = $this->_context(true, true); $this->json_response($this->_aiStep('data', $org, $userId)); }
    public function ai_paragraf() { [$org, $userId] = $this->_context(true, true); $this->json_response($this->_aiStep('paragraf', $org, $userId)); }

    /** Periksa hasil susunan AI: semua isian bisa diubah sebelum disimpan sebagai draf. */
    public function hasil() {
        [$org, $userId] = $this->_context();
        [$runId, $run] = $this->_aiLoad($org, $userId);
        $state = $run['data'];
        if (empty($state['blocks'])) $this->_go('/surat/buat/?proses='.$runId);
        $query = $this->_query();
        $template = $this->_aiTemplate($org,$state);
        $signers = $query->signers((int)$org['id']);
        $byId = array_column($signers, null, 'id');
        $values = array_map(static fn($f) => $f['value'], $state['fields']);
        $blocks = [];
        foreach ($template['definition']['blocks'] as $b) {
            $blocks[] = isset($state['blocks'][$b['id']]) ? ['id' => $b['id'], 'text' => $state['blocks'][$b['id']]['text']] : ['id' => $b['id']];
        }
        $letterheads = $query->letterheads((int)$org['id']);
        $previewSigners=array_values(array_filter(array_map(static fn($r)=>$byId[$r['signer_id']]??null,$state['route'])));
        if($org['kind']==='personal')$previewSigners[]=['name'=>(string)($values['nama_pengirim']??$values['nama_mahasiswa']??$_SESSION['user-name']??''),
            'position'=>$template['definition']['presentation']['signature_label']??'Hormat saya'];
        $preview = (new LetterRenderer())->render($template['definition'], array_filter($values, static fn($v) => $v !== null),
            array_values(array_filter($blocks, static fn($b) => !array_key_exists('text', $b) || $b['text'] !== '')),
            isset($letterheads[0]) ? $this->_kop()->decorate($this->_kop()->snapshot((int)$letterheads[0]['id']) ?? []) : [], null, $state['title'],
            $previewSigners, null, $org['kind']!=='personal');
        $this->display('surat/generate_hasil', $this->_page('Periksa Hasil', [
            'org' => $org,
            'active_menu' => 'buat',
            'state' => $state, 'ai_run' => $runId, 'ai_revision' => $run['revision'],
            'template' => $template,
            'signers' => $signers,
            'letterheads' => $letterheads,
            'preview' => $preview,
            'errors' => $state['errors'] ?? [],
            'error_message' => $state['message'] ?? null,
            'model' => $state['ai_model']??null,
        ]));
    }

    /** Simpan hasil AI (setelah diperiksa) sebagai draf biasa; permintaan dan log AI ikut ditautkan. */
    public function simpan_ai() {
        [$org, $userId] = $this->_context(true);
        [$runId, $run] = $this->_aiLoad($org, $userId);
        $state = $run['data'];
        if (empty($state['blocks'])) $this->_go('/surat/buat/?proses='.$runId);
        $template = $this->_aiTemplate($org,$state);
        [$fields, $blocks] = $this->_readContent($template['definition']);
        $title = (string)($_POST['title'] ?? '');
        $stages = [];
        try {
            $id = $this->_db()->transaction(function () use ($org,$userId,$template,$title,$fields,$blocks,&$stages,$state) {
                $templateId = !empty($state['dynamic_template'])
                    ? \Pena\Service\PersonalTemplate::store($this->_db(),$userId,(int)$org['id'],$template)
                    : (int)$template['id'];
                $id = $this->_createDraft($org,$userId,$templateId,$title,$fields,$blocks,$stages,$this->_aiPrompt($state));
                $versionId = (int)$this->_db()->value('SELECT current_version_id FROM letters WHERE id=?',[$id]);
                LetterAiService::attachToLetter($this->_db(),$state['generation_ids']??[],(int)$org['id'],$id,$versionId,$userId,(string)($state['ai_model']??'AI'));
                return $id;
            });
            $this->_aiRuns()->remove($runId,$userId,(int)$org['id']);
            $this->_flash('success', 'Draf surat tersimpan. Periksa pratinjau, lalu ajukan bila sudah benar.');
            $this->_go("/surat/detail/$id");
        } catch (PenaException $e) {
            // Keep what the secretary typed and show the problems next to it.
            $state['title'] = $title;
            $state['fields'] = $this->_aiEditedFields($state['fields'],$fields);
            foreach ($blocks as $b) if (isset($b['text'], $state['blocks'][$b['id']])) $state['blocks'][$b['id']]['text'] = $b['text'];
            $state['route'] = array_map(static fn($s) => ['stage' => $s['name'], 'signer_id' => (int)($s['signer_ids'][0] ?? 0), 'quote' => null,
                                                          'new_name' => $s['new_name'] ?? '', 'new_position' => $s['new_position'] ?? ''], $stages);
            $state['kop'] = $_POST['kop'] ?? null;
            $state['errors'] = $e->errors; $state['message'] = $e->getMessage();
            $this->_aiRuns()->write($runId,$userId,(int)$org['id'],$run['revision'],$state);
            $this->_go('/surat/hasil/?proses='.$runId);
        }
    }

    /** Tulis ulang satu paragraf hasil AI (fetch). Jawaban JSON. */
    public function tulis_ulang() {
        [$org, $userId] = $this->_context(true, true);
        [$runId,$run] = $this->_aiLoad($org,$userId,true);
        $state = $run['data'];
        if (($state['connection_revision']??'default')!==$this->_aiRevision()) $this->json_response(['status'=>'error','message'=>'Koneksi AI berubah. Simpan hasil yang ada, atau susun ulang sebelum meminta tulis ulang.']);
        $ai = $this->_aiClient();
        if (!$ai || (empty($state['template_id']) && empty($state['dynamic_template']))) $this->json_response(['status' => 'error', 'message' => 'Layanan AI atau hasil susunan tidak tersedia.']);
        @set_time_limit($ai->timeout() + 15);
        try {
            $template = $this->_aiTemplate($org,$state);
            [$values] = $this->_readContent($template['definition']);
            $problems = (new \Pena\Service\TemplateValidator())->validate($template['definition'],$values,[],false);
            if ($problems !== []) throw new PenaException('Periksa format isian sebelum meminta tulis ulang.');
            $r = (new LetterAiService($this->_db(), $ai))->rewrite((int)$org['id'], $userId, $template, $values,
                (string)($_POST['block'] ?? ''), (string)($_POST['current'] ?? ''), (string)($_POST['instruction'] ?? ''));
            $state['generation_ids'][] = $r['generation_id'];
            $blockId = (string)($_POST['block']??'');
            $state['blocks'][$blockId] = ['text'=>$r['text'],'warnings'=>$r['warnings']];
            $state['fields'] = $this->_aiEditedFields($state['fields'],$values);
            $revision = $this->_aiRuns()->write($runId,$userId,(int)$org['id'],$run['revision'],$state);
            $this->json_response(['status' => 'ok', 'text' => $r['text'], 'warnings' => $r['warnings'], 'revision'=>$revision]);
        } catch (AiException | PenaException $e) {
            $this->json_response(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Tulis ulang satu paragraf pada draf yang sudah tersimpan (fetch, JSON). Hanya mengusulkan teks:
     * tidak ada yang disimpan sampai pengguna menekan Simpan perubahan. Versi yang sudah diajukan tetap terkunci.
     */
    public function tulis_ulang_draf($id = 0) {
        [$org, $userId] = $this->_context(true, true);
        $orgId = (int)$org['id'];
        $letterId = (int)$id;
        $detail = $this->_query()->detail($letterId, $orgId);
        if (!$detail) $this->json_response(['status' => 'error', 'message' => 'Surat tidak ditemukan di ruang ini.'], 404);
        try {
            $this->_assertRewriteDraft($detail);
            if ((string)($_POST['ai_connection_revision'] ?? '') !== $this->_aiRevision()) {
                throw new PenaException('Koneksi AI berubah. Muat ulang halaman sebelum meminta tulis ulang.');
            }
            $ai = $this->_aiClient();
            if (!$ai) throw new AiException('Koneksi AI belum tersedia. Periksa Pengaturan AI, atau tulis paragraf sendiri.');
            // Not _template(): it redirects to an HTML page, and this endpoint must answer JSON.
            $template = $this->_query()->template($orgId, (int)$detail['letter']['template_id']);
            if (!$template) throw new PenaException('Kerangka surat tidak tersedia di ruang ini.');
            foreach (['block', 'current', 'instruction'] as $key) {
                if (!is_string($_POST[$key] ?? null)) throw new PenaException('Permintaan tulis ulang tidak valid. Muat ulang halaman.');
            }
            [$values] = $this->_readContent($template['definition']);
            if ((new \Pena\Service\TemplateValidator())->validate($template['definition'], $values, [], false) !== []) {
                throw new PenaException('Periksa format isian surat sebelum meminta tulis ulang.');
            }
            @set_time_limit($ai->timeout() + 15);
            $r = (new LetterAiService($this->_db(), $ai))->rewrite($orgId, $userId, $template, $values,
                $_POST['block'], $_POST['current'], $_POST['instruction']);
            // The model call can take a while: recheck that this draft is still the open working version.
            $this->_db()->transaction(function () use ($letterId, $orgId, $userId, $r, $ai): void {
                $this->_db()->one('SELECT id FROM letters WHERE id = ? AND organization_id = ? FOR UPDATE', [$letterId, $orgId]);
                $latest = $this->_query()->detail($letterId, $orgId);
                if (!$latest) throw new PenaException('Surat tidak tersedia lagi. Muat ulang halaman.');
                $this->_assertRewriteDraft($latest);
                LetterAiService::attachToLetter($this->_db(), [$r['generation_id']], $orgId, $letterId, (int)$latest['version']['id'], $userId, $ai->model());
            });
            $this->json_response(['status' => 'ok', 'text' => $r['text'], 'warnings' => $r['warnings']]);
        } catch (AiException | PenaException $e) {
            $this->json_response(['status' => 'error', 'message' => $e->getMessage()], $e instanceof PenaException ? 409 : 422);
        }
    }

    /** Identifies the saved working version the editor page was opened on. */
    private function _rewriteDraftContext(array $detail): array {
        return ['version_id' => (int)$detail['version']['id'],
                'fingerprint' => hash('sha256', LetterService::json([$detail['letter']['title'], $detail['version']['fields'], $detail['version']['blocks']]))];
    }

    private function _assertRewriteDraft(array $detail): void {
        if ($detail['version']['submitted_at'] !== null || $detail['letter']['status'] !== 'draft') {
            throw new PenaException('Versi ini sudah diajukan dan terkunci. Mulai revisi, lalu buka draf versi barunya.');
        }
        $context = $this->_rewriteDraftContext($detail);
        if ((int)($_POST['rewrite_version_id'] ?? 0) !== $context['version_id']
            || !hash_equals($context['fingerprint'], (string)($_POST['rewrite_fingerprint'] ?? ''))) {
            throw new PenaException('Draf ini berubah sejak halaman dibuka. Muat ulang halaman lalu coba lagi.');
        }
    }

    /**
     * One AI step on the session state. kerangka resets the state from the posted request;
     * data and paragraf continue it. Never throws: returns status ok | clarify | error.
     */
    private function _aiStep(string $step, array $org, int $userId, bool $json = true): array {
        [$runId,$run] = $this->_aiLoad($org,$userId,$json);
        $revision = $run['revision'];
        $connectionRevision=$this->_aiRevision();
        if($step!=='kerangka'&&($run['data']['connection_revision']??'default')!==$connectionRevision)return ['status'=>'error','message'=>'Koneksi AI berubah. Susun ulang agar data dikirim ke provider yang Anda pilih.'];
        $ai = $this->_aiClient();
        if (!$ai) return ['status' => 'error', 'message' => 'Bantuan AI sedang tidak tersedia. Permintaan Anda tetap ada; Anda dapat melanjutkan dengan isian manual.'];
        // PHP's own limit (30 s on this server) must outlast the AI timeout, or the request dies unlogged.
        // The paragraph step may call the model twice (one retry).
        @set_time_limit($ai->timeout() * ($step === 'paragraf' ? 2 : 1) + 15);
        $service = new LetterAiService($this->_db(), $ai);
        $query = $this->_query();
        $orgId = (int)$org['id'];
        $state = $run['data'];
        try {
            if ($step === 'kerangka') {
                $prompt = trim(mb_substr((string)($_POST['prompt'] ?? ''), 0, 1000));
                if ($org['kind']==='personal') {
                    $samePrompt=($state['prompt']??null)===$prompt;
                    $clarifications=$samePrompt?($state['clarifications']??[]):[];
                    $answer=trim(mb_substr((string)($_POST['clarification_answer']??''),0,300));
                    if ($answer!=='' && $samePrompt && ($state['clarify']['mode']??null)==='personal') {
                        if(count($clarifications)>=3)throw new AiException('Tuliskan tujuan surat lebih lengkap pada kotak permintaan, lalu susun kembali.');
                        $clarifications[]=['question'=>$state['clarify']['question'],'answer'=>$answer];
                    }
                    $state=['prompt'=>$prompt,'clarifications'=>$clarifications,'generation_ids'=>$samePrompt?($state['generation_ids']??[]):[],'connection_revision'=>$connectionRevision,'ai_model'=>$ai->model()];
                    $r=$service->designPersonalTemplate($orgId,$userId,$this->_aiPrompt($state));
                    $state['generation_ids']=array_merge($state['generation_ids'],$r['generation_ids']);
                    if($r['status']==='clarify'){
                        $state['clarify']=['mode'=>'personal','question'=>$r['question'],'options'=>[]];
                        $revision=$this->_aiRuns()->write($runId,$userId,$orgId,$revision,$state);
                        return ['status'=>'clarify','revision'=>$revision];
                    }
                    $state+=['dynamic_template'=>$r['template'],'code'=>'AI','title'=>$r['template']['name'],'reason'=>'Susunan dan gaya dirancang AI dari tujuan surat Anda.'];
                    $revision=$this->_aiRuns()->write($runId,$userId,$orgId,$revision,$state);
                    return ['status'=>'ok','revision'=>$revision,'label'=>$r['template']['name']];
                }
                $choice = (string)($_POST['template_choice']??'');
                if ($choice!=='' && ($state['prompt']??null)===$prompt && !empty($state['clarify'])) {
                    $offered=array_filter($state['clarify']['options']??[],static fn($option)=>is_array($option)&&($option['code']??null)===$choice);
                    if ($offered===[]) throw new AiException('Pilihan ini tidak tersedia. Muat ulang halaman dan pilih kembali.');
                    $template=$this->_template($orgId,(int)($this->_templateIdByCode($orgId,$choice)));
                    $state=['prompt'=>$prompt,'generation_ids'=>$state['generation_ids'],
                        'connection_revision'=>$connectionRevision,'ai_model'=>$ai->model(),
                        'template_id'=>(int)$template['id'],'code'=>$template['code'],'title'=>$template['name'],
                        'reason'=>'Dipilih oleh Anda setelah klarifikasi.'];
                    $revision=$this->_aiRuns()->write($runId,$userId,$orgId,$revision,$state);
                    return ['status'=>'ok','revision'=>$revision,'label'=>$template['name'].' ('.$template['code'].')'];
                }
                $state = ['prompt' => $prompt, 'generation_ids' => [],'connection_revision'=>$connectionRevision,'ai_model'=>$ai->model()];
                $revision = $this->_aiRuns()->write($runId,$userId,$orgId,$revision,$state);
                $r = $service->recommendTemplate($orgId, $userId, $prompt, $query->templates($orgId));
                $state['generation_ids'][] = $r['generation_id'];
                if ($r['status']==='unsupported') {
                    $state['unsupported']=$r['reason'];
                    $revision=$this->_aiRuns()->write($runId,$userId,$orgId,$revision,$state);
                    return ['status'=>'unsupported','revision'=>$revision];
                }
                if ($r['status'] === 'clarify') {
                    $state['clarify'] = ['question' => $r['question'], 'options' => $r['options']];
                    $revision = $this->_aiRuns()->write($runId,$userId,$orgId,$revision,$state);
                    return ['status' => 'clarify', 'question' => $r['question'], 'options' => $r['options'], 'revision'=>$revision];
                }
                $state += ['template_id' => $r['template_id'], 'code' => $r['code'], 'reason' => $r['reason'], 'title' => $r['title']];
                $revision = $this->_aiRuns()->write($runId,$userId,$orgId,$revision,$state);
                $t = $this->_template($orgId, $r['template_id']);
                return ['status' => 'ok', 'revision'=>$revision, 'label' => $t['name'] . ' (' . $t['code'] . ')'];
            }
            if (empty($state['template_id']) && empty($state['dynamic_template'])) return ['status' => 'error', 'message' => 'Mulai lagi dari kotak permintaan.'];
            $template = $this->_aiTemplate($org,$state);
            if ($step === 'data') {
                $r = $service->extract($orgId, $userId, $this->_aiPrompt($state), $template, $query->signers($orgId));
                $state['generation_ids'][] = $r['generation_id'];
                // Identity the student did not repeat comes from the personal profile, marked as such.
                $values = WorkspaceService::prefill($org, $template['definition'], array_map(static fn($f) => $f['value'], $r['fields']));
                foreach ($r['fields'] as $key => $f) {
                    if ($f['value'] === null && ($values[$key] ?? null) !== null) {
                        $r['fields'][$key] = ['value' => $values[$key], 'quote' => null, 'flag' => 'ok', 'note' => 'Dari profil Anda'];
                    }
                }
                $state['fields'] = $r['fields']; $state['route'] = $r['route']; $state['missing'] = $r['missing'];
                $revision = $this->_aiRuns()->write($runId,$userId,$orgId,$revision,$state);
                $filled = count(array_filter($r['fields'], static fn($f) => $f['value'] !== null));
                return ['status' => 'ok', 'revision'=>$revision, 'label' => $filled . ' dari ' . count($r['fields']) . ' isian'];
            }
            if (!array_key_exists('fields',$state)) return ['status' => 'error', 'message' => 'Data surat belum terisi. Susun ulang.'];
            $values = array_map(static fn($f) => $f['value'], $state['fields']);
            $r = $service->writeParagraphs($orgId, $userId, $this->_aiPrompt($state), $template, $values);
            $state['generation_ids'] = array_merge($state['generation_ids'], $r['generation_ids']);
            $state['blocks'] = $r['blocks'];
            $revision = $this->_aiRuns()->write($runId,$userId,$orgId,$revision,$state);
            return ['status' => 'ok', 'label' => count($r['blocks']) . ' paragraf', 'revision'=>$revision, 'next' => APP_PATH . '/surat/hasil/?proses='.$runId];
        } catch (AiException $e) {
            $state['error'] = $e->getMessage();
            $revision = $this->_aiRuns()->write($runId,$userId,$orgId,$revision,$state);
            return ['status' => 'error', 'message' => $e->getMessage(), 'revision'=>$revision];
        }
    }

    private function _aiPrompt(array $state): string {
        $prompt=(string)($state['prompt']??'');
        foreach($state['clarifications']??[] as $item)$prompt.="\n\nKeterangan tambahan pengguna untuk pertanyaan ".$item['question'].": ".$item['answer'];
        return $prompt;
    }

    private function _aiTemplate(array $org,array $state): array {
        if(!empty($state['dynamic_template'])){
            if($org['kind']!=='personal')$this->abort(403,'Kerangka pribadi tidak tersedia di ruang organisasi.');
            return $state['dynamic_template'];
        }
        return $this->_template((int)$org['id'],(int)$state['template_id']);
    }

    private function _templateIdByCode(int $orgId, string $code): int {
        foreach ($this->_query()->templates($orgId) as $template) if ($template['code']===$code) return (int)$template['id'];
        throw new AiException('Kerangka pilihan tidak lagi tersedia di ruang ini.');
    }

    private function _aiEditedFields(array $fields, array $values): array {
        foreach ($fields as $key => &$field) {
            $value = $values[$key] ?? null;
            if ($field['value'] !== $value) {
                $field = ['value'=>$value,'quote'=>null,'flag'=>$value===null?'kosong':'ok',
                    'note'=>$value===null?'Dikosongkan oleh Anda':'Dikoreksi oleh Anda'];
            }
        }
        unset($field);
        return $fields;
    }

    private function _aiRuns(): \Pena\Service\AiDraftStore { return new \Pena\Service\AiDraftStore($_SESSION); }

    private function _aiLoad(array $org, int $userId, bool $json = false): array {
        $id = (string)($_POST['ai_run'] ?? $_GET['proses'] ?? '');
        try {
            $run = $this->_aiRuns()->read($id,$userId,(int)$org['id']);
            if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
                ((int)($_POST['workspace_id']??0)!==(int)$org['id'] ||
                 filter_var($_POST['ai_revision']??null,FILTER_VALIDATE_INT)!==$run['revision'])) {
                throw new PenaException('Ruang atau hasil sudah berubah di tab lain. Muat ulang halaman pada ruang yang benar.');
            }
            return [$id,$run];
        } catch (PenaException $e) {
            if ($json) $this->json_response(['status'=>'error','message'=>$e->getMessage()],409);
            $this->abort(409,$e->getMessage());
        }
    }

    /** Isi manual tanpa AI: pilih kerangka, isi data, tulis paragraf, tentukan alur. */
    public function manual() {
        [$org] = $this->_context();
        $query = $this->_query();
        $templates = $query->templates((int)$org['id']);
        $templateId = (int)($_GET['kerangka'] ?? ($_SESSION['pena_old']['template_id'] ?? ($templates[0]['id'] ?? 0)));
        $template = null;
        foreach ($templates as $t) if ((int)$t['id'] === $templateId) $template = $t;
        $old = $this->_takeOld();
        if ($template && !isset($old['raw_fields'])) {
            $old['fields'] = WorkspaceService::prefill($org, $template['definition'], $old['fields'] ?? []);
        }
        $this->display('surat/form', $this->_page('Buat Surat', [
            'active_menu' => 'buat',
            'mode' => 'create',
            'org' => $org,
            'templates' => $templates,
            'template' => $template,
            'signers' => $query->signers((int)$org['id']),
            'letterheads' => $query->letterheads((int)$org['id']),
            'old' => $old,
        ]));
    }

    public function simpan() {
        [$org, $userId] = $this->_context(true);
        $templateId = (int)($_POST['template_id'] ?? 0);
        $template = $this->_template((int)$org['id'], $templateId);
        [$fields, $blocks] = $this->_readContent($template['definition']);
        $stages = [];
        try {
            $id = $this->_createDraft($org, $userId, $templateId, (string)($_POST['title'] ?? ''), $fields, $blocks, $stages, null);
            $this->_flash('success', 'Draf surat tersimpan.');
            $this->_go("/surat/detail/$id");
        } catch (PenaException $e) {
            $this->_keepOld($e);
            $this->_go('/surat/manual/?kerangka=' . $templateId);
        }
    }

    public function detail($id = 0) {
        header('Cache-Control: private, no-store');
        [$org] = $this->_context();
        $detail = $this->_query()->detail((int)$id, (int)$org['id']);
        if (!$detail) $this->abort(404, 'Surat tidak ditemukan.');
        $links = $_SESSION['pena_links'][(int)$id] ?? [];
        unset($_SESSION['pena_links'][(int)$id]);
        $this->display('surat/detail', $this->_page($detail['letter']['title'], $detail + [
            'org' => $org,
            'links' => $links,
            'signers' => $this->_query()->signers((int)$org['id']),
            'errors' => $this->_takeOld()['errors'] ?? [],
        ]));
    }

    public function unduh($id = 0) {
        [$org, $userId] = $this->_context(true);
        try {
            $file = (new \Pena\Service\ExportService($this->_db(), BASEPATH . '/storage/exports'))->create(
                (int)$id, (int)$org['id'], $userId, (string)($_POST['format'] ?? ''));
        } catch (PenaException $error) {
            $this->_flash('danger', $error->getMessage());
            $this->_go('/surat/detail/' . (int)$id);
        }
        header('Content-Type: ' . (str_ends_with($file['name'], '.pdf') ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'));
        header('Content-Disposition: attachment; filename="' . $file['name'] . '"');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . filesize($file['path']));
        readfile($file['path']);
        exit();
    }

    public function edit($id = 0) {
        [$org] = $this->_context();
        $detail = $this->_query()->detail((int)$id, (int)$org['id']);
        if (!$detail) $this->abort(404, 'Surat tidak ditemukan.');
        if ($detail['version']['submitted_at'] !== null) {
            $this->_flash('warning', 'Versi ini sudah diajukan. Mulai revisi untuk mengubah isi.');
            $this->_go("/surat/detail/$id");
        }
        $template = $this->_template((int)$org['id'], (int)$detail['letter']['template_id']);
        $old = $this->_takeOld();
        $this->display('surat/form', $this->_page('Ubah Draf', [
            'mode' => 'edit',
            'rewrite_context' => $this->_rewriteDraftContext($detail),
            'ai_connection_revision' => $this->_aiRevision(),
            'org' => $org,
            'letter' => $detail['letter'],
            'template' => $template,
            'old' => $old ?: ['fields' => $detail['version']['fields'], 'blocks' => $detail['version']['blocks'],
                              'title' => $detail['letter']['title']],
        ]));
    }

    public function perbarui($id = 0) {
        [$org, $userId] = $this->_context(true);
        $detail = $this->_query()->detail((int)$id, (int)$org['id']);
        if (!$detail) $this->abort(404, 'Surat tidak ditemukan.');
        $template = $this->_template((int)$org['id'], (int)$detail['letter']['template_id']);
        [$fields, $blocks] = $this->_readContent($template['definition']);
        try {
            $this->_letters()->updateDraft((int)$id, $userId, $fields, $blocks, null, (string)($_POST['title']??$detail['letter']['title']));
            $this->_flash('success', 'Draf diperbarui.');
            $this->_go("/surat/detail/$id");
        } catch (PenaException $e) {
            $this->_keepOld($e);
            $this->_go("/surat/edit/$id");
        }
    }

    public function ajukan($id = 0) {
        [$org, $userId] = $this->_context(true);
        $this->_owned((int)$id, (int)$org['id']);
        try {
            $result = $this->_letters()->submit((int)$id, $userId, \Pena\Service\SignatureService::fromRequest($_POST,$_FILES));
            $this->_rememberLinks((int)$id, $result['links']);
            $this->_flash('success', match (true) {
                $result['approved'] => 'Surat selesai dan siap dikirim. Isinya terkunci; perubahan berikutnya dibuat sebagai surat baru.',
                $result['letter_number'] === null => 'Surat diajukan. Kirim tautan di bawah kepada pemeriksa.',
                default => 'Surat diajukan dengan nomor ' . $result['letter_number'] . '. Kirim tautan di bawah kepada pemeriksa.',
            });
        } catch (PenaException $e) {
            $this->_keepOld($e);
            $this->_flash('danger', $e->getMessage());
        }
        $this->_go("/surat/detail/$id");
    }

    public function revisi($id = 0) {
        [$org, $userId] = $this->_context(true);
        $this->_owned((int)$id, (int)$org['id']);
        try {
            $this->_letters()->startRevision((int)$id, $userId, (string)($_POST['summary'] ?? ''));
            $this->_flash('success', 'Versi baru dibuat. Ubah isinya, lalu ajukan kembali.');
            $this->_go("/surat/edit/$id");
        } catch (PenaException $e) {
            $this->_flash('danger', $e->getMessage());
            $this->_go("/surat/detail/$id");
        }
    }

    public function batal($id = 0) {
        [$org, $userId] = $this->_context(true);
        $this->_owned((int)$id, (int)$org['id']);
        try {
            $this->_letters()->cancel((int)$id, $userId, (string)($_POST['reason'] ?? ''));
            $number = $this->_query()->detail((int)$id, (int)$org['id'])['letter']['letter_number'] ?? null;
            $this->_flash('success', $number ? "Surat dibatalkan. Nomor $number tetap tercatat terpakai." : 'Surat dibatalkan.');
        } catch (PenaException $e) {
            $this->_flash('danger', $e->getMessage());
        }
        $this->_go("/surat/detail/$id");
    }

    public function ganti($id = 0) {
        [$org, $userId] = $this->_context(true);
        $this->_owned((int)$id, (int)$org['id']);
        try {
            $this->_letters()->replaceSigner((int)$id, $userId, (int)($_POST['member_id'] ?? 0), (int)($_POST['signer_id'] ?? 0));
            $this->_flash('success', 'Pemeriksa diganti. Bila surat sedang direview, mulai revisi lalu ajukan ulang.');
        } catch (PenaException $e) {
            $this->_flash('danger', $e->getMessage());
        }
        $this->_go("/surat/detail/$id");
    }

    public function tautan($id = 0) {
        [$org, $userId] = $this->_context(true);
        $this->_owned((int)$id, (int)$org['id']);
        try {
            $requestId = (int)($_POST['request_id'] ?? 0);
            $token = $this->_letters()->reissueLink((int)$id, $userId, $requestId);
            $this->_rememberLinks((int)$id, [$requestId => $token]);
            $this->_flash('success', 'Tautan dibuat. Kirim ke pemeriksa; tautan sebelumnya (jika ada) tidak berlaku lagi.');
        } catch (PenaException $e) {
            $this->_flash('danger', $e->getMessage());
        }
        $this->_go("/surat/detail/$id");
    }

    // ------------------------------------------------------------ helpers

    /** @return array{0: array, 1: int} organization row and user id; $post also checks method + CSRF */
    private function _context(bool $post = false, bool $json = false): array {
        $this->start_default_session_app();
        if (empty($_SESSION['user-id'])) {
            if ($json) $this->json_response(['status' => 'error', 'message' => 'Sesi berakhir. Masuk lagi, lalu ulangi.'], 401);
            $this->_go('/login/');
        }
        if ($post) {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->abort(405);
            if (!$this->verify_csrf_token()) {
                if ($json) $this->json_response(['status' => 'error', 'message' => 'Sesi formulir kedaluwarsa. Muat ulang halaman, lalu ulangi.'], 419);
                $this->_flash('danger', 'Sesi formulir kedaluwarsa. Silakan ulangi.');
                $this->_go('/surat/');
            }
        }
        $org = $this->_workspaces()->current((int)$_SESSION['user-id'], isset($_SESSION['workspace-id']) ? (int)$_SESSION['workspace-id'] : null);
        if (!$org) $this->abort(403, 'Akun ini belum punya ruang kerja.');
        $_SESSION['workspace-id'] = $org['id'];
        return [$org, (int)$_SESSION['user-id']];
    }

    /**
     * Saves a new draft together with what the secretary set up along the way, in one transaction:
     * reviewers typed as "Pemeriksa baru", the organization's letterhead on its first letter,
     * and (personal workspace) the identity fields remembered for the next letter.
     * $stages is filled with what was read, so the form can be restored after an error.
     */
    private function _createDraft(array $org, int $userId, int $templateId, string $title, array $fields, array $blocks,
                                  array &$stages, ?string $prompt): int {
        $workspaces = $this->_workspaces();
        $stages = [];
        foreach ((array)($_POST['stages'] ?? []) as $stage) {
            $signerIds = array_values(array_filter(array_map('intval', (array)($stage['signer_ids'] ?? []))));
            $newName = trim((string)($stage['new_name'] ?? ''));
            $newPosition = trim((string)($stage['new_position'] ?? ''));
            if (trim((string)($stage['name'] ?? '')) === '' && $signerIds === [] && $newName === '' && $newPosition === '') continue;
            $stages[] = ['name' => trim((string)($stage['name'] ?? '')) ?: $newPosition, 'signer_ids' => $signerIds,
                         'new_name' => $newName, 'new_position' => $newPosition];
        }
        return $this->_db()->transaction(function () use ($org, $userId, $templateId, $title, $fields, $blocks, $stages, $prompt, $workspaces) {
            $orgId = (int)$org['id'];
            $ready = [];
            foreach ($stages as $stage) {
                if ($stage['new_name'] !== '' || $stage['new_position'] !== '') {
                    $stage['signer_ids'][] = $workspaces->addSigner($orgId, $userId, $stage['new_name'], $stage['new_position']);
                }
                $ready[] = ['name' => $stage['name'], 'signer_ids' => array_values(array_unique($stage['signer_ids']))];
            }
            $letterheadId = ((int)($_POST['letterhead_id'] ?? 0)) ?: null;
            if ($org['kind'] === 'organization' && $letterheadId === null && is_array($_POST['kop'] ?? null)) {
                $kop = $_POST['kop'];
                $letterheadId = $workspaces->addLetterhead($orgId, $userId, (string)($kop['name'] ?? ''), (string)($kop['address'] ?? ''), (string)($kop['contact'] ?? ''));
            }
            $id = $this->_letters()->createDraft($userId, $orgId, $templateId, $title, $fields, $blocks, $ready, $letterheadId, $prompt);
            $workspaces->rememberProfile($orgId, $userId, $fields);
            return $id;
        });
    }

    private function _owned(int $letterId, int $orgId): void {
        if (!$this->_query()->detail($letterId, $orgId)) $this->abort(404, 'Surat tidak ditemukan.');
    }

    private function _template(int $orgId,int $templateId): array {
        $template=$this->_query()->template($orgId,$templateId);
        if($template)return $template;
        $this->_flash('danger','Kerangka surat tidak tersedia di ruang ini.');
        $this->_go('/surat/manual/');
    }

    /**
     * Reads fields and blocks from the form in template order.
     * Table fields come as one row per line, columns separated by "|".
     */
    private function _readContent(array $definition): array {
        $postedFields = (array)($_POST['fields'] ?? []);
        $fields = [];
        foreach ($definition['fields'] as $spec) {
            $raw = $postedFields[$spec['key']] ?? '';
            if ($spec['type'] === 'table') {
                $columns = array_column($spec['columns'] ?? [], 'key');
                $rows = [];
                foreach (preg_split('/\R/', (string)$raw) as $line) {
                    if (trim($line) === '') continue;
                    $cells = array_map('trim', explode('|', $line));
                    $row = [];
                    foreach ($columns as $i => $column) $row[$column] = $cells[$i] ?? '';
                    $rows[] = $row;
                }
                if ($rows !== []) $fields[$spec['key']] = $rows;
                continue;
            }
            $value = trim((string)$raw);
            if ($spec['type'] === 'money') $value = str_replace(['.', ' ', 'Rp', 'rp'], '', $value);
            if ($value !== '') $fields[$spec['key']] = $value;
        }
        $postedBlocks = (array)($_POST['blocks'] ?? []);
        $blocks = [];
        foreach ($definition['blocks'] as $spec) {
            if ($spec['type'] === 'paragraph' && $spec['source'] !== 'fixed') {
                $text = trim((string)($postedBlocks[$spec['id']] ?? ''));
                if ($text === '' && empty($spec['required'])) continue;
                $blocks[] = ['id' => $spec['id'], 'text' => $text];
            } elseif ($spec['type'] === 'table' && !isset($fields[$spec['field']]) && empty($spec['required'])) {
                continue;
            } else {
                $blocks[] = ['id' => $spec['id']];
            }
        }
        return [$fields, $blocks];
    }

    private function _rememberLinks(int $letterId, array $links): void {
        $names = [];
        foreach ($links as $requestId => $token) {
            $row = $this->_db()->one('SELECT signer_name, signer_position, kind FROM approval_requests WHERE id = ?', [$requestId]);
            $names[] = $row + ['url' => APP_PATH . '/review/lihat/' . $token];
        }
        $_SESSION['pena_links'][$letterId] = $names;
    }

    private function _keepOld(PenaException $e): void {
        $_SESSION['pena_old'] = [
            'title' => $_POST['title'] ?? '', 'template_id' => $_POST['template_id'] ?? null,
            'letterhead_id' => $_POST['letterhead_id'] ?? null, 'kop' => $_POST['kop'] ?? null, 'raw_fields' => $_POST['fields'] ?? [],
            'raw_blocks' => $_POST['blocks'] ?? [], 'stages' => $_POST['stages'] ?? [],
            'message' => $e->getMessage(), 'errors' => $e->errors,
        ];
    }

    private function _takeOld(): array {
        $old = $_SESSION['pena_old'] ?? [];
        unset($_SESSION['pena_old']);
        return $old;
    }

    private function _page(string $title, array $data): array {
        $flash = $_SESSION['flash_message'] ?? null;
        unset($_SESSION['flash_message']);
        return $data + ['title' => $title . ' - PENA', 'active_menu' => 'surat', 'flash' => $flash,
                        'workspaces' => $this->_workspaces()->forUser((int)$_SESSION['user-id']),
                        'csrf_token' => $this->get_csrf_token()];
    }

    private function _flash(string $type, string $text): void {
        $_SESSION['flash_message'] = ['type' => $type, 'text' => $text];
    }

    protected function _go(string $path): never {
        header('Location: ' . APP_PATH . $path);
        exit();
    }

    protected function _aiClient(): ?AiClient {
        try { return $this->_aiSettings()->client((int)$_SESSION['user-id']); }
        catch (PenaException) { return null; } // Never fall back to a different provider after credential failure.
    }
    private function _aiSettings(): \Pena\Service\AiSettings { return new \Pena\Service\AiSettings($this->_db()); }
    private function _aiRevision(): string { return $this->_aiSettings()->metadata((int)$_SESSION['user-id'])['revision']; }

    private ?Db $db = null;
    private function _db(): Db { return $this->db ??= Db::fromTemplate(new Database()); }
    private function _query(): LetterQuery { return new LetterQuery($this->_db()); }
    private function _letters(): LetterService { return new LetterService($this->_db()); }
    private function _workspaces(): WorkspaceService { return new WorkspaceService($this->_db()); }
    private function _kop(): \Pena\Service\LetterheadService { return new \Pena\Service\LetterheadService($this->_db()); }
}
