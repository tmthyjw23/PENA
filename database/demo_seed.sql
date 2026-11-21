-- Explicit development/test fixtures; never run as a production migration.
INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `email`, `status`) VALUES
(1, 'admin', '$2y$12$0DHbmnAE/FyrZTuvXlqVtewf3en6seLuZ6WMxalycCejAUCWjCAam', 'Contoh Admin Organisasi', 'admin@pena.local', 'active'),
(2, 'sekretaris', '$2y$12$DgNVpYjQyWvIrb2jrAE1FeLJ8zgmlg4YZjoZMIFcuP65MHwRUSsx.', 'Contoh Sekretaris', 'sekretaris@pena.local', 'active'),
(3, 'anggota', '$2y$12$DgNVpYjQyWvIrb2jrAE1FeLJ8zgmlg4YZjoZMIFcuP65MHwRUSsx.', 'Contoh Mahasiswa', 'anggota@pena.local', 'active')
ON DUPLICATE KEY UPDATE `id` = `id`;

INSERT INTO `organizations` (`id`, `kind`, `name`, `short_name`, `owner_user_id`) VALUES
(1, 'organization', 'Badan Eksekutif Mahasiswa Fakultas Ilmu Komputer', 'BEM-FILKOM', 2)
ON DUPLICATE KEY UPDATE `id` = `id`;

INSERT INTO `organization_members` (`organization_id`, `user_id`, `role`) VALUES
(1, 1, 'secretary'),
(1, 2, 'secretary')
ON DUPLICATE KEY UPDATE `role` = VALUES(`role`);

INSERT INTO `letterheads` (`id`, `organization_id`, `name`, `address`, `contact`) VALUES
(1, 1, 'BADAN EKSEKUTIF MAHASISWA FAKULTAS ILMU KOMPUTER', 'Alamat kampus (ubah di pengaturan kop)', 'Email organisasi (ubah di pengaturan kop)')
ON DUPLICATE KEY UPDATE `id` = `id`;

INSERT INTO `signers` (`id`, `organization_id`, `name`, `position`) VALUES
(1, 1, 'Contoh Ketua Panitia', 'Ketua Panitia'),
(2, 1, 'Contoh Ketua BEM', 'Ketua BEM'),
(3, 1, 'Contoh Advisor', 'Advisor Organisasi'),
(4, 1, 'Contoh Dekan', 'Dekan Fakultas Ilmu Komputer')
ON DUPLICATE KEY UPDATE `id` = `id`;

INSERT INTO `organizations` (`kind`, `name`, `short_name`, `owner_user_id`)
SELECT 'personal', u.`nama_lengkap`, CONCAT('pribadi-', u.`id`), u.`id` FROM `users` u
 WHERE NOT EXISTS (SELECT 1 FROM `organizations` o WHERE o.`kind` = 'personal' AND o.`owner_user_id` = u.`id`)
ON DUPLICATE KEY UPDATE `kind` = `kind`;

INSERT IGNORE INTO `organization_members` (`organization_id`, `user_id`, `role`)
SELECT o.`id`, o.`owner_user_id`, 'secretary' FROM `organizations` o
 WHERE o.`kind` = 'personal' AND o.`owner_user_id` IS NOT NULL;

UPDATE `organizations` o JOIN `users` u ON u.`id` = o.`owner_user_id`
   SET o.`profile` = JSON_OBJECT('nama_mahasiswa', u.`nama_lengkap`)
 WHERE o.`kind` = 'personal' AND o.`profile` IS NULL;
