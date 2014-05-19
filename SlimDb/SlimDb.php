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
 * SlimDb
 * 
 * Static database abstraction layer.
 * It will ran raw queries only.
 */
class SlimDb
{
    /**
     * array with db settings (config, connection, metadata...)
     *  'my-test-database' => array(
     *    connection => array with connection data
     *    driver => string with driver name
     *    type => string with driver type (mysql, sqlite...)
     *    pdo => PDOobject
     *    cache-stmt => array with cached PDO statements
     *    metadata => array with metadata (list of tables and table's fields)
     *  );
     */
    static private $config = array();
    
    /** String with the default connection index */
    static private $defaultConnection = null;
    
    /**
     * array that holds driver anonimous functions
     *  'mysql' => array(
     *    wrapper => string with quote characters
     *    functions => array with mysql functions here...
     *  );
     */
    static private $driver = array();
    
    /** array with executed queries */
    static private $queryLog = array();

    ////////////////////////////////////////////////////////////////////
    //////////////            PSR-0 Autoloader            //////////////
    ////////////////////////////////////////////////////////////////////

    /**
     * SlimDb PSR-0 autoloader
     */
    public static function autoload($className)
    {
        $thisClass = str_replace(__NAMESPACE__.'\\', '', __CLASS__);

        $baseDir = __DIR__;

        if (substr($baseDir, -strlen($thisClass)) === $thisClass) {
            $baseDir = substr($baseDir, 0, -strlen($thisClass));
        }

        $className = ltrim($className, '\\');
        $fileName  = $baseDir;
        $namespace = '';
        if ($lastNsPos = strripos($className, '\\')) {
            $namespace = substr($className, 0, $lastNsPos);
            $className = substr($className, $lastNsPos + 1);
            $fileName  .= str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
        }
        $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';

        if (file_exists($fileName)) {
            require $fileName;
        }
    }

    /**
     * Register SlimDb's PSR-0 autoloader
     */
    public static function registerAutoloader()
    {
        spl_autoload_register(__NAMESPACE__ . "\\SlimDb::autoload");
    }
    
    ////////////////////////////////////////////////////////////////////
    //////////////             Configuration              //////////////
    ////////////////////////////////////////////////////////////////////

    /**
     * Throw and exception
     * 
     * @param $message string custom message
     * @param $method string method name
     * @param $class string class name
     */
    public static function exception($message, $method='')
    {
        if( empty($method) ) $method = __CLASS__;
        throw new \Exception("{$method} Error: {$message}");
    }
    
    /**
     * Check if the connection name index is valid or not
     * 
     * @param $config connection name index
     */
    public static function isValidConfig($index)
    {
        return isset(self::$config[$index]);
    }
    
    /**
     * Set the default connection index.
     * 
     * @param $index string connection name index
     */
    public static function setDefaultConnection($index)
    {
        self::$defaultConnection = $index;
    }
    
    /**
     * Get the default connection index.
     */
    public static function getDefaultConnection()
    {
        return self::$defaultConnection;
    }
    
    /**
     * Set database config parameters
     * 
     * @param $index string connection name index
     * @param $config mixed
     */
    public static function configure($index, $config)
    {
        // Auto-detect database driver from DNS
        $type = strtolower(current(explode(':', $config['dns'], 2)));
        self::$config[$index] = array();
        self::$config[$index] = array(
            'connection' => $config,
            'driver' => $type,
            'pdo' => null,
            'cache-stmt' => array(),
            'metadata' => array(),
        );
        
        //setup query logger
        if( isset($config['log']) && $config['log']==true )
            self::$config[$index]['log'] = true;
    }
    
    ////////////////////////////////////////////////////////////////////
    //////////////                Loggin                  //////////////
    ////////////////////////////////////////////////////////////////////

    /**
     * Return query log array
     * 
     * @return array
     */
    public static function getQueryLog()
    {
        return self::$queryLog;
    }
    
    /**
     * Internal function. Write an entry into "queryLog"
     */
    private static function logQuery($index=null, $log_time=NULL, $message='', $sqlParams=array())
    {
        $time = microtime(true);
        if( is_null($index) || is_null($log_time) ){
            return $time;
        }
        if( isset(self::$config[$index]['log']) 
            && self::$config[$index]['log']!=true )
        { return; }
        $message = self::parseQuery($message, $sqlParams);
        self::$queryLog[] = array($time - $log_time, "{$index} - {$message}");
    }
    
    private static function parseQuery($sql, $sqlParams)
    {
        if( count($sqlParams) ){
            // Avoid %format collision for vsprintf
            $sql = str_replace("%", "%%", $sql);
            // Replace placeholders in the query for vsprintf
            $sql = str_replace("?", "'%s'", $sql);
            $sql = vsprintf($sql, $sqlParams);
        }
        return $sql;
    }
    
    ////////////////////////////////////////////////////////////////////
    //////////////           Quote functions              //////////////
    ////////////////////////////////////////////////////////////////////

    /**
     * Set/get the wrapper char used by the qoute methods
     */
    public static function wrapper($index, $wrap=null)
    {
        $type = self::getConfigDriver($index);
        //is it a getter?
        if( $wrap===null ){
            return self::$driver[$type]['wrap'];
        }
        //it's a setter
        self::$driver[$type]['wrap'] = $wrap;
    }

    /**
     * Quote a columns array
     *
     * @param  array  $columns
     * @return string
     */
    public function quoteColumns($index, $columns)
    {
        if( !is_array($columns) ) $columns = array($columns);
        //return implode(', ', array_map(array($this, 'quote'), $columns));
        $retval = array();
        foreach($columns as $item){
            $retval[] = self::quote($index, $item);
        }
        return implode(', ', $retval);
    }
   
    /**
     * Quote a value in keyword identifiers.
     *
     * @param  string  $value
     * @return string
     */
    public static function quote($index, $value)
    {
        $retval = array();
        $parts = explode('.', $value);
        foreach($parts as $item){
            $retval[] = self::quoteValue($index, $item);
        }
        return implode('.', $retval);
    }

    /**
     * Quote a single part value in keyword identifiers.
     *
     * @param  string  $value
     * @return string
     */
    public static function quoteValue($index, $value)
    {
        $type = self::getConfigDriver($index);
        $exceptions = array('*', 'count(*)');
        return in_array($value,$exceptions) ? $value : sprintf(self::wrapper($index), $value);
    }

    ////////////////////////////////////////////////////////////////////
    //////////////     Connect and query functions        //////////////
    ////////////////////////////////////////////////////////////////////

    /**
     * Database lazy-loading to setup connection only when finally needed
     */
    protected static function connect($index)
    {
        $config = self::$config[$index]['connection'];
        extract(self::$config[$index]['connection']);
        if( !isset($username) ) $username='';
        if( !isset($password) ) $password='';
        if( !isset($params) ) $params=array();
        // Clear config for security reasons
        self::$config[$index]['connection'] = NULL;
        
        // Connect db and configure general settings
        $time = self::logQuery();
        $pdo = new \PDO($dns, $username, $password, $params);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        self::$config[$index]['pdo'] = $pdo;
        self::logQuery($index, $time, "Establish database connection({$index})");
        
        //load driver settings
        $type = self::getConfigDriver($index);
        if( isset(self::$driver[$type]['functions']['connect']) ){
            self::driverCall($index, 'connect', $config);
        }
    }

    /**
     * Internal function. Run a sql query and return a PDO Statement
     * 
     * @param string $index connection name
     * @param string $sql query to run
     * @param array $params the prepared query params
     * @param array $extra additional configuration data
     * @return PDOStatement
     */
    protected static function run_query($index, $sql, array $params = NULL, $extra = array() )
    {
        if( ! self::$config[$index]['pdo'] ){
            self::connect($index);
        }
        $sql = trim($sql);
        
        //no need to prepare() & execute()
        if( empty($params) ){
            $time = self::logQuery();
            $statement = self::$config[$index]['pdo']->query($sql);
            self::logQuery($index, $time, "[Raw query] $sql");
            return $statement;
        }
        
        $cacheStmt = isset($extra['cacheStmt']) ? intval($extra['cacheStmt']) : false;
        $time = self::logQuery();
        // Should we cached PDOStatements? (Best for batch inserts/updates)
        $mesage = '';
        if( $cacheStmt )
        {
            $hash = md5($sql);
            if( isset(self::$config[$index]['cache-stmt'][$hash]) ){
                $mesage = "Prepared statement - Cache found";
                $statement = self::$config[$index]['cache-stmt'][$hash];
            } else {
                $mesage = "Prepared statement - Cache miss";
                $statement = self::$config[$index]['cache-stmt'][$hash] = self::$config[$index]['pdo']->prepare($sql);
            }
        } else {
            $mesage = "Prepared statement - No cache";
            $statement = self::$config[$index]['pdo']->prepare($sql);
        }
        
        $statement->execute($params);
        self::logQuery($index, $time, "[{$mesage}] $sql", $params);
        return $statement;
    }
    
    /**
     * Run a SQL query and return the statement object
     * 
     * @param string $index connection name
     * @param string $sql query to run
     * @param array $params the prepared query params
     * @param array $extra additional configuration data
     * @return ResultSet
     */
    public static function query($index, $sql, array $params = NULL, $extra = array())
    {
        $result = null;
        try{
            $statement = self::run_query($index, $sql, $params, $extra);
            $result = new ResultSet($index, $statement, $params);
        } catch (\Exception $e){
            $sql = self::parseQuery($sql, $params);
            self::exception($e->getMessage() . $sql, __METHOD__);
        }
        return $result;
    }

    ////////////////////////////////////////////////////////////////////
    //////////////          Driver functions              //////////////
    ////////////////////////////////////////////////////////////////////

    /**
     * Internal function. Load driver
     */
    private static function loadDriver($index, $type)
    {
        $driver_file = sprintf("%s/Driver_%s.php", __DIR__, ucfirst(strtolower($type)) );
        if( !isset(self::$driver[$type]) && file_exists($driver_file) ){
            self::$driver[$type]['functions'] = include_once($driver_file);
            // Initialize driver settings
            if( isset(self::$driver[$type]['functions']['init']) ){
                self::driverCall($index, 'init');
            }
        }
    }
    
    /**
     * Get the driver type for the connection name index
     * 
     * @param $index string connection name index
     */
    public static function getConfigDriver($index)
    {
        if( isset(self::$config[$index]['driver']) ){
            $type = self::$config[$index]['driver'];
            if( !isset(self::$driver[$type]) ) self::loadDriver($index, $type);
            return $type;
        }
        self::exception("Invalid connection name ($index)!", __METHOD__);
    }

    /**
     * Call a driver function
     * 
     * Expect at least 2 parameters.
     * @param string $index connection name
     * @param string $method method name
     */
    public static function driverCall()
    {
        $args = func_get_args();
        if( count($args)<2 ){
            self::exception("Invalid arguments number. API driverCall(connection_name, method, ...)", __METHOD__);
        }
        $index = $args[0];
        $method = $args[1];
        if( !isset(self::$config[$index]) ){
            self::exception("Invalid connection name index '{$index}'!", __METHOD__);
        }
        $type = self::getConfigDriver($index);
        if( is_null(self::$driver[$type]) ){
            self::exception("Invalid driver type '{$type}'!", __METHOD__);
        }
        if( !isset(self::$driver[$type]['functions'][$method]) ){
            self::exception("Driver '{$type}' doesn't support '{$method}' method!", __METHOD__);
        }
        unset($args[1]);
        return call_user_func_array(self::$driver[$type]['functions'][$method], $args);
    }
    
    /**
     * Return last-insert-id value
     * 
     * @param string $index connection name
     */
    public static function lastInsertId($index)
    {
        return self::$config[$index]['pdo']->lastInsertId();
    }
    
    /**
     * Return database or table schema (and cache it for performance)
     * 
     * @param string $index connection name
     * @param string $table table name
     * @param bool $force_reload force reload schema
     */
    public static function schema($index, $table=NULL, $force_reload=false)
    {
        if( $table===null ){
            return self::driverCall($index, 'schemaDb');
        }
        if( isset(self::$config[$index]['metadata'][$table]) && !$force_reload){
            return self::$config[$index]['metadata'][$table];
        }
        self::$config[$index]['metadata'][$table] = self::driverCall($index, 'schemaTable', $table);
        return self::$config[$index]['metadata'][$table];
    }
     
}

/**
 * Envolve SlimDb static functions whitin an object
 */
class Database
{
    /**
     * String with current database connection name index
     * Every object must have a valid one
     */
    protected $connectionName = NULL;

    /**
     * Setup the 'connectionName' and load driver functions
     * 
     * @param string $index connection name
     */
    function __construct($index=null)
    {
        //if empty, use the default connection name
        if( empty($index) ) $index = SlimDb::getDefaultConnection();
        if( empty($index) ) SlimDb::exception("Missing connection name index!");
        if( !SlimDb::isValidConfig($index) ) SlimDb::exception("Invalid connection name index! ({$index})");
        $this->connectionName = $index;
    }
    
    ////////////////////////////////////////////////////////////////////
    //////////////            Magic Methods               //////////////
    ////////////////////////////////////////////////////////////////////
    
    public function __call($method, $args)
    {
        $class = __NAMESPACE__ . '\SlimDb';
        if( method_exists($class, $method)) {
            array_unshift($args, $this->connectionName);
            return forward_static_call_array ("{$class}::{$method}", $args);
            //return call_user_func_array(array($class, $method), $args);
        }
        SlimDb::exception("Invalid metod! ({$method})", __METHOD__);
    }
    
    public static function __callStatic($method, $args)
    {
        $class = __NAMESPACE__ . '\SlimDb';
        if(method_exists($class, $method)) {
            return call_user_func_array("{$class}::{$method}", $args); 
        }
        SlimDb::exception("Invalid static metod! ({$method})", __METHOD__);
    }

    /**
     * Factory method for Table object
     */
    public function table($table)
    {
        if( !empty($table) )
        {
            return new Table($this->connectionName, $table);
        }
        SlimDb::exception("Table '{$table}' is not valid!", __METHOD__);
    }
    
}
