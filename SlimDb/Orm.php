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
 * Orm
 * 
 * It's a micro ORM class.
 * Note: compound key are not supported!
 */

class Orm implements \Countable, \IteratorAggregate
{
    /** Object table */
    protected $tableObj = NULL;

    /** Array with row data */
    protected $data = NULL;

    /** Array with dirty row data */
    protected $dirty = array();

    /** bool flag */
    private $loadedFromDb = false;

    function __construct($tableObj, array $data = array(), $loadedFromDb = false )
    {
        $this->tableObj = $tableObj;
        //populate object
        $this->reset($data);
        $this->loadedFromDb = (bool) $loadedFromDb;
    }

    /**
     * Get the Table object
     * 
     * @return \SlimDb\Table
     */
    function Table()
    {
        return $this->tableObj;
    }
    
    /**
     * Get the number of fields in the row
     * 
     * @return int
     */
    public function count() {
        return count($this->toArray());
    }
    
    /**
     * Get an iterator for this object
     * 
     * @return \ArrayIterator
     */
    public function getIterator() {
        return new \ArrayIterator($this->toArray());
    }

    /**
     * Getter methods
     *
     * @param $key string
     * @return bool
     */
    public function __get( $key )
    {
        $data = $this->toArray();
        return isset($data[$key])? $data[$key] : null;
    }
    
    public function get( $key )
    {
        return $this->__get($key);
    }

    /**
     * Setter methods
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function __set( $key, $value )
    {
        if( !in_array($key, $this->tableObj->cols()) ) return $this;
        if( $this->loadedFromDb && $key===$this->pkName() ){
            //can't overrite the id
            return $this;
        }
        if( !array_key_exists($key, $this->data) OR $this->data[$key] !== $value ){
            $this->dirty[$key] = $value;
        }
        return $this;
    }
    
    /**
     * Populate an object
     */
    public function set()
    {
        $args = func_get_args();
        if( count($args)===2 ){
            return $this->__set($args[0], $args[1]);
        }
        if( count($args)===1 && is_array($args[0]) ){
            foreach($args[0] as $key=>$value){
                if(is_string($key) && !is_array($value)){
                    $this->__set($key, $value);
                }
            }
            return $this;
        }
        SlimDb::exception("Invalid arguments!", __METHOD__);
    }

    /**
     * Reset the object data (re-populate)
     *
     * @param array $data
     * @return bool
     */
    public function reset( $data = array() )
    {
        $this->dirty = array();
        $this->data = array();
        $this->loadedFromDb = false;
        if( !is_array($data) or empty($data) ){
            return $this;
        }
        foreach($data as $key=>$value){
            if( in_array($key, $this->tableObj->cols()) ){
                $this->data[$key] = $value;
            }
        }
        return $this;
    }

    /**
     * Save changes to db & reload the object
     */
    public function save()
    {
        if( empty($this->dirty) ) return true;
        $pkName = $this->pkName();
        $pkValue = '';
        if( !empty($this->data[$pkName])){
            $pkValue = $this->data[$pkName];
        } elseif( !empty($this->dirty[$pkName])){
            $pkValue = $this->dirty[$pkName];
        }
        if( $pkValue!=='' ){
            //lets see if there is a record in database
            if( $this->loadedFromDb || $this->tableObj->countById($pkValue) ){
                return $this->updateRecord($pkValue);
            }
        }
        $merged_data = $this->toArray();
        return $this->insertRecord($merged_data);
    }

    /**
     * Force an update & reload the object
     */
    public function update()
    {
        $pkName = $this->pkName();
        if(empty($this->data[$pkName]) || empty($this->dirty[$pkName])) return false;
        return $this->updateRecord($this->data[$pkName]);
    }

    private function updateRecord($pkValue)
    {
        $where[$this->pkName()] = $pkValue;
        $this->tableObj
            ->update($this->dirty)
            ->where($where)
            ->run();
        return $this->reload();
    }

    /**
     * Force an insert & reload the object
     */
    public function insert()
    {
        return $this->insertRecord($this->dirty);
    }

    private function insertRecord($data)
    {
        $this->tableObj
                ->insert($data)
                ->run();
        
        $pkName = $this->pkName();
        $id = empty($data[$pkName])? $this->tableObj->lastInsertId() : $data[$pkName];
        return $this->load($id);
    }

    /**
     * Should cache the statement for performance boost?
     * 
     * @param bool $bool
     * @return this
     */
    public function cacheStmt( $bool=true )
    {
        $this->tableObj->cacheStmt($bool);
        return $this;
    }
    
    /**
     * Reload object from db
     * 
     * @param mixed $id
     * @return this
     */
    public function load($id)
    {
        if( empty($id) ){
            SlimDb::exception("Invalid id value! ({$id})", __METHOD__);
        }
        $data = $this->tableObj
            ->firstById($id)
            ->toArray();
        if( $this->reset($data) ){
            $this->loadedFromDb = true;
        } else {
            $this->__set($this->pkName(), $id);
        }
        return $this;
    }
    
    /**
     * Reload object from db
     * 
     * @return this
     */
    public function reload()
    {
        $id = $this->get( $this->pkName() );
        $this->load( $id );
        return $this;
    }
    
    /**
     * Delete object from db
     */
    public function delete()
    {
        $pkName = $this->pkName();
        if( !isset($this->data[$pkName]) ){
            return false;
        }
        $where[$pkName] =  $this->data[$pkName];
        $this->tableObj
                ->where($where)
                ->delete()
                ->run();
        $this->reset();
    }

    /**
     * Get the object data as an array
     * @return array
     */
    public function toArray()
    {
        $cols = $this->tableObj->cols();
        $retval = array();
        foreach($cols as $field){
            //default value
            if( array_key_exists($field, $this->data) ){
                $retval[$field] = $this->data[$field];
            }
            //new dirty value
            if( array_key_exists($field, $this->dirty) ){
                $retval[$field] = $this->dirty[$field];
            }
        }
        return $retval;
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
        return $this->tableObj->pkName();
    }
    
    /**
     * Return primary key value
     * Note: compound key are not supported!
     *
     * @return mixed
     */
    public function pkValue()
    {
        return $this->get($this->pkName());
    }

    /**
     * Get table schema
     *
     * @return array
     */
    public function schema()
    {
        return $this->tableObj->schema();
    }

    /**
     * Get table name
     *
     * @return array
     */
    public function tableName()
    {
        return $this->tableObj->tableName();
    }

}
