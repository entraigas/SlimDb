<?php
/**
 * SlimDb
 *
 * Provides a database wrapper around the PDO and PDO statement that 
 * help to reduce the effort to interact with a database.
 *
 * @author         Marcelo Entraigas <entraigas@gmail.com>
 * @license        MIT
 * @filesource
 *
 */


/**
 * Custom methods for MySQL databases
 */

return array(
    // Initialize default driver settings after Database constructor
    'init' => function($index){
        // MySQL uses a non-standard column identifier
        self::_setWrapper($index, '`%s`');
    },
    
    // Build a limit clause
    'limit' => function( $index, $offset = 0, $limit = 0 ){
        $offset = (int) $offset;
        $limit = (int) $limit;
        if( $offset==0 && $limit==0 )
            throw new \Exception("Database Error: invalid parameters in query (offset={$offset} - limit= {$limit} - driver {$db->type}).");
        if( $offset<0 )
            throw new \Exception("Database Error: invalid <offset> parameter in query (offset={$offset} - driver {$db->type}).");
        if( $limit<0 )
            throw new \Exception("Database Error: missing <limit> parameter in query (limit= {$limit} - driver {$db->type}).");
        if( $offset>0 && $limit>0 )
            return " LIMIT {$offset}, {$limit}";
        if( $offset==0 && $limit>0 )
            return " LIMIT {$limit}";
    },
    
    // Get database name
    'dbName' => function ($index){
        return self::query($index, "SELECT DATABASE();")->getVal();
    },
    
    // List all tables from database
    'schemaDb' => function ($index){
        $tables = self::query($index, "SHOW TABLES")->getAll();
        $data = array();
        foreach($tables as $item){
            $data[] = (string) array_shift(array_values($item));
        }
        return $data;
    },
    
    // List all fields from table (describe table structure)
    'schemaTable' => function ($index, $table){
        $retval = array();
        $type = self::getConfigDriver($index);
        $table = self::quote($index, $table);
        $raw_data = self::query($index, "DESCRIBE {$table}")->getAll();
        foreach($raw_data as $item)
        {
            $row = array();
            $row['TABLE'] = trim($table,'`');
            $row['FIELD'] = $item['Field'];
            $row['TYPE'] = $item['Type'];
            if(stristr ($item['Type'],'int')){
                $row['TYPE'] = 'integer';
            } elseif(stristr ($item['Type'],'enum')) {
                $row['TYPE'] = 'string';
                $row['VALUES'] = self::driverCall($type, 'parse_enum', $item['Type']);
            } elseif(preg_match('[decimal|float|double]',$item['Type'])){
                $row['TYPE'] = 'float';
            } elseif(preg_match('[var|char|text]',$item['Type'])){
                $row['TYPE'] = 'string';
                if( preg_match('@\((.+)\)@',$item['Type'], $tmp) )
                    $row['LENGTH'] = isset($tmp[1])? $tmp[1] : NULL;
            }
            $row['DEFAULT'] = $item['Default'];
            $row['PRIMARY'] = ($item['Key']==="PRI")? true : false;
            $row['NULLABLE'] = ($item['Null']==='YES')? true : false;
            $row['UNSIGNED'] = strpos($item['Type'],'unsigned')? true : false;
            $row['IDENTITY'] = stristr($item['Extra'],'AUTO_INCREMENT')? true : false;
            $retval[$item['Field']] = $row;
        }
        return $retval;
    },
    
    // Nasty function that parse enum values
    'parseEnum' => function($text){
        preg_match('/^enum\((.*)\)$/',$text, $tmp);
        $tmp = str_replace("'",'',$tmp[1]);
        return explode(',', $tmp);
    },
    
    // Return query num rows
    'numRows' => function ($index, $sql, $params){
        $sql_count = "SELECT count(*) FROM ({$sql}) AS tmp";
        return (int) self::query($index, $sql_count, $params)->getVal();
    },
    
    //truncate
    'truncate' => function($index, $table){
        self::query($index, " TRUNCATE TABLE ?", array($table));
    }

);
