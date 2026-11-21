# Kerangka pribadi AI — 19 November 2026 WITA

Pengguna meminta surat pribadi terbuka terhadap berbagai tujuan; AI menentukan susunannya.
Alur pribadi kini merancang skema sesuai tujuan → ekstraksi → penulisan → pemeriksaan → simpan.
Contoh: permintaan maaf romantis, lamaran kerja, pengembalian uang, izin kuliah.
Alur organisasi tetap memilih kerangka katalog.

Skema menerima maksimal 16 isian, 10 bagian dan 6 kolom per tabel; blok paragraf/tabel/tanda tangan.
Ini pembatasan representasi agar editor dan Word/PDF kompatibel, bukan daftar tujuan yang diizinkan.
PersonalTemplate menolak skema tidak valid. Skema baru disimpan privat pada transaksi simpan draf;
gagal simpan tidak meninggalkan kerangka yatim, katalog tetap bersih, snapshot versi tetap lengkap.
Tampilan informal bisa menghilangkan Perihal, tanggal, penerima formal dan tanda tangan formal.
Isian penting kosong tetap perlu dilengkapi. AI diarahkan memakai penanda isian dan tidak menambah fakta.
Validasi penanda bukan pemeriksaan semantik menyeluruh atau jaminan bebas halusinasi.

Klarifikasi pribadi meminta jawaban terbuka, disimpan terpisah; prompt asli tetap utuh.
Batas output AI pribadi dinaikkan menjadi 3.000 token setelah respons JSON terpotong pada anggaran
1.500. AI_PERSONAL_MAX_TOKENS bisa dikonfigurasi (512–4.096); organisasi memakai AI_MAX_TOKENS.
Pemakaian token dapat meningkat. Provider HTTP 429 ditampilkan sebagai kegagalan, tanpa isi palsu.

Verifikasi: test:personal-ai menguji skema invalid, alur controller, rollback, skema privat, katalog,
edit, snapshot, DOCX/PDF, referensi opsional kosong dan klarifikasi terbuka. check/test/ai/
ai-controller/ai-clarify/exports juga lulus pada pemeriksaan akhir server. Schema/flow/workspace/
accounts lulus saat persiapan kandidat. Browser fixture view asli 1440/390 tanpa overflow/JS error;
fetch palsu membuktikan submit jawaban terpisah dan prompt utuh, bukan akses publik terautentikasi.
Validasi manifest Composer dan diff lulus; lokal MySQL mati, tes DB memakai DB sementara VPS.

Provider nyata menghasilkan surat maaf, lamaran, pengembalian hingga simpan/selesai dan PDF pada run
terpisah, dengan data fiktif dan pengisian manual isian wajib yang masih kosong oleh skrip uji.
PDF satu halaman A4 diperiksa visual. Ditemukan pengulangan penerima/tanda tangan dan tambahan klaim
lampiran; panduan perancangan/penulisan diperketat. Keluaran model pascaperbaikan belum diverifikasi:
pengulangan terakhir terkena HTTP 429. Jangan menyebut seluruh tujuan atau semua run pasti lulus.

Rilis aktif /srv/pena/release-20261119T120933Z, sebelumnya /srv/pena/release-20261119T112443Z.
Backup DB/env/storage sebelum aktivasi dan restore DB terpisah (jumlah users, letters, letter_versions,
letter_templates cocok) lulus di /var/backups/pena/personal-20261119T120933Z/before-activation.
Migrasi 004 menambah is_generated; rollback kode tidak menghapus skema/kerangka yang sudah tersimpan.
Environment server dipertahankan. Origin dan HTTPS beranda/login 200, buat anonim 302, .env 404,
Nginx/FPM/MariaDB aktif, Egg3l origin 200. Tidak ada email tambahan atau commit/push.

Selanjutnya: uji pengguna pada Buat surat di ruang pribadi saat provider tersedia; periksa fakta,
nada dan susunan sebelum menyelesaikan surat. Penerimaan dokumen khusus/hukum perlu penilaian
sesuai kebutuhan penerima; keluasan generator tidak menjamin keabsahan dokumen.

## Tindak lanjut 429

Probe 19 Nov: provider free usage limit reached; Retry-After 41.544 detik. Akses AI belum pulih.
[Dokumentasi resmi OpenCode](https://opencode.ai/docs/console/models/) menyatakan free-tier chat
memerlukan OpenCode; aplikasi lain memakai model berbayar/Go. Perlu pilihan akses provider pengguna.
Rilis pesan 429 /srv/pena/release-20261119T125022Z aktif: waktu tunggu ditampilkan bila tersedia,
body provider tetap privat, tanpa retry otomatis. test:ai-client, test:ai, test:personal-ai lulus.
Tidak mengubah model/key, tidak melakukan pembelian atau migrasi DB.

## Combo PENA dipilih pengguna

19 Nov: combo PENA dan koneksi OpenRouter tersedia. Konfigurasi server diubah menjadi AI_MODEL=PENA.
Model pertama semula Nemotron reasoning memberi JSON singkat valid, tetapi gagal pada alur penuh.
Gemma 31b free memberi 503 pada satu probe. Cohere north-mini-code:free lulus alur lengkap kandidat
melalui ID langsung sampai simpan/selesai/PDF (25,2 detik, 4 panggilan, 2 isian, 5 bagian).
Data fiktif, tanpa pengisian manual wajib; DB sementara dihapus. PDF A4 diperiksa visual: masih ada
kalimat berulang dan penutup kurang rapi. Ini bukti fungsi, bukan kelulusan kualitas bahasa.

Cohere dipindah ke posisi pertama combo, model lain dipertahankan. Probe combo setelah aktivasi
sebagai akun layanan pena membuktikan actual_model cohere/north-mini-code:free, JSON valid 0,76 detik.
Fallback lain belum diuji; daftar masih luas dan mencakup Lyria/OpenCode free. Kuota tetap berlaku.
[Metadata resmi OpenRouter](https://openrouter.ai/api/v1/models) dipakai untuk memeriksa kapabilitas
calon model; tercantum di katalog tidak berarti endpoint sedang tersedia atau cocok untuk tugas.

LetterAiService kini memberi satu koreksi skema bila JSON berstruktur melanggar aturan, dengan
kedua log tetap ditautkan. Tidak melonggarkan validasi atau mencoba ulang HTTP 429 otomatis.
Tes personal-ai, ai-controller, ai-clarify, ai, ai-client lulus; manifest/sintaks/diff lulus.
Rilis /srv/pena/release-20261119T130530Z aktif; sebelumnya /srv/pena/release-20261119T125022Z.
Backup environment, SQLite router, dan log: /var/backups/pena/combo-20261119T130530Z.
Tidak ada migrasi PENA/penggantian key/pembelian baru. Gateway restart, FPM reload, login publik 200,
origin PENA dan Egg3l 200. Uji browser publik terautentikasi pengguna masih diperlukan.
