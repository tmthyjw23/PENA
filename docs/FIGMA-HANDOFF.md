# Serah terima Figma, Fase 1

File: https://www.figma.com/design/pfBtKoFGp8ckRKkPoFX6oh (tim pribadi pemilik)
Dibuat: 17 Nov 2026, oleh Claude (bukan Codex; pemilik mengganti pelaksana).
Catatan ID node: `.impeccable/figma-state.json`.

## Arah

Jadwal saku penerbangan, dipilih pemilik dari undian impeccable (register lebih berani,
`seed:1908f8b73e282859`). Kontrak arah: `.impeccable/surfaces/app-view-surat-detail-php.md`.
Proses: unslop, antislop (during), design-taste-frontend, impeccable (init, new-work, craft-floor),
skill Figma (use, generate-design, generate-library).

Tesis: status surat dibaca seperti jadwal keberangkatan. Setiap surat dan setiap pemeriksa satu baris
di kolom tetap; satu pelat karbon per layar menyatakan langkah berikutnya.

## Halaman Figma

| Halaman | Isi |
|---|---|
| 00 Brief | Pembacaan desain, tesis, keputusan dan alasannya, token, risiko, indeks layar |
| 01 Design System | Button (3 gaya x 4 state), Status mark (6, termasuk Dibatalkan), Code strip (5 nada), Nav item, Input (3 state), Route row (5 status x Regular/Compact), Letter row (5, termasuk Dibatalkan), Next plate, App rail, Tab bar |
| 02 Desktop 1440 | Landing, Masuk (dengan galat), 404, Beranda, Daftar surat (dengan tab Dibatalkan), **Generate surat dengan AI x5** (ceritakan, sedang menyusun, periksa hasil, perlu keterangan, gagal), Buat surat isi manual tanpa AI, Detail surat x7 (menunggu tautan, tautan dibuat, diminta revisi, draf baru v1, draf revisi v3, disetujui dengan PDF gagal, dibatalkan), Dialog x4 (ajukan, mulai revisi, batalkan, ganti pemeriksa) |
| 03 Mobile 390 | Review lewat tautan x7 (layar pertama, penuh, konfirmasi ulang, tercatat, revisi terkirim, tidak berlaku, proses berhenti), Generate (ceritakan, periksa hasil), Beranda, Daftar surat, Detail surat |

## Alur generate surat (fitur inti)

1. **Ceritakan**: sekretaris menulis kebutuhan dalam satu kotak. Jalan lain selalu ada: "Isi manual tanpa AI".
2. **Sedang menyusun**: empat langkah terlihat (membaca, memilih kerangka, mengisi data, menulis paragraf) dan
   kerangka kertas terisi bertahap. Bisa dibatalkan.
3. **Periksa hasil**: kerangka beserta alasannya; setiap isian dengan kutipan sumbernya; "Perlu dicek" untuk yang
   disimpulkan dan "Kosong" untuk yang tidak disebut; paragraf hanya dengan penanda `{{isian}}` plus "Tulis ulang";
   peringatan pada paragraf tanpa fakta dari pengguna; alur persetujuan dari kalimat pengguna; pratinjau kertas.
   Pelat Berikutnya menyebut jumlah isian yang harus dicek sebelum "Simpan draf".
4. **Perlu keterangan**: bila permintaan terlalu samar, AI bertanya dengan pilihan jawaban, tidak menebak.
5. **Gagal**: permintaan tidak hilang; "Coba lagi" atau "Isi manual tanpa AI".

Nomor surat tidak pernah dibuat AI. Permintaan, model, dan waktu dicatat di riwayat (`ai_generations`).
Ini butuh layanan AI (9router) di Fase 4; layar Fase 2 bisa memakai data contoh tetap untuk hasilnya.
| 04 Alur | Petunjuk prototipe (alur ada di halaman 02 dan 03) |

## Token (untuk @theme Tailwind di Fase 2)

Koleksi Color (alias ke Primitives), sintaks web `var(--color-...)`:

| Token | Nilai |
|---|---|
| bg/page | #F4F4F1 |
| bg/surface | #FFFFFF |
| bg/raised | #ECEBE6 |
| bg/rail | #E7B53B (goldenrod) |
| bg/board | #263238 (papan jadwal di landing) |
| bg/plate | #1D1D1B (pelat karbon) |
| text/primary | #1D1D1B |
| text/secondary | #56554F |
| text/muted | #6B6A64 (4,96:1 di bg/page) |
| text/on-plate | #F6F2E4 |
| border/default | #D9D7CF |
| action/primary | #1F3A8A (biru tinta, komitmen merek) |
| action/primary-hover | #182E70 |
| state/approved | #1F6B46 |
| state/revision | #C2381A (5,6:1 di putih) |
| state/highlight | #FBF0D2 (stabilo penanda dan baris yang menunggu Anda) |

Jarak: 4, 8, 12, 16, 20, 24, 32, 40, 48, 64. Radius: strip 2, kontrol 4, panel 4, kertas 2.
Huruf: satu ukuran 16/24 untuk seluruh antarmuka. Barlow 400/600/700, Barlow Condensed 600/700
huruf kapital untuk kode dan kepala kolom. Kertas surat: Tinos (metrik Times New Roman) 16/24 di desktop,
14/21 di HP. Landing dan Masuk memakai Barlow Condensed Bold 48 sampai 120 untuk judul (mode Persuade).

## Aturan perilaku yang harus dibawa ke kode

- Satu pelat "Berikutnya" per layar; di HP tepat di bawah judul.
- Review di HP: bilah Setujui / Minta revisi menempel di bawah; anchor tidak boleh tertutup bilah atas.
- Tanda status selalu bersama teks status.
- Baris jadwal memakai varian Compact di kolom selebar 480 ke bawah.
- Fokus keyboard: cincin biru tinta 3 px; pada tombol Primary cincin karbon.
- Target sentuh minimal 44 px (tombol 44, tab bar 48).
- Mulai revisi, Batalkan, dan Ganti pemeriksa selalu lewat dialog; ringkasan perubahan dan alasan batal wajib
  (layanan menolak bila kosong). Tombol batal memakai warna revisi, bukan biru tinta.
- Draf yang belum pernah diajukan: tanpa nomor, tanpa tanggal, tanpa komentar, tanpa tombol Mulai revisi.
  Draf revisi: nomor tetap tampil, tanggal belum ada, pelat menyebut siapa yang akan konfirmasi ulang.

## Syarat kode yang muncul dari desain (dicatat 17 Nov 2026)

- `review.php::_changes` harus membandingkan paragraf yang sudah dirender. Saat ini teks berpenanda dibandingkan,
  jadi perubahan tanggal tidak muncul sebagai perubahan paragraf seperti di layar 03.2.
- `LetterRenderer` dan DOCX: tambahkan "di Tempat" di bawah penerima dan label "Mengetahui," untuk tahap yang
  mengetahui. Cara menandai tahap itu (nama tahap atau kolom baru) diputuskan di Fase 2 atau 3.
- Kop surat dipilih otomatis bila organisasi hanya punya satu kop aktif; `submit` menolak surat tanpa kop.
- Fase 4: tolak atau tandai teks paragraf AI yang mengetik tanggal, angka, atau nilai isian secara langsung.
  Layar 02.11 kini menjanjikan ini.
- Layar "Belum giliran" dihapus: backend hanya membuat tautan untuk pemeriksa yang sudah giliran.
  Layar 03.6 sekarang "Proses berhenti" (tautan masih bisa dibuka, keputusan ditolak karena surat diminta revisi).

## Audit diri

- Validasi skrip: 845 teks, hanya Barlow, Barlow Condensed, dan Tinos; 0 gambar raster; 0 em dash.
- Kontras dihitung untuk pasangan utama (lihat tabel token); semua di atas 4,5:1.
- Cacat yang ditemukan dan diperbaiki selama pembuatan: tinggi Input terkunci, nama pemeriksa terpotong di
  baris jadwal, kode tahap tidak berubah, ikatan teks varian Compact hilang setelah kloning, bilah keputusan
  di tengah kertas, tabel kertas lebih lebar dari teks, centang kotak pilihan salah posisi, chip penanda
  terpotong, contoh galat bertentangan, tombol "Mulai revisi" ganda, tombol Unduh PDF aktif saat PDF gagal,
  waktu terpotong di HP, warna revisi hilang di daftar HP.
- Review oleh agen peninjau impeccable belum dijalankan; itu dilakukan di akhir Fase 2 atas hasil build.

## Belum didesain

Ubah draf (sama dengan isi manual tanpa pilih jenis dan alur), skeleton loading daftar, Daftar surat kosong,
panel "Tulis ulang" satu paragraf, halaman riwayat lengkap, tombol Ganti di baris jadwal, tautan kedaluwarsa di
sisi sekretaris, keadaan "jabatan tidak ada di direktori" pada hasil generate, halaman pengaturan kop dan
penanda tangan, versi HP untuk dialog dan surat dibatalkan. Mode gelap sengaja tidak dibuat.

## Risiko terbuka

- Satu ukuran huruf membuat judul halaman tidak menonjol; uji dengan sekretaris dan dosen sungguhan.
- Migrasi ke Tailwind perlu konfirmasi dosen bahwa template masih dianggap dipakai.
- Semua nama dan data di layar adalah contoh fiktif.
