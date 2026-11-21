-- =====================================================================
-- PENA: akun, organisasi, surat, versi, persetujuan bertahap, log AI
-- Skema lengkap dalam satu berkas. Pilih database lebih dulu (composer db:install membuatnya
-- dari DB_NAME di .env). Target: MariaDB 10.4+ / MySQL 8. Aman dijalankan ulang.
-- Aturan yang dijaga lapisan aplikasi (bukan trigger, agar impor phpMyAdmin tetap jalan):
--   * letter_versions tidak boleh diubah setelah submitted_at terisi; revisi = versi baru.
--   * Persetujuan hanya berlaku untuk version_id tempatnya dicatat.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Akun orang yang masuk ke PENA. Setiap akun adalah sekretaris (pembuat surat) di ruang kerjanya.
-- Pemeriksa tidak perlu akun. Hak operator platform terpisah dari keanggotaan organisasi.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `nama_lengkap` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `last_login` DATETIME NULL,
  `email_verified_at` DATETIME NULL,
  `auth_version` INT UNSIGNED NOT NULL DEFAULT 1,
  `guide_dismissed_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Akun contoh untuk pengujian lokal (ganti atau nonaktifkan sebelum dipakai sungguhan):
--   sekretaris / sekretaris123 (BEM + pribadi), admin / admin123 (anggota BEM; nama akun lama),
--   anggota / sekretaris123 (hanya ruang pribadi). Baris yang sudah ada tidak diubah.


-- ---------------------------------------------------------------------
-- Ruang kerja (nama tabel tetap organizations): pemilik kop, nomor, direktori pemeriksa, arsip.
--   kind=personal     : dibuat otomatis saat daftar; tanpa kop dan nomor; profil mengisi identitas surat.
--   kind=organization : dibuat oleh siapa pun; kop, nomor berseri, alur bertahap.
-- owner_user_id hanya penanda pemilik (pengaturan AI nanti), bukan peran; bisa dialihkan.
-- Perubahan skema sejak 18 Nov 2026 lewat database/migrations (Pena\Support\Migrator).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `organizations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kind` ENUM('personal', 'organization') NOT NULL DEFAULT 'organization',
  `name` VARCHAR(150) NOT NULL,
  `short_name` VARCHAR(30) NOT NULL UNIQUE,
  `owner_user_id` INT NULL,
  `profile` JSON NULL,
  `verified_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_org_owner` (`owner_user_id`, `kind`),
  CONSTRAINT `fk_org_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `organization_members` (
  `organization_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  -- Semua anggota setara (sekretaris); tidak ada peran admin.
  `role` ENUM('secretary') NOT NULL DEFAULT 'secretary',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`organization_id`, `user_id`),
  INDEX (`user_id`),
  FOREIGN KEY (`organization_id`) REFERENCES `organizations`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `letterheads` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `organization_id` INT NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `address` VARCHAR(255) NULL,
  `contact` VARCHAR(255) NULL,
  `logo_path` VARCHAR(255) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`organization_id`, `is_active`),
  FOREIGN KEY (`organization_id`) REFERENCES `organizations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Direktori penanda tangan. Bisa tanpa akun (review lewat tautan).
CREATE TABLE IF NOT EXISTS `signers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `organization_id` INT NOT NULL,
  `user_id` INT NULL,
  `name` VARCHAR(150) NOT NULL,
  `position` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`organization_id`, `is_active`),
  FOREIGN KEY (`organization_id`) REFERENCES `organizations`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Kerangka surat (blok terkontrol). organization_id NULL = kerangka bawaan dari operator.
-- audience: untuk ruang organisasi atau ruang pribadi.
-- definition: {"fields":[...], "blocks":[...]} — isian terstruktur + blok yang diizinkan.
-- Kerangka tidak diubah di tempat; perubahan = baris baru dengan revision+1.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `letter_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `organization_id` INT NULL,
  `audience` ENUM('organization', 'personal') NOT NULL DEFAULT 'organization',
  `code` VARCHAR(40) NOT NULL,
  `revision` INT NOT NULL DEFAULT 1,
  `name` VARCHAR(150) NOT NULL,
  `description` VARCHAR(255) NULL,
  `number_series` VARCHAR(20) NOT NULL DEFAULT 'UMUM',
  `number_format` VARCHAR(120) NOT NULL DEFAULT '{seq}/{org}/{code}/{month_roman}/{year}',
  `definition` JSON NOT NULL,
  `is_generated` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  -- organization_id NULL tidak dianggap sama oleh UNIQUE; kerangka bawaan dijaga lewat seed.
  UNIQUE KEY `uq_template_revision` (`organization_id`, `code`, `revision`),
  INDEX (`code`, `is_active`),
  FOREIGN KEY (`organization_id`) REFERENCES `organizations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Penghitung nomor. Diambil dengan SELECT ... FOR UPDATE di dalam transaksi.
CREATE TABLE IF NOT EXISTS `number_counters` (
  `organization_id` INT NOT NULL,
  `series` VARCHAR(20) NOT NULL,
  `year` SMALLINT NOT NULL,
  `last_value` INT NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`organization_id`, `series`, `year`),
  FOREIGN KEY (`organization_id`) REFERENCES `organizations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Surat dan versinya.
-- Nomor diberikan saat pertama kali diajukan; tetap sama lintas revisi.
-- Surat batal tidak dihapus: nomornya tetap tercatat terpakai.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `letters` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `organization_id` INT NOT NULL,
  `template_id` INT NOT NULL,
  `letterhead_id` INT NULL,
  `created_by` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `source_prompt` TEXT NULL,
  `status` ENUM('draft', 'in_review', 'revision_requested', 'approved', 'cancelled')
           NOT NULL DEFAULT 'draft',
  `current_version_id` INT NULL,
  `number_series` VARCHAR(20) NULL,
  `number_year` SMALLINT NULL,
  `number_seq` INT NULL,
  `letter_number` VARCHAR(120) NULL,
  `number_assigned_at` DATETIME NULL,
  `approved_at` DATETIME NULL,
  `cancelled_at` DATETIME NULL,
  `cancel_reason` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_letter_number` (`organization_id`, `letter_number`),
  UNIQUE KEY `uq_letter_seq` (`organization_id`, `number_series`, `number_year`, `number_seq`),
  INDEX (`organization_id`, `status`, `updated_at`),
  INDEX (`created_by`),
  FOREIGN KEY (`organization_id`) REFERENCES `organizations`(`id`),
  FOREIGN KEY (`template_id`) REFERENCES `letter_templates`(`id`),
  FOREIGN KEY (`letterhead_id`) REFERENCES `letterheads`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
  CONSTRAINT `chk_number_complete` CHECK (
    (`letter_number` IS NULL AND `number_seq` IS NULL)
    OR (`letter_number` IS NOT NULL AND `number_seq` IS NOT NULL AND `number_year` IS NOT NULL AND `number_series` IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- fields  : isian terstruktur (sumber kebenaran fakta: nama, tanggal, nominal, barang).
-- blocks  : isi per blok; fakta dirujuk dengan penanda {{kunci_isian}}.
-- snapshot: salinan beku kop, kerangka, dan penanda tangan saat versi diajukan,
--           sehingga perubahan data organisasi tidak mengubah surat lama.
CREATE TABLE IF NOT EXISTS `letter_versions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `letter_id` INT NOT NULL,
  `version_no` INT NOT NULL,
  `title` VARCHAR(200) NULL,
  `fields` JSON NOT NULL,
  `blocks` JSON NOT NULL,
  `snapshot` JSON NULL,
  `content_hash` CHAR(64) NULL,
  `change_summary` TEXT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `submitted_at` DATETIME NULL,
  UNIQUE KEY `uq_letter_version` (`letter_id`, `version_no`),
  FOREIGN KEY (`letter_id`) REFERENCES `letters`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
  CONSTRAINT `chk_submitted_frozen` CHECK (
    `submitted_at` IS NULL OR (`snapshot` IS NOT NULL AND `content_hash` IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- current_version_id menunjuk ke letter_versions; ditambahkan setelah tabel versi ada.
-- Lewat prepared statement bersyarat agar impor ulang tidak gagal karena FK ganda.
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'letters' AND CONSTRAINT_NAME = 'fk_letter_current_version');
SET @fk_sql = IF(@fk_exists = 0,
  'ALTER TABLE `letters` ADD CONSTRAINT `fk_letter_current_version` FOREIGN KEY (`current_version_id`) REFERENCES `letter_versions`(`id`) ON DELETE SET NULL',
  'DO 0');
PREPARE fk_stmt FROM @fk_sql;
EXECUTE fk_stmt;
DEALLOCATE PREPARE fk_stmt;

-- ---------------------------------------------------------------------
-- Alur persetujuan: tahap berurutan, satu tahap boleh berisi beberapa pemeriksa.
-- Semua anggota wajib dalam satu tahap harus setuju sebelum tahap berikutnya aktif.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `approval_stages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `letter_id` INT NOT NULL,
  `stage_order` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  UNIQUE KEY `uq_stage_order` (`letter_id`, `stage_order`),
  FOREIGN KEY (`letter_id`) REFERENCES `letters`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `approval_stage_members` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `stage_id` INT NOT NULL,
  `signer_id` INT NOT NULL,
  `is_required` TINYINT(1) NOT NULL DEFAULT 1,
  -- Penanda tangan diganti: baris lama dinonaktifkan, bukan dihapus.
  `removed_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`stage_id`, `removed_at`),
  FOREIGN KEY (`stage_id`) REFERENCES `approval_stages`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`signer_id`) REFERENCES `signers`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Satu permintaan = satu pemeriksa untuk satu versi.
--   kind=reconfirm : penyetuju versi lama diminta konfirmasi ulang secara paralel.
--   status=waiting : tahapnya belum aktif.
--   superseded     : versi sudah diganti revisi; keputusan lama tetap tercatat untuk versi lama.
-- Token disimpan sebagai hash; tautan asli hanya ada di URL yang dikirim.
-- auth_method=link: pemegang tautan belum terverifikasi identitasnya (dicatat apa adanya).
CREATE TABLE IF NOT EXISTS `approval_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `letter_id` INT NOT NULL,
  `version_id` INT NOT NULL,
  `stage_id` INT NOT NULL,
  `stage_member_id` INT NOT NULL,
  `signer_id` INT NOT NULL,
  `signer_name` VARCHAR(150) NOT NULL,
  `signer_position` VARCHAR(150) NOT NULL,
  `kind` ENUM('review', 'reconfirm') NOT NULL DEFAULT 'review',
  `status` ENUM('waiting', 'pending', 'approved', 'revision_requested', 'cancelled', 'superseded')
           NOT NULL DEFAULT 'waiting',
  `token_hash` CHAR(64) NULL,
  `token_expires_at` DATETIME NULL,
  `auth_method` ENUM('link', 'account') NULL,
  `decided_at` DATETIME NULL,
  `decision_note` TEXT NULL,
  `decided_ip` VARCHAR(45) NULL,
  `decided_user_agent` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_token` (`token_hash`),
  -- Satu permintaan per pemeriksa per versi.
  UNIQUE KEY `uq_request_per_version` (`version_id`, `stage_member_id`),
  INDEX (`letter_id`, `status`),
  INDEX (`version_id`, `stage_id`, `status`),
  FOREIGN KEY (`letter_id`) REFERENCES `letters`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`version_id`) REFERENCES `letter_versions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`stage_id`) REFERENCES `approval_stages`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`stage_member_id`) REFERENCES `approval_stage_members`(`id`),
  FOREIGN KEY (`signer_id`) REFERENCES `signers`(`id`),
  CONSTRAINT `chk_decision_complete` CHECK (
    `status` NOT IN ('approved', 'revision_requested') OR (`decided_at` IS NOT NULL AND `auth_method` IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Komentar biasa tidak menghentikan proses; revision_request menghentikannya.
CREATE TABLE IF NOT EXISTS `letter_comments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `letter_id` INT NOT NULL,
  `version_id` INT NOT NULL,
  `request_id` INT NULL,
  `author_user_id` INT NULL,
  `author_name` VARCHAR(150) NOT NULL,
  `kind` ENUM('comment', 'revision_request') NOT NULL DEFAULT 'comment',
  `block_ref` VARCHAR(60) NULL,
  `body` TEXT NOT NULL,
  `resolved_at` DATETIME NULL,
  `resolved_by` INT NULL,
  `resolution_note` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`letter_id`, `created_at`),
  INDEX (`version_id`),
  FOREIGN KEY (`letter_id`) REFERENCES `letters`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`version_id`) REFERENCES `letter_versions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`request_id`) REFERENCES `approval_requests`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`author_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`resolved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ekspor per versi. "Disetujui, ekspor gagal" = surat approved + baris failed;
-- ekspor diulang tanpa meminta tanda tangan ulang.
CREATE TABLE IF NOT EXISTS `letter_exports` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `version_id` INT NOT NULL,
  `format` ENUM('docx', 'pdf') NOT NULL,
  `purpose` ENUM('preview', 'final') NOT NULL DEFAULT 'preview',
  `status` ENUM('pending', 'done', 'failed') NOT NULL DEFAULT 'pending',
  `file_path` VARCHAR(255) NULL,
  `sha256` CHAR(64) NULL,
  `error` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`version_id`, `format`, `status`),
  FOREIGN KEY (`version_id`) REFERENCES `letter_versions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jejak audit surat (siapa melakukan apa, pada versi mana).
CREATE TABLE IF NOT EXISTS `letter_events` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `letter_id` INT NOT NULL,
  `version_id` INT NULL,
  `actor_user_id` INT NULL,
  `actor_label` VARCHAR(150) NOT NULL,
  `event` VARCHAR(50) NOT NULL,
  `detail` JSON NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`letter_id`, `created_at`),
  FOREIGN KEY (`letter_id`) REFERENCES `letters`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`version_id`) REFERENCES `letter_versions`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`actor_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catatan setiap panggilan AI: dasar evaluasi final project (akurasi ekstraksi,
-- pelanggaran validator, latensi) dan penelusuran isi yang dihasilkan.
CREATE TABLE IF NOT EXISTS `ai_generations` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `organization_id` INT NOT NULL,
  `letter_id` INT NULL,
  `version_id` INT NULL,
  `purpose` ENUM('extract', 'recommend_template', 'generate', 'revise') NOT NULL,
  `model` VARCHAR(80) NOT NULL,
  `input` JSON NOT NULL,
  `output` JSON NULL,
  `validation` JSON NULL,
  `status` ENUM('ok', 'invalid', 'error') NOT NULL,
  `latency_ms` INT NULL,
  `input_tokens` INT NULL,
  `output_tokens` INT NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`organization_id`, `purpose`, `created_at`),
  INDEX (`letter_id`),
  FOREIGN KEY (`organization_id`) REFERENCES `organizations`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`letter_id`) REFERENCES `letters`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`version_id`) REFERENCES `letter_versions`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- DATA DEMO PENA (nama penanda tangan adalah contoh, bukan orang sungguhan)
-- =====================================================================




-- Setiap akun punya tepat satu ruang pribadi (juga akun yang dibuat sebelum ruang kerja ada).
INSERT INTO `organizations` (`kind`, `name`, `short_name`, `owner_user_id`)
SELECT 'personal', u.`nama_lengkap`, CONCAT('pribadi-', u.`id`), u.`id` FROM `users` u
 WHERE NOT EXISTS (SELECT 1 FROM `organizations` o WHERE o.`kind` = 'personal' AND o.`owner_user_id` = u.`id`)
ON DUPLICATE KEY UPDATE `kind` = `kind`;
INSERT IGNORE INTO `organization_members` (`organization_id`, `user_id`, `role`)
SELECT o.`id`, o.`owner_user_id`, 'secretary' FROM `organizations` o
 WHERE o.`kind` = 'personal' AND o.`owner_user_id` IS NOT NULL;
-- Nama pemilik menjadi isian profil pertama (dipakai mengisi identitas surat pribadi).
UPDATE `organizations` o JOIN `users` u ON u.`id` = o.`owner_user_id`
   SET o.`profile` = JSON_OBJECT('nama_mahasiswa', u.`nama_lengkap`)
 WHERE o.`kind` = 'personal' AND o.`profile` IS NULL;





-- Blok: source fixed = teks baku (penanda diisi sistem), ai = disusun AI dalam batas instruksi,
-- field_table = tabel dari isian, signatures = dari alur persetujuan.
INSERT INTO `letter_templates` (`id`, `organization_id`, `code`, `revision`, `name`, `description`, `number_series`, `number_format`, `definition`) VALUES
(1, NULL, 'PMJ', 1, 'Surat Peminjaman', 'Peminjaman ruangan atau barang untuk kegiatan', 'UMUM',
 '{seq}/{org}/{code}/{month_roman}/{year}',
 '{"fields":[
   {"key":"penerima_jabatan","label":"Jabatan penerima","type":"text","required":true},
   {"key":"penerima_instansi","label":"Instansi penerima","type":"text","required":true},
   {"key":"nama_kegiatan","label":"Nama kegiatan","type":"text","required":true},
   {"key":"tanggal_mulai","label":"Tanggal mulai","type":"date","required":true},
   {"key":"tanggal_selesai","label":"Tanggal selesai","type":"date","required":false},
   {"key":"tempat_kegiatan","label":"Tempat kegiatan","type":"text","required":true},
   {"key":"barang","label":"Barang/ruangan dipinjam","type":"table","required":true,
    "columns":[{"key":"nama","label":"Nama Barang/Ruangan"},{"key":"jumlah","label":"Jumlah"},{"key":"keterangan","label":"Keterangan"}]}
  ],
  "blocks":[
   {"id":"pembuka","type":"paragraph","source":"fixed","required":true,"text":"Dengan hormat,"},
   {"id":"maksud","type":"paragraph","source":"ai","required":true,"instruction":"Jelaskan maksud peminjaman untuk kegiatan {{nama_kegiatan}} pada {{tanggal_mulai}} di {{tempat_kegiatan}}. Gunakan penanda isian, jangan menulis tanggal atau nama secara langsung."},
   {"id":"daftar_barang","type":"table","source":"field_table","required":true,"field":"barang"},
   {"id":"ketentuan","type":"paragraph","source":"ai","required":false,"instruction":"Kesanggupan menjaga dan mengembalikan barang dalam kondisi baik."},
   {"id":"penutup","type":"paragraph","source":"fixed","required":true,"text":"Demikian surat ini kami sampaikan. Atas perhatian dan kerja samanya, kami ucapkan terima kasih."},
   {"id":"tanda_tangan","type":"signatures","source":"approval","required":true}
  ]}'),
(2, NULL, 'PMH', 1, 'Surat Permohonan', 'Permohonan izin, dukungan, atau dana kegiatan', 'UMUM',
 '{seq}/{org}/{code}/{month_roman}/{year}',
 '{"fields":[
   {"key":"penerima_jabatan","label":"Jabatan penerima","type":"text","required":true},
   {"key":"penerima_instansi","label":"Instansi penerima","type":"text","required":true},
   {"key":"nama_kegiatan","label":"Nama kegiatan","type":"text","required":true},
   {"key":"tanggal_mulai","label":"Tanggal kegiatan","type":"date","required":true},
   {"key":"tempat_kegiatan","label":"Tempat kegiatan","type":"text","required":true},
   {"key":"hal_dimohon","label":"Hal yang dimohon","type":"text","required":true},
   {"key":"nominal","label":"Nominal (jika ada)","type":"money","required":false}
  ],
  "blocks":[
   {"id":"pembuka","type":"paragraph","source":"fixed","required":true,"text":"Dengan hormat,"},
   {"id":"latar","type":"paragraph","source":"ai","required":true,"instruction":"Latar kegiatan {{nama_kegiatan}} pada {{tanggal_mulai}} di {{tempat_kegiatan}}. Gunakan penanda isian."},
   {"id":"permohonan","type":"paragraph","source":"ai","required":true,"instruction":"Nyatakan permohonan {{hal_dimohon}} secara jelas; sebut {{nominal}} hanya bila terisi."},
   {"id":"penutup","type":"paragraph","source":"fixed","required":true,"text":"Demikian permohonan ini kami sampaikan. Atas perhatian dan kebijaksanaannya, kami ucapkan terima kasih."},
   {"id":"tanda_tangan","type":"signatures","source":"approval","required":true}
  ]}')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Kerangka pribadi. Riset 18 Nov 2026: surat mahasiswa yang paling sering dicontohkan adalah izin tidak
-- hadir kuliah dan permohonan dispensasi. Tanpa nomor; isian "profile" diisi dari profil ruang pribadi.
-- Permohonan ke dosen dan surat pernyataan menyusul.
INSERT INTO `letter_templates` (`id`, `organization_id`, `audience`, `code`, `revision`, `name`, `description`, `number_series`, `number_format`, `definition`) VALUES
(3, NULL, 'personal', 'IZN', 1, 'Surat Izin Tidak Hadir', 'Izin tidak mengikuti kuliah karena sakit, urusan keluarga, atau keperluan mendesak', 'PRIBADI',
 '{seq}',
 '{"fields":[
   {"key":"penerima_jabatan","label":"Ditujukan kepada","type":"text","required":true},
   {"key":"penerima_instansi","label":"Instansi penerima","type":"text","required":false},
   {"key":"nama_mahasiswa","label":"Nama lengkap","type":"text","required":true,"profile":true},
   {"key":"nim","label":"NIM","type":"text","required":true,"profile":true},
   {"key":"program_studi","label":"Program studi","type":"text","required":true,"profile":true},
   {"key":"mata_kuliah","label":"Mata kuliah","type":"text","required":true},
   {"key":"tanggal_mulai","label":"Tanggal tidak hadir","type":"date","required":true},
   {"key":"tanggal_selesai","label":"Sampai tanggal","type":"date","required":false},
   {"key":"alasan","label":"Alasan","type":"text","required":true}
  ],
  "blocks":[
   {"id":"pembuka","type":"paragraph","source":"fixed","required":true,"text":"Dengan hormat,"},
   {"id":"identitas","type":"paragraph","source":"fixed","required":true,"text":"Saya yang bertanda tangan di bawah ini, {{nama_mahasiswa}} (NIM {{nim}}), mahasiswa Program Studi {{program_studi}},"},
   {"id":"izin","type":"paragraph","source":"ai","required":true,"instruction":"Sampaikan permohonan izin tidak dapat mengikuti perkuliahan {{mata_kuliah}} pada {{tanggal_mulai}} karena {{alasan}}. Gunakan penanda isian, sopan dan singkat, sudut pandang orang pertama (saya)."},
   {"id":"penutup","type":"paragraph","source":"fixed","required":true,"text":"Demikian surat izin ini saya sampaikan. Atas perhatian dan pengertian Bapak/Ibu, saya ucapkan terima kasih."},
   {"id":"tanda_tangan","type":"signatures","source":"approval","required":true}
  ]}'),
(4, NULL, 'personal', 'DSP', 1, 'Surat Permohonan Dispensasi', 'Dispensasi kehadiran karena lomba, kepanitiaan, magang, atau pelatihan', 'PRIBADI',
 '{seq}',
 '{"fields":[
   {"key":"penerima_jabatan","label":"Ditujukan kepada","type":"text","required":true},
   {"key":"penerima_instansi","label":"Instansi penerima","type":"text","required":false},
   {"key":"nama_mahasiswa","label":"Nama lengkap","type":"text","required":true,"profile":true},
   {"key":"nim","label":"NIM","type":"text","required":true,"profile":true},
   {"key":"program_studi","label":"Program studi","type":"text","required":true,"profile":true},
   {"key":"nama_kegiatan","label":"Nama kegiatan","type":"text","required":true},
   {"key":"penyelenggara","label":"Penyelenggara","type":"text","required":false},
   {"key":"tanggal_mulai","label":"Tanggal mulai","type":"date","required":true},
   {"key":"tanggal_selesai","label":"Tanggal selesai","type":"date","required":false},
   {"key":"tempat_kegiatan","label":"Tempat kegiatan","type":"text","required":true},
   {"key":"mata_kuliah","label":"Mata kuliah terdampak","type":"text","required":false}
  ],
  "blocks":[
   {"id":"pembuka","type":"paragraph","source":"fixed","required":true,"text":"Dengan hormat,"},
   {"id":"identitas","type":"paragraph","source":"fixed","required":true,"text":"Saya yang bertanda tangan di bawah ini, {{nama_mahasiswa}} (NIM {{nim}}), mahasiswa Program Studi {{program_studi}},"},
   {"id":"permohonan","type":"paragraph","source":"ai","required":true,"instruction":"Mohon dispensasi kehadiran kuliah karena mengikuti {{nama_kegiatan}} pada {{tanggal_mulai}} di {{tempat_kegiatan}}; sebut {{penyelenggara}} dan {{mata_kuliah}} hanya bila terisi. Gunakan penanda isian, sudut pandang orang pertama (saya)."},
   {"id":"kesanggupan","type":"paragraph","source":"ai","required":false,"instruction":"Kesanggupan mengejar materi atau tugas yang tertinggal."},
   {"id":"penutup","type":"paragraph","source":"fixed","required":true,"text":"Demikian permohonan ini saya sampaikan. Atas perhatian dan kebijaksanaan Bapak/Ibu, saya ucapkan terima kasih."},
   {"id":"tanda_tangan","type":"signatures","source":"approval","required":true}
  ]}')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- One-use account tokens; only hashes are persisted.
CREATE TABLE IF NOT EXISTS account_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    purpose ENUM('verify_email','reset_password') NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_account_tokens_user (user_id, purpose),
    INDEX idx_account_tokens_expiry (expires_at),
    CONSTRAINT fk_account_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Signature images are private per-version evidence, distinct from approval.
CREATE TABLE IF NOT EXISTS letter_signatures (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 version_id INT NOT NULL,
 slot_key VARCHAR(80) NOT NULL,
 content_hash CHAR(64) NOT NULL,
 png MEDIUMBLOB NOT NULL,
 image_sha256 CHAR(64) NOT NULL,
 user_id INT NULL,
 request_id INT NULL,
 auth_method ENUM('account','link') NOT NULL,
 consented_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY signature_per_slot (version_id,slot_key),
 CONSTRAINT signature_version FOREIGN KEY (version_id) REFERENCES letter_versions(id) ON DELETE CASCADE,
 CONSTRAINT signature_request FOREIGN KEY (request_id) REFERENCES approval_requests(id),
 CONSTRAINT signature_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Platform authority is granted explicitly through the server console; no role seeds.
CREATE TABLE IF NOT EXISTS platform_operators (
 user_id INT NOT NULL PRIMARY KEY,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_platform_operator_user FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS operator_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 actor_user_id INT NULL,
 action VARCHAR(40) NOT NULL,
 target_user_id INT NULL,
 reason VARCHAR(300) NOT NULL,
 before_status VARCHAR(20) NULL,
 after_status VARCHAR(20) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_operator_event_time(created_at,id),
 CONSTRAINT fk_operator_event_actor FOREIGN KEY(actor_user_id) REFERENCES users(id),
 CONSTRAINT fk_operator_event_target FOREIGN KEY(target_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS operator_checks (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 actor_user_id INT NOT NULL,
 status ENUM('ok','error') NOT NULL,
 model VARCHAR(150) NOT NULL,
 latency_ms INT UNSIGNED NULL,
 message VARCHAR(255) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_operator_check_actor FOREIGN KEY(actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_ai_settings (
 user_id INT NOT NULL PRIMARY KEY,
 mode ENUM('platform','personal') NOT NULL DEFAULT 'platform',
 provider VARCHAR(30) NOT NULL,
 model VARCHAR(150) NOT NULL,
 base_url VARCHAR(500) NOT NULL DEFAULT '',
 encrypted_key TEXT NOT NULL,
 revision CHAR(32) NOT NULL,
 probe_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_user_ai_settings_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
