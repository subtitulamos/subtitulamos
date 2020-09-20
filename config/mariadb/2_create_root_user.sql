/* Creates a root user with password root */
INSERT INTO `users` (`id`, `username`, `password`, `email`, `banned`, `roles`, `last_seen`, `registered_at`, `ban_id`) VALUES (1, 'root', '$2y$13$jhlmdYapSLBSONC57nhuku24pn4n5A7u6iA0aL1F1zzgqapMXB.1i', 'root@localhost.dev', 0, '["ROLE_USER", "ROLE_MOD"]', '2020-09-20 22:06:00', '2020-09-20 22:05:59', NULL);
