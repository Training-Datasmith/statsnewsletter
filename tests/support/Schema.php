<?php

if (!defined('STATSNEWSLETTER_TEST_ROOT')) {
    define('STATSNEWSLETTER_TEST_ROOT', dirname(dirname(__DIR__)));
}

require_once STATSNEWSLETTER_TEST_ROOT . '/tests/support/Harness.php';

$db = Db::getInstance();

$statements = array(
    'CREATE TABLE IF NOT EXISTS `ps_shop_group` (
        `id_shop_group` INT UNSIGNED NOT NULL,
        `share_customer` TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id_shop_group`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
    'CREATE TABLE IF NOT EXISTS `ps_shop` (
        `id_shop` INT UNSIGNED NOT NULL,
        `id_shop_group` INT UNSIGNED NOT NULL,
        PRIMARY KEY (`id_shop`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
    'CREATE TABLE IF NOT EXISTS `ps_customer` (
        `id_customer` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
        `id_shop_group` INT UNSIGNED NOT NULL DEFAULT 1,
        `newsletter_date_add` DATETIME NULL,
        PRIMARY KEY (`id_customer`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
    'CREATE TABLE IF NOT EXISTS `ps_emailsubscription` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
        `id_shop_group` INT UNSIGNED NOT NULL DEFAULT 1,
        `newsletter_date_add` DATETIME NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
    'CREATE TABLE IF NOT EXISTS `ps_module` (
        `id_module` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(128) NOT NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id_module`),
        UNIQUE KEY `module_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
    'CREATE TABLE IF NOT EXISTS `ps_hook` (
        `id_hook` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(128) NOT NULL,
        PRIMARY KEY (`id_hook`),
        UNIQUE KEY `hook_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
    'CREATE TABLE IF NOT EXISTS `ps_hook_module` (
        `id_module` INT UNSIGNED NOT NULL,
        `id_hook` INT UNSIGNED NOT NULL,
        `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
        PRIMARY KEY (`id_module`, `id_hook`, `id_shop`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
    'CREATE TABLE IF NOT EXISTS `ps_employee` (
        `id_employee` INT UNSIGNED NOT NULL,
        `stats_date_from` DATE NOT NULL,
        `stats_date_to` DATE NOT NULL,
        PRIMARY KEY (`id_employee`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8',
);

foreach ($statements as $sql) {
    $db->execute($sql);
}
