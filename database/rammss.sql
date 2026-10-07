-- ==========================================================================
-- Campus Life - Groupe RAMMSS
-- Base de données MySQL (à importer via phpMyAdmin dans MAMP)
-- ==========================================================================

CREATE DATABASE IF NOT EXISTS `rammss` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `rammss`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `audit_log`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `comments`;
DROP TABLE IF EXISTS `votes`;
DROP TABLE IF EXISTS `requests`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------------------------
-- Utilisateurs (étudiants & administrateurs)
-- --------------------------------------------------------------------------
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(60) NOT NULL,
  `last_name` VARCHAR(60) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('student','admin') NOT NULL DEFAULT 'student',
  `admin_function` VARCHAR(30) DEFAULT NULL,
  `promo` VARCHAR(100) DEFAULT NULL,
  `theme` ENUM('auto','light','dark') NOT NULL DEFAULT 'auto',
  `notif_validation` TINYINT(1) NOT NULL DEFAULT 1,
  `notif_threshold` TINYINT(1) NOT NULL DEFAULT 1,
  `notif_comments` TINYINT(1) NOT NULL DEFAULT 0,
  `default_anon` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Catégories de requêtes
-- --------------------------------------------------------------------------
CREATE TABLE `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(40) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Requêtes / idées soumises
-- status : pending (modération) -> voting -> threshold (seuil atteint)
--          -> validated (validée campus) | rejected
-- --------------------------------------------------------------------------
CREATE TABLE `requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(100) NOT NULL,
  `description` TEXT NOT NULL,
  `location` VARCHAR(150) DEFAULT NULL,
  `impact` VARCHAR(255) DEFAULT NULL,
  `is_anonymous` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('pending','voting','threshold','validated','rejected') NOT NULL DEFAULT 'pending',
  `is_reported` TINYINT(1) NOT NULL DEFAULT 0,
  `report_reason` VARCHAR(255) DEFAULT NULL,
  `decision_comment` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `moderated_at` DATETIME DEFAULT NULL,
  `threshold_at` DATETIME DEFAULT NULL,
  `validated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_requests_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_requests_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Votes (1 vote par étudiant et par requête, modifiable)
-- --------------------------------------------------------------------------
CREATE TABLE `votes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `value` ENUM('pro','neutral','con') NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_vote` (`request_id`, `user_id`),
  CONSTRAINT `fk_votes_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_votes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Commentaires / avis
-- --------------------------------------------------------------------------
CREATE TABLE `comments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `content` TEXT NOT NULL,
  `is_anonymous` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_comments_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Notifications (Story 12)
-- --------------------------------------------------------------------------
CREATE TABLE `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `request_id` INT UNSIGNED DEFAULT NULL,
  `type` ENUM('validated','threshold','comment','moderation','info') NOT NULL DEFAULT 'info',
  `title` VARCHAR(150) NOT NULL,
  `message` VARCHAR(255) NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_read` (`user_id`, `is_read`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Journal d'audit des décisions administratives
-- --------------------------------------------------------------------------
CREATE TABLE `audit_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` INT UNSIGNED DEFAULT NULL,
  `request_id` INT UNSIGNED DEFAULT NULL,
  `action` ENUM('approved','rejected','validated','threshold','reported','precision') NOT NULL,
  `message` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_audit_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================================
-- DONNÉES DE DÉMONSTRATION
-- Mot de passe étudiants : campus2026!secret
-- Mot de passe admin     : admin2026!
-- ==========================================================================

INSERT INTO `categories` (`id`, `slug`, `name`) VALUES
(1, 'equipements',   'Équipements & Locaux'),
(2, 'coworking',     'Espaces de travail & Coworking'),
(3, 'vie-etudiante', 'Vie étudiante & Événements'),
(4, 'restauration',  'Cafétéria & Restauration'),
(5, 'ecologie',      'Écologie & Développement durable'),
(6, 'pedagogie',     'Pédagogie & Outils d''apprentissage'),
(7, 'bien-etre',     'Bien-être & Santé');

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `password_hash`, `role`, `admin_function`, `promo`, `notif_comments`) VALUES
(1, 'Alban',  'Elie',       'alban.elie@coda.school',     '$2y$10$dfcxir94PVMZ2dwL0PeJpOS0oloqke/PJViXBeIb0o3zpWuIoNj2y', 'student', NULL, 'Promo B2 Dev FullStack', 0),
(2, 'Léa',    'Martin',     'lea.martin@coda.school',     '$2y$10$dfcxir94PVMZ2dwL0PeJpOS0oloqke/PJViXBeIb0o3zpWuIoNj2y', 'student', NULL, 'Promo B2 Fullstack', 1),
(3, 'Thomas', 'Bernard',    'thomas.bernard@coda.school', '$2y$10$dfcxir94PVMZ2dwL0PeJpOS0oloqke/PJViXBeIb0o3zpWuIoNj2y', 'student', NULL, 'Délégué B3', 1),
(4, 'Clara',  'Dupont',     'clara.dupont@coda.school',   '$2y$10$dfcxir94PVMZ2dwL0PeJpOS0oloqke/PJViXBeIb0o3zpWuIoNj2y', 'student', NULL, 'Eco-déléguée B1', 0),
(5, 'Maxime', 'Roux',       'maxime.roux@coda.school',    '$2y$10$dfcxir94PVMZ2dwL0PeJpOS0oloqke/PJViXBeIb0o3zpWuIoNj2y', 'student', NULL, 'Promo B1 Informatique', 0),
(6, 'Sophie', 'Carpentier', 'direction@coda.school',      '$2y$10$r4x.gc2I30CtxYGkTG5IG.R0P4si9u7KVtpIkReAau1TjkDOFVN/m', 'admin', 'direction', 'Direction du Campus', 0);

-- Étudiants votants de démonstration (comptes non connectables : mot de passe invalide)
DROP TABLE IF EXISTS `tmp_digits`;
CREATE TABLE `tmp_digits` (`d` TINYINT UNSIGNED NOT NULL);
INSERT INTO `tmp_digits` VALUES (0),(1),(2),(3),(4),(5),(6),(7),(8),(9);

DROP TEMPORARY TABLE IF EXISTS `tmp_numbers`;
CREATE TEMPORARY TABLE `tmp_numbers` (`n` INT UNSIGNED NOT NULL PRIMARY KEY);
INSERT INTO `tmp_numbers` (`n`)
SELECT a.d * 100 + b.d * 10 + c.d + 1
FROM `tmp_digits` a CROSS JOIN `tmp_digits` b CROSS JOIN `tmp_digits` c
WHERE a.d * 100 + b.d * 10 + c.d + 1 <= 400;

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `password_hash`, `role`, `promo`, `created_at`)
SELECT 1000 + n, 'Étudiant', CONCAT('Votant ', n), CONCAT('votant', LPAD(n, 3, '0'), '@demo.campus'), '!demo-account', 'student', 'Promo démo', '2026-09-01 08:00:00'
FROM `tmp_numbers`;

INSERT INTO `requests` (`id`, `user_id`, `category_id`, `title`, `description`, `location`, `impact`, `is_anonymous`, `status`, `is_reported`, `report_reason`, `decision_comment`, `created_at`, `moderated_at`, `threshold_at`, `validated_at`) VALUES
(1, 2, 1,
 'Installation de 3 micro-ondes supplémentaires et d''un point d''eau fraîche au Bâtiment B',
 'Contexte : actuellement, le Bâtiment B accueille plus de 320 étudiants à midi (promos B1, B2 et B3 réunies). La salle de pause ne dispose que d''un unique micro-ondes standard. Cela crée quotidiennement une file d''attente continue entre 12h10 et 13h20, dépassant souvent les 25 minutes d''attente pour réchauffer un simple repas.\n\nSolution proposée :\n- Installation de 3 micro-ondes professionnels équipés d''une minuterie rapide.\n- Création d''un meuble dédié avec multiprises sécurisées coupe-circuit.\n- Raccordement d''une fontaine à eau fraîche avec filtre charbon actif.\n- Mise en place d''une charte de propreté et de lingettes biodégradables.\n\nCe projet impacte directement la santé, la convivialité et la ponctualité de plus de 300 étudiants chaque jour.',
 'Bâtiment B - Niveau 0', 'Plus de 300 étudiants par jour (B1, B2, B3)', 0, 'validated', 0, NULL,
 'La commission des investissements vie étudiante a approuvé l''achat des 3 micro-ondes et la pose du raccordement d''eau. Les travaux et la livraison sont prévus pour les vacances d''automne.',
 '2026-09-28 10:30:00', '2026-09-28 16:00:00', '2026-10-02 11:00:00', '2026-10-05 09:00:00'),
(2, 3, 2,
 'Extension des horaires d''ouverture des salles de projet et coworking jusqu''à 22h00',
 'Pendant les périodes de rendus de projets et de Hackathons, la fermeture à 19h00 oblige les groupes à se disperser ou à travailler dans des conditions compliquées. Nous demandons l''ouverture sécurisée par badge jusqu''à 22h00.',
 'Salles de projet & coworking', 'Toutes les promos en période de rendu', 0, 'threshold', 0, NULL, NULL,
 '2026-10-05 14:20:00', '2026-10-05 15:00:00', '2026-10-06 18:00:00', NULL),
(3, 1, 3,
 'Création d''un pôle E-Sport et organisation d''un grand tournoi inter-promos d''hiver',
 'Proposition de mettre en place une association étudiante dédiée à l''e-sport avec des soirées compétitives (Rocket League, Smash Bros, Valorant) pour renforcer la cohésion d''équipe et l''intégration des nouveaux arrivants.',
 'Amphithéâtre & salle informatique', 'Environ 150 étudiants intéressés', 1, 'voting', 0, NULL, NULL,
 '2026-10-06 12:00:00', '2026-10-06 13:00:00', NULL, NULL),
(4, 4, 5,
 'Mise en place de bacs de compost et collecte dédiée au recyclage du matériel électronique',
 'Installation d''un point de collecte responsable pour les composants électroniques obsolètes (câbles, cartes, écrans) et de bacs de compost pour les déchets organiques de la cafétéria.',
 'Cafétéria & hall d''entrée', 'Tout le campus', 0, 'voting', 0, NULL, NULL,
 '2026-10-04 09:45:00', '2026-10-04 11:00:00', NULL, NULL),
(5, 5, 7,
 'Aménagement d''un espace sieste / détente silencieux',
 'Demande de 4 poufs géants et suppression des écrans en salle A04 afin de créer un espace calme pour se reposer entre deux cours.',
 'Salle A04', 'Étudiants en journée continue', 1, 'pending', 0, NULL, NULL,
 '2026-10-07 09:15:00', NULL, NULL, NULL),
(6, 5, 3,
 'Tournoi de cartes payant avec mise en argent',
 'Organisation d''un tournoi de cartes hebdomadaire avec droit d''entrée et gains en argent pour les gagnants.',
 'Cafétéria', NULL, 1, 'voting', 1, 'Non-conforme au règlement intérieur du campus', NULL,
 '2026-10-03 17:00:00', '2026-10-03 18:00:00', NULL, NULL);

-- Votes de démonstration : (requête, nb Pour, nb Neutre, nb Contre)
-- 1 : 342 / 15 / 12  |  2 : 189 / 31 / 24  |  3 : 95 / 12 / 8  |  4 : 67 / 19 / 5  |  6 : 12 / 0 / 65
INSERT INTO `votes` (`request_id`, `user_id`, `value`, `created_at`)
SELECT 1, 1000 + n, CASE WHEN n <= 342 THEN 'pro' WHEN n <= 357 THEN 'neutral' ELSE 'con' END, '2026-09-30 12:00:00' FROM `tmp_numbers` WHERE n <= 369;
INSERT INTO `votes` (`request_id`, `user_id`, `value`, `created_at`)
SELECT 2, 1000 + n, CASE WHEN n <= 189 THEN 'pro' WHEN n <= 220 THEN 'neutral' ELSE 'con' END, '2026-10-06 12:00:00' FROM `tmp_numbers` WHERE n <= 244;
INSERT INTO `votes` (`request_id`, `user_id`, `value`, `created_at`)
SELECT 3, 1000 + n, CASE WHEN n <= 95 THEN 'pro' WHEN n <= 107 THEN 'neutral' ELSE 'con' END, '2026-10-06 20:00:00' FROM `tmp_numbers` WHERE n <= 115;
INSERT INTO `votes` (`request_id`, `user_id`, `value`, `created_at`)
SELECT 4, 1000 + n, CASE WHEN n <= 67 THEN 'pro' WHEN n <= 86 THEN 'neutral' ELSE 'con' END, '2026-10-05 12:00:00' FROM `tmp_numbers` WHERE n <= 91;
INSERT INTO `votes` (`request_id`, `user_id`, `value`, `created_at`)
SELECT 6, 1000 + n, CASE WHEN n <= 12 THEN 'pro' ELSE 'con' END, '2026-10-04 12:00:00' FROM `tmp_numbers` WHERE n <= 77;

DROP TEMPORARY TABLE IF EXISTS `tmp_numbers`;
DROP TABLE IF EXISTS `tmp_digits`;

INSERT INTO `comments` (`request_id`, `user_id`, `content`, `is_anonymous`, `created_at`) VALUES
(1, 4, 'Pensez aussi à prévoir des cloches de protection micro-ondes pour que les plats n''éclaboussent pas les parois, ça évitera les odeurs et simplifiera le nettoyage !', 1, '2026-09-29 13:10:00'),
(1, 5, 'Totalement pour ! J''ai dû renoncer deux fois à chauffer mon plat la semaine dernière parce que la pause était finie avant d''arriver au micro-ondes. C''est une excellente initiative.', 0, '2026-09-30 12:45:00'),
(1, 6, 'Merci pour cette proposition très bien documentée et pour cette forte mobilisation étudiante. La demande a été votée et validée par la direction. Les équipements ont été commandés auprès du fournisseur campus.', 0, '2026-10-05 09:05:00'),
(2, 1, 'Indispensable pendant les semaines de rendu, on finit toujours chez quelqu''un faute de salle.', 0, '2026-10-06 19:00:00'),
(3, 2, 'Super idée pour l''intégration des B1 !', 0, '2026-10-06 21:30:00'),
(4, 3, 'Un vrai plus pour le bilan RSE de l''école.', 0, '2026-10-05 10:00:00');

INSERT INTO `notifications` (`user_id`, `request_id`, `type`, `title`, `message`, `is_read`, `created_at`) VALUES
(2, 1, 'validated', 'Requête validée !', '« Micro-ondes Bâtiment B » a été approuvée par l''administration.', 0, '2026-10-05 09:00:00'),
(1, 1, 'validated', 'Requête validée !', '« Micro-ondes Bâtiment B » a été approuvée par l''administration.', 0, '2026-10-05 09:00:00'),
(1, 2, 'threshold', 'Seuil de 100 votes atteint', '« Extension coworking 22h » est transmise à la commission.', 0, '2026-10-06 18:00:00'),
(1, 3, 'comment', 'Nouveau débat', 'Nouvel avis sur votre projet « Pôle E-Sport ».', 0, '2026-10-06 21:30:00'),
(3, 2, 'threshold', 'Seuil de 100 votes atteint', 'Votre requête « Extension coworking 22h » est transmise à la commission.', 0, '2026-10-06 18:00:00');

INSERT INTO `audit_log` (`admin_id`, `request_id`, `action`, `message`, `created_at`) VALUES
(6, 6, 'reported', 'Requête #REQ-006 (Jeux payants) signalée pour non-conformité au règlement intérieur.', '2026-10-04 08:30:00'),
(6, 1, 'validated', 'Requête #REQ-001 (Micro-ondes Bâtiment B) validée par la Direction. Notification envoyée à l''auteur.', '2026-10-05 09:00:00'),
(NULL, 2, 'threshold', 'Requête #REQ-002 (Horaires coworking 22h) a franchi le palier des 100 votes et a été inscrite à l''ordre du jour.', '2026-10-06 18:00:00');
