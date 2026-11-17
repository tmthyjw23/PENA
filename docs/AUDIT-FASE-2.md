# Audit PENA Fase 2 (17 Nov 2026)

Cakupan: semua halaman PENA berbasis Tailwind (beranda, daftar, detail dalam 4 keadaan, buat surat AI dan manual, review, landing, masuk, 404, tautan tidak berlaku). Halaman contoh template (mahasiswa, panduan) tidak diaudit.
Bukti: detektor impeccable (8 snapshot halaman yang butuh login + 4 URL publik di 1280 dan 390 px), pemeriksaan overflow dan target sentuh di 375/768/1024 px, klik manual di browser, tes `composer test` dan `test:flow`.

## Skor

| # | Dimensi | Skor | Temuan kunci |
|---|---|---|---|
| 1 | Aksesibilitas | 3 | Cincin fokus tidak terlihat di pelat karbon dan urutan fokus HP terbalik (keduanya sudah diperbaiki) |
| 2 | Performa | 3 | Tiga keluarga huruf dari Google Fonts memblokir render dan butuh internet |
| 3 | Theming | 3 | Token dari Figma dipakai; dua warna dan satu bayangan landing tadinya hard-coded (sudah dijadikan token) |
| 4 | Responsif | 3 | Di 1024 sampai 1279 px kertas surat terjepit dan nomor meluber (sudah diperbaiki) |
| 5 | Integritas implementasi | 3 | "Mulai revisi" di beranda hanya membuka halaman (kini membuka dialognya); em dash di daftar perubahan review (sudah diganti) |
| **Total** | | **15/20** | **Good** |

## Verdict integritas

LULUS. Token, strip kode, tanda status geometris, pelat "Berikutnya", dan kertas Tinos konsisten di semua halaman dan sesuai Figma. Satu-satunya temuan detektor yang tersisa di halaman aplikasi adalah `flat-type-hierarchy` (satu ukuran 16 px). Itu keputusan desain Figma yang disetujui pemilik, bukan cacat; risikonya tercatat di FIGMA-HANDOFF.md.

## Temuan

- **[P1] Kertas terjepit di layar 1024 sampai 1279 px** (detail, generate, form manual, beranda). Rail 220 px + kolom kanan 480 px menyisakan sekitar 240 px untuk kertas; nomor surat keluar dari kertas. DIPERBAIKI: susunan dua kolom pindah ke breakpoint xl (1280); di bawahnya satu kolom.
- **[P2] Cincin fokus tidak terlihat di pelat karbon** (`resources/css/app.css`). WCAG 2.4.7. DIPERBAIKI: cincin di dalam pelat memakai warna teks pelat (#F6F2E4), dicek dengan Tab.
- **[P2] Urutan fokus di HP tidak sama dengan urutan visual** (`surat/detail.php`). WCAG 2.4.3. DIPERBAIKI: pelat di depan dalam DOM; di desktop jadwal tetap di atas lewat `xl:order`.
- **[P2] "Mulai revisi" di beranda tidak memulai revisi** (`dashboard/index.php`). DIPERBAIKI: membuka dialog revisi lewat `?buka=dlg-revisi`; "Buat tautan" melompat ke pelat; "Lanjutkan draf" langsung ke form ubah.
- **[P2] Em dash di daftar perubahan konfirmasi ulang** (`controller/review.php`). DIPERBAIKI: diganti "(kosong)".
- **[P2] Huruf bergantung pada Google Fonts** (`layout/head.php`). Tanpa internet saat demo, tampilan jatuh ke huruf sistem dan kertas ke Times New Roman. DIPERBAIKI (izin pemilik): 7 file woff2 subset latin (149 KB) di `public/assets/fonts`, `@font-face` di app.css, dua huruf utama di-preload. Dicek: semua huruf dimuat dari `/assets/fonts/`, tidak ada permintaan ke Google.
- **[P3] Satu kata sendirian di baris terakhir** (landing, review). DIPERBAIKI: `text-wrap: pretty` untuk paragraf dan `balance` untuk judul.
- **[P3] Warna landing hard-coded.** DIPERBAIKI: token `ok-on-board`, `rev-on-board`, `shadow-board`.
- **[P3] Pemati transisi global untuk reduced motion.** DIPERBAIKI: dihapus; gerak hanya transisi warna 150 ms.
- **[P3] Logo PENA sebagai tautan hanya 24 px tinggi.** DIPERBAIKI: minimal 44 px.
- **[P3] Judul landing 96 px untuk 44 karakter** (detektor `oversized-h1`). DIBIARKAN: ukuran dari Figma yang disetujui.
- **[P3] Belum ada tautan "Lompat ke konten" di review, masuk, landing.** TERBUKA.

## Pola

- Breakpoint dua kolom dipilih dari lebar layar, bukan dari ruang setelah rail; semua halaman dengan rail kena sekaligus.
- Satu warna fokus tidak memperhitungkan permukaan gelap.

## Yang sudah baik

- Tidak ada overflow horizontal di 375, 768, 1024, 1440 px.
- Target interaktif di halaman aplikasi minimal 44 px setelah perbaikan logo.
- Status selalu tanda geometris + teks; kontras token utama di atas 4,5:1 (terendah: merah revisi di latar revisi, 4,5:1).
- Dialog memakai `<dialog>` asli: Esc menutup, fokus terkunci, judul terhubung.
- Kontrol yang belum berfungsi (Unduh Word/PDF, Simpan draf hasil AI) dinonaktifkan dengan alasan tertulis di sebelahnya.

## Delivery Gate antislop (mode during)

Hard Gate, semua jawaban "tidak":
- R-02 PASS: 0 em dash di view PENA dan controller (dua di review.php sudah diganti).
- R-03 PASS: tidak ada overflow di 375/768/1024/1440; tab bar dan tombol 44 px atau lebih.
- R-17, R-18, R-36, R-38 PASS: tidak ada statistik, testimoni, atau klaim. Data di papan landing ditandai "Contoh"; hasil AI ditandai "Contoh tetap, bukan hasil AI".
- R-23 PASS: logo berupa teks "PENA" di strip (dari Figma); tidak ada foto atau avatar, inisial saja.
- R-24 PASS: navigasi hanya Beranda, Surat, Buat surat, Keluar; "Cara kerja" menuju #cara-kerja yang ada.
- R-25 PASS: pasangan token dihitung; terendah 4,5:1.
- R-26 PASS: semua tombol berfungsi; Unduh Word/PDF dan Simpan draf hasil AI nonaktif dengan label alasan.
- R-27 PASS: kosong (daftar, beranda, komentar, pencarian tanpa hasil), galat (flash, galat form, tautan tidak berlaku, 404), memuat: halaman dirender di server tanpa pemuatan asinkron, jadi tidak ada keadaan memuat terpisah. Layar "Sedang menyusun" AI ikut Fase 4.
- R-28 PASS: tidak ada FAQ.
- R-32 PASS: dialog tutup dengan Esc, cincin fokus terlihat di terang dan di pelat, tautan lompat ke konten di halaman aplikasi.
- R-33 PASS: semua di sumber; build lewat Tailwind CLI.
- R-34 PASS: hanya tema terang, tanpa toggle (alasan: kertas surat harus sama dengan cetakan).
- R-35 PASS: dijalankan di server lokal. Klik: masuk -> beranda; Surat -> daftar; tab dan cari -> daftar tersaring; detail -> Batalkan surat membuka dialog, Esc menutup; Buat tautan -> tautan tampil sekali, jadwal jadi "Menunggu keputusan"; tautan dibuka di HP -> halaman review dengan bilah keputusan; Susun surat -> hasil contoh; contoh permintaan -> mengisi kotak; Simpan draf manual -> detail draf v1; Batalkan draf -> keadaan Dibatalkan; Ganti pemeriksa -> dialog terisi nama dan id; Mulai revisi -> dialog; `?buka=dlg-revisi` -> dialog terbuka; Tab ke "Setujui" -> cincin terang. Konsol: hanya 404/410 yang memang dibuka sengaja. Tidak diklik: Ajukan, Setujui, Minta revisi, Kirim komentar, Salin tautan (mengubah data uji; jalurnya diuji `test:flow` di layanan).
- R-37 PASS: arah dari Figma "Jadwal saku" yang disetujui pemilik; dial ENERGY 2 / RHYTHM 2 / MOTION 1 (landing ENERGY 3).

Purpose-Gate: tidak ada gradien, glow, glassmorphism, grid latar, ilustrasi, atau ikon pustaka. Satu-satunya bayangan ada di kertas (objek utama) dan papan landing. Huruf kapital kondensed hanya untuk kode dan kepala kolom (alasan: suara jadwal cetak). PASS.

Liveliness: dial dinyatakan; satu fokus per layar (pelat Berikutnya); satu aksen (biru tinta) untuk aksi; motif identitas strip kode + rail goldenrod. PASS.

Craftsmanship dan Quality Locks: tidak ada CTA generik atau buzzword; radius 2/4 px, tanpa pil; palet goldenrod + karbon + biru tinta, hijau/merah hanya untuk status. PASS.

## Tindakan yang disarankan

1. **[P3] `$impeccable polish`**: tautan lompat ke konten di halaman tanpa rail.
2. **`$impeccable document`**: tulis ulang DESIGN.md dari hasil build.
