/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

-- --------------------------------------------------------
-- Host:                         localhost
-- Server version:               10.4.13-MariaDB-1:10.4.13+maria~bionic - mariadb.org binary distribution
-- Server OS:                    debian-linux-gnu
-- HeidiSQL Version:             11.0.0.6049
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;


-- Dumping database structure for subs
CREATE DATABASE IF NOT EXISTS `subs` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `subs`;

-- Dumping structure for table subs.alerts
CREATE TABLE IF NOT EXISTS `alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `by_user_id` int(11) DEFAULT NULL,
  `status` int(11) NOT NULL,
  `create_time` datetime NOT NULL DEFAULT current_timestamp(),
  `for_subtitle_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_F77AC06BDC9C2434` (`by_user_id`),
  KEY `IDX_F77AC06B93912E43` (`for_subtitle_id`),
  CONSTRAINT `FK_F77AC06B93912E43` FOREIGN KEY (`for_subtitle_id`) REFERENCES `subtitles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_F77AC06BDC9C2434` FOREIGN KEY (`by_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Dumping structure for table subs.alert_comments
CREATE TABLE IF NOT EXISTS `alert_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alert_id` int(11) DEFAULT NULL,
  `text` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `type` int(11) NOT NULL,
  `by_user_id` int(11) DEFAULT NULL,
  `create_time` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_2A6A661193035F72` (`alert_id`),
  KEY `IDX_2A6A6611DC9C2434` (`by_user_id`),
  CONSTRAINT `FK_2A6A661193035F72` FOREIGN KEY (`alert_id`) REFERENCES `alerts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_2A6A6611DC9C2434` FOREIGN KEY (`by_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Dumping structure for table subs.bans
CREATE TABLE IF NOT EXISTS `bans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `by_user_id` int(11) DEFAULT NULL,
  `target_user_id` int(11) DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `until` datetime NOT NULL,
  `unban_user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_CB0C272CDC9C2434` (`by_user_id`),
  KEY `IDX_CB0C272C6C066AFE` (`target_user_id`),
  KEY `IDX_CB0C272C7DF4C103` (`unban_user_id`),
  CONSTRAINT `FK_CB0C272C6C066AFE` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `FK_CB0C272C7DF4C103` FOREIGN KEY (`unban_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `FK_CB0C272CDC9C2434` FOREIGN KEY (`by_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Dumping structure for table subs.episodes
CREATE TABLE IF NOT EXISTS `episodes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `show_id` int(11) DEFAULT NULL,
  `season` int(11) NOT NULL,
  `number` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `downloads` int(11) NOT NULL,
  `creation_time` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `IDX_7DD55EDDD0C1FC64` (`show_id`),
  CONSTRAINT `FK_7DD55EDDD0C1FC64` FOREIGN KEY (`show_id`) REFERENCES `shows` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Dumping structure for table subs.episode_comments
CREATE TABLE IF NOT EXISTS `episode_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `episode_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `text` longtext COLLATE utf8_unicode_ci NOT NULL,
  `type` int(11) NOT NULL,
  `publish_time` datetime NOT NULL DEFAULT current_timestamp(),
  `edit_time` datetime NOT NULL DEFAULT current_timestamp(),
  `soft_deleted` tinyint(1) NOT NULL,
  `pinned` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_9618BF58362B62A0` (`episode_id`),
  KEY `IDX_9618BF58A76ED395` (`user_id`),
  CONSTRAINT `FK_9618BF58362B62A0` FOREIGN KEY (`episode_id`) REFERENCES `episodes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_9618BF58A76ED395` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Dumping structure for table subs.open_locks
CREATE TABLE IF NOT EXISTS `open_locks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subtitle_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `grantTime` datetime NOT NULL,
  `sequence_number` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_B78C4D91A76ED395` (`user_id`),
  KEY `IDX_B78C4D9110F3A34` (`subtitle_id`),
  CONSTRAINT `FK_B78C4D9110F3A34` FOREIGN KEY (`subtitle_id`) REFERENCES `subtitles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_B78C4D91A76ED395` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Dumping structure for table subs.pauses
CREATE TABLE IF NOT EXISTS `pauses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `start` datetime NOT NULL,
  `subtitle_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_C562CB5C10F3A34` (`subtitle_id`),
  KEY `IDX_C562CB5CA76ED395` (`user_id`),
  CONSTRAINT `FK_C562CB5C10F3A34` FOREIGN KEY (`subtitle_id`) REFERENCES `subtitles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_C562CB5CA76ED395` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Dumping structure for table subs.remember_tokens
CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `token` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`token`),
  KEY `IDX_F08F5CA1A76ED395` (`user_id`),
  CONSTRAINT `FK_F08F5CA1A76ED395` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Dumping structure for table subs.sequences
CREATE TABLE IF NOT EXISTS `sequences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subtitle_id` int(11) DEFAULT NULL,
  `revision` int(11) NOT NULL,
  `number` int(11) NOT NULL,
  `start_time` int(11) NOT NULL,
  `end_time` int(11) NOT NULL,
  `text` tinytext COLLATE utf8_unicode_ci NOT NULL,
  `locked` tinyint(1) NOT NULL,
  `verified` tinyint(1) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_B7E0B09C10F3A34` (`subtitle_id`),
  KEY `IDX_B7E0B09CA76ED395` (`user_id`),
  CONSTRAINT `FK_B7E0B09C10F3A34` FOREIGN KEY (`subtitle_id`) REFERENCES `subtitles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_B7E0B09CA76ED395` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Dumping structure for table subs.shows
CREATE TABLE IF NOT EXISTS `shows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `zero_tolerance` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Dumping structure for table subs.subtitles
CREATE TABLE IF NOT EXISTS `subtitles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `version_id` int(11) DEFAULT NULL,
  `lang` int(11) NOT NULL,
  `direct_upload` tinyint(1) NOT NULL,
  `upload_time` datetime NOT NULL DEFAULT current_timestamp(),
  `progress` double NOT NULL,
  `pause_id` int(11) DEFAULT NULL,
  `edit_time` datetime DEFAULT NULL,
  `complete_time` datetime DEFAULT NULL,
  `downloads` int(11) NOT NULL,
  `resync` tinyint(1) NOT NULL,
  `last_edited_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_A739C98694D614D6` (`pause_id`),
  KEY `IDX_A739C9864BBC2705` (`version_id`),
  KEY `IDX_A739C986C16AD6BA` (`last_edited_by`),
  CONSTRAINT `FK_A739C9864BBC2705` FOREIGN KEY (`version_id`) REFERENCES `versions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_A739C98694D614D6` FOREIGN KEY (`pause_id`) REFERENCES `pauses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_A739C986C16AD6BA` FOREIGN KEY (`last_edited_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Dumping structure for table subs.subtitle_comments
CREATE TABLE IF NOT EXISTS `subtitle_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subtitle_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `text` longtext COLLATE utf8_unicode_ci NOT NULL,
  `publish_time` datetime NOT NULL DEFAULT current_timestamp(),
  `edit_time` datetime NOT NULL DEFAULT current_timestamp(),
  `soft_deleted` tinyint(1) NOT NULL,
  `pinned` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_16F8A47B10F3A34` (`subtitle_id`),
  KEY `IDX_16F8A47BA76ED395` (`user_id`),
  CONSTRAINT `FK_16F8A47B10F3A34` FOREIGN KEY (`subtitle_id`) REFERENCES `subtitles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_16F8A47BA76ED395` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Dumping structure for table subs.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(60) COLLATE utf8_unicode_ci NOT NULL,
  `email` varchar(80) COLLATE utf8_unicode_ci NOT NULL,
  `banned` tinyint(1) NOT NULL,
  `roles` longtext COLLATE utf8_unicode_ci NOT NULL COMMENT '(DC2Type:json_array)',
  `last_seen` datetime DEFAULT NULL,
  `registered_at` datetime DEFAULT NULL,
  `ban_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_1483A5E9F85E0677` (`username`),
  UNIQUE KEY `UNIQ_1483A5E9E7927C74` (`email`),
  UNIQUE KEY `UNIQ_1483A5E91255CD1D` (`ban_id`),
  CONSTRAINT `FK_1483A5E91255CD1D` FOREIGN KEY (`ban_id`) REFERENCES `bans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Dumping structure for table subs.versions
CREATE TABLE IF NOT EXISTS `versions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `episode_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(60) COLLATE utf8_unicode_ci NOT NULL,
  `comments` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_19DC40D2362B62A0` (`episode_id`),
  KEY `IDX_19DC40D2A76ED395` (`user_id`),
  CONSTRAINT `FK_19DC40D2362B62A0` FOREIGN KEY (`episode_id`) REFERENCES `episodes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_19DC40D2A76ED395` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
