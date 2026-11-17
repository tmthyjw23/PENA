<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
set_error_handler(static function (int $level, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $level, $file, $line);
});
foreach (['Security', 'Database', 'Controller'] as $class) {
    require dirname(__DIR__) . '/app/core/' . $class . '.php';
}
require dirname(__DIR__) . '/app/model/User_model.php';
define('HOST_DB', '127.0.0.1');
define('NAME_DB', 'unused');
define('USER_DB', 'unused');
define('PASS_DB', '');

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function rejects(callable $action, string $exception): void {
    try { $action(); } catch (Throwable $error) {
        check($error instanceof $exception, 'Unexpected exception: ' . $error::class);
        return;
    }
    throw new RuntimeException('Expected ' . $exception);
}

$compiler = new ReflectionMethod(Database::class, 'compileParameters');
$sql = "SELECT ':ignored', `:column` FROM users WHERE username=:u OR email=:u AND id=:id /* :comment */ -- :line\n";
$payload = "O'Brien :id ' OR 1=1 --";
[$prepared, $values] = $compiler->invoke(null, $sql, ['u' => $payload, 'id' => 7]);
check($prepared === str_replace(['username=:u', 'email=:u', 'id=:id'], ['username=?', 'email=?', 'id=?'], $sql), 'SQL literals or comments were modified');
check($values === [$payload, $payload, 7], 'Repeated parameters or parameter-like input corrupted');
check($compiler->invoke(null, 'SELECT :n, :b, :f', ['n' => null, 'b' => false, 'f' => 2.5])[1] === [null, false, 2.5], 'Parameter types lost');
rejects(fn() => $compiler->invoke(null, 'SELECT :missing', []), InvalidArgumentException::class);
echo "PASS SQL binding: repeated names, malicious input, literals, comments, null and missing values\n";

// Exercise the actual model against a small data double; no database is needed here.
final class UsersFixture {
    public array $user;
    public int $writes = 0;
    public function isConnected(): bool { return true; }
    public function query(string $sql): void {}
    public function bind(string $name, mixed $value): void {}
    public function single(): array { return $this->user; }
    public function execute(): bool { $this->writes++; return true; }
}
$fixture = new UsersFixture();
$modelReflection = new ReflectionClass(User_model::class);
$model = $modelReflection->newInstanceWithoutConstructor();
$modelReflection->getProperty('db')->setValue($model, $fixture);
$current = ' changed password ';
foreach (['admin' => 'admin123', 'sekretaris' => 'sekretaris123'] as $username => $oldPassword) {
    $fixture->user = ['id' => 1, 'username' => $username, 'password' => password_hash($current, PASSWORD_DEFAULT),
                      'nama_lengkap' => 'X', 'email' => 'x@pena.local'];
    check($model->validate_credentials($username, $oldPassword) === false, 'Demo password bypass remains');
    $user = $model->validate_credentials($username, $current);
    check(is_array($user) && !isset($user['password']), 'Current password rejected or hash returned');
    check($model->validate_credentials($username, trim($current)) === false, 'Password whitespace ignored');
}
check(!Security::verify_password('plaintext', 'plaintext'), 'Plaintext password accepted');
check(Security::verify_password('secret', Security::hash_password('secret')), 'Hash verification failed');
$controller = (new ReflectionClass(Controller::class))->newInstanceWithoutConstructor();
$_POST = ['password' => $current, 'username' => ' admin '];
$_GET = $_COOKIE = [];
$_REQUEST = $_POST;
$controller->sanitize_global_inputs();
check($_POST['password'] === $current && $_POST['username'] === 'admin', 'Input cleanup changed password');
echo "PASS authentication: changed passwords, demo bypass rejection, hashing and whitespace\n";

$output = tempnam(sys_get_temp_dir(), 'pena-docx-');
try {
    $service = new Pena\Service\WordDocumentService();
    $rows = [['Barang', 'Jumlah']];
    for ($i = 1; $i <= 80; $i++) $rows[] = ['Kamera & gimbal <' . $i . '>', $i];
    $signers = [];
    for ($i = 1; $i <= 5; $i++) $signers[] = ['role' => 'Advisor', 'name' => 'Pemeriksa ' . $i];
    $service->save($output, ['name' => 'BEM & Panitia', 'address' => 'Kampus'], ['Nomor' => '001/XI/2026'], [
        ['type' => 'paragraph', 'text' => 'Permohonan peminjaman kamera & gimbal.'],
        ['type' => 'table', 'rows' => $rows], ['type' => 'signatures', 'signers' => $signers],
    ]);
    $zip = new ZipArchive();
    check($zip->open($output) === true, 'DOCX is not a ZIP document');
    foreach (['word/document.xml', 'word/header1.xml'] as $part) {
        $xml = $zip->getFromName($part);
        check(is_string($xml), 'Missing DOCX part: ' . $part);
        $dom = new DOMDocument();
        check($dom->loadXML($xml), 'Invalid XML: ' . $part);
    }
    $xml = $zip->getFromName('word/document.xml');
    check(str_contains($xml, 'Pemeriksa 5') && str_contains($xml, 'gimbal &lt;80&gt;'), 'Long table or signers missing');
    check(str_contains($xml, 'w:headerReference'), 'Repeated header missing');
    $zip->close();
    rejects(fn() => $service->save($output, ['name' => 'BEM'], [], [['type' => 'unknown']]), InvalidArgumentException::class);
    echo "PASS DOCX: long table, five signers, XML escaping, repeated header and block validation\n";

    // PDF needs Microsoft Word on this machine; skip loudly instead of failing elsewhere.
    $converter = new Pena\Service\WordPdfConverter();
    if (!$converter->isAvailable()) {
        echo "SKIP PDF: Microsoft Word / com_dotnet tidak tersedia\n";
    } else {
        $docx = $output . '.docx';
        $pdf = $output . '.pdf';
        try {
            $service->save($docx, ['name' => 'BEM & Panitia'], ['Nomor' => '001/XI/2026'], [
                ['type' => 'paragraph', 'text' => 'Uji konversi.'],
                ['type' => 'table', 'rows' => $rows], ['type' => 'signatures', 'signers' => $signers],
            ]);
            $converter->convert($docx, $pdf);
            $bytes = (string)file_get_contents($pdf);
            check(str_starts_with($bytes, '%PDF-'), 'Output is not a PDF');
            check(preg_match_all('#/Type\s*/Page[^s]#', $bytes) >= 2, 'Long table should span several PDF pages');
            rejects(fn() => $converter->convert($output . '-missing.docx', $pdf), RuntimeException::class);
            echo "PASS PDF: Word conversion of multi-page letter, missing-source rejection\n";
        } finally {
            foreach ([$docx, $pdf] as $file) if (is_file($file)) unlink($file);
        }
    }
} finally {
    if (is_file($output)) unlink($output);
}
