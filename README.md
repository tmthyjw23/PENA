# PENA

## Dokumentasi penggunaan dan teknis

Buka [Mulai di sini](docs/dokumentasi/Mulai-Di-Sini.md) untuk panduan pengguna, dokumentasi kode/arsitektur, PDF dan paket khusus teman. [Versi browser](docs/dokumentasi/Mulai-Di-Sini.html) dapat dibuka tanpa menjalankan aplikasi.

Penyusunan dan persetujuan surat mahasiswa: surat organisasi dan surat pribadi. Aplikasi PHP native dengan arsitektur MVC
(router `app/core/App.php`, `Controller`, `Database`, folder `app/controller`, `app/model`, `app/view`).
Kelas layanan PENA ada di `app/Service` (PSR-4 `Pena\`).

## Menjalankan di XAMPP (Apache + MySQL)

- PHP 8.2 ke atas dengan ekstensi curl, dom, gd, mbstring, mysqli, zip (dan com_dotnet untuk PDF lewat Word).
- Folder proyek dipasang lewat alias Apache `D:\xampp\apache\conf\extra\httpd-pena.conf`
  (di-include dari `httpd.conf`), jadi `http://localhost/pena` membuka proyek tanpa memindahkannya.

```powershell
D:\xampp\php\php.exe .\.tools\composer.phar install
D:\xampp\php\php.exe .\.tools\composer.phar setup:local   # membuat .env dari .env.example bila belum ada
D:\xampp\php\php.exe .\.tools\composer.phar db:install    # membuat database DB_NAME, menjalankan migrasi, menerapkan skema
```

Isi `.env`: `APP_URL=http://localhost/pena`, koneksi DB, dan pengaturan AI (lihat `.env.example`).
Pemasangan baru tidak membuat akun demo. Untuk fixture lokal saja, impor `database/demo_seed.sql`
ke database lokal melalui phpMyAdmin setelah `db:install`. Jangan impor seed demo ke produksi.
Akun contoh dari fixture lokal: `sekretaris / sekretaris123` (BEM + ruang pribadi) dan `anggota / sekretaris123` (hanya ruang
pribadi). Akun `admin / admin123` tersisa dari versi lama sebagai anggota BEM biasa. Nonaktifkan sebelum dipakai sungguhan.
Akun baru dibuat sendiri lewat `/daftar/`.
Server dev alternatif tanpa Apache: `composer serve` (http://127.0.0.1:8787).

## Database

Peran: hanya **sekretaris** (setiap akun, di ruang kerjanya), **pemeriksa** (tanpa akun, lewat tautan), dan
**operator platform**. Tidak ada peran admin. Setiap akun punya ruang pribadi (tanpa kop dan nomor; tanpa pemeriksa,
surat langsung jadi) dan boleh membuat ruang organisasi (kop diisi bersama surat pertama, nomor berseri, alur bertahap).
Pemeriksa ditambahkan langsung di form surat ("Pemeriksa baru"). Lihat [docs/ANALISIS-PRODUK.md](docs/ANALISIS-PRODUK.md).

`database/pena_schema.sql` adalah skema lengkap: akun, ruang kerja (`organizations.kind`), kop, direktori pemeriksa,
kerangka surat (JSON), penghitung nomor, surat dan versinya, tahap dan permintaan persetujuan
(token tautan disimpan sebagai hash), komentar, ekspor, jejak audit, dan log AI. Data contoh terpisah
di `database/demo_seed.sql`; tes database memuatnya secara eksplisit. Skema tidak menimpa identitas
organisasi dengan contoh saat dijalankan ulang.
Perubahan untuk database yang sudah ada ada di `database/migrations/` (dicatat di tabel `schema_migrations`);
`db:install` menjalankannya sebelum skema. Cadangkan database dulu sebelum memasang di server.
Setiap koneksi memakai `sql_mode` strict (`Database::STRICT_SQL_MODE`).

## Layanan dan halaman

- `LetterService`, `ApprovalService`, `ApprovalFlow`, `TemplateValidator`, `LetterNumberService`: alur surat
  dari draf, ajukan (nomor + snapshot + hash), review lewat tautan, revisi dengan konfirmasi ulang, sampai disetujui.
- `ExportService`: unduhan Word/PDF dari snapshot surat yang sudah disetujui, dengan pemeriksaan akses ruang
  dan penyimpanan privat. `WordDocumentService` membentuk DOCX; PDF melalui `WordPdfConverter` pada Windows
  (Microsoft Word, Apache dari XAMPP Control Panel), atau `LibreOfficePdfConverter` pada Linux
  (`libreoffice-writer` dan font Liberation; `SOFFICE_BINARY` opsional). Kegagalan ekspor tidak menghapus persetujuan.
- Halaman: `/dashboard/`, `/surat/`, `/surat/buat/` (AI), `/surat/manual/`, `/surat/detail/{id}`,
  `/review/lihat/{token}` (pemeriksa tanpa akun).

## Tampilan

Patokan: Figma "Jadwal saku" ([docs/FIGMA-HANDOFF.md](docs/FIGMA-HANDOFF.md), [DESIGN.md](DESIGN.md)).
Tailwind CSS v4: sumber `resources/css/app.css`, hasil `public/assets/css/app.css` ikut disimpan.
`npm install` sekali, lalu `npm run css` setelah mengubah view. Huruf disimpan di `public/assets/fonts`.

## AI lewat 9router

Jalankan `9router` (boleh di WSL), ambil API key dari `http://localhost:20128/dashboard`, isi `AI_BASE_URL`,
`AI_API_KEY`, `AI_MODEL` (ID model yang tersedia pada gateway Anda) di `.env`.
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
D:\xampp\php\php.exe .\.tools\composer.phar test:workspace # migrasi dari skema 17 Nov, daftar, ruang kerja
D:\xampp\php\php.exe .\.tools\composer.phar test:accounts  # token email, reset, pencabutan sesi, transport palsu
D:\xampp\php\php.exe .\.tools\composer.phar test:exports   # akses, cache snapshot, gagal PDF, converter nyata bila tersedia
```

## Status operasional

[Laporan rilis 18 November 2026](docs/RELEASE-2026-11-18.md) mencatat backup, migrasi, uji dan batas deployment.
[Panduan Resend](docs/SETUP-EMAIL-RESEND.md) memandu konfigurasi pengirim. Verifikasi email dan reset
password sudah aktif di VPS; email verifikasi nyata diterima/dikonfirmasi pada 19 November.
Lihat [laporan aktivasi](docs/DEPLOY-EMAIL-2026-11-19.md). Lihat [laporan akun/email](docs/EMAIL-ACCOUNT-2026-11-18.md). Batas login, pendaftaran dan panggilan AI tersimpan di `request_limits`;
batas keluaran/permintaan AI diatur lewat `.env.example`.

Setelah migrasi akun/email, ruang kerja memerlukan email terverifikasi, termasuk untuk akun lama.
`/akun/verifikasi/` menyediakan kirim ulang; `/akun/lupa/` memulai reset. Kegagalan email tidak
membatalkan akun. Gunakan APP_URL eksplisit dan HTTPS pada produksi. JavaScript diperlukan untuk
membaca token fragmen dari tautan email. Konfigurasi MAIL_FROM memakai `notify.egg3l.com`.

## AI di website — 19 November 2026

Alur AI VPS sudah diuji nyata untuk IZN dan PMJ dengan data fiktif. Proses form dipisahkan per pengguna/ruang,
dan revisi melindungi dari tab usang. Lihat [laporan AI website](docs/AI-WEBSITE-2026-11-19.md).
Tes tambahan: `composer test:ai-drafts` dan `composer test:ai-controller` (transport palsu, DB sementara).
Jika membuka tab lama sebelum rilis ini, buka ulang Buat surat agar form memuat identitas proses terbaru.

## AI pribadi per akun

Akun biasa wajib memilih provider dan menyimpan API key sendiri setelah masuk/verifikasi sebelum membuka ruang kerja. AI bawaan VPS hanya untuk operator platform; koneksi tersimpan tetap berlaku saat login berikutnya. Menyimpan/login tidak memanggil provider.

Menu **Pengaturan AI** (`/preferensi/`) mendukung OpenAI, OpenRouter, Groq, Google AI Studio dan endpoint HTTPS publik
yang kompatibel dengan Chat Completions. Kunci pengguna disimpan terenkripsi; butuh OpenSSL dan
`AI_CREDENTIAL_KEY` (base64 32 byte acak) atau `AI_CREDENTIAL_KEY_FILE` (file privat di luar web root).
Jangan mengganti kunci induk ketika ciphertext masih ada. [Rancangan, keamanan dan pemulihan](docs/BYOK-2026-11-20.md).
Tes: `composer test:ai-settings`, `composer test:ai-endpoint`, `composer test:ai-transport`.

## Bantuan dan petunjuk awal

Akun baru mendapat hint yang menunjuk kontrol di Beranda setelah verifikasi dan pengaturan AI. Hint dapat ditutup dan diulang melalui Bantuan → Tampilkan petunjuk di layar. Bantuan dapat dibuka lagi
melalui sidebar/menu akun (`/bantuan/`); petunjuk singkat tersedia di halaman tugas. Menutup panduan
disimpan per akun, tidak menandakan pemahaman pengguna. Form draf memberi peringatan perubahan
belum disimpan; tidak ada autosave. [Perubahan dan verifikasi UX](docs/UX-GUIDANCE-2026-11-20.md), [hint dan akses AI terbaru](docs/HINTS-AI-ACCESS-2026-11-21.md).
