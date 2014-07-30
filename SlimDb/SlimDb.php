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
     *  $connectionName => array(
     *    driver => string with driver type (mysql, sqlite...)
     *    pdo => PDOobject
     *    cache-stmt => array with cached PDO statements
     *    metadata => array with metadata (list of tables and table's fields)
     *    database = \SlimDb\Database object
     *  );
     */
    static private $config = array();

    /** String with the default connection index */
    static private $defaultConnection = null;

    /**
     * array that holds driver anonymous functions
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
     * @param string $message custom message
     * @param string $method method name
     * @throws \Exception
     */
    public static function exception($message, $method='')
    {
        if( empty($method) ) $method = __CLASS__;
        throw new \Exception("{$method} Error: {$message}");
    }

    /**
     * Check if the connection name index is valid or not
     *
     * @param string $connectionName
     * @return bool
     */
    public static function isValidConfig($connectionName)
    {
        return isset(self::$config[$connectionName]);
    }

    /**
     * Set the default connection name index.
     *
     * @param string $connectionName
     */
    public static function setDefaultConnection($connectionName)
    {
        self::$defaultConnection = $connectionName;
    }

    /**
     * Get the default connection name index.
     * @return string connection name
     */
    public static function getDefaultConnection()
    {
        return self::$defaultConnection;
    }

    /**
     * Set database config parameters
     *
     * @param string $connectionName
     * @param array $config
     */
    public static function configure($connectionName, $config)
    {
        self::$config[$connectionName] = array(
            'driver' => $config['driver'],
            'getPdo' => $config['getPdo'],
            'pdo' => null,
            'cache-stmt' => array(),
            'metadata' => array(),
            'database' => null
        );
    }

    ////////////////////////////////////////////////////////////////////
    //////////////                Logs                    //////////////
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
    private static function _logQuery($connectionName=null, $log_time=NULL, $message='', $sqlParams=array())
    {
        $time = microtime(true);
        if( is_null($connectionName) || is_null($log_time) ){
            return $time;
        }
        if( isset(self::$config[$connectionName]['log'])
            && self::$config[$connectionName]['log']!=true )
        { return; }
        $message = self::_parseQuery($message, $sqlParams);
        self::$queryLog[] = array($time - $log_time, "{$connectionName} - {$message}");
    }

    /**
     * Generate a sql query with values instead of placeholders
     */
    private static function _parseQuery($sql, $sqlParams)
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
     * Set the wrapper char used by the quote methods
     *
     * @param string $connectionName
     * @param string $wrap
     */
    private static function _setWrapper($connectionName, $wrap)
    {
        $type = self::getConfigDriver($connectionName);
        self::$driver[$type]['wrap'] = $wrap;
    }

    /**
     * Get the wrapper char used by the quote methods
     *
     * @param string $connectionName
     * @return string
     */
    public static function getWrapper($connectionName)
    {
        $type = self::getConfigDriver($connectionName);
        return self::$driver[$type]['wrap'];
    }

    /**
     * Quote a columns array
     *
     * @param string $connectionName
     * @param  array  $columns
     * @return string
     */
    public static function quoteColumns($connectionName, $columns)
    {
        //return implode(', ', array_map(array($this, 'quote'), $columns));
        if( !is_array($columns) ) $columns = explode(',', $columns);
        $retval = array();
        foreach($columns as $item){
            $retval[] = self::quote($connectionName, $item);
        }
        return implode(', ', $retval);
    }

    /**
     * Quote a value in keyword identifiers.
     *
     * @param string $connectionName
     * @param string $value
     * @return string
     */
    public static function quote($connectionName, $value)
    {
        $retval = array();
        $alias = preg_split("/ as /i", $value);
        $parts = explode('.', $alias[0]);
        foreach($parts as $item){
            $retval[] = self::quoteValue($connectionName, $item);
        }
        $retval =  implode('.', $retval);
        if(isset($alias[1])) $retval = $retval . " AS {$alias[1]}";
        return $retval;
    }

    /**
     * Quote a single part value in keyword identifiers.
     *
     * @param string $connectionName
     * @param string $value
     * @return string
     */
    public static function quoteValue($connectionName, $value)
    {
        //$type = self::getConfigDriver($connectionName);
        $value = trim($value);
        $exceptions = array('*', 'count(*)');
        return in_array($value,$exceptions) ? $value : sprintf(self::getWrapper($connectionName), $value);
    }

    ////////////////////////////////////////////////////////////////////
    //////////////     Connect and query functions        //////////////
    ////////////////////////////////////////////////////////////////////

    /**
     * Database lazy-loading. Setup connection only when finally needed
     */
    protected static function connect($connectionName)
    {
        $time = self::_logQuery();
        //make a connection to db
        self::$config[$connectionName]['pdo'] = call_user_func(self::$config[$connectionName]['getPdo']);
        unset(self::$config[$connectionName]['getPdo']);
        //load driver
        $type = self::getConfigDriver($connectionName);
        self::_logQuery($connectionName, $time, "Establish database connection({$connectionName})");
    }

    /**
     * Internal function. Run a sql query and return a PDO Statement
     *
     * @param string $connectionName connection name
     * @param string $sql query to run
     * @param array $params the prepared query params
     * @param array $extra additional configuration data
     * @return PDOStatement object
     */
    protected static function _run_query($connectionName, $sql, array $params = NULL, $extra = array() )
    {
        if( ! self::$config[$connectionName]['pdo'] ){
            self::connect($connectionName);
        }
        $sql = trim($sql);

        //no need to prepare() & execute()
        if( empty($params) ){
            $time = self::_logQuery();
            $statement = self::$config[$connectionName]['pdo']->query($sql);
            self::_logQuery($connectionName, $time, "[Raw query] $sql");
            return $statement;
        }

        $cacheStmt = isset($extra['cacheStmt']) ? intval($extra['cacheStmt']) : false;
        $time = self::_logQuery();
        // Should we cached PDOStatements? (Best for batch inserts/updates)
        $message = '';
        if( $cacheStmt )
        {
            $hash = md5($sql);
            if( isset(self::$config[$connectionName]['cache-stmt'][$hash]) ){
                $message = "Prepared statement - Cache hit";
                $statement = self::$config[$connectionName]['cache-stmt'][$hash];
            } else {
                $message = "Prepared statement - Cache miss";
                $statement = self::$config[$connectionName]['cache-stmt'][$hash] = self::$config[$connectionName]['pdo']->prepare($sql);
            }
        } else {
            $message = "Prepared statement";
            $statement = self::$config[$connectionName]['pdo']->prepare($sql);
        }
        $statement->execute($params);
        self::_logQuery($connectionName, $time, "[{$message}] $sql", $params);
        return $statement;
    }

    /**
     * Run a SQL query and return the statement object
     *
     * @param string $connectionName
     * @param string $sql query to run
     * @param array $params the prepared query params
     * @param array $extra additional configuration data
     * @return ResultSet object
     */
    public static function query($connectionName, $sql, array $params = NULL, $extra = array())
    {
        $result = null;
        try{
            $statement = self::_run_query($connectionName, $sql, $params, $extra);
            return new ResultSet($connectionName, $statement, $params);
        } catch (\Exception $e){
            $sql = self::_parseQuery($sql, $params);
            self::exception($e->getMessage() . " - $sql", __METHOD__);
        }
    }

    ////////////////////////////////////////////////////////////////////
    //////////////          Driver functions              //////////////
    ////////////////////////////////////////////////////////////////////

    /**
     * Internal function. Load driver
     */
    private static function _loadDriver($connectionName, $type)
    {
        $driver_file = sprintf("%s/Driver_%s.php", __DIR__, ucfirst(strtolower($type)) );
        if( !isset(self::$driver[$type]) && file_exists($driver_file) ){
            self::$driver[$type]['functions'] = include_once($driver_file);
            // Initialize driver settings
            if( isset(self::$driver[$type]['functions']['init']) ){
                self::driverCall($connectionName, 'init');
            }
        }
    }
    
    /**
     * Get the driver type for the connection name index
     * 
     * @param $connectionName string connection name index
     * @return string
     */
    public static function getConfigDriver($connectionName)
    {
        if( isset(self::$config[$connectionName]['driver']) ){
            $type = self::$config[$connectionName]['driver'];
            if( !isset(self::$driver[$type]) ) self::_loadDriver($connectionName, $type);
            return $type;
        }
        self::exception("Invalid connection name ($connectionName)!", __METHOD__);
    }

    /**
     * Call a driver function.
     * Expect at least 2 parameters.
     *
     * @param string $connectionName connection name
     * @param string $method method name
     * @return mixed
     */
    public static function driverCall()
    {
        $args = func_get_args();
        if( count($args)<2 ){
            self::exception("Invalid arguments number. API driverCall(connection_name, method, ...)", __METHOD__);
        }
        $connectionName = $args[0];
        $method = $args[1];
        if( !isset(self::$config[$connectionName]) ){
            self::exception("Invalid connection name index '{$connectionName}'!", __METHOD__);
        }
        $type = self::getConfigDriver($connectionName);
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
     * @param string $connectionName connection name
     * @return integer
     */
    public static function lastInsertId($connectionName)
    {
        return self::$config[$connectionName]['pdo']->lastInsertId();
    }

    /**
     * Return database name
     *
     * @param string $connectionName connection name
     * @param string $table table name
     * @param bool $force_reload force reload schema
     * @return array
     */
    public static function getDbName($connectionName)
    {
        return self::driverCall($connectionName, 'dbName');
    }
    
    /**
     * Return schema for database or table (and cache it for performance)
     *
     * @param string $connectionName connection name
     * @param string $table table name
     * @param bool $force_reload force reload schema
     * @return array
     */
    public static function schema($connectionName, $table=NULL, $force_reload=false)
    {
        if( $table===null ){
            return self::driverCall($connectionName, 'schemaDb');
        }
        if( isset(self::$config[$connectionName]['metadata'][$table]) && !$force_reload){
            return self::$config[$connectionName]['metadata'][$table];
        }
        self::$config[$connectionName]['metadata'][$table] = self::driverCall($connectionName, 'schemaTable', $table);
        return self::$config[$connectionName]['metadata'][$table];
    }

    /**
     * Factory method for Databse object
     */
    public static function Db($connectionName)
    {
        return new Database($connectionName);
    }
    
    /**
     * Factory method for Table object
     */
    public static function Table($connectionName, $table)
    {
        if( !empty($table) )
        {
            return new Table($connectionName, $table);
        }
        SlimDb::exception("Table '{$table}' is not valid!", __METHOD__);
    }
    
    /**
     * Factory method for ORM object
     */
    public static function Orm($connectionName, $table)
    {
        if( !empty($table) )
        {
            $table = new Table($connectionName, $table);
            return new Orm($table);
        }
        SlimDb::exception("Table '{$table}' is not valid!", __METHOD__);
    }
    
}

/**
 * Wrap up SlimDb static functions within an object
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
     * @param string $connectionName connection name
     */
    function __construct($connectionName=null)
    {
        //if empty, use the default connection name
        if( empty($connectionName) ) $connectionName = SlimDb::getDefaultConnection();
        if( empty($connectionName) ) SlimDb::exception("Missing connection name index!");
        if( !SlimDb::isValidConfig($connectionName) ) SlimDb::exception("Invalid connection name index! ({$connectionName})");
        $this->connectionName = $connectionName;
    }
    
    /**
     * Get the 'connectionName' index
     * 
     * @return string
     */
    function getConnectionName()
    {
        return $this->connectionName;
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
        SlimDb::exception("Invalid method! ({$method})", __METHOD__);
    }
    
    public static function __callStatic($method, $args)
    {
        $class = __NAMESPACE__ . '\SlimDb';
        if(method_exists($class, $method)) {
            return call_user_func_array("{$class}::{$method}", $args);
        }
        SlimDb::exception("Invalid static method! ({$method})", __METHOD__);
    }
    
}
