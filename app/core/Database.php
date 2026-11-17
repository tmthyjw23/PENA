<?php
/**
 * Database wrapper of the MVC framework: MySQLi connection with strict SQL mode,
 * model-style query -> bind -> single/resultSet with native prepared statements, and diagnostics.
 * PENA services use the thinner Pena\Support\Db over the same connection.
 */

class Database {

	private $server_name = HOST_DB;
	private $db_name     = NAME_DB;
	private $user_name   = USER_DB;
	private $password    = PASS_DB;

	private $con = null;
	private $pdo = null;
	private $stmt = null;
	private $query_sql = "";
	private $bind_params = [];
	private $last_result = null;

	private $is_connected = false;
	private $connection_error = "";
    private $executed = false;

	public function __construct($customDbName = null){
		if ($customDbName !== null) {
			$this->db_name = $customDbName;
		}
		$this->init_connection();
	}

	// XAMPP's MariaDB ships without STRICT_TRANS_TABLES, so invalid ENUM values become ''
	// and long text is truncated silently. Letter status and numbers must fail loudly instead.
	public const STRICT_SQL_MODE = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION';

	public static function applyStrictMode(mysqli $conn): void {
		$conn->query("SET SESSION sql_mode = '" . self::STRICT_SQL_MODE . "'");
	}

	public function init_connection(){
        $this->is_connected = false;
        $this->con = null;
        try {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $conn = new mysqli($this->server_name, $this->user_name, $this->password, $this->db_name, PORT_DB);
            $conn->set_charset('utf8mb4');
            self::applyStrictMode($conn);
            $this->con = $conn;
            $this->is_connected = true;
            $this->connection_error = '';
        } catch (mysqli_sql_exception $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            $this->connection_error = 'Koneksi database gagal. Periksa konfigurasi DB dan jalankan impor skema bila database belum ada.';
        }
    }

    public function ensure_connected(){
        // Never silently reconnect: doing so could lose an active transaction.
        if (!$this->isConnected()) {
            throw new RuntimeException($this->connection_error ?: 'Database belum terhubung.');
        }
    }

	/**
	 * Cek status apakah database terhubung
	 */
	public function isConnected(){
		return $this->is_connected && ($this->con instanceof mysqli);
	}

	/**
	 * Ambil pesan error koneksi terakhir
	 */
	public function getConnectionError(){
		return $this->connection_error;
	}

	/**
	 * Ambil nama database aktif
	 */
	public function getDatabaseName(){
		return $this->db_name;
	}

	/**
	 * Ambil native connection MySQLi
	 */
	public function getConnection(){
		$this->ensure_connected();
		return $this->con;
	}

	/**
	 * Ambil atau inisialisasi instance PDO terhubung
	 */
	public function getPdo(){
		if ($this->pdo instanceof PDO) {
			return $this->pdo;
		}

		$pwd = $this->password;
		try {
			$dsn = "mysql:host=" . $this->server_name . ";port=" . PORT_DB . ";dbname=" . $this->db_name . ";charset=utf8mb4";
			$this->pdo = new PDO($dsn, $this->user_name, $pwd, [
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
				PDO::ATTR_EMULATE_PREPARES => false
			]);
			return $this->pdo;
		} catch (PDOException $e) {
			return null;
		}
	}

	/**
	 * Dapatkan daftar tabel yang ada di database saat ini
	 */
	public function getTables(){
		if (!$this->isConnected()) return [];
		$tables = [];
		try {
			$res = $this->con->query("SHOW TABLES");
			if ($res) {
				while ($row = $res->fetch_row()) {
					$tables[] = $row[0];
				}
			}
		} catch (Throwable $e) {}
		return $tables;
	}

	/**
	 * Cek apakah tabel tertentu ada di database
	 */
	public function hasTable($tableName){
		$tables = $this->getTables();
		return in_array($tableName, $tables);
	}

	/**
	 * Hitung jumlah baris pada tabel tertentu
	 */
	public function getTableRowCount($tableName){
		if (!$this->isConnected() || !$this->hasTable($tableName)) return 0;
		try {
			$safeName = str_replace('`', '', $tableName);
			$res = $this->con->query("SELECT COUNT(*) AS total FROM `" . $safeName . "`");
			if ($res && ($row = $res->fetch_assoc())) {
				return (int)$row['total'];
			}
		} catch (Throwable $e) {}
		return 0;
	}

	/**
	 * Ambil laporan diagnostik lengkap sistem koneksi
	 */
	public function getDiagnostics(){
		$tables = $this->getTables();
		$defaultTables = ['users', 'organizations', 'letter_templates', 'letters', 'letter_versions', 'approval_requests', 'ai_generations'];
		$missingTables = array_diff($defaultTables, $tables);
		$serverVersion = ($this->con) ? @$this->con->server_info : null;

		return [
			'is_connected' => $this->isConnected(),
			'host' => $this->server_name,
			'database' => $this->db_name,
			'user' => $this->user_name,
			'error_message' => $this->connection_error,
			'server_version' => $serverVersion,
			'tables' => $tables,
			'total_tables' => count($tables),
			'default_tables' => $defaultTables,
			'missing_tables' => array_values($missingTables),
			'is_schema_ready' => empty($missingTables) && count($tables) > 0,
			'users_count' => $this->getTableRowCount('users'),
		];
	}

	/**
	 * Eksekusi berkas SQL (misal: database/pena_schema.sql). Hanya bila ALLOW_DB_SETUP aktif.
	 * $database: berkas tanpa pernyataan USE butuh database yang dipilih.
	 */
	public function importSqlFile($sqlFilePath, ?string $database = null){
		if (!file_exists($sqlFilePath)) {
			return ['success' => false, 'message' => "Berkas SQL tidak ditemukan pada: " . htmlspecialchars($sqlFilePath)];
		}

		$sqlContent = file_get_contents($sqlFilePath);
		if (empty(trim($sqlContent))) {
			return ['success' => false, 'message' => "Berkas SQL kosong."];
		}

        if (!ALLOW_DB_SETUP) {
            return ['success' => false, 'message' => 'Setup database dinonaktifkan.'];
        }
        try {
            $conn = new mysqli($this->server_name, $this->user_name, $this->password, $database ?? '', PORT_DB);
        } catch (mysqli_sql_exception $e) {
            error_log('Database setup connection failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Koneksi untuk setup database gagal. Periksa konfigurasi DB.'];
        }

		$conn->set_charset("utf8mb4");
		self::applyStrictMode($conn);

        try {
            $conn->multi_query($sqlContent);
            $statementCount = 0;
            do {
                if ($res = $conn->store_result()) $res->free();
                $statementCount++;
            } while ($conn->more_results() && $conn->next_result());
            $this->init_connection();
            return ['success' => true, 'statements' => $statementCount, 'message' => 'Skema database berhasil diimpor.'];
        } catch (mysqli_sql_exception $e) {
            error_log('Database import failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Impor gagal; sebagian pernyataan mungkin sudah diterapkan. Periksa log server sebelum mengulang.'];
        } finally {
            $conn->close();
        }
    }

    // Existing models use query -> bind -> fetch/execute; preparation is lazy.
    public function query($sql){
        return $this->prepare($sql);
    }

    public function prepare($sql){
        if ($this->stmt instanceof mysqli_stmt) $this->stmt->close();
        $this->query_sql = $sql;
        $this->bind_params = [];
        $this->stmt = null;
        $this->last_result = null;
        $this->executed = false;
        return $this;
    }

    public function bind($param, $value, $type = null){
        $this->bind_params[ltrim($param, ':')] = $value;
        $this->executed = false;
        return $this;
    }

    public function execute(){
        if ($this->executed) return true;
        $this->ensure_connected();
        [$sql, $values] = self::compileParameters($this->query_sql, $this->bind_params);
        $this->stmt = $this->con->prepare($sql);
        $this->stmt->execute($values);
        $this->last_result = $this->stmt->get_result();
        $this->executed = true;
        return true;
    }

    private static function compileParameters(string $sql, array $bindings): array
    {
        $values = [];
        // Quoted SQL literals, identifiers and comments must not become parameters.
        $pattern = <<<'REGEX'
~('(?:\\.|''|[^'\\])*'|"(?:\\.|""|[^"\\])*"|`(?:``|[^`])*`|/\*.*?\*/|--[^\r\n]*|\#[^\r\n]*)|:([a-zA-Z_][a-zA-Z0-9_]*)~s
REGEX;
        $compiled = preg_replace_callback($pattern, static function (array $match) use ($bindings, &$values): string {
            if (!isset($match[2])) return $match[0];
            $name = $match[2];
            if (!array_key_exists($name, $bindings)) {
                throw new InvalidArgumentException('Parameter SQL belum diikat: ' . $name);
            }
            $values[] = $bindings[$name];
            return '?';
        }, $sql);
        return [$compiled, $values];
    }

	// RESULT SET (Fetch all rows as associative array)
	public function resultSet(){
		$this->execute();
		if ($this->last_result instanceof mysqli_result) {
			$rows = [];
			while ($row = $this->last_result->fetch_assoc()) {
				$rows[] = $row;
			}
			return $rows;
		}
		return [];
	}

	// SINGLE ROW (Fetch one row as associative array)
	public function single(){
		$this->execute();
		if ($this->last_result instanceof mysqli_result) {
			$row = $this->last_result->fetch_assoc();
			return $row ?: null;
		}
		return null;
	}

	// ROW COUNT
	public function rowCount(){
		if ($this->last_result instanceof mysqli_result) {
			return $this->last_result->num_rows;
		}
		if ($this->con) {
			return $this->stmt instanceof mysqli_stmt ? $this->stmt->affected_rows : $this->con->affected_rows;
		}
		return 0;
	}

	// LAST INSERT ID
	public function lastInsertId(){
		return $this->con ? $this->con->insert_id : 0;
	}

	// Close database connection
	public function db_close(){
		if ($this->con) {
			@$this->con->close();
			$this->con = null;
		}
		$this->is_connected = false;
	}

	// Escape wrapper (anti SQL injection)
	public function my_real_escape_string($string){
		$this->ensure_connected();
		if ($this->con && $string !== null) {
			return $this->con->real_escape_string($string);
		}
		throw new RuntimeException('Database belum terhubung.');
	}

	// Transactions
	public function beginTransaction(){
		$this->ensure_connected();
        return $this->con->begin_transaction();
	}

	public function commit(){
		$this->ensure_connected();
        return $this->con->commit();
	}

	public function rollBack(){
		$this->ensure_connected();
        return $this->con->rollback();
	}
}
