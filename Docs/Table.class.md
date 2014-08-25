# Using the Table class

This class is meant for doing common task in a table without writing raw queries.
After setting up the query arguments, you have to end with a `run()` call to get a ResultSet.

Note: internally, this class will use `SlimDb::query()` method.

**Running Select queries**

Syntax: 

	$recordSet = $db->Table('user')
		[ distinct(), select(), join(), where(), orderBy(), limit() ]
	->run();

Below it's a comparison between Table class & Database class select query.

	//fetching a single row using Database class
	$db = new Database('portal');
	$sql = "select * from customer where id = 1";
	$resultSet = $db->query($sql);
	$row = $resultSet->getRow();
	
	//fetching a single row using Table class
	$db = new Database('portal');
	$table = $db->Table('customer');
	$resultSet = $table->where("id = 1")->run();
	$row = $resultSet->getRow();
	//alterntive syntax
	$row = $db->Table('customer')
		->where("id = 1")
		->run()
		->getRow();
	
	//fetching a multiples rows using Database class
	$sql = "select * from customer";
	$row = $db->query($sql)->getAll();
	
	//fetching a multiples rows using Table class
	$row = $db->Table('customer')->run()->getAll();
	
**ResultSet operations**

    //get all rows from customer table
    $resultSet = $db->Table('customer')->run();
    foreach($resultSet as $row) {
        print_r($row);
    }
    echo $resultSet->count(); //returned rows

    //get some rows from customer table (where name like '%jhon%')
    $resultSet = $db->Table('customer')
		->where("name like ?", array('%jhon%'))
		->run();
    foreach($resultSet as $row) {
        print_r($row);
    }
    echo count($resultSet); //returned rows
	
	//change the returned format
    $resultSet = $db->Table('customer')->run();
	//$resultSet->asArray(); //change the default format to array (default)
	$resultSet->asObject(); //change the default format to objects
	//$resultSet->asOrm(); //change the default format to Orm objects
	print_r($resultSet->getAll());

**Insert, update, delete operations**

    //insert into customer(name,age) values('Jhon Doe',18)
    $data = array( 
		'name'=>'Jhon Doe',
		'age'=>18
	);
    $resultSet = $db->Table('customer')
		->insert($data)
		->run();
    echo $resultSet->lastInsertId();
	echo $resultSet->count(); //affected rows
    
    //update customer where id=1 set name='Jhon Doe'
    $data = array( 'name'=>'Jhon Doe' );
    $resultSet = $db->Table('customer')
		->update($data)
		->where("id=?", array(1))
		->run();
    echo $resultSet->count(); //affected rows

    //delete where id=1 or category=9
    $db->Table('customer')
		->delete()
		->where("id=? or category=?", array(1, 9))
		->run();
    echo $resultSet->count(); //affected rows

**Select short-cuts operations**

Note: The short-cut methods makes an implicit call to `run()`.

`first()`  method.
This method return the first record of the given where clause.

`firstById()`  method.
This method expect a single value as parameter, and will search in the table's primary key field for a record with this value.

Below it's a comparison between the all the select methods:

	//classic way
	$row = $db->Table('customer')
		->where("id = 1")
		->limit(1)
		->run()
		->getRow();
	//1st short-cut
	$row = $db->Table('customer')->first("id = ?", array(1))->getRow();
	//2nd short-cut
	$row = $db->Table('customer')->firstById("1")->getRow();


`count()`  method.
This method return an integer value.  Example:

	//select count(*) from customer
	$total = $db->Table('customer')->count();
	//select count(*) from customer where age > 18
	$total = $db->Table('customer')->count("age > 18");

`countById()`  method.
This method expect a single value as parameter, and will return either 1 or 0 if it find a record or not. Example:

	//select count(*) from customer where id=1
	$found = $db->Table('customer')->countById(1);

**Complex select operations**

	//get the first 10 customers from company id = 1, ordered by customer's name.
	$result = Db()->Table('customer')
		->distinct()
		->select("customer.*, company.name")
		->join('company', 'company.id = customer.id')
		->where('company.name like ?', array("%acme%"))
		->orderBy('customer.name')
		->limit(10)
		->run();
