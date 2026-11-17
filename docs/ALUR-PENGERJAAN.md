# Alur pengerjaan PENA

Ditetapkan pemilik proyek pada 17 Nov 2026. Tenggat: 21 Nov 2026.
Urutan ini mengikat: fase berikutnya dimulai setelah fase sebelumnya disetujui pemilik.

## Fase 0: Fondasi (sudah ada, dipertahankan)

Skema database, layanan surat, alur persetujuan, nomor surat, DOCX/PDF via Word, dan tesnya
sudah jalan (`composer test`, `test:schema`, `test:flow`). Fase berikut tidak mengulang ini;
perubahan hanya bila desain Figma membutuhkannya.

## Fase 1: Desain di Figma (dikerjakan Codex, diaudit Claude)

Figma menjadi patokan UI/UX untuk semua fase berikutnya.

- Dasar: design system (warna, tipografi, jarak, radius, komponen) lalu frame desktop dan mobile.
- Halaman: Landing, Masuk, Beranda, Daftar surat, Buat/ubah surat, Detail surat,
  Review lewat tautan, Selesai/tautan kedaluwarsa, Error.
- Wajib bebas sisa template dosen (menu, teks, ikon, contoh CRUD mahasiswa) di alur PENA.
- Skill anti AI slop: antislop (mode during), design-taste-frontend, impeccable, ui-ux-pro-max.
  Arah awal dari [DESIGN.md](../DESIGN.md); boleh direvisi di fase ini.
- Selesai bila: pemilik menyetujui frame, dan audit impeccable + antislop atas Figma tidak
  menyisakan temuan prioritas tinggi.

## Fase 2: UI statis dari Figma (Tailwind)

- Migrasi tampilan dari Bootstrap ke Tailwind CSS, mengikuti Figma. Router/MVC template tetap.
  Catatan: konfirmasi ke dosen bahwa mengganti Bootstrap masih dianggap memakai template.
- Halaman disambungkan ke fitur yang sudah ada (buat, ajukan, review, revisi).
- Selesai bila: tampilan cocok dengan Figma di 375 dan 1440 px, tidak ada error console,
  audit impeccable P1/P2 bersih, Delivery Gate antislop PASS.

## Fase 3: Database (bila perlu)

Penyesuaian skema yang muncul dari desain atau fitur baru saja. Setiap perubahan disertai tes.

## Fase 4: AI gateway (9router)

- Pelajari dokumentasi resmi 9router dulu, baru integrasi.
- Kunci API hanya di `.env`. Setiap panggilan dicatat di `ai_generations`.
- AI hanya menulis paragraf dengan penanda `{{isian}}`; fakta tetap dari isian data.

## Fase 5: Fitur

Ekspor Word/PDF untuk surat disetujui, bantuan AI di form surat, dan fitur lain yang disepakati.

## Status

| Fase | Status |
|---|---|
| 0 Fondasi | Selesai |
| 1 Figma | Disetujui pemilik 17 Nov 2026 setelah perbaikan dari [ANALISIS-PIPELINE-VS-FIGMA.md](ANALISIS-PIPELINE-VS-FIGMA.md). Ringkasan: [FIGMA-HANDOFF.md](FIGMA-HANDOFF.md). |
| 2 UI statis | Build selesai 17 Nov 2026: semua halaman PENA di Tailwind v4, dicek di 375 dan 1440 px, tes lulus. Audit impeccable 15/20 dan Delivery Gate antislop PASS ([AUDIT-FASE-2.md](AUDIT-FASE-2.md)). P1/P2 bersih (huruf kini di proyek). DESIGN.md ditulis ulang dari hasil build. Sisa kecil: label "Mengetahui," di kertas (butuh aturan tahap), tautan lompat ke konten di halaman tanpa rail. Halaman contoh template tetap Bootstrap. |
| 3 Database | Tidak perlu perubahan: `ai_generations` dan `letters.source_prompt` sudah ada sejak Fase 0. |
| 4 AI 9router | Kode selesai 17 Nov 2026 (AiClient, LetterAiService, halaman Buat surat dan Periksa hasil yang tersambung, `composer test:ai` lulus). Uji nyata lewat 9router (WSL): pipeline lengkap jalan untuk PMJ dan PMH dengan `flash-medium` (26 sampai 36 detik); `flash-high` terlalu lambat. Ditemukan dan diperbaiki: batas 30 detik PHP memutus permintaan tanpa log; nilai yang tidak tertulis di kalimat kini selalu "Perlu dicek"; nama tahap dari jabatan. Uji browser dengan `AI_MODEL=ag/gemini-3.8-flash-medium` berhasil penuh: ceritakan → tiga langkah (sekitar 31 detik) → periksa hasil → tulis ulang → koreksi isian → simpan draf (surat #4), empat log AI tertaut dan riwayat mencatat "Disusun dengan AI". Semua suite tes lulus. |
| 5 Fitur | Menunggu |
