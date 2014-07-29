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


# Additional features

### Get the db schema

You can retrieve the database schema using the `schema()` method. See the example:

	$array = $db->schema();
	print_r($array);
	
### Get a log with all executed queries

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