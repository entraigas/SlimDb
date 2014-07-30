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
    protected $tableName = NULL;

    /** Array that holds query arguments */
    private $queryArgs = array();

    function __construct($connectionName, $tableName)
    {
        parent::__construct($connectionName);
        $this->tableName = $tableName;
        $this->_reset_args();
    }

    /**
     * Factory method for ORM object
     * @param array @data initial data for object (marked dirty)
     * @return \SlimDb\Orm
     */
    public function Orm(array $data=array())
    {
        $object = new Orm($this);
        if(! empty($data) ) $object->set($data);
        return $object;
    }

    /**
     * Reset $this->queryArgs array
     */
    private function _reset_args()
    {
        $this->queryArgs = array();
        $this->queryArgs['table'] = $this->tableName;
        $this->queryArgs['method'] = 'query';
        $this->queryArgs['cacheStmt'] = false;
    }

    /**
     * Create an insert prepared/parameterized statement
     *
     * @param array $args
     * @return array
     */
    private function _buildInsert($args)
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
     * @param array $args
     * @return array
     */
    private function _buildUpdate($args)
    {
        if( !is_array($args['data']) ){
            SlimDb::exception("Invalid data argument. Must be an array!", __METHOD__);
        }
        $table = $this->quote($args['table']);
        $sql = "UPDATE {$table}";
        if(isset($args['join'])){
            $sql .= ' ' . implode(" ", $args['join']);
        }
        foreach(array_keys($args['data']) as $item)
        {
            $columns[] = $this->quote($item) . ' = ?';
        }
        $columns = implode(', ', $columns);
        $sql .= " SET {$columns}";
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
     * @param array $args
     * @return array
     */
    private function _buildDelete($args)
    {
        $table = $this->quote($args['table']);
        $sql = "DELETE FROM {$table}";
        if(isset($args['join'])){
            $sql .= ' ' . implode(" ", $args['join']);
        }
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
     * @param array $args
     * @return array
     */
    private function _buildSelect($args)
    {
        if( !isset($args['columns']) ) $args['columns'] = '*';
        $sql =  isset($args['distinct'])? 'SELECT DISTINCT' : 'SELECT';
        $sql .= sprintf(" %s FROM %s"
            , $this->quoteColumns($args['columns'])
            , $this->quote($args['table'])
        );
        if(isset($args['join'])){
            $sql .= ' ' . implode(" ", $args['join']);
        }
        // Process WHERE conditions
        $params = array();
        if( isset($args['where']) ){
            $sql .= " WHERE {$args['where']}";
            $params = $args['params'];
        }
        // ORDER BY sorting
        if( isset($args['order']) ){
            $sql .= $this->_buildOrderBy($args['order']);
        }
        // LIMIT conditions
        if( isset($args['limit']) )
        {
            $sql .= $this->_buildLimit($args['offset'], $args['limit']);
        }
        return array($sql, $params);
    }

    /**
     * Create the ORDER BY clause
     *
     * @param array $fields to order by
     * @return string
     */
    private function _buildOrderBy($fields = NULL)
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
     * @param int $offset
     * @param int $limit
     * @return string
     */
    private function _buildLimit($offset = 0, $limit = 0)
    {
        return $this->driverCall('limit', $offset, $limit);
    }

    /**
     * Build & run the sql query
     *
     * @return ResultSet
     */
    public function run()
    {
        $method =  strtolower( $this->queryArgs['method'] );
        if( $method=='insert' ||  $method=='update' ||  $method=='delete' )
        {
            $cacheStmt = (bool) $this->queryArgs['cacheStmt'];
            $extra = array('cacheStmt'=>$cacheStmt, 'debug'=>true);
            $method = "_build" . ucfirst($method);
            list($sql,$params) = $this->$method($this->queryArgs);
            $resultSet = $this->query($sql, $params, $extra);
            $this->_reset_args();
            return $resultSet;
        }
        if( $method=='query' ||  $method=='value' )
        {
            list($sql,$params) = $this->_buildSelect($this->queryArgs);
            if( $method=='value'){
                $resultSet = $this->query($sql, $params)->getVal();
            }
            if( $method=='query' ){
                $resultSet = $this->query($sql, $params)
                    ->setTableName($this->tableName)
                    ->asArray();
            }
            $this->_reset_args();
            return $resultSet;
        }
        return false;
    }

    /**
     * Enable/disable the 'distinct' in select
     *
     * @param bool $bool
     * @return $this
     */
    public function distinct($bool = true)
    {
        $this->queryArgs['distinct'] = $bool;
        return $this;
    }

    /**
     * Setup select columns
     *
     * @param array $fields
     * @return $this
     */
    public function select($fields)
    {
        $this->queryArgs['columns'] = $fields;
        return $this;
    }

    /*
     * Join functions
     */
    private function _buildJoin($type, $table, $clause=null)
    {
        $sql = sprintf("%s %s", $type, $this->quote($table));
        if($clause){
            $tmp = explode("=", $clause);
            foreach($tmp as $key=>$item){
                $tmp[$key] = $this->quote($item);
            }

            $sql.=" ON " . implode(" = ", $tmp);
        }
        $this->queryArgs['join'][$table] = $sql;
    }

    public function join($table, $clause=null)
    {
        $this->_buildJoin("INNER JOIN", $table, $clause);
        return $this;
    }

    public function lJoin($table, $clause=null)
    {
        $this->_buildJoin("LEFT JOIN", $table, $clause);
        return $this;
    }

    public function rJoin($table, $clause=null)
    {
        $this->_buildJoin("RIGHT JOIN", $table, $clause);
        return $this;
    }

    /**
     * Setup where condition
     *
     * @param array $where
     * @param array $params
     * @return $this
     */
    public function where($where = NULL, $params = NULL)
    {
        $this->queryArgs['where'] = $where;
        $this->queryArgs['params'] = $params;
        return $this;
    }

    /**
     * Setup order by clause
     *
     * @param array $fields
     * @return $this
     */
    public function orderBy($fields)
    {
        $this->queryArgs['order'] = $fields;
        return $this;
    }

    /**
     * Setup limit clause
     *
     * @param int $limit
     * @param int $offset
     * @return $this
     */
    public function limit($limit, $offset = 0)
    {
        $this->queryArgs['limit'] = $limit;
        $this->queryArgs['offset'] = $offset;
        return $this;
    }

    /**
     * Run a select query and return the first record
     *
     * @param array $where
     * @param array $params
     * @return array|object
     */
    public function first($where = NULL, $params = NULL)
    {
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
     * @param array $params
     * @return value
     */
    public function count($where = NULL, $params = NULL)
    {
        $this->queryArgs['method'] = 'value';
        $this->queryArgs['columns'] = 'count(*)';
        $this->queryArgs['where'] = $where;
        $this->queryArgs['params'] = $params;
        return $this->run();
    }

    /**
     * Creates an UPDATE statement using the values provided.
     *
     * @param array $data
     * @return $this
     */
    public function update($data)
    {
        $this->queryArgs['method'] = 'update';
        $this->queryArgs['data'] = $data;
        return $this;
    }

    /**
     * Creates a DELETE statement using the values provided
     *
     * @return $this
     */
    public function delete()
    {
        $this->queryArgs['method'] = 'delete';
        return $this;
    }

    /**
     * Creates an INSERT statement using the values provided.
     *
     * @param array $data
     * @return $this
     */
    public function insert(array $data)
    {
        $this->queryArgs['method'] = 'insert';
        $this->queryArgs['data'] = $data;
        return $this;
    }

    /**
     * Return column info.
     *
     * @return array
     */
    public function schema()
    {
        return SlimDb::schema($this->connectionName, $this->tableName);
    }

    /**
     * Return column name.
     *
     * @return array
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
     *
     * @return string
     */
    public function name()
    {
        return $this->tableName;
    }

    /**
     * Enable/disable the cache Statement in query
     */
    public function cacheStmt($bool = true)
    {
        $this->queryArgs['cacheStmt'] = $bool;
        return $this;
    }

}
