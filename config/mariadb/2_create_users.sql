/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

/* Creates:
    - a root user with password root
    - a bunch of secondary users for different permission levels, all with passwords same as their name:
        - mods: mod
        - tts: tt
        - regular users: regular, alice, bob, foo, bar
*/
INSERT INTO `users` (`id`, `username`, `password`, `email`, `banned`, `roles`, `last_seen`, `registered_at`, `ban_id`)
VALUES
(1, 'root', '$2y$13$jhlmdYapSLBSONC57nhuku24pn4n5A7u6iA0aL1F1zzgqapMXB.1i', 'root@localhost.dev', 0, '["ROLE_USER", "ROLE_MOD"]', '2020-09-20 22:06:00', '2020-09-20 22:05:59', NULL),
(2, 'mod', '$2y$13$lrpBVGPUFtlSDxAuQBvReOIk4.nVkO9956ZXDAv5gx9Oti0IWfV0y', 'mod@localhost.dev', 0, '["ROLE_USER", "ROLE_MOD"]', '2020-09-20 22:06:00', '2020-09-20 22:05:59', NULL),
(3, 'tt', '$2y$13$t0rCTvzg4KW9li4jcmSPh.bDXNqjsJvMo8Pjxdia.EGd4rePdVVEu', 'tt@localhost.dev', 0, '["ROLE_USER", "ROLE_TT"]', '2020-09-20 22:06:00', '2020-09-20 22:05:59', NULL),
(4, 'regular', '$2y$13$o.TQRldSbTmrdIwxa.eGseI6OZdiaUlRMLauRtSNdZEivGnzD77N6', 'regular@localhost.dev', 0, '["ROLE_USER"]', '2020-09-20 22:06:00', '2020-09-20 22:05:59', NULL),
(5, 'alice', '$2y$13$bW52STkY9tGf06z..vPo1u0TKgaHhxsKez.Is2JlSWxREIw012kxa', 'alice@localhost.dev', 0, '["ROLE_USER"', '2020-09-20 22:06:00', '2020-09-20 22:05:59', NULL),
(6, 'bob', '$2y$13$Cfc2TsDn7F3akPVYBoxNFuUuz0f2S8PsmDA76xk18MVw5rsLxOCPm', 'bob@localhost.dev', 0, '["ROLE_USER"]', '2020-09-20 22:06:00', '2020-09-20 22:05:59', NULL),
(7, 'foo', '$2y$13$8xL3lgFGGIML4R.Sb42/6uCokzNAsCusRUSWKxRpLBpKtzOPvJImu', 'foo@localhost.dev', 0, '["ROLE_USER"]', '2020-09-20 22:06:00', '2020-09-20 22:05:59', NULL),
(8, 'bar', '$2y$13$XilD2xXjL9UEp2kFKAhQBODTEtMa3ZrN1aCe.zk6EicDsz6HPLTn2', 'bar@localhost.dev', 0, '["ROLE_USER"]', '2020-09-20 22:06:00', '2020-09-20 22:05:59', NULL);
