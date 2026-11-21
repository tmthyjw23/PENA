<?php
/**
 * Base controller of the MVC framework: request hardening, views, models, session and CSRF.
 * Every controller in app/controller extends this class; the router (App.php) calls its public methods.
 */
class Controller {

	/** Idle minutes before a signed-in session ends (SESSION_IDLE_MINUTES in .env). */
	private const DEFAULT_IDLE_MINUTES = 60;
	private bool $accountSessionChecked = false;

	public function __construct(){
		$this->send_security_headers();
		$this->sanitize_global_inputs();
		$this->check_security_payloads();
	}

	private static ?string $cspNonce = null;

	/** One nonce per request; every inline <script> in a view carries it (see pena_nonce()). */
	public static function csp_nonce(): string {
		return self::$cspNonce ??= base64_encode(random_bytes(16));
	}

	public function send_security_headers(){
		if (!headers_sent()) {
			@header_remove('X-Powered-By');
			// Scripts: own files plus inline blocks with this request's nonce. No inline handlers or styles.
			// img data:/blob: = signature images and the PNG preview before upload.
			header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-" . self::csp_nonce() . "'; "
				. "style-src 'self'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; "
				. "form-action 'self'; frame-ancestors 'self'; base-uri 'self'; object-src 'none'");
			header('X-Frame-Options: SAMEORIGIN');
			header('X-Content-Type-Options: nosniff');
			header('Referrer-Policy: strict-origin-when-cross-origin');
			header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
			header('Cross-Origin-Opener-Policy: same-origin');
			if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443) {
				header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
			}
		}
	}

	/** Rejects requests whose parameters look like SQL injection, code execution, path traversal or script injection. */
	public function check_security_payloads(){
		$params = array_merge($_GET, $_POST, $_COOKIE);
		if (!empty($_SERVER['QUERY_STRING'])) $params['__raw_query__'] = $_SERVER['QUERY_STRING'];
		$checks = [
			'detect_sqli' => 'Permintaan diblokir karena terdeteksi pola query SQL.',
			'detect_rce_payload' => 'Permintaan diblokir karena terdeteksi pola eksekusi kode.',
			'detect_path_traversal' => 'Permintaan diblokir karena terdeteksi pola traversal direktori.',
			'detect_xss' => 'Permintaan diblokir karena terdeteksi pola script injection.',
		];
		foreach ($checks as $method => $message) {
			if (Security::$method($params)) {
				Security::log_incident($method, json_encode($params), 'Blocked');
				$this->abort(400, $message);
			}
		}
	}

	/** Ends the request with an error page (app/view/errors/index.php). */
	public function abort($code = 404, $message = ''){
		$code = (int)$code;
		http_response_code($code);
		$titles = [400 => 'Permintaan ditolak', 401 => 'Perlu masuk', 403 => 'Akses ditolak', 404 => 'Halaman tidak ditemukan',
		           405 => 'Metode tidak diizinkan', 500 => 'Kesalahan server', 503 => 'Sedang pemeliharaan'];
		$defaults = [400 => 'Permintaan tidak valid.', 401 => 'Sesi Anda berakhir atau Anda belum masuk.',
		             403 => 'Anda tidak punya akses ke halaman ini.', 404 => 'Halaman atau tautan yang Anda tuju tidak ada.',
		             405 => 'Halaman ini tidak bisa dibuka dengan cara itu.', 500 => 'Terjadi kendala di server.',
		             503 => 'Aplikasi sedang dalam pemeliharaan.'];
		$this->display('errors/index', [
			'title' => $code . ' - ' . ($titles[$code] ?? 'Kesalahan') . ' - ' . APP_NAME,
			'code' => $code,
			'status_title' => $titles[$code] ?? "Kesalahan $code",
			'message' => $message !== '' ? $message : ($defaults[$code] ?? 'Permintaan tidak dapat diselesaikan.'),
			'reference_id' => 'ERR-' . strtoupper(substr(md5($code . ($_SERVER['REQUEST_URI'] ?? '') . microtime()), 0, 8)),
		]);
		exit();
	}

	/** Renders app/view/{$view}.php with $data in scope. */
	public function display($view, $data = []){
		$this->send_security_headers();
		if (!isset($data['csrf_token'])) $data['csrf_token'] = $this->get_csrf_token();
		$path = dirname(__DIR__) . '/view/' . $view . '.php';
		if (!is_file($path)) throw new RuntimeException("View $view tidak ditemukan.");
		require $path;
	}

	/** Loads app/model/{$model}.php and returns a new instance. */
	public function logic($model){
		$path = dirname(__DIR__) . '/model/' . $model . '.php';
		if (is_file($path)) require_once $path;
		return class_exists($model) ? new $model : null;
	}

	/** Starts the session with safe cookie settings, ends idle sessions, and issues the CSRF token. */
	public function start_default_session_app(){
		$this->send_security_headers();
		if (session_status() === PHP_SESSION_NONE) {
			$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
			@session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'domain' => '', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
			$name = session_name();
			if (!empty($_COOKIE[$name]) && !preg_match('/^[a-zA-Z0-9,-]{16,128}$/', $_COOKIE[$name])) {
				unset($_COOKIE[$name]);
				session_id(bin2hex(random_bytes(16)));
			}
			@session_start();
		}

		$idleMinutes = max(5, (int)($_ENV['SESSION_IDLE_MINUTES'] ?? self::DEFAULT_IDLE_MINUTES));
		if (!empty($_SESSION['user-id']) && isset($_SESSION['LAST_ACTIVITY']) && time() - $_SESSION['LAST_ACTIVITY'] > $idleMinutes * 60) {
			$this->destroy_session_cleanly();
			header('Location: ' . APP_PATH . '/login/?timeout=1');
			exit();
		}
		$_SESSION['LAST_ACTIVITY'] = time();
		if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
		$this->check_account_session();
	}

	/** Fresh DB state makes a password reset revoke every previously signed-in session. */
	private function check_account_session(): void {
		if ($this->accountSessionChecked || empty($_SESSION['user-id'])) return;
		$this->accountSessionChecked = true;
		$database = new Database();
		if (!$database->isConnected()) $this->abort(503, 'Layanan akun belum tersedia. Coba lagi nanti.');
		$user = \Pena\Support\Db::fromTemplate($database)->one(
			"SELECT u.status,u.auth_version,u.email_verified_at,EXISTS(SELECT 1 FROM platform_operators p WHERE p.user_id=u.id) AS is_operator FROM users u WHERE u.id=?", [(int)$_SESSION['user-id']]);
		if (!\Pena\Service\AccountService::sessionIsCurrent($user, $_SESSION['user-auth-version'] ?? null)) {
			$this->destroy_session_cleanly();
			session_id('');
			@session_start();
			$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
			// Email links and login continue anonymously; workspaces redirect through their normal guards.
			return;
		}
		$_SESSION['is-operator']=!empty($user['is_operator'])&&$user['email_verified_at']!==null;
		if ($user['email_verified_at'] === null && !in_array(static::class, ['akun','login','review','landing','daftar'], true)) {
			header('Location: '.APP_PATH.'/akun/verifikasi/');
			exit();
		}
        // Keep account recovery, guest review and help reachable during required AI setup.
        if ($user['email_verified_at'] !== null && !in_array(static::class, ['akun','login','review','landing','daftar','preferensi','bantuan','operator'], true)
            && (new \Pena\Service\AiSettings(\Pena\Support\Db::fromTemplate($database)))->requiresSetup((int)$_SESSION['user-id'])) {
            $setupUrl=APP_PATH.'/preferensi/';
            $wantsJson=str_contains($_SERVER['HTTP_ACCEPT']??'', 'application/json')
                || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='xmlhttprequest'
                || preg_match('~/(?:ai_kerangka|ai_data|ai_paragraf|tulis_ulang)(?:/|$)~i',$_SERVER['REQUEST_URI']??'');
            if($wantsJson)$this->json_response(['status'=>'error','message'=>'Pilih provider dan simpan API key Anda di Pengaturan AI sebelum melanjutkan.','setup_url'=>$setupUrl],403);
            header('Location: '.$setupUrl);exit();
        }
	}

	public function destroy_session_cleanly(){
		if (session_status() === PHP_SESSION_ACTIVE) {
			$_SESSION = [];
			if (ini_get('session.use_cookies')) {
				$p = session_get_cookie_params();
				setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
			}
			@session_destroy();
		}
	}

	public function get_csrf_token(){
		if (session_status() === PHP_SESSION_NONE) $this->start_default_session_app();
		if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
		return $_SESSION['csrf_token'];
	}

	/** Checks the form's csrf_token (or the X-CSRF-Token header) against the session. */
	public function verify_csrf_token($token = null){
		if (session_status() === PHP_SESSION_NONE) $this->start_default_session_app();
		$token = $token ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
		$valid = is_string($token) && $token !== '' && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
		if (!$valid) Security::log_incident('csrf', 'Token CSRF tidak ada atau tidak cocok', 'Blocked');
		return $valid;
	}

	/** Strips control characters and normalizes UTF-8 in all input; passwords keep their whitespace. */
	public function sanitize_global_inputs(){
		$passwords = array_intersect_key($_POST, array_flip(['password', 'password_confirmation', 'api_key']));
		$_GET = $this->clean_input_array($_GET);
		$_POST = $this->clean_input_array($_POST);
		$_COOKIE = $this->clean_input_array($_COOKIE);
		$_REQUEST = $this->clean_input_array($_REQUEST);
		foreach ($passwords as $key => $password) {
			if (is_string($password)) { $_POST[$key] = $password; $_REQUEST[$key] = $password; }
		}
	}

	private function clean_input_array($data){
		if (is_array($data)) {
			$cleaned = [];
			foreach ($data as $k => $v) $cleaned[trim((string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', (string)$k))] = $this->clean_input_array($v);
			return $cleaned;
		}
		return is_string($data) ? Security::sanitize_string($data) : $data;
	}

	public function json_response($data, $status = 200){
		$this->send_security_headers();
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($data, JSON_UNESCAPED_UNICODE);
		exit();
	}
}
