# Audit dan penulisan ulang teks PENA — 20 November 2026 WITA

Cakupan: 23 berkas view aktif ditinjau. Teks surat milik pengguna, isi template dokumen, nama data, dan identifier kode dipertahankan. Desain visual yang sudah disetujui tetap digunakan.

Temuan: cakupan pribadi belum muncul pada beranda; klaim waktu pendaftaran belum diukur; peran admin organisasi sudah tidak sesuai; petunjuk memakai istilah internal; hasil tindakan kurang jelas; istilah akun dan draf tidak konsisten.

Arah: ringkas, tenang, spesifik. Tombol memakai kata kerja; pesan sukses menjelaskan hasil; pesan gagal memberi langkah berikutnya; batas identitas tautan dan tanggung jawab pemeriksaan tetap terlihat. AI disebut saat menjelaskan bantuan penulisan, bukan sebagai jargon pemasaran.

| Lokasi | Sebelumnya | Sesudah |
|---|---|---|
| app/view/landing/index.php | Surat organisasi, dari draf sampai disetujui | Surat pribadi dan organisasi |
| app/view/landing/index.php | PENA: surat organisasi dari draf sampai disetujui | PENA: surat pribadi dan organisasi |
| app/view/landing/index.php | PENA membantu sekretaris organisasi mahasiswa menyusun surat dari kerangka resmi dan meminta persetujuan bertahap lewat tautan. | Susun surat pribadi atau organisasi, minta pemeriksaan, dan unduh hasilnya dalam Word atau PDF. |
| app/view/landing/index.php | Sekretaris menyusun surat dari kerangka resmi. Ketua, advisor, dan dekan memeriksa lewat tautan, berurutan, tanpa perlu akun. | Susun draf, periksa isinya, lalu unduh Word atau PDF. Untuk surat yang perlu persetujuan, kirim tautan kepada pemeriksa sesuai urutannya. |
| app/view/landing/index.php | ['Susun', 'Dari kerangka', 'Pilih jenis surat organisasi, isi data sekali, tulis paragraf dengan penanda isian.'] | ['Susun', 'Buat draf', 'Jelaskan kebutuhan surat atau isi formulir. Periksa data dan kalimat sebelum menyimpan.'] |
| app/view/landing/index.php | ['Ajukan', 'Nomor dan kunci', 'Nomor surat dibuat otomatis dan isi versi dikunci saat diajukan.'] | ['Ajukan', 'Kirim untuk diperiksa', 'Tambahkan pemeriksa bila diperlukan. Surat organisasi mendapat nomor saat diajukan.'] |
| app/view/landing/index.php | ['Periksa', 'Berurutan', 'Tiap pemeriksa membuka tautan lewat WhatsApp, lalu setuju, berkomentar, atau minta revisi.'] | ['Periksa', 'Beri keputusan', 'Pemeriksa membuka tautan untuk menyetujui, memberi catatan, atau meminta revisi.'] |
| app/view/landing/index.php | ['Revisi', 'Dengan jejak', 'Revisi menjadi versi baru. Penyetuju lama melihat daftar perubahan lalu mengonfirmasi ulang.'] | ['Revisi', 'Perbarui versi', 'Perubahan disimpan sebagai versi baru. Persetujuan sebelumnya tetap tercatat pada versi lama.'] |
| app/view/landing/index.php | Cara surat bergerak | Dari draf ke surat selesai |
| app/view/landing/index.php | Pemeriksa tidak login. Sistem mencatat keputusan atas pemegang tautan, jadi tautan hanya dikirim ke orangnya langsung. | Pemeriksa tidak memerlukan akun. Siapa pun yang memiliki tautan dapat memberi keputusan; kirim tautan hanya kepada orang yang dituju. |
| app/view/landing/index.php | AI membantu menyusun draf. Periksa kembali data dan isi surat sebelum mengajukan. | Draf berbantuan AI tetap perlu diperiksa. Pastikan data, isi, dan gaya bahasa sesuai kebutuhan Anda. |
| app/view/dashboard/index.php | Perlu tindakan Anda | Perlu ditindaklanjuti |
| app/view/dashboard/index.php | Akun ini belum terdaftar di organisasi | Belum ada ruang kerja |
| app/view/dashboard/index.php | Halaman surat hanya untuk sekretaris atau admin organisasi. Minta admin mendaftarkan akun Anda sebagai pengurus. | Pilih ruang pribadi atau buat ruang organisasi untuk mulai menyusun surat. |
| app/view/dashboard/index.php | Tidak ada yang menunggu Anda | Tidak ada tindakan tertunda |
| app/view/dashboard/index.php | Semua surat sedang di tangan pemeriksa atau sudah selesai. | Surat yang ada masih diperiksa atau sudah selesai. |
| app/view/dashboard/index.php | Ceritakan surat yang Anda butuhkan, atau isi manual dari kerangka. Nomor surat dibuat saat diajukan. | Buat draf pertama dari permintaan Anda atau melalui formulir. |
| app/view/dashboard/index.php | belum menerima tautan | masih perlu dikirimi tautan |
| app/view/daftar/index.php | Surat pertama dalam beberapa menit | Buat akun PENA |
| app/view/daftar/index.php | Password | Kata sandi |
| app/view/login/index.php | Username atau email | Email atau nama pengguna |
| app/view/login/index.php | Password | Kata sandi |
| app/view/login/index.php | Lupa password? | Lupa kata sandi? |
| app/view/login/index.php | Masuk ke PENA | Masuk |
| app/view/akun/index.php | Password diperbarui | Kata sandi diperbarui |
| app/view/akun/index.php | Password baru | Kata sandi baru |
| app/view/akun/index.php | Lupa password | Lupa kata sandi |
| app/view/akun/index.php | Buat password baru untuk akun Anda. | Buat kata sandi baru untuk akun Anda. |
| app/view/akun/index.php | Ulangi password baru | Ulangi kata sandi baru |
| app/view/akun/index.php | Simpan password baru | Simpan kata sandi |
| app/view/akun/index.php | Minimal 8 karakter; maksimal 72 byte. | Minimal 8 karakter. |
| app/view/akun/index.php | Verifikasikan | Verifikasi |
| app/view/akun/index.php | Tekan tombol di bawah untuk mengonfirmasi email Anda. | Konfirmasikan bahwa alamat email ini milik Anda. |
| app/view/akun/index.php | Minta tautan pemulihan | Kirim tautan pemulihan |
| app/view/akun/index.php | Kembali ke halaman masuk | Kembali ke masuk |
| app/view/ruang/mulai.php | Surat ini untuk siapa? | Pilih ruang kerja |
| app/view/ruang/mulai.php | Pilih salah satu untuk memulai. Anda tetap bisa memakai keduanya; pindah ruang lewat menu Ruang kerja kapan saja. | Gunakan ruang pribadi untuk surat atas nama sendiri, atau ruang organisasi untuk surat bersama. Anda dapat berpindah ruang lewat menu Ruang kerja. |
| app/view/ruang/mulai.php | Surat izin tidak hadir, dispensasi, atau permohonan ke dosen. Tanpa kop dan nomor surat. Bisa langsung jadi, atau minta dosen wali mengetahui lebih dulu. | Buat surat atas nama sendiri, tanpa kop dan nomor organisasi. Tambahkan pemeriksa jika surat memerlukan persetujuan. |
| app/view/ruang/mulai.php | Opsional. Diisi sekali, lalu masuk otomatis ke setiap surat pribadi. | Opsional. Data disimpan di profil dan digunakan bila jenis surat membutuhkannya. |
| app/view/ruang/mulai.php | Diri sendiri | Surat pribadi |
| app/view/ruang/mulai.php | Mulai surat pribadi | Gunakan ruang pribadi |
| app/view/ruang/mulai.php | Ruang organisasi punya kop, nomor surat berseri, dan arsip sendiri. Anda menjadi pemiliknya. | Setiap organisasi memiliki kop, penomoran, pemeriksa, dan arsip surat sendiri. |
| app/view/surat/generate.php | Ceritakan surat yang Anda butuhkan. PENA | Jelaskan surat yang Anda perlukan. PENA |
| app/view/surat/generate.php | merancang susunan sesuai tujuan dan gaya Anda | menyusun draf sesuai tujuan dan gaya bahasa yang Anda minta |
| app/view/surat/generate.php | , menandai data, dan menulis draf. Anda memeriksa semuanya sebelum disimpan. | . Periksa dan lengkapi hasilnya sebelum menyimpan. |
| app/view/surat/generate.php | Butuh sedikit keterangan lagi | Lengkapi permintaan |
| app/view/surat/generate.php | Jawaban digunakan sebagai konteks tambahan. Kalimat awal Anda tetap dipertahankan. | Jawaban ini melengkapi permintaan awal Anda. |
| app/view/surat/generate.php | Klik jenis yang sesuai untuk melanjutkan. Kalimat Anda tetap dipertahankan; isian surat dapat diperiksa dan dilengkapi pada langkah berikutnya. | Pilih jenis surat untuk melanjutkan. Data yang belum lengkap dapat diisi pada langkah berikutnya. |
| app/view/surat/generate.php | Surat belum bisa disusun | Draf belum dapat dibuat |
| app/view/surat/generate.php | Permintaan Anda tetap tersimpan di kotak di atas. | Permintaan Anda tetap ada di formulir. |
| app/view/surat/generate.php | Menyusun dari permintaan Anda | Menyusun draf |
| app/view/surat/generate.php | Yang dilakukan PENA | Tentang draf ini |
| app/view/surat/generate.php | Mengisi isian hanya dari kalimat Anda; yang disimpulkan ditandai perlu dicek, yang tidak disebut dibiarkan kosong | Data yang disimpulkan ditandai untuk diperiksa. Lengkapi isian yang masih kosong |
| app/view/surat/generate.php | Menulis paragraf dengan penanda, bukan dengan fakta yang diketik ulang | Isi surat dapat diperiksa dan diubah sebelum disimpan |
| app/view/surat/generate.php | Surat pribadi tidak bernomor. Tanpa pemeriksa, surat langsung jadi saat Anda menyelesaikannya. | Surat pribadi tidak memakai nomor organisasi. Tanpa pemeriksa, Anda dapat menyelesaikan surat sendiri. |
| app/view/surat/generate.php | Isi manual tanpa AI | Isi formulir sendiri |
| app/view/surat/generate.php | Susun surat | Buat draf |
| app/view/surat/generate_hasil.php | Periksa hasil susunan | Periksa draf |
| app/view/surat/generate_hasil.php | Ubah apa pun yang kurang tepat. Belum ada yang tersimpan atau terkirim. | Periksa data dan isi surat. Draf belum disimpan atau diajukan. |
| app/view/surat/generate_hasil.php | Dirancang untuk: | Susunan: |
| app/view/surat/generate_hasil.php | Dipilih karena: | Jenis surat: |
| app/view/surat/generate_hasil.php | Bagian surat dirancang khusus untuk permintaan ini. Ubah kalimat permintaan jika susunannya belum cocok. | Jika susunannya belum sesuai, ubah permintaan lalu buat draf kembali. |
| app/view/surat/generate_hasil.php | Periksa hasil ekstraksi AI berikut. Isian yang disimpulkan ditandai perlu dicek; kutipan sumber diperiksa terhadap kalimat Anda. | Periksa setiap isian. Data yang disimpulkan ditandai perlu dicek. |
| app/view/surat/generate_hasil.php | Penanda {{isian}} diisi dari data di atas saat surat ditampilkan. Periksa apakah kalimat dan penggunaan datanya sesuai maksud Anda. | Teks seperti {{nama}} mengambil nilai dari data surat. Periksa kalimatnya setelah isian dilengkapi. |
| app/view/surat/generate_hasil.php | Periksa fakta, susunan, dan gaya bahasanya sekali lagi sebelum menyimpan draf. | Periksa data, susunan, dan bahasa sebelum menyimpan. |
| app/view/surat/generate_hasil.php | Anda masih bisa mengubah semuanya setelah disimpan. | Draf dapat diubah sebelum diajukan. |
| app/view/surat/generate_hasil.php | Susun ulang | Buat ulang draf |
| app/view/surat/generate_hasil.php | Server tidak bisa dihubungi. Paragraf lama tetap dipakai. | Paragraf belum diperbarui. Periksa koneksi, lalu coba lagi. Teks sebelumnya tetap tersedia. |
| app/view/surat/form.php | Buat surat, isi manual | Isi formulir surat |
| app/view/surat/form.php | Ceritakan saja ke PENA | Buat draf dengan AI |
| app/view/surat/form.php | Nama, NIM, dan program studi terisi dari profil Anda. | Data profil digunakan jika diperlukan oleh jenis surat. |
| app/view/surat/form.php | Fakta resmi diisi sekali di sini. Paragraf merujuk lewat penanda, jadi mengubah tanggal di sini ikut mengubah kalimat surat. | Lengkapi data surat. Teks yang memakai {{isian}} akan mengikuti data ini. |
| app/view/surat/form.php | Jenis surat dan alur persetujuan tidak berubah di sini. | Jenis surat dan pemeriksa tetap mengikuti draf ini. |
| app/view/surat/form.php | Teks baku kerangka, terkunci | Teks tetap |
| app/view/surat/form.php | Kolom tanda tangan disusun dari alur persetujuan. | Nama penanda tangan mengikuti daftar pemeriksa. |
| app/view/surat/detail.php | Tautan hanya ditampilkan sekali. Kirim langsung ke orangnya, misalnya lewat WhatsApp. | Salin tautan sebelum meninggalkan halaman. Kirim langsung kepada pemeriksa yang dituju. |
| app/view/surat/detail.php | Siapa pun yang memegang tautan bisa memberi keputusan atas nama pemeriksa itu. | Siapa pun yang memiliki tautan ini dapat memberi keputusan. Jangan bagikan di grup atau tempat umum. |
| app/view/surat/detail.php | Kirim ke penerima, atau cetak untuk tanda tangan basah. | Unduh surat untuk dikirim atau dicetak. |
| app/view/surat/detail.php | Unduh salinan versi yang sudah disetujui. | Unduh versi yang telah disetujui. |
| app/view/surat/detail.php | Setelah diganti, status menjadi Diminta revisi | Setelah diganti, surat perlu direvisi |
| app/view/review/index.php | Keputusan Anda | Beri keputusan |
| app/view/review/index.php | Proses sedang berhenti | Keputusan belum diperlukan |
| app/view/review/index.php | PENA tidak memeriksa identitas: keputusan dicatat atas nama pemegang tautan ini. | Identitas pemegang tautan belum diverifikasi. Pastikan surat dan nama pemeriksa sudah sesuai. |
| app/view/review/index.php | Surat lengkap versi | Dokumen versi |
| app/view/errors/index.php | Halaman ini tidak ada | Halaman tidak ditemukan |
| app/view/errors/index.php | Cara membuka halaman ini tidak diizinkan | Tindakan tidak tersedia |
| app/view/errors/index.php | Ada kendala di server | Halaman belum dapat dimuat |
| app/view/layout/app_top.php | Surat organisasi</ | Ruang surat</ |
| app/view/errors/index.php | Surat organisasi</ | Ruang surat</ |
| app/view/landing/index.php | Surat organisasi</ | Ruang surat</ |

Teks yang sudah singkat dan tepat, seperti Simpan draf, Unduh PDF, Nama lengkap, dan Minta revisi, dipertahankan. Audit bukan penggantian semua kata secara mekanis. Pesan validasi layanan dan controller diperiksa terpisah dari label view.

Review tambahan: sapaan halaman masuk dibuat lebih langsung; cakupan pribadi dijelaskan pada pendaftaran; keterangan teknis hash/model disederhanakan pada halaman utama; label langkah Periksa dipendekkan agar tidak menabrak langkah berikutnya di HP. Pesan validasi kata sandi di controller/service diselaraskan. Penanda isian tetap dijelaskan karena diperlukan saat mengedit paragraf.
