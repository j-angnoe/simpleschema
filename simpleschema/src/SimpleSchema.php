<?php

namespace SimpleSchema;

require_once 'DB.php';
require_once 'TableDefinition.php';

use SimpleSchema\DB;
use SimpleSchema\TableDefinition;

/**
 * new SimpleSchema('table', [
 *  'COLUMN_NAME' => 'COLUMN_DEFINITION',
 * ], [
 *  'idx_my_index' => '(column1, column2)'
 * ]);
 */


// internal function
/**
 *
 * Example  
         $nodes = [
            'comments',
            'posts',
            'topics',
            'users',
        ];

        // A has many B 
        $edges = [
            ['topics','posts'],
            ['users', 'posts'],
            ['users','comments'],
            ['posts', 'comments']
        ];

        print_r(topological_sort($nodes, $edges));

    Output:
    Array(
            [0] => topics
            [1] => users
            [2] => posts
            [3] => comments
        )
 */
// from https://stackoverflow.com/q/11953021
function topological_sort($nodeids, $edges) {
    $L = $S = $nodes = array();
    foreach($nodeids as $id) {
        $nodes[$id] = array('in'=>array(), 'out'=>array());
        foreach($edges as $e) {
            if ($id==$e[0]) { $nodes[$id]['out'][]=$e[1]; }
            if ($id==$e[1]) { $nodes[$id]['in'][]=$e[0]; }
        }
    }
    foreach ($nodes as $id=>$n) { if (empty($n['in'])) $S[]=$id; }
    while (!empty($S)) {
        $L[] = $id = array_shift($S);
        foreach($nodes[$id]['out'] as $m) {
            $nodes[$m]['in'] = array_diff($nodes[$m]['in'], array($id));
            if (empty($nodes[$m]['in'])) { $S[] = $m; }
        }
        $nodes[$id]['out'] = array();
    }
    foreach($nodes as $n) {
        if (!empty($n['in']) or !empty($n['out'])) {
            return null; // not sortable as graph is cyclic
        }
    }

    return $L;
}

class SimpleSchema {

    // Controls wheter processField_Stage1 performs a field type lookup or not.
    var $skip_fk_field_lookup = false;

    function __construct($table, $lines) {
        $this->table = $table;
        $this->lines = $lines;
    }

    function getCreateTable($table) {
        try { 
            return $this->grabAll("SHOW CREATE TABLE `$table`")[0]['Create Table'];
        } catch (\PDOException $e) {
            return '';
        }
    }

    static function format($definition) {

        // Skip multiline comments
        $definition = preg_replace('~/\*.+?\*/~s', '', $definition);

        $definition = explode("\n", trim($definition));

        $blocks = [];

        $tableDef = new TableDefinition;

        $parser = [
            function($line) use (&$blocks, &$parseBlocks, $tableDef) {
                if (substr(trim($line), -1) === ':') {
                    $block_name = substr($line, 0, -1);
                    $blocks[$block_name] = [];                
                    return function ($line, $next) use (&$blocks, $block_name) { 
                        
                        if ($line > '') {
                            @list($key, $def) = explode(' ', $line, 2);
            
                            $blocks[$block_name][] = $line;
                        }
    
                        if (substr($next, -1) === ':') {
                            return false;
                        } 
                    };
                };
            }
        ];

        for ($currentLine = 0; $currentLine < sizeof($definition); $currentLine++) {
            $line = trim($definition[$currentLine]);
            $next = trim($definition[$currentLine+1] ?? '');

            // skip comment lines # en // 
            if (substr($line, 0, 1) === '#' || substr($line, 0, 2) === '//') {
                continue;
            }
        
            $result = $parser[count($parser)-1]($line, $next);
            if ($result === false) {
                array_pop($parser);
            } else if ($result) {
                $parser[] = $result;
            }
        }        
        return SimpleSchema::define($blocks);
    }

    static function define($table, $lines = []) {

        
        if (is_array($table)) {

            $edges = [];
            
            foreach ($table as $k=>$v) { 
                $nodes[$k] = $k;
                $obj = new SimpleSchema($k,$v);
                $obj->skip_fk_field_lookup = true;

                $def = $obj->processFields_Stage1($v);
                foreach ($def->getReferingTables() as $ref) { 
                    // edge = [ A hasMany B ] 
                    $nodes[$ref]=$ref;
                    $edges[] = [$ref, $k];
                }
            }

            $orderOfOperations = topological_sort($nodes, $edges);

            foreach ($orderOfOperations as $tbl) {
                if (isset($table[$tbl])) { 
                    $objs[] = SimpleSchema::define($tbl, $table[$tbl]);
                }
            }
            return new class($objs) { 
                function __construct($objs) { 
                    $this->objs = $objs;
                }
                function __call($name, $args) {
                    $result = [];
                    foreach ($this->objs as $o) {
                        $result = array_merge($result, toa($o->$name(...$args)));
                    }
                    return $result;
                }
            };
        } else {
            $object = new self($table, $lines);
            return $object;
        }
    }

    function db() {
        return DB::getPdoConnection();
    }

    function query($query, $data = []) {
        $statement = $this->db()->prepare($query);
        call_user_func_array([$statement, 'execute'], $data);
        while ($row = $statement->fetch()) {
            yield $row;
        }
        $statement->closeCursor();
    }

    function grabAll($query, $data = []) {
        return iterator_to_array($this->query($query, $data));
    }

    function describeTable($table) {
        return iterator_to_array($this->query("DESCRIBE $table"));
    }
    function processFields() { 
        $def = $this->processFields_Stage1($this->lines);
        $def = $this->processFields_Stage2($def);

        return $def;
    }


    function processFields_Stage1() { 
        $def = new TableDefinition;
        $def->setLines($this->lines);

        $translateFields = [
            'string' => 'VARCHAR(255)',
            'name' => 'VARCHAR(40)',
            'text' => 'TEXT',
            'json' => 'JSON',
            's' => 'VARCHAR(255)',
            'n' => 'INT',
            'f' => 'FLOAT',
            'b' => 'TINYINT'
        ];

        $shortcuts = [
            'timestamps' => [
                'created_at' => 'TIMESTAMP NULL DEFAULT NULL',
                'updated_at' => 'TIMESTAMP NULL DEFAULT NULL'
            ],
            'softDeletes' => [
                'deleted_at' => 'TIMESTAMP NULL DEFAULT NULL'
            ]
        ];

        $newLines = [];

        /**
         * Todo:
         * - Vertaal types
         * - Haal de indexes eruit
         * - En de references...
         */

        $fieldIndices = [];

        foreach ($def->parse() as $line_id => $line) { 
            // print_r($line);
            // Skip non field classes.
            if ($line['class'] !== 'field') { 
                $newLines[] = $line['full'];
                continue;
            }            

            // Handle shortcuts.
            if (isset($shortcuts[trim($line['full'])])) {
                foreach ($shortcuts[trim($line['full'])] as $addField => $addLine) {
                    $newLines[] = "`$addField` $addLine";
                }
                continue;
            }

            if (isset($translateFields[$line['type']])) {
                $line['type'] = $translateFields[$line['type']];
            }

            $type_extra = trim($line['type'] . ' ' . $line['extra']);
            $key = $line['field'];

            // Handle field defs with an index formulation
            $type_extra = preg_replace_callback('/\W(?<idx_type>index|unique|unique index)(\s*\((?<idx_name>[^\)]+)\))*/i', function ($match) use ($key, &$fieldIndices) {
                // var_dump($match);
                $idx_name = $match['idx_name'] ?? 'idx_' . $key;
                $idx_type = strtolower($match['idx_type']);
                $fieldIndices[$idx_name] = $fieldIndices[$idx_name] ?? [];

                $fieldIndices[$idx_name]['fields'][$key] = $key;
                $fieldIndices[$idx_name]['type'] = $idx_type;

                return ' ';
            },  $type_extra);            
                        
            // Handle index definitions:
            $type_extra = preg_replace_callback('/^(?<idx_type>index|unique|unique index|fulltext key)(\s*\((?<idx_fields>[^\)]+)\))/i', function ($match) use ($key, &$fieldIndices) {
                $idx_name = $key;
                $idx_fields = preg_split('/\s*,\s*/', $match['idx_fields']);
                $idx_type = strtolower($match['idx_type']);
                $fieldIndices[$idx_name] = $fieldIndices[$idx_name] ?? [];
                $fieldIndices[$idx_name]['fields'] = $idx_fields;
                $fieldIndices[$idx_name]['type'] = $idx_type;

                return '';
            },  $type_extra);     

            if (preg_match('/(foreign key )*references\s+(?<table>\w+)\s*(\.\s*(?<field1>\w+)|\((?<field2>\w+)\))(?<fk_extra>\s(on)\s(delete|update)(\s(cascade))?)?(?<extra>.+)?/i', $type_extra, $match)) {
                $table = $match['table'];
                $field = $match['field1'] ? $match['field1'] : $match['field2'];
                $match['extra'] ??= '';
                $match['fk_extra'] ??= '';
                
                if ($this->skip_fk_field_lookup) {
                    $type_extra = 'INTEGER UNSIGNED';
                } else { 
                    try {
                        $type_extra = $this->grabAll("DESCRIBE `{$table}_simpleschema_tmp` `$field`")[0]['Type'] . " " . ltrim($match['extra'] ?? '');

                    } catch (\Exception $e) { 
                        error_log("Error at foreign key $key $type_extra... writing an unsigned int: " . $e->getMessage());

                        $type_extra = "INTEGER UNSIGNED";
                    }    
                }

                $fieldIndices["fk_{$this->table}_{$table}_{$field}"] = [
                    'type' => 'foreign_key',
                    'foreign_key' => $key,
                    'references' => [$table, $field],
                    'extra' => $match['fk_extra']
                ];
                
            }

            if (trim($type_extra) > '') { 
                $comment = '';
                if ($line['comment']) { 
                    $comment = "COMMENT '" . addcslashes($line['comment'], "'") . "'";
                }
                $newLines[] = trim("`$key` $type_extra $comment");
            }
        }

        foreach ($fieldIndices as $idx_name => $def) { 
            switch($def['type']) {
                case 'unique':
                case 'index':
                    $sqlType = $def['type'] === 'unique' ? 'UNIQUE' : 'KEY';
                    $newLines[] = "$sqlType `$idx_name` (" . join(',', $def['fields']) .')';
                break;
                case 'foreign_key':
                    # $foreignKeyLines[] = "KEY `$idx_name` (`{$def['foreign_key']}`)";
                    $newLines[] = "CONSTRAINT `$idx_name` FOREIGN KEY (`{$def['foreign_key']}`) REFERENCES `{$def['references'][0]}` (`{$def['references'][1]}`) {$def['extra']}";
                break;
            }
        }

        $def = TableDefinition::autodetect(join("\n", $newLines));

        return $def;
    }

    private function processFields_Stage2($def) {
        $table = $this->table;

        $tmpTable = $table . '_simpleschema_tmp';

        

        $tmpStatement = [];

        $foreignKeyLines = [];

        $useTemporaryTable = true;
        foreach ($def->parse() as $line) { 
            if ($line['class'] === 'key') {
                switch (strtolower($line['key_type'])) {
                    case 'constraint':
                        list(,$fk) = get_preg_match($line['full'], '~KEY (\([^\)]+\))~');
                        $foreignKeyLines[] = "KEY `{$line['id']}` $fk";
                        $foreignKeyLines[] = "\t{$line['full']}";
                    break;
                    case 'fulltext key':
                        $useTemporaryTable = false;

                    case 'unique':
                    case 'unique key':
                    case 'key':
                    case 'index':

                        $tmpStatement[] = "\t{$line['full']}";
                    break;
                    default:
                        throw new \Exception('Cannot handle ' . $line['key_type']);
                }
            } else {
                $tmpStatement[] = "\t{$line['full']}";
            }
        }

        $this->db()->exec("DROP TEMPORARY TABLE IF EXISTS $tmpTable;");
        $this->db()->exec("DROP TABLE IF EXISTS $tmpTable;");

        $tmpStatement = join(",\n\t", $tmpStatement) . "\n) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        // echo $tmpStatement;

        if (!preg_match('/AUTO_INCREMENT|PRIMARY KEY/i', $tmpStatement)) {
            $tmpStatement = "\n\tid INTEGER PRIMARY KEY AUTO_INCREMENT,\n\t" . $tmpStatement;
        }

        if ($useTemporaryTable) { 
            $tmpStatement = "CREATE TEMPORARY TABLE $tmpTable (" . $tmpStatement;
        } else {
            error_log($table . ' uses fulltext, using a non-temporary table instead.');

            $tmpStatement = "CREATE TABLE $tmpTable (" . $tmpStatement;
        }

        try { 
            iterator_to_array($this->query($tmpStatement));

            // error_log("Created temporary table $tmpTable\n");

            $tmpDefinition = $this->grabAll("SHOW CREATE TABLE `$tmpTable`")[0]['Create Table'];

            $def = new TableDefinition();
            $def->setSql($tmpDefinition);
            $def->addLines($foreignKeyLines);

            return $def;

        } catch (\PDOException $e) { 
            error_log("Failed at creating temporary table $tmpTable\n$tmpStatement\n$e");
            exit;
        }
    }

    function diff() {
        $def = new TableDefinition();
        $table = $this->table;
        $schema = $this->processFields();
        $modifications = [];

        try { 
            $createTable = $this->grabAll("SHOW CREATE TABLE `$table`")[0]['Create Table'];
            $def->setSql($createTable);

        } catch (\PDOException $e) { 
            // error_log("Created table");
            // $createTable = $this->grabAll("CREATE TABLE `$table` LIKE `{$table}_simpleschema_tmp`");

            $createTable = $this->grabAll("SHOW CREATE TABLE `{$table}_simpleschema_tmp`")[0]['Create Table'];
            $modifications[] = str_replace(["CREATE TEMPORARY ", "`{$table}_simpleschema_tmp`"], ["CREATE ","`$table`"], $createTable);

            $def->setSql($createTable);
        }

        foreach ($def->getSqlDiff($schema) as $mod) {
            $modifications[] = "ALTER TABLE `$table` $mod";
        }

        return $modifications;
    }

    function run() {
        $modifications = $this->diff();
        $table = $this->table;

        $this->db()->beginTransaction();

        if (empty($modifications)) {
            error_log("$table is sync.");
        } else {
            foreach ($modifications as $m) {
                $query = "$m";
                echo trim($query) . "\n";
                $this->db()->exec($query);
            }
        }

        $this->db()->commit();
    }
}
