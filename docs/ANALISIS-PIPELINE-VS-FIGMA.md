# Analisis: pipeline sistem vs desain Figma

Dibuat 17 Nov 2026. Sumber: kode di `app/Service`, `app/controller`, `database/pena_schema.sql`,
dan teks semua layar Figma `pfBtKoFGp8ckRKkPoFX6oh` (dibaca langsung lewat API Figma).

## 1. Pipeline yang benar-benar ada di kode

```
buat draf ──► ubah draf ──► AJUKAN ──────────────► in_review
 (draft, v1)                 nomor dibuat (sekali),        │
                             isi dikunci (snapshot+hash),  │ tahap 1 pending, tautan dibuat
                             permintaan per pemeriksa      ▼
                                              pemeriksa buka /review/lihat/{token}
                                              ├─ setuju ─► tahap berikutnya pending TANPA tautan
                                              │            └─ sekretaris "Buat tautan" ─► kirim manual
                                              ├─ komentar (proses jalan terus)
                                              └─ minta revisi ─► revision_requested (proses berhenti)
 revision_requested / in_review ── Mulai revisi (ringkasan wajib) ──► draft v(n+1), nomor tetap,
                                   permintaan lama superseded       ajukan lagi ─► penyetuju lama
                                                                    dapat "reconfirm" paralel
 semua tahap setuju ─► approved          kapan saja sebelum selesai ─► Batalkan (alasan wajib) ─► cancelled
 Ganti pemeriksa saat in_review ─► revision_requested (snapshot memuat nama lama)
```

Tautan: 64 heksa, disimpan sebagai hash, berlaku 7 hari, hilang setelah keputusan.
Hanya pemeriksa berstatus `pending` yang bisa punya tautan.

Belum tersambung ke UI sama sekali: ekspor Word/PDF (`WordDocumentService`, `WordPdfConverter` ada,
tidak ada rute, `letter_exports` tidak pernah ditulis), AI (`ai_generations` tidak pernah ditulis,
`source_prompt` tidak dikirim controller), pengaturan kop dan direktori penanda tangan (hanya seed SQL),
penyelesaian komentar (`resolved_at` tanpa layanan).

## 2. Yang sudah cocok

- Status draft / in_review / revision_requested / approved, jadwal per tahap, pelat "Berikutnya".
- "Menunggu tautan dari Anda" dan "Buat tautan untuk dikirim": persis perilaku `ApprovalService::approve`
  (tahap berikutnya dibuka tanpa tautan) dan `reissueLink`.
- Tautan hanya ditampilkan sekali (02.5), sidik konten per versi, persetujuan terikat versi.
- Konfirmasi ulang setelah revisi (03.2) sesuai `kind = reconfirm`.
- Review: setuju dengan catatan opsional, minta revisi per bagian (`block_ref`), komentar tidak menghentikan proses.
- Layar selesai dan tautan tidak berlaku (03.3 sampai 03.5) sesuai pesan `review.php`.
- Nomor dibuat saat diajukan, tidak oleh AI; paragraf memakai `{{isian}}`; teks baku terkunci.
- Langkah generate cocok dengan enum `ai_generations.purpose`: memilih kerangka = `recommend_template`,
  mengisi data = `extract`, menulis paragraf = `generate`, Tulis ulang = `revise`.

## 3. Desain salah atau tidak bisa terjadi (perbaiki di Figma)

| # | Layar | Masalah | Usul |
|---|---|---|---|
| A1 | 03.6 Belum giliran | Backend tidak pernah membuat tautan untuk pemeriksa yang belum giliran, jadi layar ini tidak akan pernah muncul. | Hapus layar, atau ubah backend agar tautan dibuat sejak awal (mengubah model keamanan). Saran: hapus. |
| A2 | 02.7 Draf | Campur dua keadaan: lencana V2, "Nomor dibuat saat diajukan", pratinjau sudah bernomor, pelat "isi versi 1 dikunci", tombol "Mulai revisi" (tidak sah untuk draf), komentar versi 1. | Pisah jadi "Draf baru v1" (tanpa nomor, tanpa komentar) dan "Draf revisi v3" (lihat B2). |
| A3 | 02.11 Periksa hasil | "8 dari 9 isian", padahal PMJ punya 7 isian (8 dengan Perihal). | Hitung ulang dari kerangka. |
| A4 | 03.2 Konfirmasi ulang | Baris "Paragraf maksud 30 November → 7 Desember" tidak akan muncul: kode membandingkan teks paragraf mentah yang berisi `{{tanggal_mulai}}`, jadi teksnya sama. | Ubah `_changes` di `review.php` agar membandingkan paragraf yang sudah dirender (perubahan kecil). |
| A5 | 01.1 Landing, 02.4 | "Bantuan AI belum tersedia" dan slot "Segera" bertentangan dengan alur generate. | Samakan: AI ada di Buat surat; slot "Segera" diganti "Tulis ulang". |
| A6 | 02.11 | Kalimat "Fakta di kalimat memakai penanda, jadi selalu sama dengan data di atas" overclaim: validator hanya memastikan penanda yang dipakai terdaftar, tidak mencegah AI mengetik "7 Desember" langsung. | Fase 4 tambah cek angka/tanggal/nilai isian di teks AI, atau lunakkan kalimatnya. |
| A7 | Kertas di semua detail | Pratinjau menampilkan "di Tempat" dan "Mengetahui," sebelum Dekan; `LetterRenderer` tidak membuat keduanya, dan tanda tangan dirender berurutan tanpa pengelompokan. | Putuskan: tambahkan ke renderer + DOCX (butuh aturan "tahap terakhir = Mengetahui"), atau hapus dari desain. |
| A8 | 01.2 Masuk | Tombol akun contoh "Dosen": akun itu bukan anggota organisasi, jadi berakhir di 403. Pemeriksa PENA tidak memakai akun. | Hapus tombol Dosen. |

## 4. Ada di backend, belum didesain

| # | Keadaan | Kenapa perlu |
|---|---|---|
| B1 | Surat dibatalkan (chip daftar, keadaan detail, filter) | Status `cancelled` ada; nomornya tetap tercatat terpakai. Tidak ada satu pun layar. |
| B2 | Draf revisi (setelah Mulai revisi) | Status kembali `draft` tetapi sudah bernomor, versi n+1, ada ringkasan perubahan, penyetuju lama akan konfirmasi ulang. Ini keadaan yang paling sering dalam demo revisi. |
| B3 | Dialog Mulai revisi (ringkasan wajib), Batalkan (alasan wajib), konfirmasi Ajukan | Layanan menolak tanpa isian ini. Dua yang terakhir sudah tercatat "belum didesain". |
| B4 | Ganti pemeriksa | `replaceSigner` ada; saat surat direview, statusnya pindah ke diminta revisi. Perlu tombol dan penjelasan efeknya. |
| B5 | Tautan kedaluwarsa di sisi sekretaris | Pemeriksa pending dengan tautan lewat 7 hari perlu "Buat ulang tautan". Beranda saat ini juga tidak menghitungnya. |
| B6 | Review saat proses berhenti | Pemeriksa lain di tahap yang sama minta revisi: tautan masih bisa dibuka, tetapi tombol keputusan akan ditolak. Butuh keadaan "Proses sedang berhenti". |
| B7 | Review: galat "Dokumen berubah sejak halaman dibuka" | Dari cek hash; cukup sebagai pesan di atas tombol. |
| B8 | Review: pernyataan identitas tidak diverifikasi | Prinsip produk no. 3 meminta ini di tempat keputusan. Sisi sekretaris (02.5) sudah ada, sisi pemeriksa belum. |
| B9 | Pilihan kop surat | Ajukan ditolak bila kop kosong; form manual dan hasil generate tidak punya pilihan kop. Saran: pilih otomatis bila hanya ada satu kop aktif. |
| B10 | Beberapa pemeriksa dalam satu tahap | Didukung backend (semua wajib setuju). Jadwal di detail dan review hanya menunjukkan satu orang per tahap. |
| B11 | Riwayat lengkap dan daftar versi | Sudah tercatat "belum didesain". |

## 5. Ada di Figma, belum ada di backend (dikerjakan di fase berikutnya)

| Fitur | Keadaan kode | Fase |
|---|---|---|
| Daftar: filter status, cari, kolom Jenis | `LetterQuery::letters` mengembalikan semua tanpa filter | 2 |
| Unduh Word/PDF, "PDF gagal, Coba lagi" | Layanan ada, rute dan `letter_exports` belum | 5 |
| Seluruh alur generate | Belum ada controller, klien 9router, maupun penulisan `ai_generations` | 4 |
| Progres 4 langkah | PHP biasa tidak bisa melapor bertahap dalam satu permintaan. Jujurnya: 3 sampai 4 panggilan AJAX berurutan (satu per purpose, masing-masing dicatat), atau satu indikator tanpa langkah. | 4 |
| "Perlu keterangan" | Tidak ada purpose khusus; dicatat sebagai `extract` dengan output pertanyaan | 4 |
| Alur persetujuan dari kalimat | Harus dicocokkan ke tabel `signers`; desain belum punya keadaan "jabatan tidak ada di direktori" | 4 |
| "Ubah di pengaturan kop" | Tidak ada halaman pengaturan kop maupun direktori penanda tangan, di kode atau di Figma | perlu keputusan |
| Tandai komentar selesai | Kolom ada, layanan dan desain belum | opsional |

## 6. Sisa template dosen

Controller `mahasiswa`, `panduan`, `admin`, `setup`, `home` masih bisa dibuka lewat URL. Figma tidak memuatnya.
Di Fase 2 perlu diputuskan: disembunyikan dari menu saja, atau dibatasi untuk admin.

## Status perbaikan (17 Nov 2026)

Sudah dikerjakan di Figma: A1 (03.6 diubah jadi "Proses berhenti", sekaligus B6), A2 (02.7 jadi draf baru v1),
A3 (6 dari 7, HP "3 isian lainnya"), A5, A6 (kalimat diganti janji yang harus dipenuhi Fase 4), A8,
B1 (02.15 Dibatalkan, varian Status mark dan Letter row, tab dan baris di Daftar), B2 (02.14 Draf revisi v3),
B3 dan B4 (dialog 02.16 sampai 02.19), B8, B9 (baris kop otomatis di form manual dan hasil generate).
Cerita 02.6 disesuaikan: revisi versi 2 sekarang soal daftar barang, sorotan pindah ke tabel.

Koreksi atas analisis ini: B8 sebagian salah. Footer review sudah memuat pernyataan identitas; yang diubah
adalah memindahkannya ke dekat tombol keputusan.

Belum dikerjakan: A4 dan A7 (perubahan kode, dicatat di FIGMA-HANDOFF.md), B5, B7, B10, B11, dan seluruh bagian 5.

## 7. Urutan yang disarankan

1. Perbaiki A1 sampai A8 di Figma (kecil, kebanyakan teks).
2. Desain B1, B2, B3, B4, B6, B8, B9 (sekitar 7 frame); sisanya cukup sebagai catatan perilaku.
3. Putuskan halaman pengaturan kop dan penanda tangan: masuk cakupan 21 Nov atau tidak.
4. Baru setujui Figma sebagai patokan Fase 2.
