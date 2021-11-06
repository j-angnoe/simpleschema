<?php

namespace SimpleSchema;

use SimpleSchema\SimpleSchema;
use SimpleSchema\DB;
use ReflectionClass;

require_once __DIR__ . '/SimpleSchema.php';

function collect_settings($log = false) { 
    $path = explode(DIRECTORY_SEPARATOR, getcwd());

    $built=[];    
    foreach ($path as $piece) { 
        $built[] = join('', [ end($built), $piece, '/']);
    }
    $built = array_slice($built,1);
    $settings = [];

    foreach (array_reverse($built) as $b) { 
        if ($log) error_log("Looking for package.json in $b");
        if (file_exists("$b/package.json")) { 
            if ($log) error_log("Found a package.json in $b");
            $data = json_decode(file_get_contents("$b/package.json"), 1);
            if ($data['simpleschema']) { 
                if ($log) error_log("Reading `simpleschema` key from $b/package.json");
                return $data['simpleschema'];
            }
        }
    }

    return $settings;
}
  
/**
 * Used by the simpleschema binary.
 */
class CliController {
    static function dispatch($argv, $controller = null) { 
        $controller = $controller ?: new static;
        
        if (!isset($argv[1]) || preg_match("~(-h|--help|help|-\?)~", $argv[1]) || !method_exists($controller, $argv[1])) {
            $controller->help();

            exit(1);
        }

        $result = call_user_func_array([$controller, $argv[1]], array_slice($argv, 2));
        if (is_array($result)) { 
            echo json_encode($result, JSON_PRETTY_PRINT);
            echo "\n";
        } else if (is_string($result)) { 
            echo $result;
        }
    }
    /**
     * Shows scanned config files and displays the current settings.
     */
    function settings() {
        $settings = collect_settings(true);
        return $settings;
    }

    /**
     * scan the current project for simpleschema files,
     * excluded directories: node_modules, build, dist, vendor, .git and .cache
     */
    function ls() { 
        // @todo - these must become a setting of sorts.
        $excludeDirs = ['node_modules','build','dist','vendor','.cache', '.git'];

        $findCommand = "find . -type d \( " . join(' -o ', array_map(fn($n) => '-name '.$n, $excludeDirs)) ." \) -prune -false -o -type f -name '*.simpleschema*'";


        $schemas = explode("\n", trim(`$findCommand`));

        return $schemas;
    }

    /**
     * concatenate all content from simpleschema files.
     * and do awesome shit.
     */
    function cat() { 
        $str = '';
        foreach ($this->ls() as $file) { 
            $str .= file_get_contents($file) . "\n";
        }
        return $str;
    }

    /**
     * calculate the changes between the active database
     * and the written simpleschemas.
     */
    function diff() {
        $content = $this->cat();
        $this->load_settings();

        $obj = SimpleSchema::format($content);
        return $obj->diff();
    }

    /**
     * Synchronize the database schema with the
     * simpleschema files.
     */
    function run() {
        $content = $this->cat();
        $this->load_settings();

        $obj = SimpleSchema::format($content);
        return $obj->run();
    }

    private function load_settings() { 
        if (!(isset($_ENV['DB_HOST']) || isset($_ENV['DB_USERNAME']))) { 
            $settings = collect_settings();
            $_ENV += $settings;
        }
    }

    /**
     * Display this usage information
     */
    function help() {
        echo "SimpleSchema usage:\n\n";

        $x = new ReflectionClass($this);
        foreach ($x->getMethods() as $m) { 
            $comment = $m->getDocComment();
            if ($comment) { 
                echo 'simpleschema ' . $m->getName() . "\n";
                echo str_replace("\n","\n\t", preg_replace("~(/\*+|[ \t]+\*\s|\*+/)~", "", $comment)) . "\n";
            }
        }
    }

    /**
     * Exports the current database schema to simpleschema format.
     * Tables are exported in the right order according to their
     * dependencies / foreign key relations.
     * 
     * example usage: 
     * simpleschema export                  - to export all
     * simpleschema export table1 table2    - to export only selected tables.
     * 
     * simpleschema export > my.simpleschema.txt - write to file.
     */

    function export($table1 = null, $table2 = null) { 
        $tables = func_get_args();
        $this->load_settings();

        $data = [];

        if (empty($tables)) { 
            foreach (DB::fetchAll("SHOW TABLES") as $row) {
                $tables[] = current($row);
            }
        }

        $edges = [];
        $nodes = [];
        foreach ($tables as $table) { 
            
            $create = DB::fetchOne("SHOW CREATE TABLE `$table`")['Create Table'];

            $obj = \SimpleSchema\TableDefinition::autodetect($create);
            $nodes[$table]=$table;
            foreach ($obj->getReferingTables() as $ref) {
                if (!in_array($ref, $tables)) { 
                    $nodes[$ref]=$ref;
                    error_log("Warning: Table `$table` depens on `$ref` which is not included in your selection.");
                }
                $edges[] = [$ref, $table];    
            }
            $data[$table] = $obj;
        }

        if (count($data) === 1) {
            $theRightOrder = array_keys($data);
        } else {
            $theRightOrder = topological_sort($nodes, $edges);
        }
        
        foreach ($theRightOrder as $table) {
            if (isset($data[$table])) { 
                echo "$table:\n";
                foreach ($data[$table]->getLines() as $line) {
                    echo "\t" . $line . "\n";
                }
                echo "\n";
            }
        }
    }

    /**
     * Display manual
     */
    function man() {
        echo file_get_contents(__DIR__ . '/../README.md');
    }
}