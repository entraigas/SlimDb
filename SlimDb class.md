# Running raw queries

If you want to run raw queries you can use `SlimDb` (which is a static class) or `Database` (which is not static).
These two classes are just a wrapper around pdo, and will return a `ResultSet` object after a query.

Examples

    $sql = "select * from customer";
    
    //static example
    $resultSet = \SlimDb\SlimDb::query('portal', $sql);
    
    //non static example using the default connection (portal)
    $db = \SlimDb\Database();
    $resultSet = $db->query($sql);


# Fetching data

Every time you run a `query()` method, you'll get a `ResultSet` object 
(which is a wrapper around pdo statement object).
Now you can use `getAll()`, `getRow()` or `getVal()` methods to retrieve 
data.

Please note, when running raw queries, `ResultSet` objects will return 
data as an array.

**getVal() example**

    //fetching a single value
    $sql = "select count(*) from customer";
    $resultSet = $db->query( $sql )->getVal();
    $totalCustomers = $resultSet->getVal();
    //$totalCustomers = $db->query( $sql )->getVal(); //same result in 1 line

**getRow() example**

    //fetching the 'where id=1' row
    $sql = "select * from customer where id=?";
    $row = $db->query($sql, array(1))->getRow();
    print_r($row);

**getAll() examples**

    //fetching several rows from db (low memory consumption)
    $resultSet = $db->query($sql);
    foreach($resultSet as $row) {
        print_r($row);
    }
    
	//fetching several rows from db into an array
    $sql = "select * from customer";
	$array = $db->query($sql)->getAll();
    foreach($array as $row) {
        print_r($row);
    }

# Additional features

### Get the db schema

You can retrieve the database schema using the `schema()` method. See the example:

	$array = $db->schema();
	print_r($array);
	
### Get all executed queries

By default, all queries are loggued.
You can retrieve this log using the `getQueryLog()` method.

	$array = $db->getQueryLog();
	echo "<ol>";
	foreach($array as $sql){
		echo "<li>{$sql}</li>";
	}
	echo "</ol>";

### Quote functions

There are several quote functions (`quoteColumns()`, `quote()`, `quoteValue()`).
But most likely you'll end up using `quote()`.