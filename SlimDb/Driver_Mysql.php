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
    'init' => function($connectionName){
        // MySQL uses a non-standard column identifier
        self::_setWrapper($connectionName, '`%s`');
    },
    
    // Build a limit clause
    'limit' => function( $connectionName, $offset = 0, $limit = 0 ){
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
    'dbName' => function ($connectionName){
        return self::query($connectionName, "SELECT DATABASE();")->getVal();
    },
    
    // List all tables from database
    'schemaDb' => function ($connectionName){
        $tables = self::query($connectionName, "SHOW TABLES")->getAll();
        $data = array();
        foreach($tables as $item){
            $tmp = array_values($item); //Strict Standards: Only variables should be passed by reference
            $data[] = (string) array_shift($tmp);
        }
        return $data;
    },
    
    // List all fields from table (describe table structure)
    'schemaTable' => function ($connectionName, $table){
        $fn_parse_enum = function($text){
            preg_match('/^enum\((.*)\)$/',$text, $tmp);
            $tmp = str_replace("'",'',$tmp[1]);
            return explode(',', $tmp);
        };
        $retval = array();
        $table = self::quote($connectionName, $table);
        $raw_data = self::query($connectionName, "DESCRIBE {$table}")->getAll();
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
                $row['VALUES'] = $fn_parse_enum($item['Type']);
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
    
    // Return query num rows
    'rowCount' => function ($connectionName, $sql, $params, $statement){
        return (int) $statement->rowCount();
    },
    
    //truncate
    'truncate' => function($connectionName, $table){
        self::query($connectionName, " TRUNCATE TABLE ?", array($table));
    }

);
