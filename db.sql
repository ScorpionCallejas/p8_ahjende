CREATE DATABASE IF NOT EXISTS db_test;
USE db_test;

CREATE TABLE ejecutivo (
    id_eje INT(11) AUTO_INCREMENT PRIMARY KEY,
    nom_eje VARCHAR(255) NOT NULL,
    tel_eje VARCHAR(15) NOT NULL,
    eli_eje INT DEFAULT 1,
    id_padre INT NULL,
    FOREIGN KEY (id_padre) REFERENCES ejecutivo(id_eje)
);