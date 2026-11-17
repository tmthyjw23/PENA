---
version: 1
slug: "app-view-surat-detail-php"
primary_target: "app/view/surat/detail.php"
related_targets: ["app/view/review/index.php","app/view/surat/form.php","app/view/surat/index.php"]
---

# Surface brief: PENA aplikasi (surat, review lewat tautan)

Mode: Operate. Pengguna: sekretaris (laptop dan HP seimbang), pemeriksa tanpa akun di HP.
Tugas: menyusun surat, mengajukan, mengirim tautan, menindaklanjuti revisi; pemeriksa memutuskan.
Batasan: nama PENA dan aksen biru tinta wajib (PRODUCT.md). Kertas surat sama dengan Word/PDF.
Build: code-led, Tailwind di template PHP MVC, didahului desain Figma sebagai patokan.

## Direction contract

THESIS: Status surat dibaca seperti jadwal keberangkatan: setiap surat dan setiap pemeriksa adalah satu baris di kolom tetap, dan satu pelat terbalik per layar menyatakan langkah berikutnya. Menolak dasbor SaaS putih dengan kartu dan badge pil.

OWN-WORLD: Rail dan bidang navigasi kuning goldenrod, teks karbon; panel tabel batu tulis gelap untuk rute; latar kerja abu sangat terang netral; kertas surat putih. Merah vermilion hanya revisi atau galat, hijau botol hanya disetujui, biru tinta hanya aksi utama, tautan, dan fokus. Satu ukuran huruf untuk seluruh antarmuka (16 px), peringkat lewat ketebalan, huruf kapital kondensed untuk kode dan kepala kolom, dan pembalikan warna. Tanda status di sel tetap: isi berarti beres, kosong berarti menunggu, coret vermilion berarti revisi, centang hijau berarti setuju.

STORY: Sekretaris melihat di mana setiap surat berada dan apa yang ditunggu dari dirinya, lalu menekan satu aksi. Pemeriksa di HP membaca surat dan memutuskan tanpa belajar apa pun.

FIRST VIEWPORT: Detail surat desktop 1440. Kiri rail goldenrod 220 px (PENA, Beranda, Surat, Buat surat). Kolom tengah kertas surat putih. Kolom kanan 520 px: judul dan kode surat, tabel jadwal persetujuan (Tahap, Pemeriksa, Status, Waktu), lalu pelat karbon terbalik "Berikutnya" berisi aksi biru tinta "Buat tautan untuk dikirim".

FORM: Jadwal saku penerbangan (rw-timetable-slide-rack), kartu ke-2 dari hand register lebih berani, dipilih pemilik. seed:1908f8b73e282859. Kemiringan 11 derajat tidak dipakai pada wadah karena mengganggu keterbacaan kerja; huruf kapital hanya untuk kode dan kepala kolom pendek tanpa tracking lebar.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
