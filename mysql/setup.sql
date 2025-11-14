-- CS 365 — Assignment 3

-- reset
DROP DATABASE IF EXISTS student_passwords;
CREATE DATABASE student_passwords;
USE student_passwords;

-- aes settings
SET block_encryption_mode = 'aes-256-ecb';
SET @key_str = UNHEX(SHA2('My awesome passphrase', 256));

-- tables
CREATE TABLE IF NOT EXISTS userAccount (
  userId     INT NOT NULL AUTO_INCREMENT,
  firstName  VARCHAR(50)  NOT NULL,
  lastName   VARCHAR(50)  NOT NULL,
  username   VARCHAR(50)  NOT NULL UNIQUE,
  email      VARCHAR(255) NOT NULL UNIQUE,
  PRIMARY KEY (userId)
);

CREATE TABLE IF NOT EXISTS site (
  siteId    INT NOT NULL AUTO_INCREMENT,
  siteName  VARCHAR(100) NOT NULL,
  url       VARCHAR(255) NOT NULL UNIQUE,
  PRIMARY KEY (siteId)
);

CREATE TABLE IF NOT EXISTS credential (
  credId         INT            NOT NULL AUTO_INCREMENT,
  userId         INT            NOT NULL,
  siteId         INT            NOT NULL,
  passwordCipher VARBINARY(255) NOT NULL,
  comment        TEXT,
  createdAt      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (credId),
  CONSTRAINT fk_credential_user FOREIGN KEY (userId) REFERENCES userAccount(userId),
  CONSTRAINT fk_credential_site FOREIGN KEY (siteId) REFERENCES site(siteId)
);

-- seed
INSERT INTO userAccount (firstName,lastName,username,email) VALUES
 ('Jordan','Spector','jspector','jordan.n.spector@gmail.com'),
 ('Mia','Stein','mstein','mimi@outlook.com');

INSERT INTO site (siteName,url) VALUES
 ('Blackboard','https://blackboard.hartford.edu/ultra/stream'),
 ('GitHub','https://github.com'),
 ('Hartford','https://hartford.edu'),
 ('Mecha Ramen','https://mecharamen.com'),
 ('ASUS','https://www.asus.com');

INSERT INTO credential (userId,siteId,passwordCipher,comment) VALUES
 (1,1,AES_ENCRYPT('BbJS_F25!1',@key_str),'initial registration'),
 (1,2,AES_ENCRYPT('GhJS_F25!2',@key_str),'2FA enabled'),
 (1,3,AES_ENCRYPT('HfdJS_F25!3',@key_str),'migrated from legacy'),
 (1,4,AES_ENCRYPT('MrJS_F25!4',@key_str),'password rotation'),
 (1,5,AES_ENCRYPT('AsusJS_F25!5',@key_str),'warranty portal'),
 (2,1,AES_ENCRYPT('BbMS_F25!6',@key_str),'initial registration'),
 (2,2,AES_ENCRYPT('GhMS_F25!7',@key_str),'2FA enabled'),
 (2,4,AES_ENCRYPT('MrMS_F25!9',@key_str),'password rotation');

-- db user
DROP USER IF EXISTS 'passwords_user'@'localhost';
CREATE USER 'passwords_user'@'localhost' IDENTIFIED BY '';
GRANT ALL ON student_passwords.* TO 'passwords_user'@'localhost';
FLUSH PRIVILEGES;
