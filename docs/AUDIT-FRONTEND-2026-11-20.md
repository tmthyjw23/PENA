# Audit frontend PENA — 20 November 2026 WITA

Tindak lanjut: revisi F01–F10 dan panduan pengguna sudah diterapkan pada 20 November 2026.
Lihat [perubahan, pengujian dan batas hasil](UX-GUIDANCE-2026-11-20.md). Temuan di bawah
merekam keadaan sebelum perbaikan dan tetap disimpan sebagai bukti audit.

## Kesimpulan

Frontend mempunyai identitas yang konsisten: rail kuning, panel karbon untuk tindakan berikutnya,
tipografi Barlow, kertas surat terpisah, kontrol native, serta status berupa teks dan ikon. Fondasi
ini layak dipertahankan. Masalah paling mendesak ada pada kombinasi state formulir, aturan CSS yang
menjangkau komponen bersarang, perlindungan pekerjaan belum disimpan, dan akses keyboard.

Dua temuan perlu dibereskan sebelum demo fitur terkait: penyimpanan Google dapat terblokir oleh
URL kustom tersembunyi, dan tombol Hapus gambar hampir tidak terlihat pada panel tanda tangan.
Tidak ada skor persentase kesiapan; cakupan uji ini tidak mendukung klaim seluruh frontend sudah
aksesibel atau siap untuk semua perangkat. Audit ini tidak mengubah kode UI atau deployment.

## Skill dan metode

- `D:/Agent-Assets/Skills/frontend/frontend-ui-engineering/SKILL.md`: desain sistem, responsive,
  hierarchy, state interaksi, keyboard dan WCAG. Contoh React diadaptasi ke HTML/PHP/JS yang ada;
  skill tidak menjadi alasan mengganti framework dosen.
- `D:/Agent-Assets/Skills/frontend/modern-web-guidance/SKILL.md`: pencarian CLI
  `accessible loading feedback`; retrieve `accessible-error-announcement`, `viewport-aware-dropdowns`,
  `responsive-table`. Ketiganya dipakai sebagai acuan evaluasi, tanpa implementasi baru.
- `design-state-ownership` dibaca untuk menentukan relevansi. Paket skill Redux dan Chrome extension
  tidak diterapkan: dependensi/pola itu tidak digunakan PENA dan tidak diperlukan untuk audit ini.
- `DESIGN.md` dan `docs/FIGMA-HANDOFF.md` diperiksa sebagai arah lokal. File Figma tidak diperiksa
  ulang melalui API; tidak ada klaim kesesuaian pixel-perfect terhadap file Figma.
- Salinan kode dari rilis aktif `/srv/pena/release-20261120T023949Z`; `.env` dan storage produksi
  dikecualikan. Database/user/storage/session/server terpisah, seluruh identitas dan surat fiktif.
- Chrome headless melalui CDP dan axe-core 4.14.1: **18 konteks layar, 46 snapshot pengukuran,
  36 scan axe**. Semua konteks pada 320/1440 px; lima konteks surat organisasi juga pada 768/1024 px.
  Tidak menjalankan axe pada semua ukuran tengah. Pengukuran tambahan untuk panel tanda tangan
  terbuka, perpindahan breakpoint tanpa reload, dropdown terbuka dan state formulir tersembunyi.
- Halaman publik: landing, login, daftar, lupa kata sandi. Aplikasi: beranda/surat kosong ruang pribadi,
  buat surat AI, pengaturan AI, operator ringkasan/akun/layanan/log, beranda/daftar/detail/edit/manual
  organisasi, dan review tamu. Halaman hasil AI diperiksa dari kode, tidak dibangkitkan lewat provider.
- Bukti otomatis: `docs/previews/frontend-audit-2026-11-20/audit.json`, `interactions.json`, `extra.json`,
  `resize.json`, `signature-contrast.json`; screenshot terpilih di folder yang sama. Token fixture pada
  route hasil disamarkan. Kredensial/session/manifest pengujian tidak disalin ke laporan atau repo.

## Temuan terurut

P1 berarti menghambat tugas utama atau kontrol penting sangat sulit dibaca. P2 berarti gangguan
penggunaan/aksesibilitas yang terverifikasi. P3 berarti usulan penyempurnaan atau keputusan produk.
Urutan ini adalah prioritas kerja dalam proyek, bukan skor CVSS atau sertifikasi WCAG.

### F01 — P1: field endpoint tersembunyi dapat memblokir simpan Google

**Bukti browser:** pilih Endpoint kustom, isi `not a url`, beralih ke Google, lengkapi semua field
Google, kata sandi dan consent. `form.checkValidity()` tetap `false`; satu-satunya kontrol tidak
valid adalah `base_url`, dengan `hidden=true`, `disabled=false`, `willValidate=true`.

**Penyebab:** `app/view/preferensi/ai.php:38` hanya menyembunyikan pembungkus endpoint. Validasi
native tetap berlaku pada input `type=url` di dalam pembungkus tersembunyi.

**Dampak:** pengguna dapat gagal menyimpan meskipun seluruh isian yang terlihat sudah benar.
Pesan native juga tidak bisa memfokuskan kontrol yang tersembunyi.

**Perbaikan:** nonaktifkan input endpoint saat provider bukan custom; pertahankan validasi server.
Tambahkan regresi perpindahan custom-invalid→Google/platform/preset lain. Tidak perlu pustaka baru.

### F02 — P1: tombol Hapus gambar hampir tidak terlihat

**Bukti:** pada review tamu dengan panel tanda tangan terbuka, teks tombol `#f6f2e4` berada di atas
`#ffffff`: axe mengukur **1,12:1**, sementara teks biasa perlu 4,5:1 menurut acuan WCAG.
Screenshot: `signature-390.png`. Ini tombol aktif, bukan kontrol disabled yang dikecualikan.

**Penyebab:** `resources/css/app.css:117`, `.plate .btn-secondary`, menjangkau semua keturunan,
termasuk tombol di dalam `details[data-signature]` yang latarnya putih. Komponen terkait:
`app/view/surat/_signature.php:13` dan `app/view/review/index.php:91`.

**Perbaikan:** batasi varian tombol panel gelap ke konteks yang benar atau berikan varian permukaan
terang secara eksplisit pada komponen tanda tangan. Uji drawn/upload pada review dan pengajuan
pribadi, dalam kondisi panel terbuka. Scan halaman saat disclosure tertutup tidak menangkap ini.

### F03 — P2: perubahan draf tidak dijaga saat meninggalkan halaman

**Bukti:** di Ubah draf, ubah nama kegiatan menjadi nilai audit, klik Beranda, lalu buka kembali
Ubah draf. Nilai kembali ke yang tersimpan; tidak ada peringatan perubahan belum disimpan. Source
form tidak mempunyai dirty-state guard atau autosave. Browser Back mungkin memulihkan state pada
sebagian kondisi; audit ini tidak mengklaim semua jalur Back selalu menghapus isian.

**Dampak:** pengguna bisa kehilangan pekerjaan lokal ketika berpindah menu/ruang kerja tanpa sadar.
Keberhasilan AI dan data yang sudah disimpan di server tetap merupakan state yang berbeda.

**Perbaikan:** tandai form berubah, tunjukkan status Belum disimpan, dan beri konfirmasi hanya saat
meninggalkan form kotor. Bersihkan guard setelah simpan. Autosave memerlukan keputusan lanjutan
tentang kegagalan/koneksi/konflik; tidak perlu langsung menambahkannya untuk menutup risiko awal.

### F04 — P2: fokus keyboard berjalan di belakang skeleton layar penuh

**Bukti simulasi browser:** panggil fungsi produksi `PenaFeedback.begin` pada fixture, mulai fokus
dari input kata sandi lalu Tab. Fokus pindah ke Simpan pengaturan AI, skip link, logo dan Beranda
sementara seluruh kontrol tertutup overlay. Bukti di `interactions.json`.

**Penyebab:** `public/assets/js/feedback.js:30` dan `resources/css/app.css:151` menambahkan lapisan
visual fixed, tanpa mengelola fokus atau membuat konten di belakangnya inert. `pointer-events:none`
pada tombol pending tidak membatasi keyboard.

**Perbaikan:** utamakan skeleton pada area yang sedang diproses. Jika overlay global dipertahankan,
buat status fokus yang terlihat, atur inert hanya pada konten di belakangnya, dan pulihkan fokus
sesudah selesai/gagal. Jangan memindahkan fokus ke status non-focusable lalu menganggapnya berhasil.
Ini sejalan dengan tujuan WCAG Focus Not Obscured; bukan hasil pengujian screen reader nyata.

Catatan metode: percobaan menahan POST melalui CDP menahan evaluasi navigasi harness, sehingga
dihentikan. Temuan fokus di atas berasal dari simulasi fungsi yang sama dan pemeriksaan kode;
tidak diklaim sebagai pengukuran end-to-end jaringan lambat.

### F05 — P2: tabel surat yang bergulir tidak dapat difokuskan dengan keyboard

**Bukti:** pada 320 px, detail surat dan review tamu memunculkan `scrollable-region-focusable`.
Target `div.overflow-x-auto[data-block=daftar_barang]` berisi tabel tanpa kontrol focusable.
Pada 1440 px, data fixture muat sehingga masalah ini tidak terdeteksi.

**Sumber:** `app/view/surat/_preview.php:34`.

**Perbaikan:** sediakan wrapper scroll yang focusable dan bernama, indikator fokus, serta petunjuk
geser bila memang overflow. Pertahankan tabel semantik dan relasi header. Jangan menumpuk tabel
surat menjadi kartu bila hasilnya mengubah makna atau menjauh dari pratinjau dokumen.

### F06 — P2: baris editor tabel memakai breakpoint sekali saat dibuat

**Bukti:** buka manual pada 320 px, lalu ubah ke 1440 tanpa reload. Header berubah menjadi grid,
baris lama tetap `grid-template-columns:1fr`. Tambah baris: baris baru memakai lima kolom sementara
baris lama tetap satu kolom. `resize.json` menyimpan kedua gaya pada layar yang sama.

**Sumber:** `app/view/surat/_rows_editor.php:22`, `matchMedia` hanya dipanggil dalam `addRow`.

**Perbaikan:** gunakan aturan responsive CSS untuk semua baris atau satu listener breakpoint yang
memperbarui seluruh baris. Uji resize/orientasi tanpa reload, termasuk baris yang sudah berisi data.

### F07 — P2: dropdown ruang kerja melewati tepi layar sempit

**Bukti:** menu terbuka pada 320 px memiliki batas kanan **323,67 px**, membuat dokumen selebar
324 px. Source `app/view/layout/app_top.php:35` memakai lebar tetap `w-64`, diposisikan relatif
terhadap trigger yang sudah bergeser dari sisi kiri. Screenshot `dropdown-320.png`.

**Perbaikan:** batasi lebar berdasarkan ruang yang tersedia dan jepit posisi panel ke viewport.
Periksa keyboard serta nama organisasi panjang. Anchor positioning/popover bisa menjadi pilihan
enhancement, tetapi perlu fallback; perbaikan ini tidak mensyaratkan migrasi ke API baru.

### F08 — P2: kontras teks pendamping login tidak cukup

**Bukti:** paragraf Lihat draf, status pemeriksaan… memakai `#56554f` di atas kuning `#e7b53b`:
**3,94:1** untuk teks 16 px normal. Source `app/view/login/index.php:18`. `login-1440.png`.

**Perbaikan:** gunakan warna teks rail yang cukup gelap untuk konteks kuning; jangan menggelapkan
seluruh token secondary karena token itu dipakai pula di permukaan lain yang sudah lolos.

### F09 — P2: landmark halaman autentikasi belum lengkap

**Bukti:** login dan daftar memunculkan `landmark-one-main` serta `region`. Landing hanya `region`
untuk sebagian konten di luar landmark. Ini temuan struktur/best-practice, bukan berarti setiap
halaman tanpa main otomatis melanggar seluruh WCAG. Shell aplikasi utama sudah memiliki main.

**Perbaikan:** pastikan tugas utama form autentikasi berada dalam satu main dan struktur header/
footer tepat. Pertahankan heading berurutan dan label form native yang sudah tersedia.

### F10 — P2, pemeriksaan kode: error per-field hasil AI belum ditautkan konsisten

**Bukti sumber:** `app/view/surat/generate_hasil.php:113`/`:117` menunjuk `aria-describedby` hanya
ke sumber isian. Pesan error di bawahnya tidak mempunyai ID yang ikut direferensikan; textarea
paragraf pada `:143` diberi aria-invalid tetapi tidak terhubung ke pesan error. Form manual sudah
memiliki helper untuk menautkan error, sehingga pola yang lebih baik tersedia di proyek.

**Perbaikan:** berikan ID pada error yang terlihat, gabungkan dengan hint/sumber saat invalid,
serta fokuskan ringkasan/field invalid setelah kegagalan submit. Buktikan dengan hasil AI fixture
yang mempunyai error. Halaman hasil AI tidak dirender pada audit ini; dampak AT belum diuji nyata.

## Usulan UX/copy yang memerlukan pertimbangan

1. **Perihal tidak tersedia di Ubah draf.** `surat/form.php:122` membatasi field title hanya saat
   membuat surat; `surat::perbarui` tidak menerima judul. Pengguna yang salah menulis perihal harus
   membuat ulang draf atau menemukan jalan lain. Usul izinkan ubah sebelum diajukan dan pastikan
   nomor/snapshot tidak ikut berubah tanpa alur versi. Ini membutuhkan perubahan model/controller,
   bukan semata penggantian label frontend; audit ini belum mengimplementasikannya.
2. **Hierarchy judul aplikasi datar.** H1 dan banyak H2/label sama-sama 16 px bold pada app shell.
   Ini sesuai aturan desain lama satu ukuran 16/24, sehingga saya tidak menandainya sebagai bug.
   Pertimbangkan judul halaman 20–24 px dengan label tetap 16; validasi terhadap desain yang dipilih.
   Ruang kosong pada beranda dengan sedikit surat tidak perlu diisi grafik dekoratif.
3. **Penjelasan pengaturan AI padat.** Pertahankan ringkasan risiko dan consent penting tetap terlihat;
   pindahkan detail tambahan ke disclosure atau bantuan kontekstual. Jangan menghapus penjelasan
   pihak yang menerima key/data hanya untuk memendekkan halaman.
4. **Copy login tidak mencakup pemakai pribadi.** Kalimat Gunakan akun yang terdaftar di organisasi
   Anda menyiratkan keanggotaan organisasi wajib. Alternatif: Masuk dengan akun PENA Anda.
   Rapikan review/direview/pemeriksaan berdasarkan state yang benar tanpa mengklaim pemeriksa sedang
   membaca jika sistem baru mencatat permintaan menunggu.

## Hal yang sudah cukup baik dalam cakupan ini

- Tidak ditemukan uncaught JavaScript error pada 46 snapshot valid. Ini tidak menjamin semua
  cabang event, seluruh console warning, atau semua peramban bebas masalah.
- Tidak ada overflow seluruh halaman pada snapshot menu tertutup; overflow terverifikasi muncul
  saat dropdown terbuka. Scroll lokal tabel tetap membutuhkan akses keyboard sebagaimana F05.
- Empty state ruang pribadi, status teks+ikon, navigasi tiga menu, indikator langkah aktif,
  tombol primer, focus-visible global dan skip link pada shell aplikasi tersedia.
- Kontrol input memakai elemen native dan label; dialog surat sudah memakai dialog dengan judul.
- Font self-hosted, CSS dibangun, feedback.js defer, dan tidak ada alasan menambah Redux/React.
  Total transfer resource landing pada satu pengamatan pertama sekitar 138 KB; ini data lab tunnel,
  bukan Core Web Vitals lapangan atau skor Lighthouse. Tidak menyimpulkan INP/LCP sudah memenuhi target.
- Google memakai model/endpoint awal otomatis; kunci tidak ditampilkan kembali pada UI yang diuji.
  Audit frontend ini tidak membuktikan keamanan backend atau keberhasilan provider nyata.

## Urutan perbaikan yang direkomendasikan

1. **Sebelum demo fitur:** F01 dan F02; tambahkan regresi state tersembunyi dan disclosure terbuka.
2. **Stabilkan interaksi:** F03–F06, termasuk fokus dan perubahan breakpoint tanpa reload.
3. **Rapikan aksesibilitas lintas halaman:** F07–F10; verifikasi ulang dengan keyboard dan axe.
4. **Keputusan UX:** perihal draf, hierarchy, kepadatan bantuan dan copy. Pertahankan identitas PENA
   dan fondasi MVC yang diwajibkan. Jangan memulai redesign menyeluruh sebelum gangguan tugas beres.

Kriteria selesai perbaikan: skenario reproduksi tidak gagal lagi, teks aktif cukup kontras,
Tab tidak menuju kontrol tertutup, tabel dapat digeser dengan keyboard, seluruh baris editor
konsisten setelah resize, perubahan belum disimpan mendapat perlindungan yang disepakati, dan
tidak ada regresi pada pengajuan/persetujuan versi. Audit ini sendiri belum memenuhi kriteria itu
karena permintaan saat ini adalah evaluasi, bukan penerapan perbaikan.

## Batas audit dan referensi

Belum menjalankan NVDA/VoiceOver, Safari/Firefox, ponsel fisik, pembesaran font OS atau pengujian
pengguna penyandang disabilitas. Belum mengukur CWV produksi/Lighthouse/INP, menguji model AI nyata,
atau mengulang seluruh ekspor dan tanda tangan secara end-to-end. Hasil AI hanya source review.
Automated axe membantu menemukan masalah, tidak memberikan sertifikasi WCAG ketika hasilnya nol.

Harness sempat salah menganggap field title tersedia pada edit dan salah membaca map token fixture;
keduanya diperbaiki. Snapshot guest bertoken kosong dibuang lalu diulang dengan token fixture sah.
Pengukuran empty state diulang dengan pemilihan ruang pribadi eksplisit. Tidak ada data pengguna,
API key nyata, email atau tindakan pada akun produksi yang digunakan untuk audit ini.

- [WCAG Contrast Minimum](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html)
- [WCAG Focus Not Obscured](https://www.w3.org/WAI/WCAG22/Understanding/focus-not-obscured-minimum.html)
- Skill Modern Web Guidance: error state harus ditautkan saat invalid; dropdown harus mempertahankan
  akses/posisi di viewport; wrapper tabel yang overflow perlu akses keyboard dan nama yang jelas.
