<?php

require_once __DIR__ . '/includes.php';

use SimpleSchema\SimpleSchema;


 // Test stuff with comments
 $file = "
 test_articles:
     name string             
     veld_met_comment string         # Alles goed
     fulltext key (`name`)
 ";
 print_r(SimpleSchema::format($file)->diff());

 exit;
 $assertStructure("xxx CREATE TABLE `test_articles` (
     `id` int(11) NOT NULL AUTO_INCREMENT,
     `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
     `veld_met_comment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Alles goed',
     PRIMARY KEY (`id`)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 

