SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='TRADITIONAL';


-- -----------------------------------------------------
-- Table `files`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `files` ;

CREATE  TABLE IF NOT EXISTS `files` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT ,
  `parent_id` INT(10) UNSIGNED NULL DEFAULT NULL ,
  `dt_created` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ,
  `dt_updated` TIMESTAMP NULL DEFAULT NULL ,
  `owner_id` VARCHAR(64) NULL DEFAULT NULL ,
  `file_path` VARCHAR(256) NULL DEFAULT NULL ,
  `upload_adapter` VARCHAR(45) NULL DEFAULT NULL ,
  `file_name` VARCHAR(45) NULL DEFAULT NULL ,
  `status` TINYINT(4) NULL DEFAULT NULL ,
  `metadata` TEXT NULL DEFAULT NULL ,
  `settings_id` INT(11) NULL DEFAULT NULL ,
  `type` VARCHAR(45) NULL DEFAULT NULL ,
  PRIMARY KEY (`id`) )
ENGINE = InnoDB
AUTO_INCREMENT = 33
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `logs`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `logs` ;

CREATE  TABLE IF NOT EXISTS `logs` (
  `id` INT(10) UNSIGNED NOT NULL ,
  `dt_created` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ,
  `level` VARCHAR(45) NULL DEFAULT NULL ,
  `content` TEXT NULL DEFAULT NULL ,
  PRIMARY KEY (`id`) )
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;

CREATE INDEX `INDEX` ON `logs` (`level` ASC) ;


-- -----------------------------------------------------
-- Table `queue`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `queue` ;

CREATE  TABLE IF NOT EXISTS `queue` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT ,
  `dt_created` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ,
  `dt_last_request` TIMESTAMP NULL DEFAULT NULL ,
  `delay_next_request` INT(11) NULL DEFAULT '0' ,
  `failed_attempts` TINYINT(4) NULL DEFAULT '0' ,
  `action` VARCHAR(45) NULL DEFAULT NULL ,
  `priority` VARCHAR(45) NULL DEFAULT NULL ,
  `object_id` VARCHAR(45) NULL DEFAULT NULL ,
  `params` TEXT NULL DEFAULT NULL ,
  `locked` TINYINT(1) NULL DEFAULT '0' ,
  `status` VARCHAR(45) NULL DEFAULT '0' ,
  PRIMARY KEY (`id`) )
ENGINE = InnoDB
AUTO_INCREMENT = 29
DEFAULT CHARACTER SET = utf8;

CREATE INDEX `index2` ON `queue` (`priority` ASC, `action` ASC) ;


-- -----------------------------------------------------
-- Table `settings`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `settings` ;

CREATE  TABLE IF NOT EXISTS `settings` (
  `transcoder_name` VARCHAR(45) NOT NULL ,
  `id` VARCHAR(128) NOT NULL ,
  `type` VARCHAR(128) NULL DEFAULT NULL ,
  `slug` VARCHAR(45) NULL DEFAULT NULL ,
  `name` VARCHAR(256) NULL DEFAULT NULL ,
  `technical` VARCHAR(256) NULL DEFAULT NULL ,
  `extension` VARCHAR(3) NULL DEFAULT NULL ,
  `recommend` TINYINT(1) NULL DEFAULT NULL ,
  `checked` TINYINT(1) NULL DEFAULT '0' ,
  `available` TINYINT(1) NULL DEFAULT '1' ,
  PRIMARY KEY (`id`, `transcoder_name`) )
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `variables`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `variables` ;

CREATE  TABLE IF NOT EXISTS `variables` (
  `id` VARCHAR(64) NOT NULL ,
  `val` VARCHAR(255) NULL DEFAULT NULL ,
  `dt_updated` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP ,
  PRIMARY KEY (`id`) )
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;

CREATE UNIQUE INDEX `key_UNIQUE` ON `variables` (`id` ASC) ;



SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
