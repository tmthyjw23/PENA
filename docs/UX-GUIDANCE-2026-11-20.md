# Revisi frontend dan panduan pengguna PENA — 20 November 2026 WITA

Pembaruan 21 November: panel pengantar diganti hint pada kontrol, provider pribadi wajib bagi akun biasa, AI VPS khusus operator. Rincian terkini: [hint dan akses AI](HINTS-AI-ACCESS-2026-11-21.md). Bagian petunjuk di bawah mencatat rancangan 20 November.

## Hasil

Permintaan: terapkan revisi audit frontend dan beri petunjuk penggunaan bagi pengguna baru dengan
acuan HCI/usability heuristics. Perubahan mempertahankan PHP native MVC, identitas PENA, CSS
Tailwind yang dibangun dan JavaScript biasa. Tidak menambah React, Redux, tour library atau CDN.

## Temuan audit yang ditangani

| Audit | Perubahan |
|---|---|
| F01 URL tersembunyi | Input endpoint dinonaktifkan bila provider bukan custom atau mode bukan pribadi; required hanya saat aktif. Validasi server tetap berlaku. |
| F02 tombol tanda tangan | Varian surface terang serta outline fokus pada signature-panel mengatasi pewarisan warna dari plate gelap. |
| F03 pekerjaan belum disimpan | Form manual/edit, hasil AI, permintaan AI dan pengaturan AI mempunyai dirty flag di memori, status Belum disimpan, konfirmasi sebelum navigasi/menu/pergantian ruang, serta beforeunload untuk reload/keluar. Tidak menyimpan key, password atau isian ke localStorage. |
| F04 fokus skeleton | Overlay memindahkan fokus ke status yang dapat difokuskan, membuat konten belakang inert, menyimpan keadaan inert semula dan memulihkan fokus pada selesai/timeout/pageshow. POST yang belum pasti tetap tidak dibuka untuk kirim ulang otomatis. |
| F05 tabel pratinjau | Wrapper overflow mempunyai tabindex=0, role region dan nama tabel; tabel semantik tetap digunakan. |
| F06 editor tabel | Grid baris mengikuti CSS media query dan jumlah kolom CSS variable, bukan hasil matchMedia satu kali. Baris lama/baru konsisten saat resize. |
| F07 pemilih ruang | Panel ponsel terikat inset viewport, tinggi dibatasi dan dapat bergulir; tidak keluar ke kanan pada 320 px. |
| F08 kontras login | Teks pada rail kuning memakai token ink, bukan secondary yang kontrasnya tidak cukup. |
| F09 landmark | Login/daftar mempunyai satu main; bagian pengantar diberi region bernama. Intro landing juga diberi nama. |
| F10 error hasil AI | Error isian, paragraf, kop dan judul memiliki ID dan ditautkan dari field; ringkasan error dapat difokuskan sesudah server mengembalikan masalah. Input editor tabel mewarisi deskripsi/error sumbernya. |

Perbaikan tambahan: role status yang tidak valid pada ul peringatan hasil AI dihapus sementara
aria-live dipertahankan; Enter pada instruksi rewrite menghormati composition; teks hasil rewrite,
chip isian dan editor baris menandai form berubah. Listener close-on-outside hanya untuk menu,
sehingga bantuan, model dan panel tanda tangan tidak tertutup hanya karena pengguna berinteraksi
dengan isian lain. Judul halaman 24/32 dan bagian 18/26 memperjelas hierarchy, body tetap 16/24.
Copy login mencakup ruang pribadi; label status/tindakan memakai istilah pemeriksaan secara konsisten.

### Perihal draf dan keutuhan versi

Perihal kini tersedia pada Ubah draf dan dapat diubah hanya selama versi belum diajukan. Service
memvalidasi panjang/isi dan menolak perubahan pada versi terkunci. Submit membekukan title dalam
snapshot baru dan kolom letter_versions.title. Render versi lama dan halaman review menggunakan
judul versinya; perbandingan konfirmasi ulang juga mencantumkan perubahan perihal.

Migrasi 009 menyalin judul yang saat ini diketahui ke kolom baru untuk versi lama. Snapshot,
content_hash, fields dan blocks versi terkunci tidak ditulis ulang. Hash versi lama memang belum
memuat title secara terpisah pada implementasi terdahulu; migrasi tidak mengklaim menambah bukti
kriptografis historis yang sebelumnya tidak ada. Snapshot baru memasukkan title ke canonical hash.
Perubahan perihal draf revisi tidak mengubah hasil render judul versi lama dalam tes regresi.

## Petunjuk pengguna baru

- Setelah email terverifikasi, akun baru mendapat panel Mulai menggunakan PENA di Beranda:
  pilih ruang, buat/periksa draf, simpan, lalu selesaikan/ajukan dan kirim tautan yang diperlukan.
- Saya mengerti dan Lewati dulu menutup panel, disimpan per akun di users.guide_dismissed_at,
  sehingga tidak berulang saat pindah perangkat/masuk lagi. Ini marker preferensi, bukan bukti bahwa
  pengguna telah membaca atau menguasai alur. Tidak ada modal otomatis atau penguncian aplikasi.
- Akun yang sudah ada sebelum migrasi tidak dipaksa melihat panel pengenalan. Semua akun dapat
  membuka Bantuan di sidebar/menu akun ponsel. Panduan dan topik tugas tetap tersedia setelah dilewati.
- Bantuan `/bantuan/`: memilih ruang, menyusun/periksa draf, persetujuan/tanda tangan, revisi/unduhan,
  arti status, AI pribadi dan penyelesaian kendala. Navigasi topik berupa tautan anchor native.
- Petunjuk kontekstual berupa details pada Buat surat, Periksa draf, form manual/edit, detail surat,
  pengaturan AI dan review tamu. Pengguna dapat membacanya sambil mengisi, bukan menghafal satu tour.
- Reviewer tamu mendapat petunjuk di halaman review tanpa dipaksa membuat akun. Tidak memakai
  fingerprint atau cookie pelacak untuk menentukan apakah tamu pernah membuka panduan.

### Hubungan dengan heuristik

| Prinsip | Penerapan yang dapat ditunjukkan |
|---|---|
| Visibility of system status | status belum disimpan, skeleton/status proses dan fokus yang terlihat |
| Match with real world | draf, perihal, versi, pemeriksa dan tindakan konkret dalam bahasa Indonesia |
| User control and freedom | Lewati dulu, membuka bantuan lagi, menolak meninggalkan form kotor |
| Consistency and standards | menu/tombol yang sama, dialog/details/validasi native, label pemeriksaan |
| Error prevention | endpoint tersembunyi tidak divalidasi, konfirmasi form kotor, batas edit versi |
| Recognition rather than recall | petunjuk pada halaman tugas, status dan urutan yang dapat dibaca lagi |
| Flexibility and efficiency | panduan dapat ditutup; pemakai lama langsung bekerja; jalur AI/manual |
| Aesthetic/minimalist design | pengantar non-modal sekali, detail bantuan dibuka sesuai kebutuhan |
| Error recovery | error ditautkan ke field, ringkasan fokus dan panduan kendala |
| Help/documentation | Bantuan permanen dan petunjuk kontekstual |

Ini pemetaan keputusan desain terhadap heuristik, bukan bukti seluruh heuristik terpenuhi atau
sertifikasi usability. Efektivitas belajar/waktu menyelesaikan tugas belum diukur pada pengguna nyata.

## Implementasi dan data

Controller baru bantuan; domain UserGuide membaca/menutup preferensi pemilik dari sesi. POST
penutupan memerlukan CSRF dan akun aktif/verified. Form tidak menerima owner yang dipercaya dari
input. Migrasi 008 menambahkan guide_dismissed_at dan menandai akun lama sebelum aktivasi tanpa
mengubah updated_at mereka; akun yang dibuat berikutnya default NULL. Migrasi 009 menambah title
versi; keduanya dapat dipulihkan dari penerapan parsial tanpa menimpa nilai yang sudah tersimpan.

Dirty guard memegang boolean dan status di memori; bukan autosave. Pengguna masih dapat memilih
meninggalkan pekerjaan, browser dapat mengabaikan beforeunload pada kondisi tertentu, dan guard
tidak memulihkan pekerjaan setelah crash/browser dihentikan. Simpan tetap tindakan yang diperlukan.

## Verifikasi yang dijalankan

- Backend terisolasi: lint/check, ux, architecture, schema, workspace, flow, signatures, exports,
  ai-controller, personal-ai, accounts, operator, ai-settings dan unit lulus. Pengujian memakai
  identitas MariaDB yang hanya berhak pada pena_test_*, bukan database produksi.
- UserGuide: unverified/inactive ditolak, akun baru verified mendapat panduan, dismiss persisten,
  idempoten dan tidak menutup panduan pengguna lain. Browser juga mengirim user_id palsu milik
  fixture lain saat dismiss; yang berubah tetap akun sesi.
- Judul: edit sebelum submit, validasi kosong/terlalu panjang rollback, submit freeze, penolakan edit
  terkunci, judul lama tetap sama setelah judul draf revisi berubah, hash lama tetap sama, serta
  perbandingan reconfirmation menampilkan perihal.
- Chrome: onboarding tampil; Bantuan/CSRF/dismiss/relogin/skip/reopen; URL custom invalid→Google
  tersimpan tanpa key ditampilkan lagi; fokus overlay/inert dan pemulihan; bantuan tetap terbuka;
  cancel navigasi menjaga nilai yang belum disimpan; perihal tersimpan; error server memfokuskan
  ringkasan; grid lama/baru selaras setelah 320→1440; menu 320 tetap di viewport.
- Guest review: disclosure tanda tangan dibuka, warna tombol diperiksa, axe tidak menemukan
  pelanggaran pada skenario yang dipindai; wrapper tabel focusable. Hasil AI berasal dari fixture
  session yang dirender melalui halaman asli, tanpa provider; error tanggal/paragraf ditautkan
  ke ID yang benar dan ringkasan menerima fokus.
- Axe: introduction, Bantuan, login/daftar 320, guest-signature 390 dan hasil-AI-error 390 lulus
  scan yang dijalankan. Halaman Bantuan/pengaturan/manual/operator diperiksa pada 320/768/1024/1440,
  tanpa overflow dokumen atau uncaught JS error. Screenshot pengantar, bantuan, signature dan
  hasil error diperiksa visual. Nol temuan otomatis bukan sertifikasi WCAG menyeluruh.
- Tidak memakai key Google/provider nyata atau mengirim email. Fixture-only controller pembuat
  state hasil AI tidak dimasukkan dalam paket rilis. Harness sempat salah meng-escape regex split
  pada assertion; diperbaiki. Role ul/status yang ditemukan saat rendering hasil AI diperbaiki
  pada kode, lalu pengujian diulang lulus.

Status aktivasi, backup/restore dan pembersihan fixture dicatat di SESSION.md.

## Modern Web Guidance dan sumber

- `accessible-error-announcement`: aturan "list the error message in aria-describedby" diterapkan
  pada error terlihat; ringkasan error menerima fokus setelah submit yang gagal.
- `viewport-aware-dropdowns`: panel disesuaikan terhadap viewport melalui posisi fixed/inset untuk
  varian mobile. Disclosure native yang sudah ada dipertahankan; tidak memerlukan polyfill anchor.
- `responsive-table`: aturan "Add a tabindex=\"0\" attribute to the container" dipakai bersama nama
  region untuk tabel surat yang overflow; semantik tabel tidak diganti menjadi kartu.
- `content-first-noise-reduction`: "Put long or rarely needed content ... in a details element"
  menjadi acuan bantuan sekunder. Informasi risiko key/data yang penting tetap terlihat.
- `persistent-app-tours` dipelajari; pola overlay tethered tidak diterapkan karena pengantar inline
  dan petunjuk sesuai tugas mencukupi kebutuhan. Tidak memperkenalkan API dengan fallback tambahan.
- [Nielsen: 10 usability heuristics](https://www.nngroup.com/articles/ten-usability-heuristics/)
- [WCAG Contrast Minimum](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html)
- [WCAG Focus Not Obscured](https://www.w3.org/WAI/WCAG22/Understanding/focus-not-obscured-minimum.html)

Belum NVDA/VoiceOver, Safari/Firefox, ponsel fisik, tes pengguna baru sungguhan, atau CWV produksi.
Ukuran font OS dan kondisi crash/offline ekstrem belum diuji. Bahasa/perilaku beforeunload berasal
dari browser dan tidak sepenuhnya dapat dikendalikan aplikasi.


## Aktivasi dan pemeriksaan penutup

Rilis utama /srv/pena/release-20261120T032420Z mengaktifkan migrasi 008/009. Backup DB/env/master/
storage di /var/backups/pena/ux-20261120T032420Z/before-activation; restore DB terpisah dan hitungan
delapan tabel cocok. Snapshot/content_hash/fields/blocks seluruh versi yang sudah diajukan
dibandingkan sebelum/sesudah migrasi dan tetap sama. Tidak ada perubahan credential/platform AI.

Pemeriksaan penutup menemukan overlay biasa masih berada di belakang native dialog yang terbuka.
Perbaikan: dialog ditutup sementara saat proses dimulai dan dipulihkan bersama fokus sebelumnya
jika halaman tetap terbuka setelah selesai/timeout; notice ditempatkan dalam dialog yang terbuka
bila perlu. Fixture membuktikan status terlihat/terfokus, dialog pulih ke input reason, serta POST
nyata dari dialog membawa field/CSRF dan berhasil. Ini bukan pembatalan request server otomatis.

Rilis akhir /srv/pena/release-20261120T034244Z aktif, dengan backup/restore terpisah di
/var/backups/pena/ux-focus-20261120T034244Z/before-activation. Tidak ada migrasi tambahan pada rilis
penutup. Nginx test/reload FPM/layanan aktif, origin PENA/Egg3l 200, Bantuan anonim 302/login.
Health sebagai user OS pena membuktikan migrasi lengkap, judul versi terkunci tidak NULL, controller
fixture tidak ada, akun lama tidak mendapat intro otomatis, AI bawaan tetap dikonfigurasi. Env dan
master cocok dengan backup. Lint akhir 114 PHP; MVC 12 controller/28 view tanpa query DB di view.
Fixture server, DB, user, storage, session dibersihkan. Tidak ada email, provider nyata, commit/push.
