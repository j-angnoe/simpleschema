<?php

use SimpleSchema\SimpleSchema;

$_ENV['DB_DATABASE'] = 'simpleschema_db';
$_ENV['DB_HOST'] = 'localhost';
$_ENV['DB_PORT'] = 9836;
$_ENV['DB_USERNAME'] = 'root';
$_ENV['DB_PASSWORD'] = 'password';

require_once __DIR__ . '/../simpleschema/src/DB.php';
require_once __DIR__ . '/../simpleschema/src/SimpleSchema.php';

$obj = new SimpleSchema('_', []);

$assertStructure = function ($expectStructure = '', $table = 'test_articles') use ($obj) { 
    $currentStructure = $obj->getCreateTable($table);

    if (trim(preg_replace('/\s+/m',' ',$currentStructure)) !== trim(preg_replace('/\s+/m', ' ', $expectStructure))) {
        $message = "Assertion failed:\n";
        $message .= "Expected to see:\n----------\n$expectStructure\n-----------\nBut got:\n---------------\n$currentStructure\n-----------\n\n";

        throw new Exception($message);
    }
};

$printStructure = function ($table = 'test_articles') use ($obj) {
    echo "\n----------\n" . $obj->getCreateTable($table) . "\n------------\n";
};


////////////////////////////
// Perform some tests
////////////////////////////

$file = "
test_articles:
    name string
";
//SimpleSchema::format($file)->run();
SimpleSchema::format($file)->run();

$assertStructure("CREATE TABLE `test_articles` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");



// Test add column 

$file = "
test_articles:
    name string
    new_column int
";
SimpleSchema::format($file)->run();
//$assertStructure();

$assertStructure("CREATE TABLE `test_articles` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `new_column` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");


// Test file index:
$file = "
test_articles:
    name string
    new_column int
    category varchar(10) index
";
SimpleSchema::format($file)->run();
$assertStructure("CREATE TABLE `test_articles` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `new_column` int(11) DEFAULT NULL,
    `category` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_category` (`category`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");


// Test index sql style
$file = "
test_articles:
    name string
    new_column int
    category varchar(10) index
    key `idx_optimized` (new_column, category)
";
SimpleSchema::format($file)->run();
$assertStructure("CREATE TABLE `test_articles` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `new_column` int(11) DEFAULT NULL,
    `category` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_category` (`category`),
    KEY `idx_optimized` (`new_column`,`category`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");


// Test index sql style
$file = "
test_categories:
    name varchar(10)

test_articles:
    name string
    new_column int
    category references test_categories.id
    key `idx_optimized` (new_column, category)
";
SimpleSchema::format($file)->run();
$assertStructure("CREATE TABLE `test_articles` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `new_column` int(11) DEFAULT NULL,
    `category` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_optimized` (`new_column`,`category`),
    KEY `fk_category_test_categories_id` (`category`),
    CONSTRAINT `fk_category_test_categories_id` FOREIGN KEY (`category`) REFERENCES `test_categories` (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");


// Test removal / cleanup
$file = "
test_articles:
    name string
";
SimpleSchema::format($file)->run();
$assertStructure("CREATE TABLE `test_articles` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");


// Test stuff with comments
$file = "
test_articles:
    name string             
    veld_met_comment string         # Alles goed
";
SimpleSchema::format($file)->run();
$assertStructure("CREATE TABLE `test_articles` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `veld_met_comment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Alles goed',
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Test mysql format
$file = "
test_articles:
    `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT
    `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
    `veld_met_comment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Alles goed'
    `iets_met_default` tinyint(4) DEFAULT 1
";
SimpleSchema::format($file)->run();
$assertStructure("CREATE TABLE `test_articles` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `veld_met_comment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Alles goed',
    `iets_met_default` tinyint(4) DEFAULT 1,
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");


// Test remove columns door ze weg te commenten:
$file = "
test_articles:
    name string             
    veld_met_comment string         # Alles goed
    # iets_met_default TINYINT DEFAULT 1
";
SimpleSchema::format($file)->run();
$assertStructure("CREATE TABLE `test_articles` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `veld_met_comment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Alles goed',
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");


// Test remove columns door ze weg te commenten:
$file = "
/* dit wordt allemaal geskipped
test_articles:
    name string             
    veld_met_comment string         # Alles goed
    # iets_met_default TINYINT DEFAULT 1
*/

// en dit wordt 'm:
test_articles:
    xx string 
    yy string
";
SimpleSchema::format($file)->run();
$assertStructure("CREATE TABLE `test_articles` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `xx` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `yy` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");


/**
 * FIXME: DIT WERKT NIET, het hernoemen van primary key:
 * 
 * // en dit wordt 'm:
test_articles:
    xx string primary key
    yy string
";
SimpleSchema::format($file);
$assertStructure("CREATE TABLE `test_articles` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `veld_met_comment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Alles goed',
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 */

 echo "\n\nAll tests ran succesfully.\n";
 exit(0);