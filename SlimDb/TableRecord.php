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
 * TableRecord
 * 
 * It's a micro ORM class.
 * Note: compound key are not supported!
 */

class TableRecord implements \Countable, \IteratorAggregate
{
    /** Object table */
    protected $table = NULL;

    /** Array with row data */
    protected $data = NULL;

    /** Array with dirty row data */
    protected $dirty = array();

    function __construct( $table, array $data = array() )
    {
        $this->table = $table;
        //populate object
        $this->reset($data);
    }
    
    /**
     * Get the number of fields in the row
     * 
     * @return int
     */
    public function count() {
        return count($this->data);
    }
    
    /**
     * Get an iterator for this object
     * 
     * @return \ArrayIterator
     */
    public function getIterator() {
        return new ArrayIterator($this->data);
    }

    /**
     * Getter methods
     * 
     * @param $key string
     */
    public function __get( $key )
    {
        return isset($this->data[$key])? $this->data[$key] : null;
    }
    
    public function get( $key )
    {
        return $this->get($key);
    }
    
    /**
     * Setter methods
     * 
     * @param $key string
     * @param $value mixed
     */
    public function __set( $key, $value )
    {
        $schema = $this->schema();
        if( !in_array($key, $this->table->cols()) ) return $this;
        if( !array_key_exists($key, $this->data) OR $this->data[$key] !== $value ){
            $this->dirty[$key] = $value;
            $this->saved = false;
        }
        return $this;
    }
    
    public function set()
    {
        $args = func_get_args();
        if( count($args)===2 ){
            return $this->__set($args[0], $args[1]);
        }
        if( count($args)===1 ){
            foreach($args as $key=>$value){
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
     * @param $data array
     */
    public function reset( $data = array() )
    {
        $this->dirty = array();
        $this->data = array();
        if( count($data) ){
            foreach($data as $key=>$value){
                if( in_array($key, $this->table->cols()) ){
                    $this->data[$key] = $value;
                    $this->saved = true;
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Save changes to db
     */
    public function save()
    {
        if( empty($this->dirty) ) return true;
        
        $pkName = $this->pkName();
        //is it an update?
        if( isset($this->data[$pkName]) ){
            $this->table->update($this->dirty, "{$pkName}=?", array($this->data[$pkName]) );
            $merged_data[$pkName] = $this->data[$pkName];
        } else {
            //it's an insert
            $merged_data = array_merge(
                $this->array_diff_schema($this->dirty),
                $this->array_diff_schema($this->data)
            );
            $this->table->insert($merged_data);
            $pk = $this->pkName();
            if( !isset($merged_data[$pk]) )
                $merged_data[$pk] = $this->table->lastInsertId();
        }
        $this->reset($merged_data);
        return true;
    }

    /**
     * Should cache the statement for performance boost?
     * 
     * @param $bool bool
     */
    public function cacheStmt( $bool=true )
    {
        $this->table->cacheStmt($bool);
        return $this;
    }
    
    /**
     * Reload object from db
     * 
     * @param $id
     */
    public function load($id)
    {
        if( empty($id) ){
            SlimDb::exception("Invalid id value! ({$id})", __METHOD__);
        }
        $this->reset( $this->table->first('id=?', array($id)) );
        return $this;
    }
    
    /**
     * Reload object from db
     * 
     * @param $id
     */
    public function reload()
    {
        $id = $this->get( $this->pkName() );
        $this->reload( $id );
        return $this;
    }
    
    /**
     * Private function. Filter an array returning only the schema fields
     */
    private function array_diff_schema(array $array)
    {
        $schema = $this->schema();
        $retval = array();
        foreach($schema as $field => $metadata){
            if( isset($array[$field]) )
                $retval[$field] = $array[$field];
        }
        return $retval;
   }
   
    /**
     * Get the object data as an array
     * @return array
     */
    public function asArray()
    {
        return $this->data;
    }

    /**
     * Get table schema
     */
    public function schema()
    {
        return $this->table->schema();
    }
    
    /**
     * Return primary key field name
     * Note: compound key are not supported!
     */
    public function pkName()
    {
        $schema = $this->schema();
        foreach($schema as $col){
            if( $col['PRIMARY'] === true )
                return $col['FIELD'];
        }
        throw new \Exception( __CLASS__ ." Error: could not find Primary Key for table: " . $this->table->name() ); 
    }

}
