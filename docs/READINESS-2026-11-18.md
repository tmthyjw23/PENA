# Kesiapan PENA untuk presentasi dan produksi

Tanggal penilaian: 18 November 2026, WITA. Penyusun: Codex, chat PENA.

## Kesimpulan

PENA menyusun surat dari permintaan bahasa alami dan mencatat persetujuan bertingkat atas versi dokumen.
Kini ada ruang organisasi dan pribadi. Analisis ini mengasumsikan demo satu kelompok dan pilot kecil
sekitar 5–10 pengguna; angka tersebut asumsi perencanaan, bukan kapasitas server yang sudah terbukti.
Pendaftaran publik bebas memperluas paparan sehingga memerlukan kontrol penyalahgunaan sebelum rilis umum.

**Presentasi:** dasar teknis sudah layak dipersiapkan, tetapi alur keluaran, kesamaan lingkungan demo,
dan bukti evaluasi masih menghalangi klaim bahwa proyek sudah selesai dari awal sampai akhir.
**Produksi umum dengan data nyata:** belum siap. Ada kekurangan pada autentikasi, batas pemakaian AI,
ekspor Linux, tata kelola data, serta bukti pemulihan dan operasi.
Hasil tes yang baik tidak sama dengan sertifikasi keamanan atau jaminan kebenaran setiap surat.

## Sumber dan cara penilaian

- Transkrip awal Claude `../Percakapan_Final_Project_AI_PENA.md`, keputusan pengguna dalam chat PENA,
  lampiran Egg3l Manager yang disampaikan pengguna, `../AGENTS.md` dan `../SESSION.md`.
- Dokumen Claude Code: `ALUR-PENGERJAAN.md`, `ANALISIS-PRODUK.md`, `FIGMA-HANDOFF.md`, `../DESIGN.md`.
- Laporan deployment: `C:/Users/ASUS/OneDrive/Documents/ChatGPT/Egg3l/DEPLOYMENT-PENA.md`.
- Kode lokal: commit `6234fee`, dengan perubahan UI lokal yang belum di-commit saat pemeriksaan.
  Perubahan tersebut dipertahankan; analisis ini tidak mengubah kode aplikasi atau konfigurasi VPS.
- Skill: Ponytail Audit untuk menelusuri risiko dan bukti; Ponytail untuk mencari perbaikan paling kecil
  yang lengkap. Writing for Agents dipakai pada pemeliharaan catatan serah terima.
- Riwayat yang tersedia dibaca dari transkrip/laporan. Ini bukan akses otomatis ke semua chat Claude,
  Codex, Antigravity, atau Egg3l Manager. State terbaru tetap perlu dicocokkan dengan kode dan lingkungan.

## Bukti baru dalam penilaian ini

### Akses publik, tanpa login atau perubahan server

| Pemeriksaan | Hasil 18 November |
| --- | --- |
| `https://pena.egg3l.site/` | 200, koneksi TLS berhasil dengan verifikasi standar |
| `/login/` melalui HTTPS | 200; cookie sesi Secure, HttpOnly, SameSite=Lax; HSTS ada |
| `/dashboard/` tanpa sesi | 302 ke halaman login HTTPS |
| Login melalui HTTP | 301 ke HTTPS |
| `/daftar/` publik | 404 |
| `http://localhost/pena/daftar/` | 200 |

Kesimpulan: status sertifikat Pending di laporan lama sudah kedaluwarsa untuk akses yang diperiksa.
Perilaku pendaftaran membuktikan lingkungan publik dan lokal belum setara; commit/skema VPS belum
diperiksa langsung. Tidak ada uji login akun pemilik atau AI nyata pada server dalam penilaian ini.
Header CSP tidak ditemukan pada respons publik yang diperiksa; ini satu kontrol tambahan yang perlu
direncanakan, bukan bukti bahwa situs sedang dieksploitasi.

### Pemeriksaan lokal yang benar-benar dijalankan

Dengan `D:/XAMPP/php/php.exe .tools/composer.phar`:

| Perintah | Hasil | Batas bukti |
| --- | --- | --- |
| `check` | PASS, 67 file PHP | Sintaks, bukan seluruh perilaku aplikasi |
| `test` | PASS | SQL binding, autentikasi, DOCX; konversi PDF Word di Windows juga PASS |
| `test:schema` | PASS | Skema dan batasan database sementara |
| `test:flow` | PASS | Urutan, revisi, konfirmasi ulang, token, pembatalan pada database uji |
| `test:ai` | PASS | Transport palsu; tidak menguji provider nyata atau kualitas model |
| `test:workspace` | PASS | Pendaftaran, ruang pribadi/organisasi, migrasi dan isolasi dasar |
| `validate --strict` | PASS | Manifest Composer valid |

Tes database memakai database sementara `pena_test_*`, bukan data pengguna. Tes konversi menguji
file PDF, bukan pemeriksaan visual seluruh halaman atau unduhan melalui aplikasi.
Log lengkap tersimpan di `C:/Users/ASUS/OneDrive/Documents/ChatGPT/Egg3l/tmp/pena-readiness-20261118/`.

Dua probe tambahan yang berhasil direproduksi:

1. Nama organisasi dan pemeriksa diubah pada database uji. Setelah SQL seed diterapkan kembali,
   keduanya kembali ke nama contoh: `organization_name_preserved=false`, `signer_name_preserved=false`.
2. Isi tabel direvisi dari Kamera/jumlah 1 menjadi Proyektor/jumlah 9 dengan jumlah baris tetap satu.
   Formatter review menghasilkan `before="1 baris"`, `after="1 baris"`.

## Yang sudah kuat dan perlu dipertahankan

- Template wajib dosen tetap menjadi fondasi PHP native MVC; tampilan PENA mengikuti desain Tailwind
  yang sudah disetujui. Pembaruan teknologi tidak memerlukan penggantian framework.
- Prepared statement, transaksi, counter nomor terkunci, dan batasan unik database sudah dipakai.
- Versi yang diajukan dibekukan; persetujuan memiliki versi dan hash; token lama tidak ikut berlaku pada revisi.
- Konfirmasi ulang paralel dan pemeriksaan membership ruang kerja sudah memiliki tes.
- Data resmi dipisahkan dari paragraf AI; penanda, kutipan sumber, validasi dan koreksi pengguna tersedia.
- Peran terbaru adalah pembuat surat/sekretaris, pemeriksa melalui tautan, dan operator platform.
  Keputusan ruang pribadi/organisasi sudah diimplementasikan; jangan kembali menambahkan admin organisasi
  hanya karena usulan awal PRD menyebutnya.

## Wajib dibereskan sesuai target rilis

### 1. Samakan rilis lokal, skema, dan lingkungan demo

**Peran bagian ini:** memastikan fitur yang dilihat dosen/pengguna merupakan versi yang sedang dikembangkan.
**Masalah:** pendaftaran publik 404 sedangkan lokal 200; laporan VPS memakai snapshot sebelum perubahan ruang kerja.
**Perbaikan terkecil:** pilih satu build sebagai kandidat rilis, catat commit dan perubahan yang masuk,
cadangkan database VPS, lakukan migrasi dengan kredensial migrasi terpisah, lalu smoke test di URL tujuan.
**Jika ditunda:** demo bisa menampilkan fitur lama atau kode baru gagal pada skema lama.

Target: wajib sebelum demo publik. Login, daftar, pindah ruang, buat surat dan review harus diuji pada
lingkungan yang sama. Izin DDL untuk migrasi tidak boleh menjadi izin permanen akun runtime.
Perhatikan temuan seed nomor 5 sebelum menjalankan `db:install` di server.

### 2. Sambungkan unduhan surat; buktikan PDF pada Linux

**Peran bagian ini:** mengubah surat yang disetujui menjadi hasil yang dapat dipakai.
**Masalah:** `app/view/surat/detail.php` masih menonaktifkan tombol Unduh Word/PDF;
controller surat belum menyediakan jalur ekspor. Converter yang ada memakai Word/COM Windows.
**Perbaikan terkecil:** hubungkan ekspor ke snapshot versi yang disetujui, simpan hasil/status pada
`letter_exports`, periksa akses unduhan dan tangani kegagalan PDF tanpa menghapus persetujuan.
Untuk VPS Ubuntu, evaluasi satu converter Linux, misalnya LibreOffice headless melalui interface yang ada.
**Jika ditunda:** sistem berakhir di status Disetujui tanpa memberikan produk utamanya.

Target: wajib untuk demo yang diklaim lengkap dan untuk produksi. Bukti selesai adalah klik unduh dari
aplikasi untuk satu surat nyata, PDF terbuka dan diperiksa visual: kop, logo yang diperlukan, nama,
tabel panjang, nomor, tanggal, serta blok tanda tangan. Unduhan workspace lain harus ditolak.
Satu jalur DOCX → PDF mengurangi perbedaan, tetapi tidak menjamin tata letak identik di semua mesin.
Lihat [parameter resmi LibreOffice](https://help.libreoffice.org/latest/en-US/text/shared/guide/start_parameters.html).

### 3. Lindungi pendaftaran dan akses akun sebelum dibuka umum

**Peran bagian ini:** memastikan orang dapat masuk, menjaga akun, dan memulihkan akses.
**Masalah:** pendaftaran baru langsung membuat sesi; alur login belum memiliki pembatas percobaan;
belum ada alur verifikasi email, ganti/lupa password dan penghentian sesi terkait perubahan password.
**Perbaikan terkecil:** batas login/daftar per akun dan IP, verifikasi kepemilikan email, serta
ganti/reset password dengan token sekali pakai dan kedaluwarsa. Pisahkan kredensial demo dari akun nyata.
**Jika ditunda:** pendaftaran mudah disalahgunakan, akun dapat ditebak tanpa batas aplikasi, dan pengguna
yang kehilangan password bergantung pada edit database operator.

Target: wajib sebelum pendaftaran publik dengan data nyata. Pilot terkontrol boleh menjadi tahap antara,
tetapi perlu dipilih pengguna; itu tidak mengganti keputusan produk bahwa pendaftaran akhirnya bebas.
Dasar: [OWASP Authentication](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
dan [pemulihan password](https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html).

### 4. Batasi pemakaian dan biaya AI

**Peran bagian ini:** memberikan bantuan AI tanpa menghabiskan kapasitas layanan bersama.
**Masalah:** `AiClient::chat()` belum mengirim batas keluaran; jalur controller belum memiliki kuota;
panggilan sinkron menahan worker selama model menjawab. Timeout membatasi durasi, bukan total penggunaan.
**Perbaikan terkecil:** batas panggilan per akun/ruang, batas keluaran yang didukung provider,
batas pekerjaan aktif, timeout dan pesan saat layanan sibuk. Catat pemakaian dan sediakan mode manual.
**Jika ditunda:** beberapa permintaan panjang atau satu akun yang mengulang dapat mengganggu review,
login, dan biaya operator.

Target: wajib sebelum akses umum. Jumlah worker/kapasitas VPS dalam laporan belum merupakan load test.
Untuk pilot kecil mulai dengan pembatas yang terukur; tambahkan antrean pekerjaan berbasis infrastruktur
yang ada bila hasil pengujian membutuhkan. Streaming sendiri tidak otomatis membebaskan worker PHP.
Dasar: [OWASP Unbounded Consumption](https://genai.owasp.org/llmrisk/llm102025-unbounded-consumption/).

### 5. Pisahkan migrasi produksi dari seed demo yang menimpa data

**Peran bagian ini:** memperbarui database tanpa mengembalikan data pengguna ke contoh bawaan.
**Masalah:** `scripts/install-db.php:31` menjalankan seluruh `pena_schema.sql` setelah migrasi.
Seed organisasi/kop/pemeriksa memakai `ON DUPLICATE KEY UPDATE name=VALUES(name)`
(`database/pena_schema.sql:364`, 388, 395). Probe membuktikan nama yang diedit tertimpa.
**Perbaikan terkecil:** pisahkan seed demo menjadi perintah khusus lingkungan uji/lokal. Migrasi
produksi memperbarui struktur dan data sistem yang disengaja tanpa menyentuh identitas pengguna.
**Jika ditunda:** perintah yang tampak aman dijalankan ulang dapat merusak identitas organisasi atau pemeriksa.

Target: sebelum deployment/migrasi berikutnya. Tes idempoten saat ini hanya membuktikan impor ulang
tidak menduplikasi baris, belum membuktikan data pengguna tetap utuh. Tambahkan tes yang menjaga nilai
yang sudah diedit, dan pisahkan aktivasi akun contoh dari instalasi produksi.

### 6. Selesaikan kontrak dokumen yang benar-benar diperiksa dan disetujui

**Peran bagian ini:** memastikan keputusan pemeriksa merujuk pada isi dan tampilan yang dipahami.
**Masalah:** halaman review mengganti tanggal menjadi literal `(tanggal surat)`
(`app/controller/review.php:32`), sedangkan pratinjau surat memakai `submitted_at`
(`app/Service/LetterQuery.php:119`). Diff tabel hanya menampilkan jumlah baris
(`app/controller/review.php:95`), sehingga perubahan barang/nominal tidak tampak pada ringkasan.
**Perbaikan terkecil:** pilih kebijakan tanggal surat bersama pengguna, simpan dalam versi,
tampilkan konsisten di review dan ekspor; tampilkan perbedaan isi sel/baris tabel yang berubah.
**Jika ditunda:** pengguna menyetujui tanggal yang tidak ditampilkan atau melewatkan perubahan penting
walaupun hash versi secara teknis benar.

Target: sebelum demo revisi yang diklaim transparan dan sebelum penggunaan surat nyata. Persetujuan
hash data belum membuktikan pemeriksa sudah melihat PDF final; kebijakan tampilan final perlu diselesaikan.

### 7. Jelaskan dan batasi bukti identitas serta tanda tangan

**Peran bagian ini:** membedakan siapa yang menyetujui dari siapa yang sekadar disebut di surat.
**Masalah:** pemegang tautan adalah satu-satunya bukti untuk pemeriksa; nama/jabatan direktori dapat
diketik pengguna. Pada jalur pribadi tanpa pemeriksa, submit langsung memberi status approved,
tetapi blok nama/jabatan bukan bukti pembubuhan gambar atau tanda tangan tersertifikasi.
**Perbaikan terkecil:** gunakan istilah persetujuan lewat tautan dan penyelesaian surat pribadi secara
jujur; konfirmasi proses ini diterima advisor/fakultas. Bila harus membuktikan pejabat tertentu,
tambahkan verifikasi kanal yang sesuai dan mekanisme memastikan kewenangannya.
**Jika ditunda:** status Disetujui atau QR dapat disalahartikan sebagai bukti kewenangan institusi.

Target: wajib untuk klaim presentasi dan batas penggunaan produksi. Kolom `verified_at` belum membentuk
proses verifikasi organisasi. Halaman verifikasi surat sebaiknya membuktikan versi/status arsip, bukan
otomatis mengesahkan organisasi atau seluruh nama pejabat. Tampilkan informasi publik seminimal perlu.

### 8. Buktikan pemulihan dan proses rilis PENA

**Peran bagian ini:** menjaga surat tetap tersedia setelah kesalahan deployment, kerusakan atau kehilangan server.
**Masalah:** laporan Egg3l Manager tidak mencatat backup database PENA dan uji restore penuh;
backup website Egg3l di Google Drive bukan backup PENA. Tidak ada bukti baru load test atau rollback rilis.
**Perbaikan terkecil:** backup database dan aset terkait, simpan rahasia secara terpisah dengan aman,
uji restore ke lingkungan terisolasi, lalu dokumentasikan rilis/migrasi/rollback dan pemantauan dasar.
**Jika ditunda:** ada risiko kehilangan data atau waktu pemulihan yang tidak diketahui.

Target: sebelum menyimpan surat nyata yang hanya tersedia di PENA. Pengguna sebelumnya memilih backup
manual; penjadwalan baru perlu keputusan tersendiri. Pilihan manual tetap membutuhkan prosedur,
penanggung jawab dan hasil restore. Menurunkan versi kode tidak otomatis membalik perubahan skema.

## Perlu dibereskan berikutnya

### 9. Isolasi proses AI per dokumen dan ruang kerja

`app/controller/surat.php` memakai satu `$_SESSION['pena_ai']` untuk seluruh proses pengguna.
Kasus konkret: tab B memulai prompt baru dan menimpa state yang sedang ditinjau tab A; berpindah ruang
organisasi yang memakai kerangka umum sama juga berisiko membawa hasil ke konteks yang salah.
Gunakan ID proses/draf beserta workspace dan pemiliknya, lalu validasi pada setiap langkah/simpan.
Hindari merancang antrean besar hanya untuk menyelesaikan masalah identitas state ini.

### 10. Minimalkan data AI dan tentukan masa simpannya

`LetterAiService::extract()` mengirim prompt dan direktori nama/jabatan; `log()` menyimpan input/output.
Retensi/pembersihan dan pilihan penghapusan belum ditemukan pada jalur yang ditinjau. Beri pemberitahuan
penyedia data, kirim bagian direktori yang diperlukan, tentukan retensi, dan samarkan rahasia/error provider.
Token review dalam URL juga perlu perlakuan khusus pada access log/telemetri; menyimpan hash di database
tidak otomatis menghapus token dari semua log. Konfigurasi log VPS belum diperiksa langsung di sini.

### 11. Cocokkan pratinjau dengan edit dan buat pemulihan kesalahan jelas

Pada `generate_hasil.php`, pratinjau berasal dari render server sebelum pengguna mengedit; JavaScript
yang ditinjau menangani tulis ulang, belum menyinkronkan semua isian dengan pratinjau kertas.
Tampilkan pembaruan atau penanda bahwa pratinjau perlu diperbarui. Uji pengguna di HP: tahu langkah
selanjutnya, tahu kapan AI masih bekerja, bisa memperbaiki data, dan tetap bisa menyelesaikan manual.
Saat konfirmasi sudah tercatat atau tautan kedaluwarsa, jelaskan status dan jalan lanjutannya.

### 12. Uji beban dan kondisi bersamaan sesuai pemakaian yang diharapkan

Suite lokal menguji banyak invariant, tetapi hasil tersebut bukan bukti kinerja multiworker.
Uji dua pembuatan nomor serentak, submit ganda, persetujuan bersamaan dengan revisi/pembatalan,
serta beberapa permintaan AI sementara pemeriksa membuka surat. Ukur waktu dan kegagalan pada VPS;
tetapkan batas pilot dari hasil itu. Pantau kegagalan AI/ekspor, latensi, disk, RAM dan backup terakhir.

### 13. Ganti klaim manfaat dengan evaluasi yang dapat diperiksa

Angka 10 menit berasal dari pengalaman sekretaris fakultas; itu bukan baseline yang otomatis berlaku
untuk semua surat pribadi/ormawa. Ukur waktu kerja aktif menyusun/merevisi dan waktu menunggu persetujuan
secara terpisah. AI mungkin mempercepat redaksi; sistem tidak mengendalikan kapan advisor merespons.
Regex fakta dan penanda tidak membuktikan nol halusinasi atau tidak ada janji/klaim tambahan.
Kutipan yang cocok belum menjamin seluruh nilai tabel benar; `valueWritten()` menerima tabel tanpa
memeriksa setiap nilai sel terhadap prompt. Pemeriksaan manusia dan pengujian kasus tetap diperlukan.

## Paket minimal untuk presentasi yang jujur

### Kriteria lolos demo

1. Satu lingkungan kandidat rilis dipilih dan seluruh akun/data demo disiapkan dengan identitas fiktif.
2. Alur nyata: prompt → pemeriksaan isian → edit → draf → ajukan → review → revisi → konfirmasi ulang
   → disetujui → unduh Word/PDF. Jangan menyebut alur lengkap selesai jika unduh belum tersambung.
3. Satu surat pribadi menunjukkan perbedaan aturan tanpa kop/nomor; contoh organisasi tetap menjadi alur utama.
4. Jalur gagal diperagakan/dijelaskan: prompt kurang jelas, API gagal, token lama, atau PDF gagal.
5. Bekukan build setelah latihan. Gunakan laptop lokal sebagai cadangan yang disiapkan; uji akses HP pada
   jaringan yang dipakai, jangan menganggap localhost laptop bisa langsung dibuka dari HP.
6. Siapkan video dari alur yang benar-benar berjalan. Jika dipakai karena layanan gagal, jelaskan sebagai
   rekaman/cadangan; jangan menampilkan keluaran tersimpan sebagai panggilan model langsung.

### Bukti akademik

- Periksa brief/rubrik asli mata kuliah AI. Checklist DevSecOps untuk Filkom Day pada awal chat Egg3l Manager
  adalah tugas lain; jangan menganggapnya rubrik PENA. Brief AI asli belum diperiksa ulang dalam penilaian ini.
- Selaraskan judul/tujuan dengan pengguna terbaru: pribadi dan organisasi, dengan fokus demo ormawa.
  Konfirmasi perubahan target dari sekretaris fakultas kepada dosen bila belum dilakukan.
- Siapkan RCA/fishbone berbasis wawancara dan contoh; salin-tempel sebagai penyebab tetap hipotesis sampai
  dikonfirmasi. Jangan mengklaim penelitian UNKLAB memiliki gap tertentu tanpa memeriksa paper aslinya.
- Siapkan diagram arsitektur, model data, status surat dan hubungan revisi dengan persetujuan versi lama.
- Jelaskan kode yang ada: `recommendTemplate`, `extract`, `writeParagraphs`, `rewrite`, validator,
  counter nomor dan snapshot versi. Saat ini pemilihan kerangka berbasis LLM; jangan menyebut Naive Bayes,
  embedding, RAG arsip atau training dataset sebagai implementasi jika belum tersedia.
- Lakukan evaluasi kecil berlabel, misalnya 30 prompt lintas kerangka yang didukung: jelas, kurang lengkap,
  tanggal relatif, nama/jabatan salah, serta instruksi yang mencoba mengubah aturan. Bandingkan kerangka
  dan setiap isian dengan label manusia; catat nilai salah, ditolak, perlu dicek, waktu, dan edit manual.
  Laporkan jumlah sampel serta keterbatasan, bukan generalisasi akurasi produksi.
- Tambahkan pembanding sederhana: formulir/template manual untuk kualitas dan waktu aktif, atau
  pencocokan kata kunci untuk pemilihan kerangka. Jangan membangun model baru hanya untuk menambah nama algoritma.
- Uji tugas dengan beberapa pengguna sasaran dan minimal satu calon pemeriksa jika tersedia. Catat sukses
  tugas, salah pemahaman, dan kebutuhan revisi; sampel kecil adalah evaluasi awal, bukan bukti seluruh kampus.

### Alur presentasi yang disarankan

Masalah nyata dan batas klaim → keputusan manusia/AI → demo satu surat organisasi sampai unduh →
contoh revisi/konfirmasi ulang → penjelasan algoritma/arsitektur → hasil uji dan batas → rencana produksi.
Durasi ditentukan dari latihan dan slot dosen; jangan mengasumsikan semua langkah selesai dalam lima menit.

## Urutan menjelang 21 November

| Urutan | Hasil yang harus terlihat | Batas keputusan |
| --- | --- | --- |
| 1. Kandidat demo | Pilih lingkungan, catat build/skema; perbaiki pemisahan seed sebelum migrasi berikutnya | Perubahan rilis tetap mengikuti permintaan/persetujuan yang berlaku |
| 2. Hasil surat | Unduhan aktual dari snapshot, tanggal konsisten, PDF pada lingkungan demo, diff tabel jelas | Prioritas teratas untuk proyek generator surat |
| 3. Keandalan demo | Jalur AI nyata dan manual, timeout, review HP, latihan dan cadangan | Jika server belum siap, demo lokal harus dinyatakan demikian |
| 4. Bukti presentasi | Evaluasi berlabel, pembanding, diagram dan klaim yang sesuai kode | Bekukan scope fitur baru yang tidak perlu untuk membuktikan alur utama |
| 5. Rilis data nyata | Keamanan akun/AI, prosedur identitas, privasi, restore, kapasitas dan operasi | Tenggat presentasi tidak menghapus syarat produksi |

Ini urutan rekomendasi, bukan janji seluruh produksi selesai dalam tiga hari. BYOK, OCR, kolaborasi
real-time, dan perluasan jenis surat tetap bisa masuk roadmap; semuanya bukan prasyarat universal
untuk demo alur utama. Rancangan multi-organisasi dan keputusan tanpa admin organisasi dipertahankan.

## Batas pemeriksaan dan putusan

**Putusan:** siapkan demo yang lengkap sampai dokumen keluar, lalu buka pilot terbatas setelah gerbang
akun, biaya AI, identitas, dan pemulihan terpenuhi. Produksi umum belum dapat dinyatakan siap.

Belum diperiksa: SSH/state runtime VPS terbaru, login pemilik publik, panggilan AI nyata saat ini,
seluruh halaman melalui browser, keamanan menyeluruh/pentest, hasil visual PDF semua kerangka,
load test, kebijakan provider, brief dosen asli, dan penerimaan proses oleh fakultas.
Tidak ada kode aplikasi, konfigurasi server, atau jadwal backup yang diubah dalam analisis ini.
