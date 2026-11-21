# Gambar tanda tangan — 19 November 2026 WITA

Keputusan pengguna: gambar langsung dan unggah PNG, konfirmasi pemakaian untuk setiap versi.
Pribadi: buka draf → Selesaikan/Ajukan → Tambahkan tanda tangan → gambar/unggah → konfirmasi → kirim.
Pemeriksa: buka tautan → periksa versi → Tambahkan tanda tangan → gambar/unggah → konfirmasi → Setujui.
Persetujuan tanpa gambar tetap tersedia. Surat yang telah selesai tidak diubah; buat surat baru jika
perlu mengganti isi atau menambahkan tanda tangan sesudahnya. Gambar tidak menjadi aset profil reusable.

SignatureService menerima PNG maksimal 512 KB, 1600×800 piksel. Header/jenis/dimensi/dekode/piksel
diperiksa di server; metadata dibuang, latar putih ditransparankan, gambar dipotong ke tinta dan
dibatasi 600×200. PNG palsu, gambar kosong, ukuran berlebih, unggah gagal dan tanpa consent ditolak.
Unggah memakai is_uploaded_file; sumber gambar Word berupa byte hasil normalisasi, bukan URL/path
pengguna. Tidak menambah dependensi; memakai GD dan PHPWord yang sudah tersedia.

Migrasi 005 menambah letter_signatures: versi, slot penanda tangan, content_hash, PNG privat BLOB,
image_sha256, user/request, auth_method dan consented_at. Satu slot satu gambar. Tidak ada URL gambar
publik. Tampilan detail/review memakai no-store, inline gambar hanya sesudah pemeriksaan akses.
Pemrosesan gambar/keputusan berjalan dalam transaksi yang sama. Draf pribadi diperiksa ulang melalui
hash pratinjau dan versi. Tamu tetap memerlukan token aktif dan content_hash yang benar.

Snapshot baru memiliki key slot; snapshot lama dipetakan dari urutan request tanpa mengubah snapshot.
Gambar lama tetap milik versi lama; revisi/konfirmasi ulang tidak menyalinnya. Renderer menambahkan
gambar pada slot terkait. Jika surat pribadi tidak mempunyai blok signatures, pembubuhan eksplisit
menambahkan blok pengirim pada tampilan keluaran. Cache ekspor tetap immutable: penambahan gambar
hanya saat pengajuan/approval, sebelum versi final boleh diekspor.

Verifikasi:

- test:signatures: PNG/consent, kepemilikan, draf berubah, hash gambar/dokumen, simpan atomik,
  preview, DOCX berisi media, PDF Linux, token/versi/hash salah, token terpakai, slot legacy,
  revisi tanpa transfer, konfirmasi tanpa reuse, alur organisasi sampai PDF.
- flow/workspace/exports/personal-ai/ai-controller/test/schema lulus di database sementara VPS,
  yang dihapus setelah tes. Tidak memakai database produksi untuk data uji. MySQL lokal tetap mati.
- Browser widget asli 1440/390: menggambar, hapus, unggah/pratinjau, konfirmasi wajib, no overflow/JS
  error. Upload HTTP sesungguhnya ke fixture lokal menguji is_uploaded_file dan empat kasus valid/
  tanpa consent/PNG palsu/oversize. Ini bukan login dan klik publik dengan akun pengguna.
- PDF pribadi dan organisasi dirender ke PNG lalu diperiksa: gambar tampil di slot yang tepat,
  kop/tabel/nama tetap terbaca. Gambar geometris fiktif digunakan, bukan tanda tangan orang nyata.
- Lint 91 PHP, manifest Composer dan diff lulus.

Rilis /srv/pena/release-20261119T134452Z aktif, sebelumnya /srv/pena/release-20261119T130530Z.
Backup DB/env/storage di /var/backups/pena/signatures-20261119T134452Z/before-activation.
Dump dipulihkan ke DB terpisah; jumlah users/letters/letter_versions/approval_requests cocok, DB restore
dihapus. Migrasi 005 root diterapkan, konfigurasi PENA dan shared storage dipertahankan. Kode lama
disimpan; rollback kode tidak membatalkan migrasi atau menghapus bukti tanda tangan.
nginx -t lulus, FPM reload, layanan aktif, origin PENA/Egg3l 200. JS/login HTTPS 200, buat anonim 302;
tabel baru dapat dibaca oleh user layanan pena. Tidak ada perubahan model/provider, email, commit/push.

Belum: uji pengguna di browser publik terautentikasi, penerimaan advisor/fakultas, serta integrasi
tanda tangan tersertifikasi. Pemegang tautan belum terbukti sebagai pejabat yang namanya tertera.
Hash mengikat rekaman pada versi; ini bukan tanda tangan kriptografis pada file PDF.

## Koreksi render setelah laporan pengguna

Dua gambar surat 2 tersimpan namun kerangka AI tidak memiliki blok tanda tangan. Rilis koreksi
141059Z menambahkan slot gambar yang telah dikonfirmasi tanpa mengubah snapshot. Cache ekspor
memakai fingerprint hasil render agar berkas lama dibuat ulang. Regresi negatif/positif lulus;
metadata surat 2 membuktikan dua gambar tersimpan dan dua dirender. Rilis UI 20 Nov mencakup fix.
