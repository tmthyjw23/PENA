# Brief Fase 1: desain PENA di Figma

Untuk: Codex (aplikasi, dengan plugin Figma). Auditor: Claude. Pemilik keputusan: pemilik proyek.
Tempel seluruh isi file ini ke Codex.

---

## Peranmu

Kamu desainer produk untuk PENA. Hasil kerjamu adalah file Figma yang menjadi **patokan tunggal**
UI/UX untuk fase berikutnya (UI statis Tailwind, AI, fitur). Fase ini **hanya Figma**: jangan mengubah
kode di repo, kecuali menulis satu file serah terima `docs/FIGMA-HANDOFF.md` di akhir.

Targetnya terlihat dibuat desainer, bukan AI: punya identitas, tiap keputusan punya alasan,
tidak ada pola template.

## Wajib dibaca sebelum mulai

1. `D:\collage\AI\FINALE\pena\docs\ALUR-PENGERJAAN.md` (urutan fase, kamu di Fase 1)
2. `D:\collage\AI\FINALE\pena\DESIGN.md` (jawaban pemilik: modern ramah, aksen biru tinta)
3. Skill (baca SKILL.md beserta referensinya, terapkan, jangan hanya dikutip):
   - antislop: `D:\Agent-Assets\Skills\workflow\antislop\SKILL.md`
     Mode sudah dipilih pemilik: **during**. Jangan tanyakan lagi.
   - design-taste-frontend: `D:\Agent-Assets\Skills\design\design-taste-frontend\SKILL.md`
     (bagian landing/hero hanya untuk halaman Landing; sisanya produk UI)
   - impeccable: `C:\Users\ASUS\.claude\skills\impeccable\SKILL.md` (pakai untuk audit diri di akhir)
   - ui-ux-pro-max: `D:\Agent-Assets\Skills\design\ui-ux-pro-max\SKILL.md`
     (bila menjalankan `--design-system`, perlakukan hasilnya sebagai saran; sebelumnya ia menyarankan
     ungu + emas, dan itu ditolak karena pola bawaan AI)
   - apple-design: `D:\Agent-Assets\Skills\design\apple-design\SKILL.md` (gerak dan detail interaksi)
   - humanizer: `D:\Agent-Assets\Skills\writing\humanizer\SKILL.md` (semua teks UI)

Bila dua skill bertentangan, ikuti DESIGN.md dan aturan Hard Gate antislop, lalu catat konfliknya.

## Tentang PENA

Aplikasi web untuk **sekretaris organisasi mahasiswa** (contoh: BEM Fakultas Ilmu Komputer) menyusun
surat resmi dari kerangka, lalu meminta persetujuan bertahap (Ketua Panitia → Advisor → Dekan).
Pemeriksa **tidak punya akun**: mereka membuka tautan sekali pakai, biasanya dari WhatsApp di HP.

Aturan bisnis yang harus terlihat di desain:
- Fakta resmi (tanggal, nama, tempat, nominal, daftar barang) diisi sekali di "Data surat".
  Paragraf hanya merujuk lewat penanda `{{isian}}`, jadi fakta tidak diketik ulang di kalimat.
- Nomor surat dibuat otomatis saat diajukan, contoh `001/BEM-FILKOM/PMJ/XI/2026`, dan tetap
  sama saat revisi.
- Setelah diajukan, isi versi terkunci. Perubahan = revisi = versi baru.
- Tahap berjalan berurutan; satu tahap bisa berisi beberapa pemeriksa, semua harus setuju.
- Pemeriksa bisa: setujui, minta revisi (proses berhenti), atau komentar (proses jalan terus).
- Setelah revisi, penyetuju versi lama mengonfirmasi ulang secara paralel dan melihat tabel
  perubahan "sebelumnya → sekarang".
- Tautan disimpan sebagai hash, jadi **hanya bisa ditampilkan sekali**. Saat tahap berikutnya
  terbuka, sekretaris harus menekan "Buat tautan untuk dikirim" lalu mengirimnya sendiri.
- Identitas pemeriksa tidak diverifikasi. Ini harus dinyatakan jujur di halaman review dan riwayat.
- Pratinjau kertas surat harus sama dengan hasil Word/PDF: Times New Roman 12pt, kop dengan garis
  ganda, tabel bergaris, blok tanda tangan. **Jangan mendesain ulang isi kertas surat**; desain
  wadah dan anotasinya saja (misalnya sorotan bagian yang diminta revisi).

Status surat: Draf, Sedang direview, Diminta revisi, Disetujui, Dibatalkan.
Status per pemeriksa: Belum diajukan, Menunggu tahap sebelumnya, Menunggu keputusan,
Disetujui, Minta revisi, Digantikan revisi, plus penanda "konfirmasi ulang".

Aplikasi yang jalan bisa dilihat untuk memahami alur (bukan untuk ditiru gayanya):
`http://127.0.0.1:8787` (server lokal pemilik). Akun contoh lokal tersedia di halaman Masuk
("Sekretaris BEM"). Tampilan yang sekarang hanya sementara dan masih membawa sisa template dosen.

## Halaman dan state (desktop 1440 dan mobile 375)

1. **Landing**: apa itu PENA, alur singkat, batasan jujur (tanpa akun pemeriksa, PDF butuh Word,
   AI belum ada di versi awal). Satu CTA "Masuk". Tanpa statistik, testimoni, atau logo karangan.
2. **Masuk**: form, error login, tombol isi akun contoh (khusus lokal).
3. **Beranda**: "Perlu tindakan" (diminta revisi, pemeriksa menunggu tautan, draf belum diajukan),
   ringkasan jumlah per status. State: ada tindakan, tidak ada tindakan, belum ada surat,
   akun belum terdaftar di organisasi.
4. **Daftar surat**: perihal, nomor, status, diperbarui. State: isi, kosong.
   Mobile: baris bertumpuk, bukan tabel geser.
5. **Buat surat**:
   - pilih jenis surat (saat ini 2: Surat Peminjaman PMJ, Surat Permohonan PMH);
   - Data surat: teks, tanggal, nominal Rp, dan isian tabel (baris barang: nama, jumlah, keterangan,
     tambah/hapus baris);
   - Isi surat: teks baku terkunci, paragraf yang bisa ditulis, chip penanda untuk disisipkan,
     panduan per paragraf, dan **slot "Bantu tulis dengan AI"** (dipakai di Fase 4, beri tanda
     "segera" di desain, jangan dibuat seolah sudah jalan);
   - Alur persetujuan: nama tahap + pilih pemeriksa (multi), tahap opsional;
   - error validasi per isian dan ringkasan error.
6. **Ubah draf**: sama tanpa pilih jenis dan alur.
7. **Detail surat**, satu frame per keadaan:
   draf (ubah, ajukan dengan konfirmasi) · baru diajukan (panel tautan sekali tampil + salin) ·
   tahap berikutnya menunggu tautan · diminta revisi (komentar revisi menunjuk bagian surat,
   mulai revisi dengan ringkasan perubahan) · disetujui (tombol unduh Word dan PDF untuk Fase 5,
   termasuk keadaan "ekspor gagal, coba lagi") · dibatalkan.
   Komponen: pratinjau kertas, rute tahap persetujuan, kartu pemeriksa (ganti pemeriksa,
   buat tautan baru), komentar, riwayat, sidik konten versi.
8. **Review lewat tautan** (prioritas mobile): menunggu keputusan · konfirmasi ulang dengan tabel
   perubahan · bukan giliran (menunggu tahap sebelumnya) · validasi catatan revisi kosong.
   Tombol keputusan harus terjangkau ibu jari.
9. **Selesai dan error**: terima kasih (setuju / revisi terkirim), tautan kedaluwarsa atau sudah
   dipakai, 403/404/500.

Juga desain: konfirmasi sebelum ajukan dan batalkan, toast/flash sukses, state loading
(skeleton yang sesuai bentuk), fokus keyboard.

## Struktur file Figma

- `00 Brief`: Design Read, dial ENERGY/RHYTHM/MOTION, alasan tiap keputusan besar (satu baris
  per keputusan: warna, tipografi, layout, jarak, kartu, ikon).
- `01 Design System`: **Variables** (warna, jarak, radius, ukuran teks) dengan nama yang mudah
  dipetakan ke token Tailwind; text style; komponen dengan variant (tombol, input, select,
  textarea, checkbox, chip status, chip penanda, kartu pemeriksa, rute tahap, baris daftar,
  alert, empty state, nav desktop, tab bar mobile, panel tautan, tabel perubahan).
- `02 Desktop 1440` dan `03 Mobile 375`: semua halaman dan state di atas, nama frame
  `NN Halaman / State`.
- `04 Alur`: prototipe klik untuk dua jalur: sekretaris (buat → ajukan → kirim tautan) dan
  pemeriksa di HP (buka tautan → setujui / minta revisi).

## Batasan (Hard Gate)

- Bahasa Indonesia yang wajar dan spesifik. **Tanpa em dash (—)**. Tanpa kata "seamless",
  "revolusioner", "berbasis AI" sebagai jualan. CTA spesifik ("Ajukan untuk review", bukan "Mulai").
- Tanpa angka, testimoni, logo, atau klaim keamanan karangan. Data contoh yang realistis boleh,
  ditandai sebagai contoh.
- **Nol sisa template dosen** di alur PENA: tidak ada "Kelola Mahasiswa", ikon `</>`,
  "Template Sederhana untuk Mahasiswa", badge nama database. Contoh bawaan template cukup
  diakses dari menu akun.
- Kontras WCAG AA (4,5:1 teks normal), target sentuh minimal 44 px, status tidak hanya warna.
- Satu keluarga ikon, satu skala radius, maksimal 2-3 warna inti + 1 aksen.
- Tema: terang. Alasannya: objek utama adalah surat cetak. Bila kamu mengusulkan mode gelap,
  tulis alasannya dan desain keduanya sampai tuntas.
- Huruf harus tersedia gratis untuk web (Google Fonts atau self-host).

## Cara kerja

1. Baca semua bahan di atas. Tulis Design Read satu baris dan nilai dial di `00 Brief`.
2. Bila ada yang benar-benar ambigu, ajukan **satu** pertanyaan ke pemilik, lalu lanjut.
3. Kerjakan Design System dulu, lalu Mobile 375 untuk Review lewat tautan (pengguna paling rentan),
   lalu halaman lainnya.
4. Audit diri dengan impeccable (skor dan temuan P1-P4) dan Delivery Gate antislop. Perbaiki semua
   temuan prioritas tinggi sebelum serah terima.
5. Tulis `docs/FIGMA-HANDOFF.md`: tautan file Figma, daftar frame, token (nama → nilai),
   keputusan beserta alasan, hasil audit, dan hal yang sengaja belum didesain.

Tenggat proyek 21 Nov 2026; target Fase 1 selesai 18 Nov 2026. Utamakan kelengkapan state
halaman inti daripada hiasan.

## Yang akan diaudit Claude

Kecocokan dengan aturan bisnis di atas, kelengkapan state, Hard Gate antislop, kontras,
konsistensi token dan komponen, keterbacaan di 375 px, dan apakah desain bisa dibangun dengan
Tailwind tanpa trik rapuh.
