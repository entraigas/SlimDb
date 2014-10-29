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
    /** @var array firstById() cache. Store PDO ResultSet objects */
    static private $cache = array();

    static public function debug(){
        return self::$cache;
    }

    /** String table name */
    protected $tableName = NULL;

    /** string primary key name*/
    protected $pkName = null;

    /** Array that holds query arguments */
    private $queryArgs = array();

    function __construct($connectionName, $tableName)
    {
        parent::__construct($connectionName);
        $this->tableName = $tableName;
        $this->_reset_args();
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
        $this->queryArgs['params'] = array();
    }

    private function _sqlInsert($args)
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

    private function _sqlUpdate($args)
    {
        if( !is_array($args['data']) ){
            SlimDb::exception("Invalid data argument. Must be an array!", __METHOD__);
        }
        $table = $this->quote($args['table']);
        $sql = "UPDATE {$table}";
        $params = array();
        if(isset($args['join'])){
            foreach($args['join'] as $join){
                $sql .= ' ' . $join['sql'];
                $params = $this->_addParameters($params, $join['params']);
            }
        }
        foreach(array_keys($args['data']) as $item)
        {
            $columns[] = $this->quote($item) . ' = ?';
        }
        $columns = implode(', ', $columns);
        $sql .= " SET {$columns}";
        $params = $this->_addParameters($params, array_values($args['data']));
        // Process WHERE conditions
        if( isset($args['where']['sql']) ){
            $sql .= " WHERE {$args['where']['sql']}";
            $params = $this->_addParameters($params, $args['where']['params']);
        }
        return array($sql, $params);
    }

    private function _sqlDelete($args)
    {
        $table = $this->quote($args['table']);
        $params = array();
        $sql = "DELETE FROM {$table}";
        if(isset($args['join'])){
            foreach($args['join'] as $join){
                $sql .= ' ' . $join['sql'];
                $params = $this->_addParameters($params, $join['params']);
            }
        }
        // Process WHERE conditions
        if( isset($args['where']) ){
            $sql .= " WHERE {$args['where']['sql']}";
            $params = $this->_addParameters($params, $args['where']['params']);
        }
        return array($sql, $params);
    }

    private function _sqlSelect($args)
    {
        $params = array();
        if( !isset($args['columns']) ) $args['columns'] = '*';
        $sql =  isset($args['distinct'])? 'SELECT DISTINCT' : 'SELECT';
        $sql .= sprintf(" %s FROM %s"
            , $this->_parseFields($args['columns'])
            , $this->quote($args['table'])
        );
        if(isset($args['join'])){
            foreach($args['join'] as $join){
                $sql .= ' ' . $join['sql'];
                $params = $this->_addParameters($params, $join['params']);
            }
        }
        // Process WHERE conditions
        if( isset($args['where']['sql']) ){
            $sql .= " WHERE {$args['where']['sql']}";
            $params = $this->_addParameters($params, $args['where']['params']);
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

    private function _addParameters($base, $new)
    {
        foreach($new as $item){
            $base[] = $item;
        }
        return $base;
    }

    //private function _buildJoin($type, $table, $clause=null, $params=null)
    private function _buildJoin()
    {
        $args = func_get_args();
        if(count($args)<3) return;

        $type = array_shift($args);
        $table = array_shift($args);
        list($clause, $params) = call_user_func_array( array(&$this, '_parseClause'), $args);
        $sql = sprintf("%s %s ON %s", $type, $this->quote($table), $clause);

        $this->queryArgs['join'][$table]['sql'] = $sql;
        $this->queryArgs['join'][$table]['params'] = $params;
    }

    private function _buildOrderBy($fields = NULL)
    {
        $fields = $this->_parseFields($fields);
        if( empty($fields) ) return '';
        return " ORDER BY {$fields}";
    }

    private function _buildLimit($offset = 0, $limit = 0)
    {
        return $this->driverCall('limit', $offset, $limit);
    }

    private function _parseFields($fields)
    {
        if( empty($fields) || $fields==='*' ){
            return $fields;
        }

        if( !is_array($fields) ){
            return $this->quoteColumns($fields);
        }

        $sql = '';
        foreach($fields as $key => $value){
            if( is_int($key) )
                $sql  .= $this->quote($value) . " , ";
            else
                $sql  .= $this->quote($key) . " $value, ";
        }
        // Remove ending ", "
        return substr($sql, 0, -2);
    }

    private function _parseClause()
    {
        $args = func_get_args();

        if(count($args)==0 || empty($args[0])){
            //error!
            return array('', array());
        }

        //one array param
        if(count($args)==1 && is_array($args[0])){
            $array = $params = array();
            foreach($args[0] as $field=>$clause){
                if(is_int($field)) continue;
                $quoted_field = $this->quote($field);
                $array[] = strstr('%',$clause)? "{$quoted_field} LIKE ?" : "{$quoted_field} = ?";
                $params[] = $clause;
            }
            $sql = implode(' AND ', $array);
            return array($sql, $params);
        }

        //backward compatible call: where($sql, array(1,'jhon'))
        if(count($args)==2 && is_string($args[0]) && is_array($args[1])){
            return array($args[0], $args[1]);
        }

        //first args is the sql, then the params
        $sql = $args[0];
        $params = array();
        if(count($args)>1){
            array_shift($args);
            $params = $args;
        }
        return array($sql, $params);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Build & run the sql query
     *
     * @return ResultSet
     */
    public function run()
    {
        $method =  strtolower( $this->queryArgs['method'] );
        $cacheStmt = (bool) $this->queryArgs['cacheStmt'];
        $extra = array('cacheStmt'=>$cacheStmt);
        if( $method=='insert' ||  $method=='update' ||  $method=='delete' )
        {
            $method = "_sql" . ucfirst($method);
            list($sql,$params) = $this->$method($this->queryArgs);
            $resultSet = $this->query($sql, $params, $extra);
            $this->_reset_args();
            return $resultSet;
        }
        if( $method=='query' ||  $method=='value' )
        {
            list($sql,$params) = $this->_sqlSelect($this->queryArgs);
            if( $method=='value'){
                $resultSet = $this->query($sql, $params)->getVal();
            }
            if( $method=='query' ){
                $resultSet = $this->query($sql, $params, $extra)
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
    public function join()
    {
        $args = func_get_args();
        if(count($args)<2) return;
        array_unshift($args, "INNER JOIN");
        call_user_func_array( array(&$this, '_buildJoin'), $args);
        return $this;
    }

    public function lJoin($table, $clause=null)
    {
        $args = func_get_args();
        if(count($args)<2) return;
        array_unshift($args, "LEFT JOIN");
        call_user_func_array( array(&$this, '_buildJoin'), $args);
        return $this;
    }

    public function rJoin($table, $clause=null)
    {
        $args = func_get_args();
        if(count($args)<2) return;
        array_unshift($args, "RIGHT JOIN");
        call_user_func_array( array(&$this, '_buildJoin'), $args);
        return $this;
    }

    /**
     * Setup where condition
     *
     * examples:
     * where("id=?" ,1)
     * where(array('id'=>1))
     * where("id>=? OR id<=?", 1, 10)
     * where("id>=? OR id<=?", array(1,10))
     *
     * @return $this
     */
    public function where()
    {
        list($sql, $params) = call_user_func_array( array(&$this, '_parseClause'), func_get_args());
        if(!empty($sql)){
            $this->queryArgs['where'] = array(
                'sql' => $sql,
                'params' => $params
            );

        }
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
     * @return ResultSet object
     */
    public function first()
    {
        call_user_func_array( array(&$this, 'where'), func_get_args());
        $this->queryArgs['limit'] = 1;
        $this->queryArgs['offset'] = 0;
        return $this->run();
    }

    /**
     * Run a select query and return the record
     *
     * @param int|string $id primary key value
     * @return ORM object
     */
    public function firstById($id)
    {
        if( isset(self::$cache[$this->tableName][$id])){
            return self::$cache[$this->tableName][$id];
        }
        $where[$this->pkName()] = $id;
        self::$cache[$this->tableName][$id] =  $this->first($where)->asOrm()->getRow();
        return self::$cache[$this->tableName][$id];
    }

    /**
     * Run a "select count(*)" and return the value
     *
     * @param array $where
     * @param array $params
     * @return int
     */
    public function count()
    {
        $this->queryArgs['method'] = 'value';
        $this->queryArgs['columns'] = 'count(*)';
        call_user_func_array( array(&$this, 'where'), func_get_args());
        return (int) $this->run();
    }

    /**
     * Run a "select count(*) from xxx where id=?" query to check if the record exist
     *
     * @param int|string $id primary key value
     * @return int
     */
    public function countById($id)
    {
        $where[$this->pkName()] = $id;
        return $this->count($where);
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
     * @param string field optional field name
     * @return array
     */
    public function schema($field=null)
    {
        return SlimDb::schema($this->connectionName, $this->tableName, $field);
    }

    /**
     * Return columns name.
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
    public function tableName()
    {
        return $this->tableName;
    }

    /**
     * Return table name.
     *
     * @return string
     */
    public function fullName()
    {
        return sprintf("%s.%s", parent::dbName(), $this->tableName);
    }

    /**
     * Return primary key field name
     * Note: compound key are not supported!
     *
     * @throws \Exception
     * @return string
     */
    public function pkName()
    {
        if( $this->pkName !== null ){
            return $this->pkName;
        }
        $schema = $this->schema();
        foreach($schema as $col){
            if( $col['PRIMARY'] === true ){
                $this->pkName = $col['FIELD'];
                return $this->pkName;
            }
        }
        throw new \Exception( __CLASS__ ." Error: could not find Primary Key for table: {$this->tableName}");
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
