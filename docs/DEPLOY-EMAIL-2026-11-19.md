# Aktivasi email PENA — 19 November 2026 WITA

## Rilis dan konfigurasi

Pengguna menyatakan konfigurasi tersimpan dan melanjutkan deployment yang sebelumnya diminta. Pemeriksaan hanya boolean membuktikan kunci tersedia, sender sesuai `PENA <noreply@notify.egg3l.com>`, APP_URL HTTPS. Satu akun aktif tidak memakai alamat contoh. Nilai kunci, alamat penerima, token dan isi `.env` tidak ditampilkan.

Rilis aktif `/srv/pena/release-20261118T204503Z` (timestamp UTC; pemasangan 19 November WITA), melalui `/srv/pena/current`. Kode sebelumnya `/srv/pena/release-20261118T143116Z` disimpan. Backup sebelum aktivasi `/var/backups/pena/20261118T204503Z/` berisi dump DB, environment privat, dan isi storage (symlink diikuti saat backup). Dump dipulihkan ke database uji terpisah; jumlah pengguna dan surat cocok; database uji kemudian dihapus.

Migrasi `003_account_email` diterapkan dan `.env` server dipertahankan. Kewajiban verifikasi kini berlaku di produksi, termasuk akun lama. Sesi lama tanpa versi autentikasi perlu masuk kembali. Storage bersama dipertahankan. Kode rollback tidak otomatis membatalkan migrasi database.

## Pemeriksaan

- Tujuh suite server (`test`, `test:schema`, `test:flow`, `test:ai`, `test:workspace`, `test:exports`, `test:accounts`) lulus sebelum pergantian rilis; database suite terpisah dan transport email palsu. PDF Linux nyata juga dibuat oleh tes ekspor.
- Lint PHP dan `nginx -t` lulus; origin beranda, login, daftar, lupa password 200 setelah reload PHP-FPM.
- Browser publik pada 1440 dan 390 px: login, daftar, lupa password, reset, konfirmasi 200, tanpa overflow atau error JS. Halaman akun memiliki no-store, tombol demo tidak ditampilkan di produksi.
- Permintaan reset dari alamat fiktif yang tidak terkait akun aktif menghasilkan respons umum melalui PHP-FPM tanpa email nyata. Ini membuktikan jalur respons publik, bukan penerimaan email reset di inbox.
- Pengguna secara eksplisit mengizinkan **satu email verifikasi ke akun aktifnya** melalui jawaban interaktif. Pemanggilan `AccountService::sendVerification` sebagai user layanan `pena` diterima Resend. Tidak ada percobaan kirim ulang otomatis atau email reset nyata oleh agent.

## Batas bukti dan tindak lanjut

Setelah API accepted, pengguna mengonfirmasi bahwa email masuk dan tombol konfirmasi sudah ditekan. Pemeriksaan DB sebagai user layanan membuktikan: satu akun aktif terverifikasi, nol permintaan verifikasi yang belum digunakan, dan migrasi email tercatat. Ini bukti pengiriman serta konfirmasi email nyata pada domain publik. Agent tidak membaca/membuka token pengguna atau menandai verified secara manual.

Pengujian alur verifikasi/reset dan pencabutan dua sesi lewat browser sudah dilakukan lokal pada database uji, sebagaimana [laporan implementasi](EMAIL-ACCOUNT-2026-11-18.md). Reset dari email nyata pada domain publik belum dijalankan. AI server, kebijakan retensi/privasi, evaluasi pengguna, dan penerimaan fakultas tetap merupakan pekerjaan terpisah dari aktivasi email ini.

Tidak ada commit/push atau perubahan konfigurasi Nginx baru pada aktivasi ini. Tidak ada pesan lain yang dikirim selain satu verifikasi yang diizinkan pengguna.

Pemeriksaan penutup: Nginx/PHP-FPM/MariaDB aktif; PENA HTTPS 200 dan origin Egg3l 200. Tidak ada email tambahan dikirim setelah satu verifikasi tersebut.
