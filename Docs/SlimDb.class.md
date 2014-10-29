# Running raw queries

If you want to run raw queries you can use `SlimDb` (which is a static class) or `Database` (which is not static).
These two classes are just a wrapper around pdo, and will return a `ResultSet` object after a query.

Examples

    $sql = "select * from customer";
    
    //static example
    $resultSet = \SlimDb\SlimDb::query('portal', $sql);
    
    //non static example using the default connection (portal)
    $db = new \SlimDb\Database();
    $resultSet = $db->query($sql);
    //alternative, you can call a static method ´$db = \SlimDb\SlimDb::Db();´ to create a Database object

Note: remember the first argument of the `query()` method is the sql (string) query, the second is (an array) where you put the arguments.

    $db->query($sql, $args);  //non static syntax
    \SlimDb\SlimDb::query('portal', $sql, $args);  //static syntax

# Additional features

### Get the db schema

You can retrieve the database schema using the `schema()` method. See the example:

	$array = $db->schema();
	print_r($array);
	
### Get a log with all executed queries

By default, all queries are logged.
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
`quote()` it quote a column. Has some logic to detect string like "user_id as UserId"
`quoteColumns()` is used for quote select or order by strings. Example: $db->quoteColumns("id, customer_name, dispay_name");
`quoteValue()` this is the most basic quote function, it almost has no logic, it just quote the string.
