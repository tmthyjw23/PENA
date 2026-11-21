# Panduan Pengguna PENA

Surat pribadi dan organisasi dari draf sampai selesai

Edisi 21 November 2026 · Untuk pembuat surat dan pemeriksa

PENA membantu Anda menyusun surat, memeriksa draf, meminta persetujuan dan mengunduh dokumen yang sudah selesai. Panduan ini menjelaskan fitur yang tersedia pada edisi ini. Hasil AI tetap perlu dibaca dan diperbaiki oleh pengguna.

**Buka aplikasi:** [pena.egg3l.site](https://pena.egg3l.site). Teman yang ingin membuat surat perlu akun, email yang bisa dibuka, serta provider dan API key sendiri. Teman yang hanya diminta memeriksa surat cukup membuka tautan pemeriksaan yang dikirim khusus kepadanya.

## 1 Mulai dari sini

Alur pembuat surat adalah **daftar → verifikasi email → hubungkan provider AI → pilih ruang → buat dan periksa draf → simpan → selesaikan atau ajukan → unduh**.

| Kebutuhan Anda | Yang perlu disiapkan |
| --- | --- |
| Mencoba surat sendiri | Email aktif, akun PENA, provider dan API key pribadi, contoh isi surat |
| Membuat surat organisasi | Persiapan di atas, nama organisasi, kop, data kegiatan dan daftar pemeriksa |
| Memeriksa surat teman | Tautan pemeriksaan yang dikirim pembuat surat; akun dan API key tidak diperlukan |

### Memahami ruang kerja

**Ruang pribadi** menyimpan surat Anda sendiri. AI dapat merancang susunan formal atau informal sesuai tujuan: misalnya permintaan maaf, lamaran kerja atau izin kuliah. Surat pribadi tidak memakai nomor dan kop organisasi. Keluwesan tujuan tidak menjamin setiap jenis surat selalu berhasil dibuat atau diterima penerimanya.

**Ruang organisasi** menggunakan kerangka yang tersedia, kop, nomor surat dan pemeriksa organisasi. Pilih ruang yang benar sebelum mulai; surat di ruang berbeda memiliki arsip berbeda.

**Operator platform** mengelola akun, metadata organisasi, layanan dan log. Membuat organisasi tidak otomatis menjadikan Anda operator. AI bawaan VPS hanya tersedia bagi operator.

### Petunjuk di layar

Akun baru mendapat hint yang menunjuk bagian antarmuka setelah masuk ruang kerja. Gunakan Lanjut, Sebelumnya atau Tutup. Untuk mengulangnya, buka **Bantuan → Tampilkan petunjuk di layar**. Pada ponsel, Bantuan dan Pengaturan AI ada di menu akun berinisial; pada desktop, keduanya ada di bagian bawah navigasi.

## 2 Daftar dan verifikasi email

1. Buka [halaman pendaftaran](https://pena.egg3l.site/daftar/) lalu isi nama, email dan kata sandi.
2. Setelah akun dibuat, buka halaman verifikasi. Pendaftaran mencoba mengirim email secara otomatis. Jika belum berhasil, gunakan **Kirim email verifikasi**.
3. Selama permintaan berlangsung, tampil skeleton **Mengirim email verifikasi**. Setelah permintaan diterima penyedia email, halaman menunjukkan **Menunggu verifikasi email**.
4. Buka email dari PENA, tekan tautan, lalu pilih **Konfirmasi email** pada halaman yang terbuka. Membuka tautan saja belum menyelesaikan konfirmasi.
5. Kembali ke halaman semula dan tekan **Periksa status verifikasi**, atau masuk kembali jika diperlukan.

Periksa folder Spam atau Promosi bila email belum terlihat. Status menunggu menunjukkan permintaan pengiriman telah diterima penyedia, bukan bukti email sudah masuk inbox. Tautan verifikasi berlaku 24 jam.

**Kirim ulang email** membuat tautan baru; gunakan email terbaru. Batasnya tiga permintaan pengiriman per jam, termasuk pengiriman pertama. Bila pengiriman ulang gagal, tautan sebelumnya yang masih berlaku tetap dapat digunakan. Status menunggu tetap tampil setelah reload selama ada tautan aktif.

Jika lupa kata sandi, buka [Lupa kata sandi](https://pena.egg3l.site/akun/lupa/). Tautan pemulihan berlaku 30 menit. Setelah kata sandi berhasil diganti, sesi masuk sebelumnya diakhiri.

## 3 Hubungkan provider dan API key Anda

Setelah verifikasi, pengguna biasa wajib menyimpan koneksi AI sebelum membuka ruang kerja. Koneksi yang sudah tersimpan tetap berlaku saat login berikutnya; Anda tidak perlu memasukkan kunci setiap kali masuk. Menyimpan pengaturan tidak otomatis menguji provider atau memakai kuota AI.

### Jika memakai Google AI Studio

1. Masuk ke [halaman API key Google AI Studio](https://aistudio.google.com/apikey) dengan akun Anda.
2. Buat atau pilih API key pada proyek yang boleh Anda gunakan. Ikuti persyaratan akun, proyek, verifikasi atau penagihan yang ditampilkan Google.
3. Di PENA, buka **Pengaturan AI**, lalu pilih **Google AI Studio (Gemini)**.
4. Masukkan API key. Alamat layanan dan model awal disediakan PENA; buka bagian Model jika ingin menggantinya dengan model yang didukung akun Anda.
5. Baca persetujuan pengiriman data dan pemakaian kuota. Centang bila setuju, isi kata sandi **akun PENA**, lalu pilih **Simpan pengaturan AI**.
6. Bila ingin memeriksa koneksi, gunakan **Uji koneksi pribadi**. Uji ini mengirim satu pesan contoh tanpa isi surat dan memakai kuota provider.

Panduan pengelolaan kunci resmi: [Google AI Studio](https://aistudio.google.com/docs/api-key). Ketersediaan model, wilayah, kuota dan biaya mengikuti provider. Panduan ini tidak menjanjikan layanan gratis atau model tertentu selalu tersedia.

### Provider lain dan endpoint kustom

Pilihan lain adalah OpenAI, OpenRouter dan Groq. Untuk provider yang tidak mengisi model awal, salin **ID model**, bukan nama tampilan atau API key, dari layanan tersebut. PENA memakai koneksi Chat Completions; tidak semua model atau API native cocok.

Endpoint kustom harus merupakan layanan yang Anda percaya, memakai domain publik HTTPS port 443 dan kompatibel dengan OpenAI Chat Completions. Isi base URL seperti `https://ai.contoh.com/v1`, tanpa `/chat/completions`. Alamat localhost, jaringan internal dan IP literal tidak diterima lewat pengaturan pengguna.

### Yang perlu diketahui tentang kunci dan data

- Masukkan kunci hanya di formulir PENA. Jangan kirim ke grup, formulir laporan masalah atau screenshot.
- Kunci disimpan terenkripsi dan tidak ditampilkan kembali. Server PENA tetap perlu membukanya untuk menghubungi provider. Enkripsi database bukan perlindungan mutlak bila server dan kunci enkripsinya dikuasai pihak lain.
- Data yang diperlukan untuk menyusun surat dikirim ke provider atau gateway pilihan Anda. PENA juga menyimpan data surat dan rekaman proses AI untuk operasional; jangan memakai data rahasia untuk percobaan.
- Koneksi pribadi yang gagal tidak otomatis dialihkan ke AI VPS. Menghapus koneksi mewajibkan pengguna biasa mengatur koneksi baru sebelum membuka ruang kerja. Surat tersimpan tetap ada.
- Jika kunci tidak dipakai lagi atau diduga bocor, cabut juga di provider. Penghapusan di PENA tidak langsung menghapus salinan dalam cadangan atau data yang telah terkirim.

## 4 Membuat surat pribadi

1. Pilih **Ruang pribadi**, lalu buka **Buat surat**.
2. Ceritakan tujuan, penerima, gaya bahasa dan fakta yang sudah diketahui. Hindari mengisi data yang belum pasti seolah-olah benar.
3. Pilih **Susun surat** dan tunggu hasilnya. Jika AI meminta keterangan, lengkapi maksud Anda.
4. Periksa susunan, judul atau perihal, semua isian dan paragraf. Lengkapi bagian kosong dan koreksi bagian yang ditandai perlu dicek.
5. Gunakan **Tulis ulang** bila ingin memperbaiki paragraf yang menyediakan tindakan itu. Baca hasilnya kembali.
6. Pilih **Simpan draf**. Draf belum berarti surat sudah selesai atau diajukan.

**Contoh permintaan:**

> Buat surat permintaan maaf untuk pacar karena aku terlambat menepati janji. Gunakan bahasa hangat, jujur dan ringkas. Nama penerima akan saya isi setelah draf tersusun.

Untuk menulis sendiri, pilih **Isi manual tanpa AI** atau **Isi formulir** sesuai tombol pada halaman. Pilihan manual memakai kerangka yang tersedia; susunan pribadi yang lebih bebas dirancang melalui AI. Pada edisi ini, syarat mengatur provider tetap berlaku sebelum ruang kerja, meskipun Anda kemudian memilih formulir manual.

### Sebelum menyimpan atau menyelesaikan

Periksa nama dan penerima, tanggal, nominal, tujuan, kalimat yang memberi janji atau kewenangan, serta gaya bahasa. Penanda seperti `{{nama_kegiatan}}` mengambil nilai dari kolom isian; ubah faktanya melalui kolom tersebut.

PENA belum menyimpan isian otomatis. Simpan sebelum pindah halaman. Peringatan **Perubahan belum disimpan** membantu mengingatkan, tetapi bukan cadangan pekerjaan.

Surat pribadi tanpa pemeriksa dapat **diselesaikan sendiri** dari detail draf. Bila ingin memakai gambar tanda tangan pengirim, bubuhkan dan konfirmasikan **sebelum** menyelesaikan versi tersebut. Surat yang sudah selesai tidak dapat ditambahi tanda tangan belakangan.

## 5 Membuat surat organisasi

Pilih ruang organisasi yang tepat. Bila belum ada, buat ruang organisasi melalui pemilih ruang kerja dan isi nama serta singkatan. Kop diisi dalam alur surat pertama. Siapkan data penerima, kegiatan, barang atau dana, serta siapa yang harus memeriksa.

1. Susun melalui AI atau formulir dengan kerangka organisasi yang tersedia.
2. Isi atau pilih kop; pastikan identitas organisasi dan kontak benar.
3. Tambahkan pemeriksa beserta jabatannya dan susun tahap sesuai proses yang benar-benar dipakai organisasi.
4. Simpan draf dan baca pratinjau lengkap.
5. Pilih **Ajukan** setelah siap. Nomor organisasi diberikan saat pengajuan pertama, bukan oleh AI. Versi yang diajukan terkunci.
6. Salin tautan pemeriksaan dan kirim langsung kepada pemeriksa yang dituju. Saat tahap berikutnya terbuka, cek detail surat untuk membuat atau menyalin tautan yang diperlukan.

**Contoh permintaan:**

> Buat permohonan peminjaman 20 kursi untuk diskusi mahasiswa pada 30 November 2026 di aula. Tujuan surat Bagian Umum fakultas. Periksa oleh ketua panitia, kemudian advisor. Nama dan jabatan akan saya cocokkan kembali.

Tahap pemeriksaan berlangsung berurutan. Bila satu tahap berisi beberapa pemeriksa wajib, semuanya harus menyetujui agar tahap berikutnya berjalan. Memasukkan nama dekan atau advisor ke draf tidak berarti orang tersebut telah menyetujui.

**Berbagi organisasi:** edisi ini belum menyediakan menu undang anggota organisasi. Mengirim alamat aplikasi hanya membantu teman membuat akun sendiri. Membuat organisasi bernama sama dari akun lain tidak otomatis memberikan akses ke organisasi Anda. Untuk meminta pemeriksaan, gunakan tautan pemeriksaan.

## 6 Memeriksa surat dan membubuhkan tanda tangan

Pemeriksa cukup membuka tautan yang diberikan pembuat surat. Pemeriksa tidak perlu akun atau API key. Tautan memberi akses kepada pemegangnya, sehingga kirim melalui pesan pribadi dan jangan unggah ke grup umum.

1. Baca judul, nomor bila ada, versi dan isi lengkap surat.
2. Bila semua sesuai, pilih tindakan persetujuan yang tersedia. Gambar tanda tangan bersifat opsional.
3. Untuk memakai gambar, pilih gambar langsung di area tanda tangan **atau** unggah PNG, lalu centang konfirmasi pemakaian untuk versi yang sedang dibaca.
4. Jika ada kesalahan, pilih **Minta revisi** dan tulis perbaikan yang spesifik. Komentar biasa tidak sama dengan meminta revisi; proses tetap berjalan bila hanya berkomentar.
5. Periksa pesan hasil tindakan sebelum menutup halaman.

PNG maksimal **512 KB** dan **1600 × 800 piksel**. Gambar harus berisi tanda tangan yang terbaca. JPG atau foto berformat lain tidak diterima melalui pilihan unggah PNG.

Persetujuan dan gambar tanda tangan melekat pada satu versi. PENA tidak menyalin gambar lama otomatis ke versi revisi. Gambar tanda tangan bukan tanda tangan elektronik tersertifikasi, dan tautan tamu belum membuktikan identitas pejabat yang dituju. Konfirmasikan penerimaan surat elektronik, tanda tangan dan cap kepada pihak tujuan.

Tautan pemeriksaan diterbitkan dengan masa berlaku tujuh hari; perubahan versi, penggantian pemeriksa atau pencabutan juga dapat membuatnya tidak berlaku lebih awal. Jika tautan ditolak, hubungi pembuat surat untuk memeriksa versi dan menerbitkan tautan yang sesuai.

## 7 Revisi status dan unduhan

| Status yang terlihat | Artinya dan langkah Anda |
| --- | --- |
| Draf | Belum diajukan. Isi dan perihal masih dapat diubah. |
| Proses pemeriksaan | Menunggu keputusan sesuai tahap; belum berarti pemeriksa sedang membaca. |
| Diminta revisi | Mulai versi baru, perbaiki, lalu ajukan kembali. |
| Disetujui atau surat selesai | Versi tersebut selesai. Unduh melalui detail surat. |
| Dibatalkan | Proses dihentikan; nomor yang sudah terpakai tidak dikembalikan untuk surat lain. |

### Contoh revisi

Versi 1 sudah disetujui ketua, tetapi advisor meminta perubahan tanggal. Pembuat menggunakan **Mulai revisi**, menulis ringkasan perubahan, mengubah isian pada versi 2, lalu mengajukan kembali. Persetujuan ketua untuk versi 1 tetap tercatat di versi 1. Ketua diminta mengonfirmasi versi 2; pihak yang pernah menyetujui dapat menerima konfirmasi ulang secara paralel.

Surat pribadi yang sudah diselesaikan sendiri tidak menyediakan revisi yang sama dengan surat dalam pemeriksaan. Buat surat baru jika perlu memperbaikinya.

### Mengunduh dokumen

Buka detail surat yang sudah disetujui atau selesai, lalu pilih **Word** atau **PDF**. Unduhan final tersedia setelah persetujuan selesai; jangan menganggap draf sudah bisa diekspor sebagai dokumen final.

Jika PDF gagal dibuat, persetujuan tetap tersimpan. Baca pesan dan coba lagi, atau laporkan kepada pengelola. Mengedit Word di luar PENA tidak memperbarui versi atau persetujuan dalam aplikasi. Bagikan PDF final kepada penerima dan jelaskan bila Word turut disertakan untuk penyuntingan.

## 8 Saat menemui kendala

| Kendala | Yang dapat Anda lakukan |
| --- | --- |
| Tidak menerima email | Periksa alamat, Spam dan Promosi; gunakan email terbaru setelah kirim ulang. Jangan meminta ulang berulang-ulang. |
| Masih di halaman verifikasi | Pastikan sudah menekan Konfirmasi email, lalu Periksa status verifikasi atau masuk lagi. |
| Selalu diarahkan ke Pengaturan AI | Simpan provider dan API key pribadi. Pengguna biasa tidak bisa memakai default VPS. |
| HTTP 401 atau 403 dari provider | Periksa kunci, izin model dan akun provider. Jangan kirim kunci ke pengelola melalui chat. |
| HTTP 429 atau batas pemakaian | Tunggu sesuai pesan, cek kuota/batas provider. Setelah koneksi disimpan, Anda tetap bisa memakai formulir manual. |
| Hasil AI kosong atau tidak sesuai | Perjelas tujuan atau isi manual. Cocokkan data dan kalimat; jangan menyimpan hanya karena AI berhasil menjawab. |
| Tanda tangan tidak terlihat | Pastikan gambar diberikan saat menyetujui/menyelesaikan versi yang benar dan konfirmasi dicentang. Persetujuan tanpa gambar tidak menghasilkan tanda tangan gambar. |
| Isian hilang setelah sesi berakhir | Buka draf tersimpan. Isian yang belum disimpan mungkin perlu dimasukkan ulang. |
| Hasil berubah di tab lain | Muat ulang tab yang relevan; hindari menyunting proses yang sama di dua tab bersamaan. |
| Tautan pemeriksaan tidak berlaku | Minta pembuat surat mengecek versi aktif dan membuat tautan baru. |

Saat melapor, sertakan halaman, tindakan, pesan error, waktu kejadian dan browser yang dipakai. Screenshot boleh disertakan setelah nama pribadi, isi sensitif, API key dan tautan pemeriksaan disamarkan.

## 9 Cara membagikan PENA kepada teman

### Untuk teman yang ingin membuat surat

Kirim **alamat aplikasi** dan **PDF panduan ini**. Teman mendaftar dengan email sendiri dan menghubungkan provider/API key miliknya. Jangan membagikan akun operator atau API key pribadi Anda agar teman dapat melewati persiapan.

Pesan yang dapat disalin:

> Teman-teman, saya sedang mengembangkan PENA untuk menyusun surat pribadi dan organisasi, termasuk pemeriksaan dan tanda tangan gambar per versi. Bisa dicoba di https://pena.egg3l.site. Untuk membuat surat, daftar dengan email sendiri, verifikasi email, lalu pilih provider dan masukkan API key kalian di Pengaturan AI. Biaya dan kuota mengikuti provider masing-masing. Saya lampirkan panduannya. Untuk percobaan, gunakan data fiktif dulu. Kalau ada kendala, kirim langkah dan pesan errornya; jangan kirim API key atau tautan pemeriksaan ke grup.

### Untuk teman yang menjadi pemeriksa

Kirim **tautan pemeriksaan khusus** dari detail surat melalui pesan pribadi. Jelaskan nama surat, versi, keputusan yang diminta dan kapan Anda memerlukan respons. Pemeriksa tidak perlu membuat akun atau mengatur AI. Jangan memakai tautan pemeriksaan sebagai undangan umum mencoba aplikasi.

### Untuk membagikan hasil surat

Kirim PDF final setelah status selesai. Bila penerima memerlukan Word, jelaskan bahwa perubahan di Word tidak memperbarui catatan PENA. URL detail surat milik Anda bukan pengganti berkas final; teman belum tentu memiliki akses.

### Percobaan bersama yang disarankan

Mulai dari satu surat pribadi dengan data fiktif. Setelah berhasil, coba surat organisasi dengan dua pemeriksa, satu permintaan revisi dan konfirmasi ulang. Catat apakah pengguna bisa selesai sendiri, bagian yang membingungkan, kegagalan dan waktu tiap langkah. Hasil percobaan ini dapat menjadi bahan evaluasi; belum otomatis membuktikan PENA menghemat waktu dibanding proses lama.

## 10 Batas penggunaan dan sumber bantuan

PENA saat ini belum menyediakan tanda tangan tersertifikasi, OCR dokumen scan, editor Word bebas, kolaborasi waktu nyata, undangan anggota organisasi melalui UI atau pengiriman otomatis tautan persetujuan ke semua pejabat. Jenis surat khusus dan penerimaan institusi tetap perlu diperiksa.

Panduan penggunaan di aplikasi: [Bantuan PENA](https://pena.egg3l.site/bantuan/), setelah masuk dan verifikasi. Dokumentasi ini adalah gambaran edisi 21 November 2026; nama tombol dan aturan dapat berubah pada rilis berikutnya.

Sumber eksternal untuk pengaturan provider: [Google API key](https://aistudio.google.com/docs/api-key) dan [OpenRouter quickstart](https://openrouter.ai/docs/quickstart), diperiksa 21 November 2026. Perilaku aplikasi dirangkum dari kode dan pengujian PENA; ketentuan provider tetap mengikuti halaman resminya.
