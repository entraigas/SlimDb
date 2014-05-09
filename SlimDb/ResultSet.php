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
    private $connectionName = NULL;
    
    /** PDO Statement Object current statement */
    private $statement = NULL;
    
    /** Array current query params of the statement */
    private $sqlParams = NULL;
    
    /** String current table */
    private $table = NULL;
    
    /** Integer total affected/returned rows by the query */
    private $rowCount = NULL;
    
    /** Object/array cursor current row */
    private $currentRow = NULL;
    
    /** 
     * Return data as...
     * false = return as array (default behaviour)
     * true = return as TableRecord object
     * 'custom string' = return as 'custom string' object
     */
    private $asObject = false;
    
    public function __construct($connectionName, $statement, $sqlParams=null)
    {
        $this->connectionName = $connectionName;
        $this->statement = $statement;
        $this->sqlParams = $sqlParams;
        $this->rowCount = null;
        $this->asObject = false;
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
        $this->asObject = false;
        return $this;
    }
    
    /**
     * Configure the default return type as an object
     * 
     * @param $class class name
     * @return ResultSet object
     */
    public function asObject($class=true){
        $this->asObject = $class;
        return $this;
    }
    
    /*
     * Required functions by Iterator
     */
    public function rewind()
    {
        $this->pointer = 0;
        $this->currentRow = $this->getRow();
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
        return $this->currentRow;
    }

    public function key()
    {
        return $this->pointer;
    }

    public function next()
    {
        $this->pointer++;
        $this->currentRow = $this->getRow();
    }

    /**
     * Required function by Countable
     *
     * @return integer
     */
    function count()
    {
        return $this->getRowCount();
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
        $asObject = $this->asObject;
        $row = $this->statement->fetch(\PDO::FETCH_ASSOC, \PDO::FETCH_ORI_NEXT);
        if($row===false) return false;
        if( $asObject===false ) return $row;
        if( $asObject===true ){
            if( $this->table===null ) return (object) $row;
            $table = new Table($this->connectionName, $this->table);
            return new TableRecord($table, $row);
        }
        //casting to a particular class
        $class = $asObject;
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
        $asObject = $this->asObject;
        if($asObject === false)
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
    private function getRowCount()
    {
        if( $this->rowCount !== null) return $this->rowCount;
        $this->rowCount = 0;
        $sql = $this->statement->queryString;
        if( preg_match('#\s*(INSERT|UPDATE|DELETE|REPLACE)\s+#i', $sql) ){
            //get the affected rows
            $this->rowCount = $this->statement->rowCount();
        } else {
            //get the returned rows
            $this->rowCount = SlimDb::driverCall($this->connectionName, 'numRows', $sql, $this->sqlParams);
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
        return $this->getRowCount();
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
        return SlimDb::lastInsertId($this->connectionName);
    }
    
}
