# Tampilan email PENA — 19 November 2026 WITA

Permintaan pengguna: email tampak profesional; pengguna juga melaporkan konfirmasi pendaftaran nyata berhasil.

Email verifikasi dan reset sekarang memiliki identitas PENA, aksen kuning, tombol biru, judul dan instruksi jelas, masa berlaku sekali pakai, tautan alternatif, serta catatan keamanan. Layout tabel dengan inline CSS, font sistem dan tanpa aset eksternal dipilih untuk email. Versi teks biasa tetap dikirim bersama HTML sesuai [API Resend](https://resend.com/docs/api-reference/emails/send-email).

File: AccountEmail menyusun HTML/teks; AccountService memakai pesan tersebut; ResendMailer menerima HTML opsional tanpa memutus pemanggil teks biasa. Tautan/token identik pada kedua format dan URL di-escape pada HTML.

Verifikasi: sintaks empat berkas lulus; composer test:accounts lulus di database sementara VPS dengan transport palsu, termasuk HTML, kesesuaian token, escaping, batas waktu, replay, kegagalan provider, dan pembatalan sesi. MySQL lokal sedang mati sehingga tes DB lokal tidak dijalankan; kegagalan koneksi awal bukan hasil lulus. Pratinjau Chrome pada viewport 760 dan 390 diperiksa visual dan scrollWidth tidak melebihi viewport. Ini bukan uji render Gmail/Outlook asli.

Rilis aktif: /srv/pena/release-20261119T104544Z. Rilis sebelumnya /srv/pena/release-20261118T204503Z dipertahankan; cadangan kode /var/backups/pena/email-style-20261119T104544Z. Tidak ada migrasi/data akun yang diubah. PHP-FPM di-reload dan halaman akun origin 200. Tidak ada email percobaan tambahan dikirim.

Pratinjau memakai token dummy, bukan tautan akun pengguna:

- [Verifikasi desktop](previews/email-verification-desktop.png)
- [Reset HP](previews/email-reset-mobile.png)

Desain baru berlaku pada email berikutnya. Tampilan dalam aplikasi email nyata tetap perlu diperiksa oleh pengguna ketika menerima email berikutnya.
