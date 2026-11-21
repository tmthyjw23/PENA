---
name: PENA
description: Surat organisasi mahasiswa dari draf sampai disetujui, dibaca seperti jadwal keberangkatan.
colors:
  rail-goldenrod: "#E7B53B"
  carbon: "#1D1D1B"
  ink-blue: "#1F3A8A"
  ink-blue-deep: "#182E70"
  ink-blue-wash: "#E6EBF7"
  bottle-green: "#1F6B46"
  bottle-green-wash: "#E2F0E8"
  vermilion: "#C2381A"
  vermilion-wash: "#FBE6E0"
  highlighter: "#FBF0D2"
  station-board: "#263238"
  station-board-row: "#34434B"
  board-green: "#9ED5B6"
  board-vermilion: "#F2A391"
  board-text-muted: "#B9C5CB"
  platform-paper: "#F4F4F1"
  sheet-white: "#FFFFFF"
  raised-stone: "#ECEBE6"
  ticket-cream: "#F6F2E4"
  graphite: "#56554F"
  pencil-grey: "#6B6A64"
  rule-grey: "#D9D7CF"
typography:
  display:
    fontFamily: "Barlow Condensed, Barlow, ui-sans-serif, sans-serif"
    fontSize: "96px"
    fontWeight: 700
    lineHeight: 0.95
    letterSpacing: "-0.015em"
  headline:
    fontFamily: "Barlow Condensed, Barlow, ui-sans-serif, sans-serif"
    fontSize: "40px"
    fontWeight: 700
    lineHeight: 1
  title:
    fontFamily: "Barlow, ui-sans-serif, system-ui, sans-serif"
    fontSize: "16px"
    fontWeight: 700
    lineHeight: "24px"
  body:
    fontFamily: "Barlow, ui-sans-serif, system-ui, sans-serif"
    fontSize: "16px"
    fontWeight: 400
    lineHeight: "24px"
    fontFeature: "tnum"
  body-strong:
    fontFamily: "Barlow, ui-sans-serif, system-ui, sans-serif"
    fontSize: "16px"
    fontWeight: 600
    lineHeight: "24px"
  label:
    fontFamily: "Barlow Condensed, Barlow, ui-sans-serif, sans-serif"
    fontSize: "16px"
    fontWeight: 700
    lineHeight: "24px"
    letterSpacing: "0.02em"
  column-head:
    fontFamily: "Barlow Condensed, Barlow, ui-sans-serif, sans-serif"
    fontSize: "16px"
    fontWeight: 600
    lineHeight: "24px"
    letterSpacing: "0.02em"
  paper:
    fontFamily: "Tinos, Times New Roman, Times, serif"
    fontSize: "16px"
    fontWeight: 400
    lineHeight: "24px"
rounded:
  strip: "2px"
  paper: "2px"
  control: "4px"
  panel: "4px"
spacing:
  "1": "4px"
  "2": "8px"
  "3": "12px"
  "4": "16px"
  "5": "20px"
  "6": "24px"
  "8": "32px"
  "10": "40px"
  "12": "48px"
  "16": "64px"
components:
  button-primary:
    backgroundColor: "{colors.ink-blue}"
    textColor: "{colors.sheet-white}"
    typography: "{typography.body-strong}"
    rounded: "{rounded.control}"
    padding: "10px 16px"
    height: "44px"
  button-primary-hover:
    backgroundColor: "{colors.ink-blue-deep}"
  button-primary-disabled:
    backgroundColor: "{colors.raised-stone}"
    textColor: "{colors.pencil-grey}"
  button-secondary:
    backgroundColor: "{colors.sheet-white}"
    textColor: "{colors.carbon}"
    typography: "{typography.body-strong}"
    rounded: "{rounded.control}"
    padding: "10px 16px"
    height: "44px"
  button-secondary-hover:
    backgroundColor: "{colors.raised-stone}"
  button-quiet:
    textColor: "{colors.vermilion}"
    typography: "{typography.body-strong}"
    padding: "10px 8px"
    height: "44px"
  button-danger:
    backgroundColor: "{colors.vermilion}"
    textColor: "{colors.sheet-white}"
    typography: "{typography.body-strong}"
    rounded: "{rounded.control}"
    padding: "10px 16px"
    height: "44px"
  field:
    backgroundColor: "{colors.sheet-white}"
    textColor: "{colors.carbon}"
    typography: "{typography.body}"
    rounded: "{rounded.control}"
    padding: "10px 12px"
    height: "44px"
  strip-outline:
    backgroundColor: "{colors.sheet-white}"
    textColor: "{colors.carbon}"
    typography: "{typography.label}"
    rounded: "{rounded.strip}"
    padding: "0 6px"
  strip-carbon:
    backgroundColor: "{colors.carbon}"
    textColor: "{colors.ticket-cream}"
    typography: "{typography.label}"
    rounded: "{rounded.strip}"
    padding: "0 6px"
  strip-ink:
    backgroundColor: "{colors.ink-blue}"
    textColor: "{colors.sheet-white}"
    typography: "{typography.label}"
    rounded: "{rounded.strip}"
    padding: "0 6px"
  strip-approved:
    backgroundColor: "{colors.bottle-green}"
    textColor: "{colors.sheet-white}"
    typography: "{typography.label}"
    rounded: "{rounded.strip}"
    padding: "0 6px"
  strip-revision:
    backgroundColor: "{colors.vermilion}"
    textColor: "{colors.sheet-white}"
    typography: "{typography.label}"
    rounded: "{rounded.strip}"
    padding: "0 6px"
  next-plate:
    backgroundColor: "{colors.carbon}"
    textColor: "{colors.ticket-cream}"
    typography: "{typography.body}"
    rounded: "{rounded.panel}"
    padding: "20px"
  panel:
    backgroundColor: "{colors.sheet-white}"
    textColor: "{colors.carbon}"
    rounded: "{rounded.panel}"
    padding: "20px"
  letter-paper:
    backgroundColor: "{colors.sheet-white}"
    textColor: "{colors.carbon}"
    typography: "{typography.paper}"
    rounded: "{rounded.paper}"
    padding: "40px 56px"
  route-row-yours:
    backgroundColor: "{colors.highlighter}"
    textColor: "{colors.carbon}"
    padding: "12px 16px"
  nav-item-active:
    backgroundColor: "{colors.carbon}"
    textColor: "{colors.ticket-cream}"
    typography: "{typography.body-strong}"
    rounded: "{rounded.control}"
    padding: "0 12px"
    height: "44px"
  rail:
    backgroundColor: "{colors.rail-goldenrod}"
    textColor: "{colors.carbon}"
    width: "220px"
    padding: "20px 16px"
---

# Design System: PENA

Ditulis ulang 17 Nov 2026 dari hasil build Fase 2 (Tailwind v4). Sumber token: `resources/css/app.css`
(`@theme`), yang sendiri berasal dari Figma `pfBtKoFGp8ckRKkPoFX6oh`. Bila kode dan dokumen ini berbeda, kode yang benar
dan dokumen ini diperbarui.

## Overview

**Creative North Star: "Jadwal Saku"**

Status surat dibaca seperti jadwal keberangkatan di stasiun atau bandara: setiap surat dan setiap pemeriksa adalah satu
baris di kolom tetap, dengan kode pendek di kiri dan status di kanan. Sekretaris tidak perlu bertanya di grup WhatsApp
"surat ini sampai mana"; jawabannya terbaca dalam satu pandang. Di setiap layar ada tepat satu pelat karbon "Berikutnya"
yang menjawab "apa yang harus saya lakukan sekarang".

Antarmuka berdisiplin seperti jadwal cetak: satu ukuran huruf, kode kondensed kapital, garis tipis, dan warna yang
hanya muncul bila membawa arti (biru tinta untuk aksi, hijau botol untuk disetujui, merah vermilion untuk revisi atau
galat). Satu-satunya objek yang diangkat dari bidang adalah kertas surat, karena surat adalah hasil kerja yang
sebenarnya; antarmuka melayaninya. Arah ini dipilih pemilik lewat undian impeccable (seed `1908f8b73e282859`) dan
disetujui di Figma. Yang ditolak: dasbor SaaS putih dengan kartu dan badge pil.

Dial: ENERGY 2, RHYTHM 2, MOTION 1 untuk aplikasi; landing dan masuk ENERGY 3 (mode Persuade).

**Key Characteristics:**
- Rail goldenrod di kiri (desktop) atau bilah atas dan tab bar goldenrod (HP) sebagai bidang dunia jadwal.
- Strip kode kondensed kapital: PMJ, V2, T1, REVISI, DRAF.
- Baris jadwal per pemeriksa dengan tanda status geometris dan teks status.
- Satu pelat karbon "Berikutnya" per layar.
- Kertas surat Tinos dengan kop bergaris ganda, satu-satunya permukaan berbayang.
- Satu ukuran huruf 16/24 di seluruh aplikasi.

## Colors

Palet jadwal cetak: bidang goldenrod, tinta karbon, satu biru tinta untuk aksi, dan dua warna status.

### Primary
- **Biru Tinta** (`ink-blue`): tombol utama, tautan, cincin fokus, tanda "menunggu keputusan", strip "Berikutnya" dan
  "Kirim tautan ini". Komitmen merek pemilik; kontras teks putih di atasnya sekitar 10:1. Hover menjadi `ink-blue-deep`.
- **Goldenrod Rail** (`rail-goldenrod`): rail navigasi, bilah atas HP, tab bar, hero landing dan sisi kiri halaman masuk.
  Hanya bidang navigasi dan Persuade; tidak pernah di dalam konten aplikasi.

### Secondary
- **Hijau Botol** (`bottle-green`): hanya status disetujui (tanda, teks, strip "Disetujui semua pihak"). Latar lembutnya
  `bottle-green-wash` untuk flash berhasil.
- **Vermilion** (`vermilion`): hanya revisi, galat, dan aksi merusak (Batalkan surat). 5,6:1 di putih; 4,5:1 di
  `vermilion-wash`, latar baris "Kosong" dan pesan galat.

### Tertiary
- **Stabilo** (`highlighter`): baris jadwal yang menunggu Anda, isian "Perlu dicek", seleksi teks, sorotan "Baru" di
  daftar perubahan, dan chip penanda isian.
- **Papan Stasiun** (`station-board`, `station-board-row`): hanya papan jadwal contoh di landing, dengan status di
  `board-green`, `board-vermilion`, dan teks redup `board-text-muted`.

### Neutral
- **Karbon** (`carbon`): teks utama, garis kuat, pelat "Berikutnya", item navigasi aktif, strip carbon.
- **Krem Tiket** (`ticket-cream`): teks di atas pelat karbon dan strip carbon; juga cincin fokus di dalam pelat.
- **Kertas Peron** (`platform-paper`): latar halaman.
- **Putih Lembar** (`sheet-white`): panel, kertas surat, isian.
- **Batu Terangkat** (`raised-stone`): teks baku kerangka yang terkunci, permintaan "Anda" di hasil AI, tombol nonaktif.
- **Grafit** (`graphite`): teks sekunder (keterangan, waktu, bantuan isian), 6,9:1 di latar halaman.
- **Pensil** (`pencil-grey`): placeholder, tanda "belum giliran", teks nonaktif. 4,96:1 di latar halaman.
- **Garis** (`rule-grey`): garis antarbaris dan tepi isian.

### Named Rules
**The Meaning-Only Rule.** Hijau hanya berarti disetujui dan vermilion hanya berarti revisi, galat, atau merusak. Warna
status tidak pernah menjadi hiasan, dan selalu disertai teks status.

**The One Ink Rule.** Biru tinta dipakai jarang, untuk satu hal: "tekan ini" (aksi, tautan, fokus). Di satu layar paling
banyak satu tombol utama biru tinta per area keputusan.

**The Rail Stays Outside Rule.** Goldenrod membingkai aplikasi (rail, bilah, landing), tidak pernah masuk ke panel atau
kertas.

## Typography

**Display Font:** Barlow Condensed (cadangan Barlow, sans sistem)
**Body Font:** Barlow (cadangan sans sistem)
**Paper Font:** Tinos (metrik sama dengan Times New Roman; cadangan Times New Roman)

**Character:** Grotesk kondensed bebas lisensi yang paling dekat dengan suara jadwal cetak; Tinos di kertas agar
pratinjau sama dengan hasil Word dan PDF. Semua huruf disimpan di `public/assets/fonts` (subset latin), tanpa Google Fonts.

### Hierarchy
- **Display** (Barlow Condensed 700, 96 px di desktop, 80 px di tablet, 56 px di HP, line-height 0.95): judul landing.
  Halaman masuk 64/40 px, judul galat 56/40 px.
- **Headline** (Barlow Condensed 700, 40 px, line-height 1): judul bagian landing "Cara surat bergerak".
- **Title** (Barlow 700, 16/24): judul halaman dan judul panel di aplikasi. Ukurannya sama dengan isi; hierarki datang
  dari tebal, posisi, dan pelat.
- **Body** (Barlow 400, 16/24, angka tabular): semua teks antarmuka. Paragraf penjelas dibatasi 44 sampai 72 ch.
- **Body Strong** (Barlow 600, 16/24): nama orang, perihal surat, label isian, tombol.
- **Label** (Barlow Condensed 700, 16/24, kapital, 0.02em): strip kode.
- **Column Head** (Barlow Condensed 600, 16/24, kapital, 0.02em): kepala kolom tabel dan judul kecil seperti
  "Jadwal persetujuan" di halaman review.
- **Paper** (Tinos 400/700, 16/24 di desktop dan tablet, 14/21 di HP): seluruh isi kertas surat; paragraf blok rata
  kanan kiri, kop dan metadata tidak.

### Named Rules
**The One Size Rule.** Antarmuka aplikasi memakai satu ukuran, 16/24. Detektor impeccable menandainya sebagai
`flat-type-hierarchy`; itu disengaja dan disetujui pemilik. Jangan menambah ukuran judul di aplikasi; naikkan
tebal, pakai strip, atau pindahkan ke pelat. Ukuran besar hanya di landing, masuk, dan halaman galat.

**The Condensed Is For Codes Rule.** Huruf kondensed kapital hanya untuk kode pendek dan kepala kolom, tidak untuk kalimat.

## Layout

Aplikasi sekretaris: rail tetap 220 px di kiri mulai 1024 px; di bawahnya bilah atas goldenrod 56 px dan tab bar
bawah 64 px (Beranda, Surat, Buat surat). Isi utama memakai padding 16 px di HP dan 32 px di desktop, lebar maksimum
1160 px. Halaman review (pemeriksa tanpa akun) tidak punya rail: bilah atas goldenrod "Pemeriksaan surat" saja.

Susunan dua kolom (kertas atau isian di kiri, jadwal dan pelat di kanan dengan lebar 340 sampai 520 px) baru dipakai
mulai 1280 px, karena rail sudah memakan 220 px. Di bawah 1280 px semua halaman satu kolom. Urutan satu kolom di
detail surat: pelat "Berikutnya", jadwal, kertas, komentar, riwayat; urutan DOM sama dengan urutan visual itu, jadi
urutan Tab di HP benar. Di 1280 ke atas jadwal tampil di atas pelat.

Jarak memakai skala 4 px (4, 8, 12, 16, 20, 24, 32, 40, 48, 64). Antarpanel 24 px; isi panel 16 px di HP dan 20 px di
desktop. Di HP, bilah keputusan review (Minta revisi / Setujui) menempel di bawah layar.

**The One Plate Rule.** Tepat satu pelat karbon "Berikutnya" per layar. Di HP pelat ada tepat di bawah judul.

## Elevation & Depth

Sistem ini datar dan berlapis tonal: halaman `platform-paper`, panel `sheet-white`, area terkunci `raised-stone`,
pelat `carbon`. Garis tipis `rule-grey` memisahkan baris. Bayangan hanya dua.

### Shadow Vocabulary
- **Kertas** (`box-shadow: 0 1px 2px rgb(29 29 27 / 0.06), 0 8px 24px rgb(29 29 27 / 0.08)`): kertas surat saja, di detail,
  review, dan hasil AI. Juga menu akun HP.
- **Papan** (`box-shadow: 0 12px 32px rgb(29 29 27 / 0.25)`): papan jadwal contoh di landing.

### Named Rules
**The Paper Is The Only Object Rule.** Hanya kertas surat yang diangkat. Panel, tombol, dan pelat datar.

## Shapes

Dunia cetak bersudut tegas: strip kode dan kertas 2 px, kontrol dan panel 4 px. Tidak ada pil, tidak ada sudut
besar. Kop surat memakai garis bawah ganda 4 px. Tanda status adalah geometri 16 px yang digambar sebagai SVG:
lingkaran penuh bercentang (disetujui), cincin tebal (menunggu), cincin putus-putus (belum giliran), cincin bergaris
miring (revisi), lingkaran abu penuh dengan garis datar (dibatalkan). Penanda isian di teks diberi latar `ink-blue-wash`;
tombol sisip penanda memakai garis putus-putus di atas stabilo.

## Components

### Buttons
Tegas dan polos, seperti tombol mesin tiket.
- **Shape:** sudut 4 px, tinggi minimal 44 px, padding samping 16 px, Barlow 600.
- **Primary:** biru tinta dengan teks putih; hover biru tinta tua. Satu per area keputusan.
- **Secondary:** putih dengan garis karbon 1 px; hover `raised-stone`. Di atas pelat garisnya putih.
- **Quiet:** teks vermilion tanpa bidang, untuk "Batalkan surat"; hover bergaris bawah.
- **Danger:** bidang vermilion, hanya untuk konfirmasi di dialog batal.
- **Disabled:** `raised-stone` dengan teks pensil, selalu disertai kalimat alasan di dekatnya (misal ekspor belum tersambung).
- **Focus:** cincin 3 px biru tinta berjarak 2 px; tombol utama cincin karbon; di dalam pelat cincin krem tiket.
- **Motion:** hanya transisi warna 150 ms ease-out.

### Strip Kode
Penanda inti dunia jadwal. Barlow Condensed 700 kapital, sudut 2 px, tinggi 24 px. Nada: outline (jenis surat, versi),
karbon (tahap T1/T2, "Draf"), biru tinta ("Berikutnya", "Kirim tautan ini", jenis terpilih), hijau ("Disetujui semua
pihak"), vermilion ("Revisi", "Kosong").

### Baris Jadwal (Route Row)
Strip tahap karbon di kiri; nama (600), jabatan (grafit), lalu baris status: tanda geometris + teks status berwarna
+ waktu `dd.mm HH.ii` dalam grafit. Garis bawah `rule-grey`. Baris yang menunggu tindakan Anda berlatar stabilo dan
melebar sampai tepi panel. Aksi kecil (Ganti pemeriksa) berupa tautan biru tinta setinggi 44 px.

### Next Plate (Pelat Berikutnya)
Bidang karbon, teks krem tiket, padding 20 px, sudut 4 px. Dibuka dengan strip (Berikutnya, Draf, Revisi diminta,
Disetujui semua pihak, Dibatalkan), lalu satu sampai dua kalimat, lalu tombol. Isinya mengikuti keadaan surat.

### Cards / Containers (Panel)
Putih, sudut 4 px, tanpa garis dan tanpa bayangan, padding 16/20 px. Tidak pernah bersarang; baris di dalamnya
dipisah garis, bukan kartu.

### Inputs / Fields
- **Style:** putih, garis `rule-grey` 1 px, sudut 4 px, tinggi minimal 44 px, label Barlow 600 di atas, bantuan grafit di bawah.
- **Hover:** garis grafit. **Focus:** garis biru tinta + cincin 2 px. **Error:** garis vermilion, pesan vermilion di
  bawah, `aria-invalid` dan `aria-describedby`.
- Isian opsional ditandai "Opsional" di teks bantuan; isian wajib tidak diberi tanda bintang.

### Letter Paper (Kertas Surat)
Tinos di atas putih, sudut 2 px, bayangan kertas, padding 40/56 px (20 px di HP). Kop rata tengah tebal kapital dengan
garis bawah ganda; nomor dan perihal di kiri, tanggal di kanan; "Kepada ... di Tempat"; paragraf blok rata kanan kiri;
tabel bergaris karbon 1 px; tanda tangan dua kolom, yang ganjil terakhir di tengah. Blok yang diminta revisi diberi latar
`vermilion-wash` dan garis putus-putus vermilion.

### Navigation
Rail: logo strip "PENA" + "Surat organisasi", tiga item 44 px; aktif = bidang karbon teks krem tiket 600; hover
karbon 10%. Bawah rail: nama organisasi, nama dan peran, "Keluar". HP: bilah atas dengan strip PENA, nama halaman,
dan inisial (menu akun); tab bar tiga item dengan aktif berbidang karbon.

### Dialog
`<dialog>` asli 560 px, putih, padding 32 px, latar belakang karbon 60%. Judul Barlow 700, penjelasan grafit, satu isian
wajib bila layanan memintanya (ringkasan revisi, alasan batal), tombol aksi lalu "Kembali". Esc menutup. Dipakai untuk
Ajukan, Mulai revisi, Batalkan, Ganti pemeriksa; `?buka=dlg-...` membukanya saat halaman dimuat.

## Do's and Don'ts

### Do:
- **Do** pakai satu pelat "Berikutnya" per layar dan letakkan aksi utama layar itu di dalamnya.
- **Do** tulis status dengan tanda geometris dan teks bersamaan; warna tidak pernah sendirian.
- **Do** pakai strip kode untuk jenis surat, versi, dan tahap, dengan teks kapital pendek.
- **Do** jaga target sentuh 44 px (tab bar 48 px isi, bilah keputusan review 44 px).
- **Do** tandai data contoh dan kontrol yang belum tersambung dengan kalimat jujur di dekatnya.
- **Do** jalankan `npm run css` setelah mengubah view atau token; hasil build ikut disimpan.

### Don't:
- **Don't** menambah ukuran huruf di antarmuka aplikasi (lihat The One Size Rule).
- **Don't** memakai hijau atau vermilion untuk hiasan, atau biru tinta untuk hal yang tidak bisa ditekan.
- **Don't** memberi bayangan pada panel, tombol, atau pelat; hanya kertas surat (dan papan landing) yang berbayang.
- **Don't** membuat sudut lebih dari 4 px atau bentuk pil.
- **Don't** memakai goldenrod di dalam panel atau kertas.
- **Don't** memuat huruf dari Google Fonts atau CDN; huruf ada di `public/assets/fonts`.
- **Don't** memakai mode gelap: kertas surat harus sama dengan cetakan.
- **Don't** menulis em dash di teks antarmuka.

## Penyesuaian antarmuka — 20 November 2026

Judul halaman aplikasi memakai 24/32 px, judul bagian 18/26 px, teks utama tetap 16/24 px.
Identitas kuning/karbon, huruf Barlow dan kertas surat dipertahankan. Petunjuk awal berupa panel
inline yang dapat ditutup; bantuan kontekstual memakai disclosure native. Tidak ada tour modal
yang muncul otomatis. Detail evaluasi dan batas pengujian: docs/UX-GUIDANCE-2026-11-20.md.
