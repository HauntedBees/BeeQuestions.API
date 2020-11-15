CREATE DATABASE IF NOT EXISTS `bqdb`
USE `bqdb`;
/* #region Base Tables */
CREATE TABLE `bqdb`.`answer` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `url` VARCHAR(30) NOT NULL,
  `user` BIGINT NOT NULL,
  `answer` NVARCHAR(1000) NOT NULL,
  `status` INT NOT NULL,
  `opened` DATETIME NOT NULL,
  `closed` DATETIME NULL,
  `views` INT NOT NULL DEFAULT 0,
  `score` INT NOT NULL DEFAULT 0,
  `bestquestion` BIGINT NULL,
  PRIMARY KEY (`id`),
  INDEX `url_key` (`url` ASC) VISIBLE,
  UNIQUE INDEX `id_UNIQUE` (`id` ASC) VISIBLE);

CREATE TABLE `bqdb`.`tag` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `name` NVARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`));

CREATE TABLE `bqdb`.`answer_tag` (
  `answer` BIGINT NOT NULL,
  `tag` BIGINT NOT NULL,
  PRIMARY KEY (`answer`, `tag`));

CREATE TABLE `bqdb`.`question` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `answer` BIGINT NOT NULL,
  `user` BIGINT NOT NULL,
  `question` NVARCHAR(2000) NOT NULL,
  `posted` DATETIME NOT NULL,
  `score` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  INDEX `answer_idx` (`answer` ASC) VISIBLE,
  CONSTRAINT `question_answer`
    FOREIGN KEY (`answer`)
    REFERENCES `bqdb`.`answer` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION);

CREATE TABLE `bqdb`.`users` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `beeauthid` BIGINT NOT NULL,
  `name` NVARCHAR(100) NOT NULL,
  `displayname` NVARCHAR(100) NULL,
  `joined` DATETIME NOT NULL,
  `lastlogin` DATETIME NOT NULL,
  `emoji` VARCHAR(10) NULL,
  `color` VARCHAR(10) NULL,
  `score` INT NOT NULL DEFAULT 100,
  `level` INT NOT NULL DEFAULT 2,
  `blockeduntil` DATETIME NULL
  PRIMARY KEY (`id`));

CREATE TABLE `bqdb`.`userlevel` (
  `level` INT NOT NULL,
  `title` NVARCHAR(45) NOT NULL,
  `description` NVARCHAR(420) NOT NULL,
  `scorerequired` INT NOT NULL,
  `answersperday` INT NOT NULL,
  `questionsperday` INT NOT NULL,
  PRIMARY KEY (`level`));

CREATE TABLE `bqdb`.`notification` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `user` BIGINT NOT NULL,
  `type` INT NOT NULL,
  `referenceid` BIGINT NOT NULL,
  `posted` DATETIME NOT NULL,
  `seen` BIT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_UNIQUE` (`id` ASC) VISIBLE);
/* #endregion */
/* #region Relationship Tables */
CREATE TABLE `bqdb`.`answer_tag` (
  `answer` BIGINT NOT NULL,
  `tag` BIGINT NOT NULL,
  INDEX `at_tag_idx` (`tag` ASC) VISIBLE,
  INDEX `at_answer_idx` (`answer` ASC) VISIBLE,
  CONSTRAINT `at_tag`
    FOREIGN KEY (`tag`)
    REFERENCES `bqdb`.`tag` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `at_answer`
    FOREIGN KEY (`answer`)
    REFERENCES `bqdb`.`answer` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION);

CREATE TABLE `bqdb`.`answer_user_likes` (
  `answer` BIGINT NOT NULL,
  `user` BIGINT NULL,
  INDEX `au_user_idx` (`user` ASC) VISIBLE,
  INDEX `au_answer_idx` (`answer` ASC) VISIBLE,
  CONSTRAINT `au_user`
    FOREIGN KEY (`user`)
    REFERENCES `bqdb`.`user` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `au_answer`
    FOREIGN KEY (`answer`)
    REFERENCES `bqdb`.`answer` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION);

CREATE TABLE `bqdb`.`question_user_likes` (
  `question` BIGINT NOT NULL,
  `user` BIGINT NOT NULL,
  INDEX `qu_question_idx` (`question` ASC) VISIBLE,
  INDEX `qu_user_idx` (`user` ASC) VISIBLE,
  CONSTRAINT `qu_question`
    FOREIGN KEY (`question`)
    REFERENCES `bqdb`.`question` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `qu_user`
    FOREIGN KEY (`user`)
    REFERENCES `bqdb`.`user` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION);

CREATE TABLE `bqdb`.`user_report` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `user` BIGINT NOT NULL,
  `reported` DATETIME NOT NULL,
  `dismissed` BINARY NOT NULL DEFAULT 0,
  `reporteduser` BIGINT NULL,
  `reportedanswer` BIGINT NULL,
  `reportedquestion` BIGINT NULL,
  PRIMARY KEY (`id`),
  INDEX `ur_user_idx` (`user` ASC) VISIBLE,
  INDEX `ur_reporteduser_idx` (`reporteduser` ASC) VISIBLE,
  INDEX `ur_reportedanswer_idx` (`reportedanswer` ASC) VISIBLE,
  INDEX `ur_reportedquestion_idx` (`reportedquestion` ASC) VISIBLE,
  CONSTRAINT `ur_user`
    FOREIGN KEY (`user`)
    REFERENCES `bqdb`.`user` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `ur_reporteduser`
    FOREIGN KEY (`reporteduser`)
    REFERENCES `bqdb`.`user` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `ur_reportedanswer`
    FOREIGN KEY (`reportedanswer`)
    REFERENCES `bqdb`.`answer` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `ur_reportedquestion`
    FOREIGN KEY (`reportedquestion`)
    REFERENCES `bqdb`.`question` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION);
/* #endregion */
/* #region Initial Data */
INSERT INTO userlevel VALUES
	(1, 'Little Egg', 'You are so little. What an egg.', 0, 1, 5), 
	(2, 'Newbie', 'You are a wee child with much to learn.', 75, 3, 9), 
	(3, 'Philosopher', 'Every question has an answer, but does every answer have a question?', 300, 7, 17), 
	(4, 'Eris Tottle', 'All men by nature desire to get points in online games.', 800, 13, 27),
	(5, 'Plate-O', 'Boredom is the feeling of a philosopher, and philosophy begins in boredom.', 1300, 21, 41),
	(6, 'So Crates', 'There is only one good, knowledge, and one evil, Bee Colony Collapse Disorder.', 2000, 31, 57),
	(7, 'Anti-Philosopher', 'Who needs rational inquiry when you can have irrational yelling?', 4000, 43, 77),
	(8, 'Queen Bee', 'A bee!', 10000, 57, 99);
/* #endregion */