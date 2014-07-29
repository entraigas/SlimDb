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
    protected $saved = true;

    function __construct($tableObj, array $data = array() )
    {
        $this->tableObj = $tableObj;
        //populate object and mark as saved
        $this->reset($data);
    }

    /**
     * Get the number of fields in the row
     * 
     * @return int
     */
    public function count() {
        return count($this->asArray());
    }
    
    /**
     * Get an iterator for this object
     * 
     * @return \ArrayIterator
     */
    public function getIterator() {
        return new \ArrayIterator($this->asArray());
    }

    /**
     * Getter methods
     *
     * @param $key string
     * @return bool
     */
    public function __get( $key )
    {
        return isset($this->data[$key])? $this->data[$key] : null;
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
        if( !array_key_exists($key, $this->data) OR $this->data[$key] !== $value ){
            $this->dirty[$key] = $value;
            $this->saved = false;
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
     * Reset the object data (re-populate) and mark as saved
     *
     * @param array $data
     * @return bool
     */
    public function reset( $data = array() )
    {
        $this->dirty = array();
        $this->data = array();
        if( !is_array($data) or empty($data) ){
            return false;
        }
        foreach($data as $key=>$value){
            if( in_array($key, $this->tableObj->cols()) ){
                $this->data[$key] = $value;
                $this->saved = true;
            }
        }
        return true;
    }

    /**
     * Save changes to db
     */
    public function save()
    {
        if( empty($this->dirty) ) return true;
        
        $pkName = $this->pkName();
        if( isset($this->data[$pkName]) ){
            //it's an update
            $this->tableObj
                    ->update($this->dirty)
                    ->where("{$pkName}=?",array($this->data[$pkName]))
                    ->run();
            $merged_data = $this->_asArray();
        } else {
            //it's an insert
            $merged_data = $merged_data = $this->asArray();
            $this->tableObj
                    ->insert($merged_data)
                    ->run();
            $pk = $this->pkName();
            if( !isset($merged_data[$pk]) ){
                $merged_data[$pk] = $this->tableObj->lastInsertId();
            }
        }
        $this->reset($merged_data);
        return true;
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
        $pkName = $this->pkName();
        $this->reset(
                $this->tableObj
                    ->first("{$pkName}=?", array($id))
                    ->getRow()
        );
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
        $this->tableObj
                ->where("{$pkName}=?", array($this->data[$pkName]))
                ->delete()
                ->run();
        $this->reset();
    }

    /**
     * Private function. Filter an array returning only the schema fields
     *
     * @param array $array
     * @return array
     */
    private function _array_diff_schema(array $array)
    {
        $cols = $this->tableObj->cols();
        $retval = array();
        foreach($cols as $field){
            if( isset($array[$field]) )
                $retval[$field] = $array[$field];
        }
        return $retval;
    }

    private function _asArray($remove_id=true)
    {
        if($remove_id){
            $pkName = $this->pkName();
            unset($this->dirty[$pkName]);
        }
        return array_merge(
            $this->_array_diff_schema($this->data),
            $this->_array_diff_schema($this->dirty)
        );
    }

    /**
     * Get the object data as an array
     * @return array
     */
    public function asArray()
    {
        return $this->_asArray(false);
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
        $schema = $this->schema();
        foreach($schema as $col){
            if( $col['PRIMARY'] === true )
                return $col['FIELD'];
        }
        throw new \Exception( __CLASS__ ." Error: could not find Primary Key for table: " . $this->tableObj->name() );
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

}
