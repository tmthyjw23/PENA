# Verifikasi email dan pemulihan password — 18 November 2026

## Pembaruan 19 November 2026 WITA

Fitur sudah aktif di VPS. Email verifikasi nyata diterima dan dikonfirmasi pengguna; DB membuktikan akun verified dan tidak ada permintaan verifikasi yang belum digunakan. Lihat [laporan aktivasi](DEPLOY-EMAIL-2026-11-19.md). Catatan implementasi 18 November di bawah bersifat historis.

## Status saat implementasi 18 November

Diimplementasikan dan diuji **lokal**. Belum dideploy ke VPS dan belum mengirim email nyata. Pemeriksaan server hanya mengembalikan boolean: `RESEND_API_KEY` belum terpasang, `MAIL_FROM` belum terpasang, `APP_URL` sudah HTTPS. Jangan mengaktifkan kewajiban verifikasi di produksi sebelum pengiriman dapat dipakai.

Keputusan pengguna: email wajib diverifikasi sebelum membuka ruang kerja, termasuk akun lama. Domain pengirim adalah `notify.egg3l.com`, sender `PENA <noreply@notify.egg3l.com>`. Domain aplikasi tidak otomatis berubah karena domain email berbeda.

## Perilaku

- Pendaftaran membuat akun dan ruang pribadi, mencoba mengirim verifikasi, lalu menuju halaman verifikasi. Kegagalan email mempertahankan akun dan menyediakan tombol coba lagi. Email tidak dinyatakan terkirim jika provider gagal.
- Akun lama tidak ditandai terverifikasi oleh migrasi. Pengguna perlu masuk kembali dan memverifikasi email. Alamat lama yang berupa contoh/tidak dapat diakses perlu dikoreksi sebelum aktivasi; perubahan email akun belum termasuk implementasi ini.
- Verifikasi 24 jam dan reset 30 menit; token acak 256 bit, hanya hash SHA-256 disimpan di `account_tokens`. Token memiliki tujuan terpisah dan sekali pakai. Kirim ulang mengganti tautan lama hanya jika transaksi/pengiriman berhasil.
- URL memakai fragmen `#token`, yang tidak dikirim ke server dalam HTTP request. JavaScript memindahkannya ke input tersembunyi POST dan membersihkan fragmen. Halaman akun memakai no-store/no-referrer. Konfirmasi membutuhkan POST+CSRF, sehingga pemindai email yang hanya membuka GET tidak mengubah akun.
- Password baru di-hash; konfirmasi harus sama persis, spasi dipertahankan, batas bcrypt 72 byte diperiksa. Reset menaikkan `users.auth_version`; setiap request terautentikasi mengecek versi ke database, sehingga sesi lama tidak dapat dipakai lagi. Reset tidak otomatis login atau memverifikasi email.
- Permintaan reset menampilkan jawaban sama untuk email dikenal/tidak dikenal, tanpa mengklaim email sudah terkirim. Di PHP-FPM respons diselesaikan sebelum lookup/panggilan provider; di Apache/dev server panggilan berlangsung sinkron, sehingga kesamaan waktu respons tidak dijamin.
- Batas: 3 email per pengguna/tujuan/jam; 90 permintaan email akun/platform/hari; reset 10 per IP/jam dan 3 per alamat/jam; tindakan akun 30 per IP/15 menit. Ini pembatasan aplikasi, bukan jaminan kuota provider atau perlindungan terhadap semua penyalahgunaan.
- Log insiden WAF tidak lagi menuliskan payload atau URL yang mungkin mengandung password/token. Log provider hanya status/jenis kegagalan, tidak mencetak body maupun rahasia. Skrip dan tombol kredensial demo dibatasi ke APP_ENV local.

## Struktur

`ResendMailer` memakai cURL yang sudah terpasang, endpoint HTTPS tetap, timeout dan validasi respons ber-ID; tidak ada dependency baru. `AccountService` mengelola token dan perubahan akun secara transaksional. Controller `akun`, view `akun/index`, login/daftar dan session guard menyambungkan alur UI. Migrasi `003_account_email` menambah kolom dan tabel akun; skema instalasi baru juga diperbarui. Konfigurasi contoh: `RESEND_API_KEY` dan `MAIL_FROM`.

Payload client mengikuti [Send Email API Resend](https://resend.com/docs/api-reference/emails/send-email). Token sekali pakai, masa berlaku, jawaban umum, dan pencabutan sesi mengikuti prinsip dalam [OWASP Forgot Password](https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html); referensi bukan sertifikasi keamanan implementasi.

## Pemeriksaan yang benar-benar dijalankan

- `composer check`: 78 berkas PHP, lulus.
- `composer test`, `test:schema`, `test:flow`, `test:ai`, `test:workspace`, `test:exports`, dan `test:accounts`: lulus. Manifest Composer strict dan diff whitespace lulus. Tes ekspor Windows juga tetap membuat PDF.
- `test:accounts`: hash token/tujuan/expiry/replay, kirim ulang, rollback kegagalan email, password/konfirmasi/byte/spasi, pencabutan versi sesi, akun nonaktif, batas email, konfigurasi kosong, sanitasi log. Semua email melalui transport palsu; pesan error provider yang disengaja adalah skenario negatif.
- Browser Chrome pada server sementara dengan database `pena_test_http_email_*`: login belum verifikasi ditahan dari dashboard/ruang/surat; GET link belum mengonsumsi token; POST konfirmasi membuka dashboard; CSRF hilang ditolak; konfirmasi password salah ditolak; reset membatalkan dua sesi aktif; login password baru berhasil; replay ditolak; jawaban reset diketahui/tidak diketahui sama. Halaman mobile tidak overflow dan tidak ada error JS.
- Uji browser menemukan halaman selesai reset pada URL yang sama dapat menyisakan navigasi fragmen tanpa formulir ketika tautan dibuka ulang. Diperbaiki dengan redirect setelah POST ke halaman selesai, lalu seluruh alur diulang dan lulus.
- Tampilan verifikasi mobile diperiksa dari screenshot. Uji provider nyata, inbox/spam, link dari email nyata, dan cabang PHP-FPM belum dilakukan.

## Aktivasi berikutnya

1. Pemilik memasang kunci lewat terminal pribadi sesuai [setup Resend](SETUP-EMAIL-RESEND.md), tanpa mengirim nilainya ke chat.
2. Pastikan akun lama yang dipakai pemilik memiliki email yang dapat diterima. Jangan otomatis menandainya verified atau mengubah alamat tanpa instruksi pemilik.
3. Siapkan backup dan rilis dengan migrasi baru. Gunakan izin deployment yang berlaku; jangan melewati review otomatis bila perubahan memerlukan izin tambahan.
4. Pengguna melakukan pendaftaran/verifikasi/reset dengan alamatnya sendiri, lalu penerimaan email dan alur publik diperiksa. Pengiriman email nyata oleh agent membutuhkan tujuan dan izin yang jelas.

Tidak ada commit/push atau perubahan fitur produksi pada pekerjaan email ini.
