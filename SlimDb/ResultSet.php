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
 * Wrapper around pdo statement.
 * Implements iterator and countable interfaces so you can loop 
 * through the results.
 */
class ResultSet  implements \Iterator, \Countable
{
    const AS_ARRAY = 0;
    const AS_OBJECT = 1;
    const AS_ORM = 2;

    /** String current db connection name index */
    private $connectionName = NULL;
    
    /** String current table */
    private $tableName = NULL;
    
    /**
     * Return data as [AS_ARRAY, AS_OBJECT, AS_ORM, 'myClass', ...]
     */
    private $returnAs = self::AS_ARRAY;

    /** PDO Statement Object current statement */
    private $statement = NULL;

    /** Array current query params of the statement */
    private $sqlParams = NULL;

    /** Integer total affected/returned rows by the query */
    private $rowCount = NULL;

    /** Object/array cursor current row */
    private $currentRow = NULL;

    /** Integer current cursor pointer */
    private $pointer = 0;

    public function __construct($connectionName, $statement, $sqlParams=null)
    {
        $this->connectionName = $connectionName;
        $this->statement = $statement;
        $this->sqlParams = $sqlParams;
        $this->rowCount = null;
    }

    /*
     * Required functions by Iterator
     */
    public function rewind()
    {
        $this->pointer = 0;
        $this->next();
    }

    public function valid()
    {
        if ($this->count()<=0) {
            return false;
        }
        return ($this->pointer <= $this->count());
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
     * Required function by Countable.
     * Calculate the affected/returned rows by the query
     *
     * @return integer
     */
    function count()
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
     * Set table name. Used by Table class.
     * 
     * @param $tableName string
     * @return ResultSet object
     */
    public function setTableName($tableName)
    {
        $this->tableName = $tableName;
        return $this;
    }

    /*
     * Configure return type. Check out "$this->returnAs" for details.
     * Used in ResultSet created by Table queries.
     */
    public function asArray()
    {
        $this->returnAs = self::AS_ARRAY;
        return $this;
    }

    public function asObject($className=null)
    {
        $this->returnAs = $className==null? self::AS_OBJECT : $className;
        return $this;
    }

    public function asOrm()
    {
        $this->returnAs = self::AS_ORM;
        return $this;
    }

    /**
     * Fetch a single column from a query.
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
     * @throws \Exeption
     * @return object|array|false
     */
    public function getRow()
    {
        $row = $this->statement->fetch(\PDO::FETCH_ASSOC, \PDO::FETCH_ORI_NEXT);
        if( ! is_array($row) ) return false;
        switch($this->returnAs){
            case ResultSet::AS_ARRAY:
                return $row;
            case ResultSet::AS_OBJECT:
                return (object) $row;
            case ResultSet::AS_ORM:
                if( $this->tableName!==null ){
                    $table = new Table($this->connectionName, $this->tableName);
                    return new Orm($table, $row, true);
                }
                return false;
            default: //custom object
                if( !is_string($this->returnAs) ){
                    throw new \Exeption("Invalid return type ({$this->returnAs})");
                }
                return new $this->returnAs($row);
        }
    }

    /**
     * Fetch all rows from db query and return them as an array
     *
     * @return array
     */
    public function getAll()
    {
        if($this->returnAs === ResultSet::AS_ARRAY){
            return $this->statement->fetchAll(\PDO::FETCH_ASSOC);
        }
        //casting to a particular class
        $data = array();
        while( $row = $this->getRow() ){
            $data[] = $row;
        }
        return $data;
    }

    /**
     * Call an object method in all objects from a result set
     * 
     * @param string $method
     * @param array $params
     * @return ResultSet object
    public function __call($method, $params = array()) {
        foreach($this as $object){
            if( !is_object($object) ){
                SlimDb::exception("Resultset must contain objects", __METHOD__);
            }
            call_user_func_array(array($object, $method), $params);
        }
        return $this;
    }
     */

    /**
     * Apply a user function to all objects in a result set.
     *
     * @param $fn
     * @internal param \SlimDb\Closure|string $function
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
