# AI di website PENA — 19 November 2026 WITA

## Status

Pengguna memilih menuntaskan cerita → hasil AI → koreksi → draf dan memakai gateway 9router VPS. Pengguna menyimpan API key/model melalui terminal pribadi. Nilai credential tidak ditampilkan.

Rilis aktif /srv/pena/release-20261119T110639Z; sebelumnya /srv/pena/release-20261119T104544Z disimpan. Cadangan kode terkait /var/backups/pena/ai-20261119T110639Z. Konfigurasi terbaru disalin dari rilis sebelumnya saat aktivasi; shared-storage tetap pena:pena. Tidak ada migrasi database produksi atau perubahan data surat pengguna. MySQL lokal sedang mati; tes DB dijalankan pada schema sementara VPS.

## Perubahan aplikasi

- AiDraftStore menyimpan proses AI per pengguna, ruang, dan ID proses acak. Setiap form baru memiliki proses sendiri; hasil menggunakan URL proses yang sama. State lama tidak dibaca lintas ruang.
- Revisi state diperiksa pada setiap POST. Kiriman tab usang/ruang yang berubah ditolak sebelum memanggil provider. Saat menyimpan satu hasil, proses lain tetap ada. Proses kedaluwarsa setelah dua jam tanpa pembaruan; maksimal 12 proses dalam satu sesi.
- Tulis ulang membaca isian yang sedang dikoreksi pada form. Isian yang dikosongkan tidak tetap tersedia sebagai penanda. Sumber kutipan AI lama dihapus bila pengguna mengoreksi nilai; UI menandainya dikoreksi/dikosongkan oleh pengguna.
- Pembuatan draf dan penautan log AI berada dalam satu transaksi. Kegagalan validasi mempertahankan koreksi pada proses tersebut; pengiriman ulang setelah berhasil tidak membuat ulang proses yang sudah dihapus.
- Body error provider tidak diteruskan ke UI/log AI; pesan HTTP umum digunakan agar credential/detail upstream tidak bocor.
- Pesan AI tidak tersedia memberi jalur manual tanpa petunjuk environment di produk. Klaim waktu tetap di layar progres dihapus. Tombol kembali ke isian memuat ulang proses; ini bukan pembatalan panggilan model di server.

## Bukti nyata

- Model konfigurasi pengguna oc/deepseek-v4.1-flash-free pada gateway loopback /v1; nama respons model deepseek-v4.1-flash-free. Uji JSON singkat berhasil sekitar 1,186 detik dengan batas keluaran 128 token.
- Surat pribadi IZN: rekomendasi, ekstraksi, paragraf, koreksi alasan, tulis ulang, simpan draf dan tautkan empat panggilan AI berhasil dalam 20,55 detik.
- Surat organisasi PMJ: rekomendasi, data termasuk kebutuhan barang, paragraf, koreksi lokasi, simpan dengan kop/pemeriksa dan tautkan tiga panggilan AI berhasil dalam 18,17 detik.
- Kedua uji provider nyata memakai identitas/data fiktif serta database pena_test_* yang dihapus dalam finally. Waktu tersebut adalah sampel, bukan jaminan kecepatan/akurasi atau hasil evaluasi pengguna.
- Gateway memiliki API key aktif dan nol baris providerConnections pada pemeriksaan. Preset OpenCode tetap menjawab; jumlah baris koneksi akun saja bukan bukti semua provider tidak siap. Daftar katalog model juga tidak membuktikan setiap model bisa dipanggil.

## Regresi dan rilis

composer check, test:ai-drafts, test:ai-controller, test:ai, test:workspace dan validasi Composer strict lulus pada kandidat; test:ai-drafts juga lulus lokal tanpa DB. Build CSS dan git diff --check lulus.

Regresi controller menjalankan tiga langkah dengan transport palsu, mengecek proses/revisi pada halaman hasil, form baru tidak menghapus hasil sebelumnya, penolakan tab/ruang sebelum provider dipanggil, koreksi/penanda pada tulis ulang, penolakan simpan organisasi tanpa pemeriksa, penyimpanan koreksi setelah validasi gagal, lalu simpan dengan pemeriksa valid dan log tertaut. Sejumlah asumsi fixture diperbaiki: prompt paragraf sengaja memakai penanda tanpa nilai mentah, dan organisasi tetap memerlukan tahap persetujuan. Validasi produk tidak dilonggarkan untuk meluluskan tes.

Sebagai user layanan pena, koneksi DB, client AI, kelas store dan konfigurasi email terbaca. Setelah pergantian symlink dan reload FPM: origin beranda/login/lupa password serta HTTPS publik 200; pemulihan kode tersedia bila smoke gagal. Tidak ada email tambahan atau commit/push pada pekerjaan AI ini.

## Batas dan langkah berikutnya

Alur service AI nyata dan controller dengan transport palsu sudah diuji. Klik seluruh alur baru dari browser publik akun pengguna masih menunggu konfirmasi; agent tidak memakai/menebak password akun pengguna. Sesi/tab lama perlu membuka ulang Buat surat agar memakai ID/revisi form baru.

Ketepatan fakta/konteks tetap harus diperiksa pengguna. AI hanya membantu; belum ada evaluasi representatif, uji beban, jaminan bebas halusinasi, atau pengujian semua model. Worst-case retry/latensi pada jalur sinkron belum dibenchmark terhadap batas proxy. Langkah berikutnya setelah konfirmasi browser: satu alur surat organisasi penuh hingga review, revisi, persetujuan dan Word/PDF.
