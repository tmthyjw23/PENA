# Analisis PENA menuju produk

Ditulis 18 Nov 2026 dari kode di `main` (630e02b) dan uji nyata sebelumnya. Sudut pandang: Senior Software
Engineer, Senior AI Engineer, HCI, HAII (Human-AI Interaction), dan analis/perancang sistem (SAD).
Kerangka dari skill Agent-Assets: `security-and-hardening` (threat model, OWASP LLM Top 10, rate limit, secrets,
SSRF), `code-review-and-quality` (lima sumbu), `SystemDesign/security-and-auth`, `SystemDesign/data-and-ai-pipelines`,
`doubt-driven-development` (setiap klaim dicek ke kode), `ui-ux-pro-max`/`antislop` untuk sisi antarmuka.
Setiap temuan di bawah sudah dicek di kode, bukan dugaan.

Prioritas: **P0** menghalangi dipakai orang lain, **P1** wajib sebelum produk, **P2** penting, **P3** polesan.

## 1. Peran (diputuskan pemilik 18 Nov 2026, menggantikan usulan admin/sekretaris/anggota)

Usulan awal memisahkan admin organisasi dari sekretaris. Pemilik menolaknya: di dunia nyata tidak ada yang mau
menjadi "admin sistem" karena pekerjaannya terlalu banyak. Keputusannya: **tidak ada peran admin**.

| Peran | Siapa | Pekerjaan |
|---|---|---|
| **Sekretaris** (pengguna) | Siapa pun yang membuat surat: sekretaris organisasi atau perorangan | Membuat, mengajukan, merevisi surat dan mengirim tautan. Pengaturan kecil dikerjakan sambil jalan, bukan di halaman admin. |
| **Pemeriksa** | Ketua, advisor, dosen, dekan | Tanpa akun, lewat tautan. |
| **Operator platform** | Penyedia layanan PENA | Kerangka bawaan, AI sistem dan kuota, penanganan penyalahgunaan. Tidak terlibat urusan harian. |

Pekerjaan admin dilebur ke alur:

- **Kop** diisi saat surat pertama ruang organisasi, lalu dipakai otomatis.
- **Pemeriksa** ditambahkan langsung di alur persetujuan ("Tambah pemeriksa baru": nama, jabatan); direktori
  terbentuk dari pemakaian.
- **Kerangka** disediakan operator.
- **Pengaturan AI/BYOK** dipegang pembuat ruang kerja (penanda pemilik, `owner`, bisa dialihkan), bukan peran.

**Ruang kerja.** Setiap akun punya ruang pribadi (dibuat saat daftar) dan boleh membuat atau diundang ke ruang
organisasi. Tabel `organizations` menjadi ruang kerja dengan `kind` = `personal` | `organization`.

| | Pribadi | Organisasi |
|---|---|---|
| Identitas | Profil (nama, NIM, prodi) mengisi surat otomatis | Kop organisasi |
| Nomor surat | Tidak ada | Berseri, unik |
| Persetujuan | Opsional; tanpa pemeriksa = langsung jadi, ditandatangani sendiri | Bertahap |
| Kerangka | Izin tidak hadir, dispensasi (lanjutan: permohonan ke dosen, pernyataan) | Peminjaman, permohonan, dan lainnya |

**Pendaftaran:** bebas, satu formulir (nama, email, password). Setelah masuk, satu pertanyaan "Surat ini untuk
siapa?" menentukan ruang kerja pertama. Konsekuensinya wajib disiapkan sebelum dipakai umum: verifikasi email,
batas percobaan login, kuota AI per akun, dan halaman verifikasi surat publik (penangkal pemalsuan kop).

## 2. Temuan per fungsi dan flow

### Organisasi dan akun (SAD, keamanan)

- **P0 Tidak ada halaman pengaturan organisasi.** Kop, alamat, direktori penanda tangan, dan kerangka hanya bisa
  diubah lewat SQL. Akibatnya setiap surat cetak memuat teks contoh "Alamat kampus (ubah di pengaturan kop)".
  Organisasi sungguhan tidak bisa memakai PENA tanpa Anda mengedit database.
- **P0 Tidak ada pengelolaan akun.** Tidak ada daftar akun, undangan, ganti password, lupa password, atau
  nonaktifkan akun. Akun contoh dengan password diketahui umum ada di seed.
- **P1 Login tanpa pembatas percobaan.** Password bisa ditebak tanpa batas. Pembatas bawaan template dulu memakai
  tabel yang tidak ada dan tidak dipanggil. Perlu batas sekitar 10 percobaan per 15 menit per akun dan per IP,
  disimpan di database.
- **P1 Satu akun hanya bisa satu organisasi.** `managedOrganization` mengambil organisasi pertama (`LIMIT 1`).
  Sekretaris yang juga pengurus organisasi lain tidak bisa berpindah.
- **P2 Belum ada Content-Security-Policy.** Script inline dipakai di beberapa halaman; CSP dengan nonce
  menutup sisa risiko XSS.
- **P3 Filter pola permintaan (WAF regex) bisa salah blokir** teks surat yang kebetulan mirip SQL atau berisi tanda
  backtick. Pertahanan utama sudah prepared statement dan escaping; pertimbangkan melonggarkan pola untuk isi surat.

### Surat dan nomor (Software Engineer, SAD)

- **P1 Tanggal surat = tanggal diajukan,** bukan tanggal disetujui atau tanggal yang dipilih sekretaris
  (`LetterQuery::preview` memakai `submitted_at`). Surat yang disetujui seminggu kemudian tetap bertanggal hari
  diajukan, dan revisi mengubah tanggalnya. Biasanya organisasi ingin tanggal ditentukan (atau tanggal disetujui).
- **P1 Draf tidak bisa mengubah alur persetujuan, kop, atau jenis surat** setelah disimpan. Ubah draf hanya
  isian dan paragraf; menambah tahap atau mengganti kop harus membuat surat baru. Ganti pemeriksa per orang ada,
  menambah atau menghapus tahap tidak.
- **P1 Ekspor Word/PDF belum tersambung** ke surat disetujui (tabel `letter_exports` tidak pernah ditulis).
  Padahal surat disetujui adalah hasil akhir produk. Konversi PDF lewat Microsoft Word juga hanya jalan di
  Windows dengan Office; server Linux butuh LibreOffice atau layanan konversi lain.
- **P2 Label "Mengetahui," dan susunan tanda tangan** belum ada; semua penanda tangan dirender sejajar.
- **P2 Pemeriksa opsional didukung skema tetapi tidak bisa dipilih** (`is_required` selalu 1).
- **P2 Komentar tidak bisa ditandai selesai** (`resolved_at` tidak dipakai), sehingga daftar komentar lama terus
  menumpuk di surat yang direvisi berkali-kali.
- **P3 Daftar surat memuat semua baris tanpa halaman.** Aman untuk puluhan surat, lambat untuk ribuan.

### Persetujuan lewat tautan (keamanan, HCI)

- **P1 Sekretaris harus menyalin tautan manual setiap tahap,** dan tahap berikutnya hanya terlihat bila ia membuka
  PENA. Kolom `signers.email` ada tetapi tidak dipakai. Minimal: tombol "Kirim lewat WhatsApp" (tautan `wa.me` dengan
  pesan terisi) dan, untuk produk, notifikasi email ke pemeriksa dan sekretaris saat tahap berubah.
- **P2 Identitas pemeriksa hanya "pemegang tautan".** Jujur ditulis di layar, tetapi untuk produk perlu opsi
  verifikasi ringan: kode sekali pakai ke email atau WhatsApp pemeriksa sebelum tombol Setujui aktif.
- **P2 Setiap kali halaman review dibuka, riwayat surat mencatat "Dibuka pemeriksa"** (permintaan GET menulis
  data). Muat ulang lima kali menghasilkan lima baris riwayat. Catat sekali per sesi atau beri jeda.
- **P3 Pemeriksa yang sudah memutuskan tidak bisa lagi membuka atau berkomentar** (tautan langsung mati). Wajar
  untuk keamanan, tetapi beri halaman "Anda sudah menyetujui pada ..." daripada "tautan tidak berlaku".

### AI (Senior AI Engineer, HAII)

Yang sudah benar: kerangka, isian, dan pemeriksa divalidasi server; kutipan dicek ke kalimat asli; nilai yang tidak
tertulis ditandai "Perlu dicek"; paragraf yang mengetik fakta ditolak; nomor surat tidak dari AI; setiap panggilan
tercatat. Ini sudah sesuai OWASP LLM05 (keluaran model diperlakukan sebagai input tak tepercaya).

- **P1 Belum ada kuota atau pembatas pemakaian AI.** Satu akun bisa memanggil model tanpa batas; di mode AI sistem
  ini berarti biaya Anda tanpa batas (OWASP LLM10). Perlu batas per pengguna per jam dan per organisasi per bulan.
- **P1 `max_tokens` tidak dikirim ke model.** Jawaban panjang tidak dibatasi biaya maupun waktunya.
- **P1 Data dikirim ke penyedia AI tanpa pemberitahuan di layar:** kalimat sekretaris dan seluruh direktori
  penanda tangan (nama dan jabatan) ikut dalam prompt. Untuk produk, tampilkan "Kalimat Anda dikirim ke [penyedia]"
  dan biarkan pemilik ruang kerja memilih penyedia; kirim hanya jabatan, bukan nama, bila cukup.
- **P1 Penyusunan berjalan sinkron sekitar 30 detik per langkah,** menahan satu proses PHP selama itu. Dengan
  beberapa pengguna bersamaan server cepat habis. Untuk produk: antrean pekerjaan (job) dan halaman yang menanyakan
  status, atau streaming.
- **P2 Hasil AI disimpan di sesi, satu per pengguna.** Dua tab "Buat surat" saling menimpa.
- **P2 Log AI menyimpan kalimat dan keluaran selamanya.** Tentukan masa simpan (misalnya 90 hari) dan pilihan
  organisasi untuk tidak menyimpan isi, hanya metrik.
- **P2 (HAII) Pratinjau di Periksa hasil tidak ikut berubah** saat isian diedit; pengguna bisa mengira hasil
  akhirnya belum berubah. Perbarui pratinjau langsung, atau sembunyikan sampai disimpan.
- **P3 (HAII) Tidak ada cara mengembalikan nilai asli dari AI** setelah diedit, dan pemeriksa tidak diberi tahu
  bahwa draf disusun dengan bantuan AI. Yang kedua pilihan etika; minimal tercatat di riwayat (sudah).

### Halaman dan alur (HCI)

- **P2 Arti "Perlu tindakan" berbeda:** Beranda memasukkan draf, tab Daftar surat tidak.
- **P2 Tidak ada halaman profil** (nama, email, ganti password) dan tidak ada halaman bantuan untuk sekretaris baru.
- **P2 Landing menyebut "PDF dibuat lewat Microsoft Word di komputer server"** dan "Layanan AI belum tersambung".
  Itu detail teknis dan sudah tidak akurat; landing produk sebaiknya bicara manfaat dan batas yang relevan bagi
  pengguna.
- **P3 Halaman review, masuk, dan landing belum punya tautan "Lompat ke konten"** (dari audit Fase 2).

### Operasional produk (SAD)

- **P1 Belum ada migrasi skema berversi.** `pena_schema.sql` hanya `CREATE IF NOT EXISTS`; perubahan kolom
  berikutnya tidak akan sampai ke database yang sudah dipakai. Perlu folder migrasi bernomor dan tabel versi.
- **P1 Belum ada cadangan otomatis, log terpusat, dan pemantauan galat.**
- **P2 Zona waktu dikunci `Asia/Makassar`** di `init.php`; organisasi di WIB atau WIT mendapat jam yang salah.
  Simpan zona waktu per organisasi.

## 3. Rancangan AI: disediakan sistem atau bawa kunci sendiri (BYOK), tetap aman

Setiap organisasi memilih satu mode di halaman Pengaturan AI (hanya pemilik ruang kerja):

1. **AI dari PENA (sistem)**: memakai 9router milik operator. Ada kuota per organisasi per bulan dan batas per
   pengguna per jam. Alamat 9router diatur operator di `.env`, tidak pernah oleh pengguna.
2. **Kunci sendiri (BYOK)**: pemilik ruang kerja memasukkan penyedia (daftar yang diizinkan, misalnya OpenAI, OpenRouter, Gemini
   lewat API OpenAI-compatible), model, dan API key. Tidak memakai kuota sistem, tetap ada batas per jam untuk
   melindungi server.
3. **Mati**: hanya isi manual.

Aturan keamanan yang wajib (diambil dari checklist `security-and-hardening` dan `security-and-auth`):

- **Kunci dienkripsi saat disimpan** dengan libsodium (`sodium_crypto_secretbox`), kunci induk di `.env`
  (`APP_KEY`, 32 byte), tidak pernah di database atau Git. Ciphertext diikat ke `organization_id` agar tidak bisa
  dipindah ke organisasi lain. Butuh mengaktifkan `extension=sodium` di PHP XAMPP (saat ini belum aktif).
- **Kunci tidak pernah dikirim balik ke browser.** Layar hanya menampilkan "sk-...a1b2" (4 karakter terakhir) dan
  tanggal diubah. Mengganti kunci berarti mengetik ulang; menghapus kunci tersedia.
- **Tidak pernah tercatat di log:** tidak di `ai_generations`, tidak di log galat, tidak di pesan galat ke pengguna.
- **Alamat penyedia BYOK hanya dari daftar yang diizinkan,** HTTPS, tanpa redirect. Bila nanti alamat bebas
  diizinkan, tolak IP privat, loopback, dan 169.254.x.x (SSRF) setelah resolusi DNS.
- **Tombol "Uji koneksi"** sebelum disimpan (satu panggilan kecil); kunci yang gagal tidak disimpan.
- **Tidak ada cadangan diam-diam:** bila kunci organisasi gagal, PENA tidak beralih ke AI sistem (biaya dan
  persetujuan data berbeda) kecuali pemilik mengaktifkannya.
- **Setiap perubahan pengaturan AI masuk audit** (siapa, kapan, mode apa), tanpa isi kunci.
- **`ai_generations` mencatat sumber kunci** (`system` atau `byok`) untuk penagihan dan dukungan.
- **Batas biaya:** `max_tokens` per langkah, panjang permintaan maksimal (sudah 1.000 karakter), kuota sistem.

Skema tambahan (Fase 3, dengan migrasi berversi):

```sql
CREATE TABLE ai_settings (
  organization_id INT PRIMARY KEY,
  mode ENUM('system','byok','off') NOT NULL DEFAULT 'system',
  provider VARCHAR(30) NULL,          -- dari daftar yang diizinkan
  model VARCHAR(80) NULL,
  key_ciphertext VARBINARY(512) NULL, -- nonce + secretbox, diikat ke organization_id
  key_last4 CHAR(4) NULL,
  monthly_call_quota INT NULL,        -- hanya mode system, diatur operator
  updated_by INT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
);
-- ai_generations: tambah key_source ENUM('system','byok')
-- ai_usage_counters (organization_id, period, calls, tokens) untuk kuota
```

## 4. Urutan pengerjaan (diperbarui 18 Nov 2026)

1. **Ruang kerja dan pendaftaran (P0):** migrasi berversi, `kind` pribadi/organisasi, pemilik, pendaftaran satu
   formulir, pilih/pindah ruang kerja, kop saat surat pertama, "Tambah pemeriksa baru", kerangka pribadi,
   surat pribadi tanpa nomor dan boleh tanpa pemeriksa.
2. **Keamanan akun (P1):** batas percobaan login, verifikasi email, ganti/lupa password.
3. **Verifikasi surat publik (P1):** kode/QR per surat disetujui yang menunjukkan isi asli dan penyetujunya.
4. **AI produk (P1):** `ai_settings` + BYOK terenkripsi + kuota + `max_tokens` + pemberitahuan data.
5. **Hasil akhir surat (P1):** tanggal surat yang benar, ekspor Word/PDF, label "Mengetahui,".
6. **Alur tautan (P1/P2):** kirim lewat WhatsApp, notifikasi email, ubah alur di draf, undang anggota ruang.
7. **Skala (P1/P2):** penyusunan AI asinkron, halaman daftar, zona waktu, cadangan dan pemantauan.
