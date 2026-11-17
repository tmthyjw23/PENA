# PENA

Penyusunan dan persetujuan surat organisasi mahasiswa. Aplikasi PHP native dengan arsitektur MVC
(router `app/core/App.php`, `Controller`, `Database`, folder `app/controller`, `app/model`, `app/view`).
Kelas layanan PENA ada di `app/Service` (PSR-4 `Pena\`).

## Menjalankan di XAMPP (Apache + MySQL)

- PHP 8.2 ke atas dengan ekstensi curl, dom, gd, mbstring, mysqli, zip (dan com_dotnet untuk PDF lewat Word).
- Folder proyek dipasang lewat alias Apache `D:\xampp\apache\conf\extra\httpd-pena.conf`
  (di-include dari `httpd.conf`), jadi `http://localhost/pena` membuka proyek tanpa memindahkannya.

```powershell
D:\xampp\php\php.exe .\.tools\composer.phar install
D:\xampp\php\php.exe .\.tools\composer.phar setup:local   # membuat .env dari .env.example bila belum ada
D:\xampp\php\php.exe .\.tools\composer.phar db:install    # membuat database DB_NAME dan menerapkan skema
```

Isi `.env`: `APP_URL=http://localhost/pena`, koneksi DB, dan pengaturan AI (lihat `.env.example`).
Akun contoh lokal: `sekretaris / sekretaris123` dan `admin / admin123`. Nonaktifkan sebelum dipakai sungguhan.
Server dev alternatif tanpa Apache: `composer serve` (http://127.0.0.1:8787).

## Database

`database/pena_schema.sql` adalah skema lengkap: akun, organisasi, kop, direktori penanda tangan,
kerangka surat (JSON), penghitung nomor, surat dan versinya, tahap dan permintaan persetujuan
(token tautan disimpan sebagai hash), komentar, ekspor, jejak audit, dan log AI. Aman dijalankan ulang.
Setiap koneksi memakai `sql_mode` strict (`Database::STRICT_SQL_MODE`).

## Layanan dan halaman

- `LetterService`, `ApprovalService`, `ApprovalFlow`, `TemplateValidator`, `LetterNumberService`: alur surat
  dari draf, ajukan (nomor + snapshot + hash), review lewat tautan, revisi dengan konfirmasi ulang, sampai disetujui.
- `WordDocumentService` + `WordPdfConverter`: DOCX dan PDF (lewat Microsoft Word; jalankan Apache dari
  XAMPP Control Panel, bukan sebagai service).
- Halaman: `/dashboard/`, `/surat/`, `/surat/buat/` (AI), `/surat/manual/`, `/surat/detail/{id}`,
  `/review/lihat/{token}` (pemeriksa tanpa akun).

## Tampilan

Patokan: Figma "Jadwal saku" ([docs/FIGMA-HANDOFF.md](docs/FIGMA-HANDOFF.md), [DESIGN.md](DESIGN.md)).
Tailwind CSS v4: sumber `resources/css/app.css`, hasil `public/assets/css/app.css` ikut disimpan.
`npm install` sekali, lalu `npm run css` setelah mengubah view. Huruf disimpan di `public/assets/fonts`.

## AI lewat 9router

Jalankan `9router` (boleh di WSL), ambil API key dari `http://localhost:20128/dashboard`, isi `AI_BASE_URL`,
`AI_API_KEY`, `AI_MODEL` (disarankan `ag/gemini-3.8-flash-medium`, sekitar 30 detik per surat) di `.env`.
`LetterAiService` menjalankan pilih kerangka, isi data, tulis paragraf, dan tulis ulang; setiap panggilan
tercatat di `ai_generations`. Server menolak isian di luar kerangka, kutipan yang tidak ada di kalimat
sekretaris, dan paragraf yang mengetik fakta langsung; nomor surat tidak pernah dari AI.

## Tes

```powershell
D:\xampp\php\php.exe .\.tools\composer.phar check
D:\xampp\php\php.exe .\.tools\composer.phar test          # SQL binding, login, DOCX, PDF
D:\xampp\php\php.exe .\.tools\composer.phar test:schema   # database sementara pena_test_*
D:\xampp\php\php.exe .\.tools\composer.phar test:flow
D:\xampp\php\php.exe .\.tools\composer.phar test:ai       # transport palsu, tanpa jaringan
```
