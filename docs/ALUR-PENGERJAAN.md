# Alur pengerjaan PENA

Ditetapkan pemilik proyek pada 17 Nov 2026. Tenggat: 21 Nov 2026.
Urutan ini mengikat: fase berikutnya dimulai setelah fase sebelumnya disetujui pemilik.

## Fase 0: Fondasi (sudah ada, dipertahankan)

Skema database, layanan surat, alur persetujuan, nomor surat, DOCX/PDF via Word, dan tesnya
sudah jalan (`composer test`, `test:schema`, `test:flow`). Fase berikut tidak mengulang ini;
perubahan hanya bila desain Figma membutuhkannya.

## Fase 1: Desain di Figma (dikerjakan Codex, diaudit Claude)

Figma menjadi patokan UI/UX untuk semua fase berikutnya.

- Dasar: design system (warna, tipografi, jarak, radius, komponen) lalu frame desktop dan mobile.
- Halaman: Landing, Masuk, Beranda, Daftar surat, Buat/ubah surat, Detail surat,
  Review lewat tautan, Selesai/tautan kedaluwarsa, Error.
- Wajib bebas sisa template dosen (menu, teks, ikon, contoh CRUD mahasiswa) di alur PENA.
- Skill anti AI slop: antislop (mode during), design-taste-frontend, impeccable, ui-ux-pro-max.
  Arah awal dari [DESIGN.md](../DESIGN.md); boleh direvisi di fase ini.
- Selesai bila: pemilik menyetujui frame, dan audit impeccable + antislop atas Figma tidak
  menyisakan temuan prioritas tinggi.

## Fase 2: UI statis dari Figma (Tailwind)

- Migrasi tampilan dari Bootstrap ke Tailwind CSS, mengikuti Figma. Router/MVC template tetap.
  Catatan: konfirmasi ke dosen bahwa mengganti Bootstrap masih dianggap memakai template.
- Halaman disambungkan ke fitur yang sudah ada (buat, ajukan, review, revisi).
- Selesai bila: tampilan cocok dengan Figma di 375 dan 1440 px, tidak ada error console,
  audit impeccable P1/P2 bersih, Delivery Gate antislop PASS.

## Fase 3: Database (bila perlu)

Penyesuaian skema yang muncul dari desain atau fitur baru saja. Setiap perubahan disertai tes.

## Fase 4: AI gateway (9router)

- Pelajari dokumentasi resmi 9router dulu, baru integrasi.
- Kunci API hanya di `.env`. Setiap panggilan dicatat di `ai_generations`.
- AI hanya menulis paragraf dengan penanda `{{isian}}`; fakta tetap dari isian data.

## Fase 5: Fitur

Ekspor Word/PDF untuk surat disetujui, bantuan AI di form surat, dan fitur lain yang disepakati.

## Status

| Fase | Status |
|---|---|
| 0 Fondasi | Selesai |
| 1 Figma | Disetujui pemilik 17 Nov 2026 setelah perbaikan dari [ANALISIS-PIPELINE-VS-FIGMA.md](ANALISIS-PIPELINE-VS-FIGMA.md). Ringkasan: [FIGMA-HANDOFF.md](FIGMA-HANDOFF.md). |
| 2 UI statis | Build selesai 17 Nov 2026: semua halaman PENA di Tailwind v4, dicek di 375 dan 1440 px, tes lulus. Audit impeccable 15/20 dan Delivery Gate antislop PASS ([AUDIT-FASE-2.md](AUDIT-FASE-2.md)). P1/P2 bersih (huruf kini di proyek). DESIGN.md ditulis ulang dari hasil build. Sisa kecil: label "Mengetahui," di kertas (butuh aturan tahap), tautan lompat ke konten di halaman tanpa rail. Halaman contoh template tetap Bootstrap. |
| 3 Database | Tidak perlu perubahan: `ai_generations` dan `letters.source_prompt` sudah ada sejak Fase 0. |
| 4 AI 9router | Kode selesai 17 Nov 2026 (AiClient, LetterAiService, halaman Buat surat dan Periksa hasil yang tersambung, `composer test:ai` lulus). Uji nyata lewat 9router (WSL): pipeline lengkap jalan untuk PMJ dan PMH dengan `flash-medium` (26 sampai 36 detik); `flash-high` terlalu lambat. Ditemukan dan diperbaiki: batas 30 detik PHP memutus permintaan tanpa log; nilai yang tidak tertulis di kalimat kini selalu "Perlu dicek"; nama tahap dari jabatan. Uji browser dengan `AI_MODEL=ag/gemini-3.8-flash-medium` berhasil penuh: ceritakan → tiga langkah (sekitar 31 detik) → periksa hasil → tulis ulang → koreksi isian → simpan draf (surat #4), empat log AI tertaut dan riwayat mencatat "Disusun dengan AI". Semua suite tes lulus. |
| 5 Fitur | Dimulai 18 Nov 2026 atas keputusan pemilik (peran tanpa admin, pengguna perorangan, daftar bebas): migrasi berversi `001_ruang_kerja`, ruang pribadi/organisasi, daftar satu formulir, pilih/pindah ruang, kop saat surat pertama, "Pemeriksa baru" di form, kerangka pribadi IZN dan DSP, surat pribadi tanpa nomor (tanpa pemeriksa langsung jadi). `test:workspace` dan semua suite lulus; diuji di browser lewat `http://localhost/pena`. Belum: batas percobaan login, verifikasi email, undangan anggota, verifikasi surat publik, ekspor Word/PDF. |

### Pembaruan Fase 5 — Codex, 18 Nov malam WITA

Ekspor Word/PDF serta pembatasan permintaan telah tersedia pada rilis VPS malam 18 Nov.
Verifikasi email dan reset password kini sudah diimplementasikan/diuji lokal; belum diaktifkan
di VPS karena konfigurasi Resend belum terpasang. Pengguna menyetujui kewajiban verifikasi
sebelum ruang kerja, juga untuk akun lama. Detail dan batas pengujian: [laporan akun/email](EMAIL-ACCOUNT-2026-11-18.md).
Status historis tabel di atas tidak otomatis menggambarkan implementasi terbaru ini.

### Aktivasi akun/email — 19 Nov 2026 WITA

Fitur verifikasi/reset sudah dirilis ke VPS dengan backup, uji restore dan semua tes server lulus.
Satu email verifikasi nyata dikirim atas izin eksplisit pengguna, diterima dan dikonfirmasi; DB
menunjukkan akun verified dan token telah digunakan. Reset dari email nyata di publik belum diuji.
Lihat [laporan aktivasi](DEPLOY-EMAIL-2026-11-19.md).

### AI website — 19 Nov 2026 WITA

Pengguna memilih prioritas cerita → koreksi → draf. Konfigurasi model/key VPS disimpan pengguna,
alur service nyata IZN dan PMJ diuji berhasil pada database sementara; isolasi proses/revisi dan
regresi controller dirilis. Konfirmasi klik browser publik masih menunggu. Lihat [laporan AI website](AI-WEBSITE-2026-11-19.md).

### Kerangka pribadi dinamis — 19 Nov 2026 WITA

Pengguna memperluas pribadi melampaui IZN/DSP. AI merancang skema pribadi formal/informal,
organisasi tetap katalog. Migrasi 004 dan rilis aktif; pemeriksaan dan batas provider nyata
tercatat di [laporan kerangka pribadi](PERSONAL-AI-2026-11-19.md).

### Gambar tanda tangan — 19 Nov 2026 WITA

Pilihan gambar langsung/unggah PNG dirilis; konfirmasi per versi, gambar privat, pratinjau dan
ekspor Word/PDF. Bukan integrasi tersertifikasi. [Implementasi dan verifikasi](SIGNATURES-2026-11-19.md).

### UI dan arsitektur — 20 Nov 2026 WITA

Skeleton/feedback, audit copy dan favicon dirilis; [laporan UI](UI-FEEDBACK-2026-11-20.md).
[Bukti MVC](BUKTI-MVC-2026-11-20.md) membedakan implementasi dan batasnya. Pengguna memilih
operator platform; [rancangannya](RENCANA-OPERATOR-2026-11-20.md) belum mengaktifkan hak baru.

### Operator platform — 20 Nov 2026 WITA

Panel awal dan guard dirilis, pengguna memilih akun pembuat surat #2 untuk hak awal. Akun/metadata
organisasi/status layanan/log tersedia; [verifikasi dan batas](OPERATOR-2026-11-20.md).
