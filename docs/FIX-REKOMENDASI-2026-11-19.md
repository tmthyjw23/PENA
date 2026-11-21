# Rekomendasi dan klarifikasi AI — 19 November 2026

Pengguna melaporkan “surat permintaan maaf untuk pacar” dipaksa menjadi pilihan IZN/DSP; klik pilihan hanya menempelkan label ke akhir prompt. Screenshot pengguna dan handler data-answer membuktikan perilaku append. Repro terhadap kode lama dengan respons unsupported palsu gagal: validator hanya mengenal ok/clarify.

## Perbaikan

- Kontrak rekomendasi membedakan ok, clarify, dan unsupported. Tujuan jelas tanpa kerangka yang sesuai tidak diarahkan ke jenis lain. Data tanggal/penerima yang belum lengkap bukan alasan otomatis meminta pilihan kerangka.
- Respons unsupported mempunyai panel sendiri, tanpa tombol pilihan yang tidak berhubungan. Daftar jenis tersedia hanya menjelaskan cakupan ruang saat ini. Prompt tetap utuh dan proses tidak lanjut ke ekstraksi/paragraf.
- Klarifikasi berisi kode kerangka dari daftar ruang; label diambil server dari kerangka, bukan teks bebas model. Minimal dua kode unik, maksimal empat; kode asing/duplikat ditolak.
- Klik pilihan adalah submit bernama template_choice, langsung melanjutkan jenis yang dipilih. FormData mengambil e.submitter secara eksplisit. Tidak ada penggabungan pilihan dengan prompt.
- Server menerima pilihan hanya bila pernah ditawarkan pada proses/revisi yang sama dan prompt tidak berubah. Jika prompt berubah, pilihan lama tidak memaksakan maksud baru; model mengklasifikasikan ulang. Pilihan tak ditawarkan ditolak sebelum provider dipanggil.
- Klarifikasi teks bebas dari sesi lama tidak dirender sebagai tombol aksi. UI model menyebut ruang/mahasiswa, tidak menganggap semua surat sebagai surat organisasi.

## Bukti

check, test:ai-clarify, test:ai, test:ai-controller, test:ai-drafts lulus pada kandidat. test:ai-clarify memakai DB uji dan transport palsu; mencakup unsupported, stop ekstraksi, pemilihan kode tepat tanpa panggilan klasifikasi tambahan, prompt tak berubah, pilihan palsu, prompt berubah, serta view asli yang dirender.

Lima kasus model nyata lulus pada DB sementara: permintaan maaf romantis → unsupported; romantis tercampur label IZN lama → unsupported; izin karena sakit dengan data belum lengkap → IZN; maksud samar “buatkan surat untuk kampus” → clarify; izin yang memuat permintaan maaf ketidakhadiran → IZN. Ini sampel regresi, bukan jaminan seluruh kalimat selalu diklasifikasikan benar. Tidak ada blacklist kata maaf/pacar di aplikasi.

Chrome menjalankan JavaScript pada view asli yang dirender dari fixture, dengan submit jaringan di-stub. Klik tombol benar-benar mengirim template_choice PMJ dan prompt asli, satu request; tidak menambah teks. View unsupported tidak menawarkan pilihan, tidak overflow pada 1440/390 px, dan tidak ada error JavaScript. Pratinjau [HP](previews/ai-unsupported-mobile.png) memakai data fiktif.

## Deployment dan batas

Rilis aktif /srv/pena/release-20261119T112443Z; sebelumnya /srv/pena/release-20261119T110639Z. Backup kode /var/backups/pena/clarify-20261119T112443Z. Environment dipertahankan; tidak ada migrasi/perubahan surat pengguna. DB/AI/email terbaca sebagai user layanan, PHP-FPM di-reload, smoke origin dan HTTPS 200. Tidak ada email atau commit/push baru.

Pengguna perlu membuka ulang halaman dan mencoba kalimat asli; teks yang sudah tercampur akibat bug lama tidak dihapus diam-diam. Surat romantis tidak ditambahkan sebagai jenis baru. Alur browser publik dengan akun pengguna setelah perbaikan tetap perlu konfirmasi; uji klik otomatis memakai fixture dan stub jaringan.
