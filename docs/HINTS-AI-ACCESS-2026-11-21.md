# Hint dan akses AI PENA — 21 November 2026 WITA

## Perilaku

- Pengantar inline diganti empat hint yang menunjuk pemilih ruang, Buat surat, Surat dan Bantuan/menu akun ponsel. Akun baru melihatnya saat masuk Beranda setelah pengaturan AI; Tutup/Selesai disimpan per akun memakai marker yang sudah ada. Akun lama tidak direset. Semua akun dapat mengulang dari Bantuan → Tampilkan petunjuk di layar.
- Hint tidak mengunci aplikasi. Fokus pada kedatangan otomatis tetap mengikuti halaman; replay yang diminta pengguna dan Lanjut/Sebelumnya memfokuskan judul hint. Tutup/Escape mengembalikan fokus ke kontrol terkait. Kegagalan menyimpan penutupan ditampilkan dan bisa dicoba lagi.
- Popover manual dan CSS Anchor Positioning dipakai bila tersedia; browser lain mendapat posisi berbasis ukuran kontrol tanpa dependensi tambahan. Layar pendek membatasi tinggi dan memberi scroll. Penjelasan diletakkan di dekat kontrol dalam urutan baca; skeleton menyuspensi popover dan memulihkannya bersama fokus.
- Panduan permanen dan petunjuk kontekstual tetap ada. Panduan kini menjelaskan kewajiban koneksi pribadi.
- Akun biasa yang belum memiliki mode personal diarahkan ke Pengaturan AI sebelum ruang kerja. Login/logout, verifikasi/pemulihan, Bantuan dan review tamu tetap dapat diakses. Endpoint AI mengembalikan JSON403 beserta setup_url.
- AI bawaan VPS hanya untuk operator yang terdaftar eksplisit, diperiksa oleh AiSettings dari database. Akun biasa tanpa pengaturan, dengan pilihan platform lama, atau setelah penghapusan koneksi tidak mendapat client platform. Forged POST mode platform ditolak. Pencabutan peran menutup resolusi client platform berikutnya.
- Koneksi pribadi yang sudah tersimpan tetap digunakan setelah login. Penyimpanan konfigurasi tidak menguji provider atau memakai kuota. Kunci invalid/kuota habis masih mungkin; Uji koneksi tetap tindakan manual. Enkripsi, master, batas endpoint publik dan larangan fallback dipertahankan.
- Simpan pertama mengarahkan ke Beranda dengan konfirmasi tersimpan. Hapus koneksi biasa meminta pengaturan baru; operator dapat kembali ke default.

## Bukti pemeriksaan

- Lint 114 PHP; pemeriksaan batas MVC 12 controller native dan 28 view tanpa akses database.
- Kandidat server dengan identitas database yang hanya berhak pena_test_*: check, ai-settings, ux, architecture, schema, workspace, flow, signatures, exports, ai-controller, personal-ai, accounts, operator, ai-endpoint, ai-transport, unit lulus. Tidak menurunkan pemeriksaan role untuk meluluskan fixture.
- Regresi AiSettings mencakup akun biasa baru, operator eksplisit, forged platform, mode platform lama, key tersimpan/enkripsi, scope akun, penghapusan, dan pencabutan operator; transport palsu untuk panggilan model.
- Chrome pada aplikasi/database/storage/sesi fiktif: login wajib provider, placeholder provider wajib dipilih, keempat endpoint AI403, forged platform ditolak, Google key-only tersimpan tanpa uji model, kunci tidak tampil kembali, login berikutnya langsung Beranda, penghapusan kembali ke setup, Help dan Keluar tetap tersedia.
- Hint seluruh langkah pada lebar320/768/1024/1440 berada dalam viewport tanpa overflow atau uncaught JS error; Next memindahkan fokus, Tutup tersimpan lintas login, replay tersedia. Fallback Popover/Anchor dimatikan lewat browser instrumentation pada320x256: konten dapat discroll, Escape menutup dan fokus kembali ke kontrol.
- Axe WCAG2A/AA,21AA,22AA dan best-practice pada hint desktop/mobile, setup provider dan fallback tidak menemukan pelanggaran dalam cakupan tersebut. Screenshot diperiksa. Pengujian fokus skeleton diulang tiga kali; penyimpanan provider memberi konfirmasi di Beranda.
- Tidak memakai API key/provider/email nyata. Browser fixture dan database uji terpisah dari produksi. Belum diuji pada Safari/Firefox, pembaca layar atau ponsel fisik; belum ada uji pemahaman pengguna. Marker tutup bukan bukti menguasai aplikasi.

## Rilis

Rilis `/srv/pena/release-20261121T122328Z` aktif; sebelumnya `/srv/pena/release-20261120T034244Z`. Backup `/var/backups/pena/hints-20261121T122328Z/before-activation` meliputi database/env/master/storage, dengan uji restore terpisah. Snapshot/hash/fields/blocks submitted dan mode/revision/hash ciphertext kunci tetap identik. Tidak ada migrasi baru atau perubahan hak operator produksi. Nginx valid, FPM direload, layanan aktif; origin PENA/Egg3l200, HTTPS login200/Bantuan anonim302/aset hint200. Health proses OS pena membuktikan satu operator dan dua akun biasa tanpa mode personal wajib setup.

Pengujian tambahan: POST dialog native menjaga field/CSRF dan berhasil pada fixture. Fixture server/database/user/storage/sesi dibersihkan. Tidak ada commit/push atau panggilan AI/email nyata. Bukti terpilih: `docs/previews/hints-2026-11-21/`.
