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

