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
 * Custom methods for Sqlite databases
 */

return array(
    // Initialize default driver settings after Database constructor
    'init' => function($connectionName){
        self::_setWrapper($connectionName, '[%s]');
    },
    
    // Get database filename
    'dbName' => function ($connectionName){
        $row = self::query($connectionName, "PRAGMA database_list;")->getRow();
        return basename( $row["file"] );
    },
    
    // List all tables.
    'schemaDb' => function ($connectionName){
        $tables = self::query($connectionName, "SELECT * FROM sqlite_master WHERE type='table'")->getAll();
        $data = array();
        foreach($tables as $item){
            $data[] = (string) $item['name'];
        }
        return $data;
    },
    
    // Describe table structure.
    'schemaTable' => function ($connectionName, $table){
        $retval = array();
        $table = self::quote($connectionName, $table);
        $raw_data = self::query($connectionName, "PRAGMA table_info({$table})")->getAll();
        foreach($raw_data as $item)
        {
            $row = array();
            $row['TABLE'] = trim($table,'[]');
            $row['FIELD'] = $item['name'];
            $row['TYPE'] = $item['type'];
            if(stristr ($item['type'],'INT')){
                $row['TYPE'] = 'integer';
            } elseif(stristr ($item['type'],'BOOL')){
                $row['TYPE'] = 'bool';
            } elseif(preg_match('[FLOA|DOUB|REAL|NUME|DECI]',$item['type'])){
                $row['TYPE'] = 'float';
            } elseif(preg_match('[CLOB|CHAR|TEXT]',$item['type'])){
                $row['TYPE'] = 'string';
                if( preg_match('@\((.+)\)@',$item['type'], $tmp) )
                    $row['LENGTH'] = isset($tmp[1])? $tmp[1] : NULL;
            }
            $row['DEFAULT'] = $item['dflt_value'];
            $row['PRIMARY'] = (intval($item['pk']))? true : false;
            $row['NULLABLE'] = (intval($item['notnull']))? false : true;
            //$row['IDENTITY'] = false; //todo...
            $retval[$item['name']] = $row;
        }
        return $retval;
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
    
    //return query num rows
    'rowCount' => function ($connectionName, $sql, $params, $statement){
        $sql_count = "SELECT count(*) FROM ({$sql}) AS tmp";
        return (int) self::query($connectionName, $sql_count, $params)->getVal();
    },
    
    //truncate
    'truncate' => function($connectionName, $table){
        //delete all records
        self::query($connectionName, "DELETE FROM ?", array($table));
        //reset autoincrement/identity field
        self::query($connectionName, "DELETE FROM SQLITE_SEQUENCE WHERE name = ?;", array($table));
    }
    
);
