# Persiapan operator platform — 20 November 2026 WITA

Pengguna memilih operator platform untuk akun, organisasi, layanan AI dan log. Ini perubahan cakupan
yang disetujui; bukan mengembalikan peran admin organisasi yang telah dihapus. Implementasi awal sudah aktif; akun awal dipilih pengguna. Detail fitur yang benar-benar tersedia
dan batasnya: [laporan implementasi](OPERATOR-2026-11-20.md). Rencana di bawah tetap menjadi acuan
untuk perluasan, bukan bukti semua kebutuhan masa depan sudah selesai.

## Batas akses

Operator mengelola layanan lintas organisasi, sedangkan sekretaris tetap bekerja di ruangnya.
Pisahkan keanggotaan organisasi dari wewenang platform. Hak operator diberikan eksplisit oleh
pemilik deployment, tidak otomatis karena akun terdaftar lebih dulu atau memiliki organisasi.
Operator tidak otomatis menjadi pemeriksa, membuka gambar tanda tangan atau isi surat, atau
menjalankan tindakan atas nama pengguna. Dukungan yang memerlukan isi surat harus punya konteks akses.

## Modul dan kriteria selesai

| Modul | Kebutuhan awal | Bukti yang diperlukan |
|---|---|---|
| Akses | Guard operator server, sesi terverifikasi, penolakan per route | Akun biasa/anonim ditolak; operator terakhir tidak dapat dinonaktifkan sembarangan |
| Akun | Cari akun, lihat status verifikasi, aktif/nonaktif dengan alasan | Nonaktif mencabut sesi lewat auth_version; perubahan diaudit |
| Organisasi | Metadata pemilik/status/jumlah surat, pembatasan akses | Tidak membocorkan isi surat atau tanda tangan saat membaca daftar |
| AI | Status gateway, model combo, penggunaan, kegagalan dan batas | Pengujian model teks dengan data fiktif; tidak menganggap katalog sebagai ketersediaan |
| Log | Filter kejadian dan error, referensi dukungan | Tidak menampilkan key/token/reset link atau prompt/PNG secara default |
| Operasi | Backup/restore dan status ekspor/email | Pemeriksaan nyata dibedakan dari status konfigurasi |

Setiap perubahan sensitif memerlukan CSRF, pemeriksaan hak, konfirmasi tindakan, alasan dan audit
pelaku/waktu/sasaran/hasil. Riwayat persetujuan dan versi tidak boleh diedit melalui halaman operator.
Kunci provider tetap privat; halaman operator cukup menunjukkan status konfigurasi dan hasil uji.

## Urutan implementasi awal (sudah ditangani)

1. Selesai: pengguna memilih akun pembuat surat #2, tanpa mengirim password/key.
2. Selesai: penyimpanan hak dan guard diuji untuk anonim/akun biasa dan sesi yang dicabut.
3. Selesai: daftar/paginasi metadata serta perubahan status akun dengan audit.
4. Selesai: status konfigurasi/uji AI manual dan log metadata, memakai feedback/skeleton bersama.
5. Selesai: uji lintas peran/browser/backup restore. Uji akun pengguna nyata dan volume produksi tetap diperlukan.

## Optimasi berdasarkan bukti

Ukur waktu route dan query terlebih dahulu. Prioritas pemeriksaan: query per pemeriksa pada detail,
daftar yang belum dipaginasi, latensi generator, cache ekspor yang terikat hasil render, dan
pembatasan provider. Tambah indeks/paginasi setelah jalur ukur jelas. Hindari cache lintas ruang atau
cache gambar tanda tangan di URL publik. Validasi identitas, CSRF, batas AI dan transaksi tidak
dikurangi demi angka latensi.
