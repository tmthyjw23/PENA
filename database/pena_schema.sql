-- =====================================================================
-- PENA: akun, organisasi, surat, versi, persetujuan bertahap, log AI
-- Skema lengkap dalam satu berkas. Pilih database lebih dulu (composer db:install membuatnya
-- dari DB_NAME di .env). Target: MariaDB 10.4+ / MySQL 8. Aman dijalankan ulang.
-- Aturan yang dijaga lapisan aplikasi (bukan trigger, agar impor phpMyAdmin tetap jalan):
--   * letter_versions tidak boleh diubah setelah submitted_at terisi; revisi = versi baru.
--   * Persetujuan hanya berlaku untuk version_id tempatnya dicatat.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Akun orang yang masuk ke PENA (sekretaris, admin). Pemeriksa tidak perlu akun.
-- Peran ditentukan per organisasi di organization_members, bukan di sini.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `nama_lengkap` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `last_login` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Akun contoh untuk pengujian lokal (ganti atau nonaktifkan sebelum dipakai sungguhan):
--   admin / admin123, sekretaris / sekretaris123. Baris yang sudah ada tidak diubah.
INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `email`, `status`) VALUES
(1, 'admin', '$2y$12$0DHbmnAE/FyrZTuvXlqVtewf3en6seLuZ6WMxalycCejAUCWjCAam', 'Contoh Admin Organisasi', 'admin@pena.local', 'active'),
(2, 'sekretaris', '$2y$12$DgNVpYjQyWvIrb2jrAE1FeLJ8zgmlg4YZjoZMIFcuP65MHwRUSsx.', 'Contoh Sekretaris', 'sekretaris@pena.local', 'active'),
(3, 'anggota', '$2y$12$DgNVpYjQyWvIrb2jrAE1FeLJ8zgmlg4YZjoZMIFcuP65MHwRUSsx.', 'Contoh Anggota Tanpa Akses', 'anggota@pena.local', 'active')
ON DUPLICATE KEY UPDATE `id` = `id`;

-- ---------------------------------------------------------------------
-- Organisasi: pemilik kop, nomor, direktori penanda tangan, kerangka, arsip.
-- Akun (users) mewakili orang; keanggotaan menentukan perannya di organisasi.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `organizations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `short_name` VARCHAR(30) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `organization_members` (
  `organization_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `role` ENUM('admin', 'secretary', 'member') NOT NULL DEFAULT 'member',
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
-- Kerangka surat (blok terkontrol). organization_id NULL = kerangka bawaan.
-- definition: {"fields":[...], "blocks":[...]} — isian terstruktur + blok yang diizinkan.
-- Kerangka tidak diubah di tempat; perubahan = baris baru dengan revision+1.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `letter_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `organization_id` INT NULL,
  `code` VARCHAR(40) NOT NULL,
  `revision` INT NOT NULL DEFAULT 1,
  `name` VARCHAR(150) NOT NULL,
  `description` VARCHAR(255) NULL,
  `number_series` VARCHAR(20) NOT NULL DEFAULT 'UMUM',
  `number_format` VARCHAR(120) NOT NULL DEFAULT '{seq}/{org}/{code}/{month_roman}/{year}',
  `definition` JSON NOT NULL,
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
INSERT INTO `organizations` (`id`, `name`, `short_name`) VALUES
(1, 'Badan Eksekutif Mahasiswa Fakultas Ilmu Komputer', 'BEM-FILKOM')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `organization_members` (`organization_id`, `user_id`, `role`) VALUES
(1, 1, 'admin'),
(1, 2, 'secretary')
ON DUPLICATE KEY UPDATE `role` = VALUES(`role`);

INSERT INTO `letterheads` (`id`, `organization_id`, `name`, `address`, `contact`) VALUES
(1, 1, 'BADAN EKSEKUTIF MAHASISWA FAKULTAS ILMU KOMPUTER', 'Alamat kampus (ubah di pengaturan kop)', 'Email organisasi (ubah di pengaturan kop)')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `signers` (`id`, `organization_id`, `name`, `position`) VALUES
(1, 1, 'Contoh Ketua Panitia', 'Ketua Panitia'),
(2, 1, 'Contoh Ketua BEM', 'Ketua BEM'),
(3, 1, 'Contoh Advisor', 'Advisor Organisasi'),
(4, 1, 'Contoh Dekan', 'Dekan Fakultas Ilmu Komputer')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

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
