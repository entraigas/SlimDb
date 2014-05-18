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

    //database configutation
    $portal = array(
		'dns' => "mysql:host=127.0.0.1;port=3306;dbname=testdb",
		'username' => 'user',
		'password' => 'secret',
		'charset' => 'UTF8',
		'log' => TRUE,
	);
	$admin => array(
		'dns' => "sqlite:/path/to/sqlite.db",
		'log' => TRUE,
    );

    //initialize SlimDb
	\SlimDb\SlimDb::configure('portal', $portal);
	\SlimDb\SlimDb::configure('admin', $admin);
	//set the default connection
	\SlimDb\SlimDb::setDefaultConnection('portal');

There are many classes bundled with the package.
Depending on what you are trying to do, you should use one over the 
other. Here is a list:

* Running raw queries: SlimDb or Database classes
* Fetching data: ResultSet class
* Working with a single table: Table 
* ORM: TableRecord class


# Running raw queries

If you want to run raw queries you can use `SlimDb` (which is a static 
class) or `Database` (which is not static).
These two classes are just a wrapper around pdo, and will return a 
`ResultSet` object after a query.

Examples

    $sql = "select * from customer";
    
    //static example
    $resultSet = \SlimDb\SlimDb::query('portal', $sql);
    
    //non static example using the default connection (portal)
    $db = \SlimDb\Database();
    $resultSet = $db->query($sql);


## Fetching data

Everytime you run a `query()` method, you'll get a `ResultSet` object 
(which is a wrapper around pdo statement object).
Now you can use `getAll()`, `getRow()` or `getVal()` methods to retrieve 
data.

Please note, when running raw queries, `ResultSet` objects will return 
data as an array by default.

**getAll()** examples

    //fetching several rows from db (low memory consumption)
    $resultSet = $db->query($sql);
    foreach($resultSet as $row) {
        print_r($row); //show an array
    }
    //fetching several rows from db into an array
    $sql = "select * from customer";
    $array = $db->query($sql)->getAll();
    foreach($array as $row) {
        print_r($row); //show an array
    }

**getRow()** example

    //fetching the 'where id=1' row
    $sql = "select * from customer where id=?";
    $row = $db->query($sql, array(1))->getRow();
    echo $row['id'];

**getVal()** example

    //fetching a single value
    $sql = "select count(*) from customer";
    $row = $db->query( $sql )->getVal();


# Using the Table class

This class is ment for doing common task in a sigle table without 
writing raw queries.
Internally, this class will use `SlimDb::query()` method, so after a 
`find()`, `first()`, `insert()`, `update()` or `delete()` call you'll 
get a `ResultSet` object.

Please note, when using `Table` object, `ResultSet` objects will return 
data as a `TableRecord` object by default.

**Fetching data** examples

    //get all rows from customer table
    $resultSet = $db->table('customer')->find();
    foreach($resultSet as $row) {
        print_r($row); //show an object
    }
    echo $resultSet->rowCount(); //returned rows

    //get some rows from customer table (where name like '%jhon%')
    $resultSet = $db->table('customer')->find("name like ?", array('%jhon%'));
    foreach($resultSet as $row) {
        print_r($row); //show an object
    }
    echo count($resultSet); //returned rows

    //get a single row
    $row = $db->table('customer')->first();
    echo $row->id;

**Insert, update, delete** operations

    //insert into customer(name) values('Jhon Doe')
    $data = array( 'name'=>'Jhon Doe' );
    $resultSet = $db->table('customer')->insert($data);
    echo $resultSet->lastInsertId();
    
    //update customer where id=1 set name='Jhon Doe'
    $data = array( 'name'=>'Jhon Doe' );
    $resultSet = $db->table('customer')->update($data, "id=?", array(1));
    echo $resultSet->rowCount(); //affected rows

    //delete where id=1 or category=9
    $db->table('customer')->delete("id=? or category=?", array(1, 9));
    echo $resultSet->rowCount(); //affected rows


## Working with TableRecord class

This class it's a small ORM class.

You can change properties values with `set()` method and then push 
changes to db with `save()` method.

Example

    //get customer id=1 and change it name
    $customer = $db->table('customer')->first("id=?", array(1));
    $customer->set('name', 'Jhon Foo')->save();
