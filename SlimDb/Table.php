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

namespace SlimDb;

/**
 * Table
 *
 * Handle common queries in single tables (select, insert, update, 
 * delete).
 */

class Table extends Database
{
    /** String table name */
    protected $table = NULL;
    
    /** Array that holds query arguments */
    private $query_args = array();
    
    function __construct($config_index, $table)
    {
        parent::__construct($config_index);
        $this->table = $table;
        $this->reset_args();
    }
    
    /**
     * Reset $this->query_args array
     */
    private function reset_args()
    {
        $this->query_args = array();
        $this->query_args['table'] = $this->table;
        $this->query_args['cacheStmt'] = false;
    }
    
    /**
     * Create an insert prepared/parameterized statement
     *
     * @param array $params
     * @return array
     */
    private function build_insert($args)
    {
        if( !is_array($args['data']) ){
            SlimDb::exception("Invalid data argument. Must be an array!", __METHOD__);
        }
        $table = $this->quote($args['table']);
        $columns = $this->quoteColumns(array_keys($args['data']));
        $place_holders = rtrim(str_repeat('?, ', count($args['data'])), ', ');
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$place_holders});";
        return array($sql, array_values($args['data']));
    }

    /**
     * Create an update prepared/parameterized statement
     *
     * @param array $params
     * @return array
     */
    private function build_update($args)
    {
        if( !is_array($args['data']) ){
            SlimDb::exception("Invalid data argument. Must be an array!", __METHOD__);
        }
        $table = $this->quote($args['table']);
        foreach(array_keys($args['data']) as $item)
        {
            $columns[] = $this->quote($item) . ' = ?';
        }
        $columns = implode(', ', $columns);
        $sql = "UPDATE {$table} SET {$columns}";
        $params = array_values($args['data']);
        // Process WHERE conditions
        if( isset($args['where']) ){
            $sql .= " WHERE {$args['where']}";
            $params = array_merge(array_values($args['data']), $args['params']);
        }
        return array($sql, $params);
    }
    
    /**
     * Create a delete prepared/parameterized statement
     *
     * @param array $params
     * @return array
     */
    private function build_delete($args)
    {
        $table = $this->quote($args['table']);
        $sql = "DELETE FROM {$table}";
        $params = array();
        // Process WHERE conditions
        if( isset($args['where']) ){
            $sql .= " WHERE {$args['where']}";
            $params = $args['params'];
        }
        return array($sql, $params);
    }
    
    /**
     * Create a basic, single-table SQL prepared/parameterized statement
     *
     * @param array $params
     * @return array
     */
    private function build_select($args)
    {
        if( !isset($args['columns']) ) $args['columns'] = '*';
        $sql =  isset($args['distinct'])? 'SELECT DISTINCT' : 'SELECT';
        $sql .= sprintf(" %s FROM %s"
            , $this->quoteColumns($args['columns'])
            , $this->quote($args['table'])
        );
        // Process WHERE conditions
        $params = array();
        if( isset($args['where']) ){
            $sql .= " WHERE {$args['where']}";
            $params = $args['params'];
        }
        // ORDER BY sorting
        if( isset($args['order']) ){
            $sql .= $this->build_order_by($args['params']);
        }
        // LIMIT conditions
        if( isset($args['limit']) )
        {
            $sql .= $this->build_limit($args['offset'], $args['limit']);
        }
        return array($sql, $params);
    }

    /**
     * Create the ORDER BY clause
     *
     * @param array $fields to order by
     * @return string
     */
    private function build_order_by($fields = NULL)
    {
        if( ! $fields) return;
        $sql = ' ORDER BY ';
        // Add each order clause
        foreach($fields as $key => $value) $sql .= $this->quote($key) . " $value, ";
        // Remove ending ", "
        return substr($sql, 0, -2);
    }

    /**
     * Create the LIMIT clause
     *
     * @param array $offset
     * @param array $limit
     * @return string
     */
    private function build_limit($offset = 0, $limit = 0)
    {
        return $this->driverCall('limit', $offset, $limit);
    }

    /**
     * Private function that build & run the sql query
     *
     * @return mixed
     */
    private function run()
    {
        $method = $this->query_args['method'];
        if( $method=='insert' ||  $method=='update' ||  $method=='delete' )
        {
            $cacheStmt = (bool) $this->query_args['cacheStmt'];
            $extra = array('cacheStmt'=>$cacheStmt);
            $method = "build_{$method}";
            list($sql,$params) = $this->$method($this->query_args);
            $result = $this->query($sql, $params, $extra);
            $this->reset_args();
            return $result;
        }
        if( $method=='all' ||  $method=='row' ||  $method=='val' )
        {
            list($sql,$params) = $this->build_select($this->query_args);
            if( $method=='val'){
                $result = $this->query($sql, $params)->getVal();
            }
            if( $method=='row'){
                $result = $this->query($sql, $params)
                    ->setTable($this->table)
                    ->asObject(true)
                    ->getRow();
            }
            if( $method=='all' ){
                $result = $this->query($sql, $params)
                    ->setTable($this->table)
                    ->asObject();
            }
            $this->reset_args();
            return $result;
        }
        return false;
    }
    
    /**
     * Enable/disable the 'distinct' in select
     */
    public function distinct($bool = true)
    {
        $this->query_args['distinct'] = $bool;
        return $this;
    }
    
    /**
     * Enable/disable the cache Statemet in query
     */
    public function cacheStmt($bool = true)
    {
        $this->query_args['cacheStmt'] = $bool;
        return $this;
    }
    
    /**
     * Run a select and return an array of objects
     *
     * @param array $where
     * @return array of ojects
     */
    public function find($where = NULL, $params = NULL)
    {
        $this->query_args['method'] = 'all';//'ResultSet';
        $this->query_args['where'] = $where;
        $this->query_args['params'] = $params;
        return $this->run();
    }
    
    /**
     * Run a select and return a single record (as an object)
     *
     * @param array $where
     * @return object
     */
    public function first($where = NULL, $params = NULL)
    {
        $this->query_args['method'] = 'row';
        $this->query_args['where'] = $where;
        $this->query_args['params'] = $params;
        $this->query_args['limit'] = 1;
        $this->query_args['offset'] = 0;
        return $this->run();
    }
    
    /**
     * Run a "select count(*)" and return the value
     *
     * @param array $where
     * @return value
     */
    public function count($where = NULL, $params = NULL)
    {
        $this->query_args['method'] = 'val';
        $this->query_args['columns'] = 'count(*)';
        $this->query_args['where'] = $where;
        $this->query_args['params'] = $params;
        return $this->run();
    }
    
    /**
     * Creates and runs an UPDATE statement using the values provided.
     *
     * @param array $data the column => value pairs
     * @return Result object
     */
    public function update($data, $where = NULL, $params = NULL)
    {
        $this->query_args['method'] = 'update';
        $this->query_args['data'] = $data;
        $this->query_args['where'] = $where;
        $this->query_args['params'] = $params;
        return $this->run();
    }

    /**
     * Creates and runs a DELETE statement using the values provided
     *
     * @param array $where array of column => $value indexes
     * @return Result object
     */
    public function delete($where = NULL, $params = NULL)
    {
        $this->query_args['method'] = 'delete';
        $this->query_args['where'] = $where;
        $this->query_args['params'] = $params;
        return $this->run();
    }
    
    /**
     * Creates and runs an INSERT statement using the values provided.
     *
     * @param array $data the column => value pairs
     * @return Result object
     */
    public function insert(array $data)
    {
        $this->query_args['method'] = 'insert';
        $this->query_args['data'] = $data;
        return $this->run();
    }

    /**
     * Return column info.
     */
    public function schema()
    {
        return SlimDb::schema($this->config_index, $this->table);
    }
    
    /**
     * Return column name.
     */
    public function cols()
    {
        $return = array();
        $data = $this->schema();
        foreach($data as $item)
        {
            $return[] = $item['FIELD'];
        }
        return $return;
    }

    /**
     * Return table name.
     */
    public function name()
    {
        return $this->table;
    }
}

