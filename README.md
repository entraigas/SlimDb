SlimDb
======

Small db layer around the PDO and PDO statement.

The package goal is to be small and handy, with the basic, commonly used 
db functions (like select, update, insert and delete).
Currently there's only support for mysql and sqlite.

# Db Setup

The configuration it's done using arrays.
In this example, there are two db settings: 
* the first has 'portal' as connection name, and it's a mysql db.
* the second has 'admin' as connection name, and it's a sqlite db.

Finally, there is a 'default' connection name configured with the 
'portal' value.

	//database configuration array
	$config['databases'] = array(
		'default' => 'portal',
		'portal' => array(
			'driver' => 'mysql',
			'getPdo' => function(){
					//validate PDO extensions
					if (!defined('\PDO::ATTR_DRIVER_NAME')) return false; //PDO is not available
					if (!extension_loaded('pdo_mysql')) return false; //pdo_mysql extension not loaded
					//make connection
					$pdo = new \PDO("mysql:host=127.0.0.1;port=3306;dbname=testdb", 'user', 'password');
					//default connection settings
					$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
					$pdo->query("SET NAMES 'utf8'");
					//done, return pdo object
					return $pdo;
				},
		),
		'admin' => array(
			'driver' => 'sqlite',
			'getPdo' => function(){
					//validate PDO extensions
					if (!defined('\PDO::ATTR_DRIVER_NAME')) return false; //PDO is not available
					if (!extension_loaded('pdo_sqlite')) return false; //pdo_sqlite extension not loaded
					//make connection
					$pdo = new \PDO("sqlite:/path/to/database.db");
					//default connection settings
					$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
					//done, return pdo object
					return $pdo;
				},
		),
	);

	//Define db resources
	foreach($config['databases'] as $index=>$setting){
		if(is_array($setting)){
			\SlimDb\SlimDb::configure($index, $setting);
		}elseif($index==='default'){
			\SlimDb\SlimDb::setDefaultConnection($setting);
		}
	}


There are many classes bundled with the package.
Depending on what you are trying to do, you should use one over the 
other. Here is a list:

* Running raw queries: SlimDb or Database classes
* Fetching data: ResultSet class
* Working with Table class
* Working with Orm class
