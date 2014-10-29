# Fetching data

Every time you run a `query()` method, you'll get a `ResultSet` object (which is a wrapper around pdo statement object).
Now you can use `getAll()`, `getRow()` or `getVal()` methods to retrieve data.

Please note, `ResultSet` objects will return data as an array by default.

**getVal() example**

    //fetching a single value
    $sql = "select count(*) from customer";
    $resultSet = $db->query( $sql );
    $totalCustomers = $resultSet->getVal();
	//or
    $totalCustomers = $db->query( $sql )->getVal();

**getRow() example**

    //fetching the 'where id=1' row
    $sql = "select * from customer where id=?";
    $sql_args = array(1);
    $row = $db->query($sql, $sql_args)->getRow();
    print_r($row);

**getAll() examples**

    //fetching several rows from db (low memory consumption)
    $resultSet = $db->query($sql);
    foreach($resultSet as $row) {
        print_r($row);
    }
    
	//fetching several rows from db into an array at once
    $sql = "select * from customer";
	$array = $db->query($sql)->getAll();
    foreach($array as $row) {
        print_r($row);
    }


### Return data as

You can fetch data as an array (by default), as a generic object or as an Orm object.
Note: only queries using Table class can return Orm objects.

**asArray() method**

	$row = $db->query( $sql )->asArray()->getRow();
	print_r($row); //an array

**asObject() method**

	$row = $db->query( $sql )->asObject()->getRow();
	print_r($row); //an object

**asOrm() method**

	$row = $db->Table('customer')->first("id=1")->asOrm()->getRow();
	print_r($row); //an Orm object


### Others methods

**count() method**

	$resultSet = $db->query( $sql );
	$totalRows = $resultSet->count(); //get the total returned rows
	
**lastInsertId() method**

	//get the last inserted id
	$resultSet = $db->query( $sql_insert );
	$lastInsertId = $resultSet->lastInsertId();
	
