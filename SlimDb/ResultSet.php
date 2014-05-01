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
 * ResultSet
 * 
 * Wrapper arround pdo statement.
 * Implements iterator and countable interfaces so you can loop 
 * throught the results.
 */
class ResultSet  implements \Iterator, \Countable
{
    /** String current db connection name index */
    private $config_index = NULL;
    
    /** PDO Statement Object current statement */
    private $statement = NULL;
    
    /** Array current query params of the statement */
    private $sql_params = NULL;
    
    /** String current table */
    private $table = NULL;
    
    /** Integer total affected/returned rows by the query */
    private $rowCount = NULL;
    
    /** Object/array cursor current row */
    private $current_row = NULL;
    
    /** 
     * Return data as...
     * false = return as array (default behaviour)
     * true = return as TableRecord object
     * 'custom string' = return as 'custom string' object
     */
    private $as_object = false;
    
    public function __construct($config_index, $statement, $sql_params=null)
    {
        $this->config_index = $config_index;
        $this->statement = $statement;
        $this->sql_params = $sql_params;
        $this->rowCount = null;
        $this->as_object = false;
    }

    /**
     * Set table name.
     * 
     * @param $table string
     * @return ResultSet object
     */
    public function setTable($table)
    {
        $this->table = $table;
        return $this;
    }
    
    /**
     * Configure the default return type as array
     * 
     * @return ResultSet object
     */
    public function asArray(){
        $this->as_object = false;
        return $this;
    }
    
    /**
     * Configure the default return type as an object
     * 
     * @param $class class name
     * @return ResultSet object
     */
    public function asObject($class=true){
        $this->as_object = $class;
        return $this;
    }
    
    /*
     * Required functions by Iterator
     */
    public function rewind()
    {
        $this->pointer = 0;
        $this->current_row = $this->getRow();
    }

    public function valid()
    {
        if ($this->rowCount()<=0) {
            return false;
        }
        return ($this->pointer < $this->rowCount());
    }

    public function current()
    {
        return $this->current_row;
    }

    public function key()
    {
        return $this->pointer;
    }

    public function next()
    {
        $this->pointer++;
        $this->current_row = $this->getRow();
    }

    /**
     * Required function by Countable
     *
     * @return integer
     */
    function count()
    {
        return $this->get_row_count();
    }
    
    /**
     * Fetch a single value from db query.
     * Useful for "select count(*)" queries.
     *
     * @param int $column the optional column to return
     * @return mixed or NULL
     */
    public function getVal($column = 0)
    {
        return $this->statement->fetchColumn($column);
    }
    
    /**
     * Fetch a single row from db query
     *
     * @return object|array
     */
    public function getRow()
    {
        $as_object = $this->as_object;
        $row = $this->statement->fetch(\PDO::FETCH_ASSOC, \PDO::FETCH_ORI_NEXT);
        if($row===false) return false;
        if( $as_object===false ) return $row;
        if( $as_object===true ){
            if( $this->table===null ) return (object) $row;
            $table = new Table($this->config_index, $this->table);
            return new TableRecord($table, $row);
        }
        //casting to a particular class
        $class = $as_object;
        return new $class($row);
    }
    
    /**
     * Fetch all rows from db query and return them as an array
     *
     * @param string $sql query to run
     * @param array $params the optional prepared query params
     * @return array array of objects|arrays
     */
    public function getAll()
    {
        $as_object = $this->as_object;
        if($as_object === false)
            return $this->statement->fetchAll(\PDO::FETCH_ASSOC);
        //casting to a particular class
        $data = array();
        foreach($this as $row){
            $data[] = $row;
        }
        return $data;
    }
    
    /**
     * Calculate the affected/returned rows by the query
     */
    private function get_row_count()
    {
        if( $this->rowCount !== null) return $this->rowCount;
        $this->rowCount = 0;
        $sql = $this->statement->queryString;
        if( preg_match('#\s*(INSERT|UPDATE|DELETE|REPLACE)\s+#i', $sql) ){
            //get the affected rows
            $this->rowCount = $this->statement->rowCount();
        } else {
            //get the returned rows
            $this->rowCount = SlimDb::driverCall($this->config_index, 'numRows', $sql, $this->sql_params);
        }
        return $this->rowCount;
    }
    
    /**
     * Get the affected/returned rows by the query
     *
     * @return integer
     */
    public function rowCount()
    {
        return $this->get_row_count();
    }
    
    /**
     * Call an object method in all objects from a result set
     * 
     * @param string $method
     * @param array $params
     * @return ResultSet object
     */
    public function __call($method, $params = array()) {
        foreach($this as $object){
            if( !is_object($object) ){
                SlimDb::exception("Resultset must contain objects", __METHOD__);
            }
            call_user_func_array(array($object, $method), $params);
        }
        return $this;
    }
    
    /**
     * Apply a user function to all objects in a result set.
     * 
     * @param Closure|string $function
     * @param array $params
     * @return ResultSet object
     */
    public function apply($fn)
    {
        foreach($this as $key=>$object){
            call_user_func_array($fn, array($object, $key));
        }
        return $this;
    }
    
    /**
     * Return the last insert id (of an insert query)
     *
     * @return integer
     */
    public function lastInsertId()
    {
        return SlimDb::lastInsertId($this->config_index);
    }
    
}
