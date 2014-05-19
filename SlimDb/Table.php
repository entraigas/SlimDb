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
    private $queryArgs = array();
    
    function __construct($connectionName, $table)
    {
        parent::__construct($connectionName);
        $this->table = $table;
        $this->reset_args();
    }
    
    /**
     * Reset $this->queryArgs array
     */
    private function reset_args()
    {
        $this->queryArgs = array();
        $this->queryArgs['table'] = $this->table;
        $this->queryArgs['cacheStmt'] = false;
    }
    
    /**
     * Create an insert prepared/parameterized statement
     *
     * @param array $params
     * @return array
     */
    private function buildInsert($args)
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
    private function buildUpdate($args)
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
    private function buildDelete($args)
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
    private function buildSelect($args)
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
            $sql .= $this->buildOrderBy($args['order']);
        }
        // LIMIT conditions
        if( isset($args['limit']) )
        {
            $sql .= $this->buildLimit($args['offset'], $args['limit']);
        }
        return array($sql, $params);
    }

    /**
     * Create the ORDER BY clause
     *
     * @param array $fields to order by
     * @return string
     */
    private function buildOrderBy($fields = NULL)
    {
        if( ! $fields) return;
        $sql = ' ORDER BY ';
        // Add each order clause
        if( !is_array($fields) ) $fields = array($fields);
        foreach($fields as $key => $value){
            if( is_int($key) )
                $sql  .= $this->quote($value) . " , ";
            else
                $sql  .= $this->quote($key) . " $value, ";
        }
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
    private function buildLimit($offset = 0, $limit = 0)
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
        $method =  strtolower( $this->queryArgs['method'] );
        if( $method=='insert' ||  $method=='update' ||  $method=='delete' )
        {
            $cacheStmt = (bool) $this->queryArgs['cacheStmt'];
            $extra = array('cacheStmt'=>$cacheStmt);
            $method = "build" . ucfirst($method);
            list($sql,$params) = $this->$method($this->queryArgs);
            $result = $this->query($sql, $params, $extra);
            $this->reset_args();
            return $result;
        }
        if( $method=='all' ||  $method=='row' ||  $method=='val' )
        {
            list($sql,$params) = $this->buildSelect($this->queryArgs);
            if( $method=='val'){
                $result = $this->query($sql, $params)->getVal();
            }
            if( $method=='row'){
                $result = $this->query($sql, $params)
                    ->setTable($this->table)
                    ->setCallback('getRow');
            }
            if( $method=='all' ){
                $result = $this->query($sql, $params)
                    ->setTable($this->table)
                    ->setCallback('getAll');
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
        $this->queryArgs['distinct'] = $bool;
        return $this;
    }
    
    /**
     * Enable/disable the cache Statemet in query
     */
    public function cacheStmt($bool = true)
    {
        $this->queryArgs['cacheStmt'] = $bool;
        return $this;
    }
    
    /**
     * Setup order by clause
     *
     * @param array $fields
     */
    public function fields($fields)
    {
        $this->queryArgs['columns'] = $fields;
        return $this;
    }
    
    /**
     * Setup order by clause
     *
     * @param array $fields
     */
    public function orderBy($fields)
    {
        $this->queryArgs['order'] = $fields;
        return $this;
    }
    
    /**
     * Setup limit clause
     *
     * @param array $fields
     */
    public function limit($limit, $offset = 0)
    {
        $this->queryArgs['limit'] = $limit;
        $this->queryArgs['offset'] = $offset;
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
        $this->queryArgs['method'] = 'all';
        $this->queryArgs['where'] = $where;
        $this->queryArgs['params'] = $params;
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
        $this->queryArgs['method'] = 'row';
        $this->queryArgs['where'] = $where;
        $this->queryArgs['params'] = $params;
        $this->queryArgs['limit'] = 1;
        $this->queryArgs['offset'] = 0;
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
        $this->queryArgs['method'] = 'val';
        $this->queryArgs['columns'] = 'count(*)';
        $this->queryArgs['where'] = $where;
        $this->queryArgs['params'] = $params;
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
        $this->queryArgs['method'] = 'update';
        $this->queryArgs['data'] = $data;
        $this->queryArgs['where'] = $where;
        $this->queryArgs['params'] = $params;
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
        $this->queryArgs['method'] = 'delete';
        $this->queryArgs['where'] = $where;
        $this->queryArgs['params'] = $params;
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
        $this->queryArgs['method'] = 'insert';
        $this->queryArgs['data'] = $data;
        return $this->run();
    }

    /**
     * Return column info.
     */
    public function schema()
    {
        return SlimDb::schema($this->connectionName, $this->table);
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

