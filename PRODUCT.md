# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Sekretaris organisasi mahasiswa** (contoh nyata di data: BEM Fakultas Ilmu Komputer). Menyusun surat
  resmi, mengajukan, mengirim tautan ke pemeriksa, dan menindaklanjuti revisi. Memakai laptop di
  sekretariat dan HP sama seringnya (dikonfirmasi pemilik 17 Nov 2026).
- **Pemeriksa tanpa akun**: Ketua Panitia, Ketua BEM, Advisor organisasi, Dekan. Membuka tautan sekali
  pakai, biasanya dari WhatsApp di HP, membaca surat, lalu setuju, minta revisi, atau berkomentar.
- **Admin organisasi**: hak sama dengan sekretaris di data saat ini.

## Product Purpose

Membuat surat organisasi dari kerangka resmi sampai disetujui semua pihak, dengan jejak versi yang
bisa dipertanggungjawabkan. Sukses berarti dua hal sekaligus (dikonfirmasi pemilik): lulus penilaian
tugas akhir pada demo 21 Nov 2026, dan layak dipakai sungguhan oleh organisasi setelahnya.

## Positioning

Fakta resmi (tanggal, nama, tempat, nominal, daftar barang) diisi sekali dan paragraf hanya merujuk lewat
penanda `{{isian}}`, sehingga revisi data tidak meninggalkan kalimat yang salah. Persetujuan bertahap
berjalan lewat tautan tanpa akun, setiap persetujuan terikat pada versi isi yang dibaca pemeriksa,
dan penyetuju lama mengonfirmasi ulang setelah revisi dengan melihat daftar perubahan.

## Operating Context

- Alur: pilih kerangka (saat ini Surat Peminjaman PMJ dan Surat Permohonan PMH), isi data, tulis
  paragraf, tentukan tahap persetujuan, simpan draf, ajukan (nomor dibuat, isi dikunci), kirim tautan,
  tunggu keputusan, revisi bila diminta, sampai disetujui.
- Nomor surat berformat `001/BEM-FILKOM/PMJ/XI/2026` dan tetap sama lintas revisi.
- Tautan disimpan sebagai hash, jadi hanya bisa ditampilkan sekali; tahap berikutnya butuh sekretaris
  membuat dan mengirim tautan baru.
- Hasil akhir adalah surat cetak: DOCX dibuat dari blok, PDF dikonversi lewat Microsoft Word.
  Pratinjau di layar harus sama dengan kertas (Times New Roman 12pt, kop bergaris ganda).
- Bahasa antarmuka: Indonesia.

## Capabilities and Constraints

- Wajib memakai template PHP MVC dosen (router/MVC tetap). Tampilan dipindah ke Tailwind CSS atas
  pilihan pemilik; perlu dikonfirmasi ke dosen.
- Sudah ada: skema database, layanan surat, alur persetujuan, nomor surat, DOCX/PDF, tes otomatis.
- Belum ada: ekspor yang tersambung ke surat disetujui, bantuan AI (rencana lewat 9router), cek
  identitas pemeriksa.
- Urutan kerja: Figma, UI statis, database bila perlu, AI, fitur (`docs/ALUR-PENGERJAAN.md`).

## Brand Commitments

- Nama produk **PENA** tetap.
- Aksen **biru tinta** tetap (dikonfirmasi pemilik 17 Nov 2026). Nilainya #1F3A8A, dikunci di DESIGN.md.

## Evidence on Hand

- Data contoh di database pengembangan: organisasi BEM-FILKOM, pemeriksa bernama "Contoh ...",
  dua surat uji. Semua data itu fiktif dan harus ditandai contoh bila tampil.
- Tidak ada testimoni, pengguna nyata, statistik penggunaan, logo kampus/BEM, atau foto. Jangan dikarang.

## Product Principles

1. Fakta ditulis sekali; kalimat merujuk, tidak menyalin.
2. Setiap keputusan terikat pada versi yang benar-benar dibaca.
3. Jujur soal batas: identitas pemeriksa tidak diverifikasi, dan itu dikatakan di tempat keputusan dibuat.
4. Kertas surat adalah objek utama; antarmuka melayaninya.
5. Pemeriksa di HP harus bisa memutuskan tanpa belajar apa pun.

## Accessibility & Inclusion

Kontras WCAG AA, target sentuh minimal 44 px, status tidak hanya dengan warna, dapat dipakai dengan
keyboard. Pemeriksa bisa jadi dosen atau dekan yang membaca di HP dengan ukuran teks besar.
