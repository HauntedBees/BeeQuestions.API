SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
CREATE DATABASE IF NOT EXISTS `bqdb` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `bqdb`;

CREATE TABLE IF NOT EXISTS `answer` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `url` varchar(30) NOT NULL,
  `user` bigint(20) NOT NULL,
  `answer` varchar(1000) NOT NULL,
  `status` int(11) NOT NULL,
  `opened` datetime NOT NULL,
  `closed` datetime DEFAULT NULL,
  `bestquestion` bigint(20) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `answer_tag` (
  `answer` bigint(20) NOT NULL,
  `tag` bigint(20) NOT NULL,
  PRIMARY KEY (`answer`,`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `answer_user_likes` (
  `answer` bigint(20) NOT NULL,
  `user` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `notification` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user` bigint(20) NOT NULL,
  `type` int(11) NOT NULL,
  `referenceid` bigint(20) NOT NULL,
  `posted` datetime NOT NULL,
  `seen` bit(1) NOT NULL DEFAULT b'0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `question` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `answer` bigint(20) NOT NULL,
  `user` bigint(20) NOT NULL,
  `question` varchar(2000) NOT NULL,
  `posted` datetime NOT NULL,
  `score` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `question_user_likes` (
  `question` bigint(20) NOT NULL,
  `user` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tag` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `userlevel` (
  `level` int(11) NOT NULL,
  `title` varchar(45) NOT NULL,
  `description` varchar(420) NOT NULL,
  `scorerequired` int(11) NOT NULL,
  `answersperday` int(11) NOT NULL,
  `questionsperday` int(11) NOT NULL,
  PRIMARY KEY (`level`),
  UNIQUE KEY `level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `userlevel` (`level`, `title`, `description`, `scorerequired`, `answersperday`, `questionsperday`) VALUES
(1, 'Little Egg', 'You are so little. What an egg.', 0, 1, 5),
(2, 'Newbie', 'You are a wee child with much to learn.', 75, 3, 9),
(3, 'Philosopher', 'Every question has an answer, but does every answer have a question?', 300, 7, 17),
(4, 'Eris Tottle', 'All men by nature desire to get points in online games.', 800, 13, 27),
(5, 'Plate-O', 'Boredom is the feeling of a philosopher, and philosophy begins in boredom.', 1300, 21, 41),
(6, 'So Crates', 'There is only one good, knowledge, and one evil, Bee Colony Collapse Disorder.', 2000, 31, 57),
(7, 'Anti-Philosopher', 'Who needs rational inquiry when you can have irrational yelling?', 4000, 43, 77),
(8, 'Queen Bee', 'A bee!', 10000, 57, 99);

CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `beeauthid` bigint(20) NOT NULL,
  `displayname` varchar(100) NOT NULL,
  `joined` datetime NOT NULL,
  `lastlogin` datetime NOT NULL,
  `emoji` varchar(10) NOT NULL,
  `color` varchar(10) NOT NULL,
  `score` int(11) NOT NULL DEFAULT 100,
  `level` int(11) NOT NULL DEFAULT 2,
  `blockeduntil` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `beeauthid` (`beeauthid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_report` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user` bigint(20) NOT NULL,
  `reported` datetime NOT NULL,
  `dismissed` bit(1) NOT NULL DEFAULT b'0',
  `reporteduser` bigint(20) DEFAULT NULL,
  `reportedanswer` bigint(20) DEFAULT NULL,
  `reportedquestion` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
COMMIT;