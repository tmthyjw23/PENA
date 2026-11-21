# UI, loading dan feedback — 20 November 2026 WITA

Skeleton muncul pada permintaan yang sedang berlangsung: navigasi, submit formulir, penyusunan draf,
penulisan ulang, dan unduhan. Tidak ada spinner, waktu minimum buatan atau persentase palsu.
Form POST dilindungi dari submit ganda pada halaman yang sama, tanpa menonaktifkan nilai tombol
yang diperlukan server. Pending normal dipulihkan saat pageshow. Bila navigasi belum merespons
45 detik, pengguna diminta memeriksa status sebelum mengulang; state POST tidak dibuka otomatis.

Unduh memeriksa status dan Content-Type, membuat file dari Blob, lalu menjelaskan bahwa file siap
diunduh. Gagal/HTML login/error/berkas kosong/timeout tidak menghasilkan pesan sukses. Timeout
120 detik menghentikan fetch; itu tidak membatalkan persetujuan. Tombol dan fokus dipulihkan.
Clipboard fallback gagal tidak diklaim tersalin. Pesan dibaca lewat role status/alert dan bisa ditutup.

Penulisan ulang menyimpan teks lama saat gagal, mempertahankan tinggi skeleton, memberi status saat
sukses, dan menjaga revisi AI. Enter pada instruksi berarti tulis ulang, bukan submit Simpan draf.
Selama rewrite, tindakan simpan ditolak dengan feedback. Gambar tanda tangan tetap memakai status
gambar/unggah/konfirmasi yang sudah ada. Tidak mengubah aturan persetujuan, perizinan, atau isi surat.

Copy: [audit sebelum/sesudah](AUDIT-COPY-2026-11-20.md). 23 view ditinjau, 90+ perbaikan kata.
Informasi yang membantu keputusan tetap ada: identitas pemegang tautan belum diverifikasi,
pemeriksaan draf AI, konfirmasi gambar per versi, dan penguncian surat selesai. Data surat pengguna
dan teks template dokumen tidak ditulis ulang. Favicon SVG/PNG/ICO memakai monogram P dan warna PENA.

Verifikasi backend kandidat: check, architecture, accounts, workspace, ai, ai-controller, ai-clarify,
personal-ai, signatures, exports dan test lulus. Lint akhir 92 file PHP, Composer validate --strict
dan git diff --check lulus. Probe sementara yang memasukkan new Database dan Db->all pada view
ditolak pemeriksaan architecture, kemudian dihapus. [Bukti dan batas MVC](BUKTI-MVC-2026-11-20.md).

Browser aplikasi memakai clone kandidat, database/user terbatas baru, storage dan session terpisah,
PHP sebagai user pena pada loopback lewat SSH. Login, manual pribadi, tanda tangan gambar,
penyelesaian, Word/PDF, organisasi, unggah PNG ketua, revisi advisor, perubahan data, ajukan ulang,
konfirmasi tanpa penyalinan gambar, tanda tangan advisor versi baru, Word/PDF organisasi, dan HP
lulus. Pengujian diselesaikan dalam dua segmen karena stub fetch uji error perlu dipulihkan di
harness; tidak ada bypass autentikasi/CSRF pada route aplikasi. Fixture akun verified hanya di DB uji.
DB/user/server/storage/session uji dihapus. Ini tidak diklaim sebagai login HTTPS akun produksi.

Browser fixture 1440/390 menguji pending/failure generator dan rewrite. Tes terisolasi feedback
menguji satu request dari klik ganda, parameter format, file sukses, HTTP gagal, timeout terkontrol,
clipboard sukses/gagal. Render skeleton HP dan detail organisasi diperiksa visual; tanpa overflow
atau error JS pada kasus yang diuji. Tidak ada email/provider AI nyata dipanggil pada pemeriksaan ini.

Rilis /srv/pena/release-20261119T165212Z aktif (UTC; 20 Nov WITA), sebelumnya
/srv/pena/release-20261119T141059Z. Environment privat dicadangkan di
/var/backups/pena/ui-20261119T165212Z, kode lama disimpan, konfigurasi/storage bersama dipertahankan.
Tanpa migrasi DB, pemberian role, perubahan provider atau commit/push. Nginx/FPM/MariaDB aktif;
origin PENA/Egg3l 200, HTTPS favicon/feedback/login 200. Favicon browser yang sudah dicache mungkin
memerlukan reload. [Rancangan operator](RENCANA-OPERATOR-2026-11-20.md) belum fitur yang diaktifkan.

Batas: sebagian pesan domain berasal dari data/AI dan perlu peninjauan pengguna; audit teks tidak
menjamin semua draf AI bermutu. Pengujian beban, penerimaan fakultas, initial operator dan uji akun
produksi terautentikasi tetap terpisah. Tidak mengklaim siap produksi penuh.
