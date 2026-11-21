<?php
$tips=[
 'buat'=>['Ceritakan kebutuhan surat',['Sebut tujuan, penerima dan data yang diketahui. Gaya bahasa dapat formal atau pribadi.','Periksa hasil AI sebelum menyimpan. Pilih Isi formulir bila ingin menyusun tanpa AI.'],'buat'],
 'periksa'=>['Periksa sebelum menyimpan',['Cocokkan nama, tanggal, angka dan maksud surat. Penanda mengambil data dari isian.','Simpan draf belum berarti mengajukan persetujuan. Anda masih dapat mengubah draf yang belum diajukan.'],'periksa'],
 'form'=>['Mengisi dan mengubah draf',['Ubah nama, tanggal dan nominal melalui isian. Teks yang memakai penanda akan mengikuti data tersebut.','Simpan sebelum berpindah halaman. Isian tidak tersimpan otomatis.'],'periksa'],
 'status'=>['Membaca status surat',['Periksa versi dan tahap yang sedang menunggu keputusan. Kirim tautan hanya kepada pemeriksa yang dituju.','Setelah versi diajukan, perubahan dilakukan lewat revisi. Persetujuan dan gambar tanda tangan berlaku untuk versi yang diperiksa.'],'persetujuan'],
 'ai'=>['Memilih koneksi AI',['Pilih provider dan simpan API key Anda setelah masuk. AI bawaan hanya untuk operator platform.','Simpan pengaturan lalu gunakan Uji koneksi pribadi. Kuota/biaya mengikuti provider Anda; jangan bagikan API key.'],'ai'],
 'review'=>['Sebelum memberi keputusan',['Baca isi lengkap dan versi surat. Pilih Minta revisi jika ada data atau kalimat yang perlu diperbaiki.','Gambar tanda tangan opsional. Gunakan tanda tangan milik Anda dan konfirmasikan pemakaian untuk versi ini.'],'persetujuan'],
];
$tip=$tips[$helpTopic]??null;
?>
<?php if($tip): ?><details class="context-help mb-5 rounded-control border border-line bg-surface p-3"><summary class="min-h-9 cursor-pointer font-semibold">Petunjuk: <?= e($tip[0]); ?></summary><ul class="mt-3 list-disc space-y-2 pl-5"><?php foreach($tip[1]as$text): ?><li><?= e($text); ?></li><?php endforeach; ?></ul><?php if(empty($guestHelp)): ?><a class="mt-2 inline-flex min-h-11 items-center underline" href="<?= APP_PATH; ?>/bantuan/#<?= e($tip[2]); ?>">Lihat panduan</a><?php endif; ?></details><?php endif; ?>
