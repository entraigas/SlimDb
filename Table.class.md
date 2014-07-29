# Using the Table class

This class is meant for doing common task in a table without writing raw queries.
After setting up the query arguments, you have to end with a `run()` call to get a ResultSet.

Note: internally, this class will use `SlimDb::query()` method.

**Running Select queries**

Syntax: 

	$recordSet = $db->table('user')
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
	$table = $db->table('customer');
	$resultSet = $table->where("id = 1")->run();
	$row = $resultSet->getRow();
	//alterntive syntax
	$row = $db->table('customer')
		->where("id = 1")
		->run()
		->getRow();
	
	//fetching a multiples rows using Database class
	$sql = "select * from customer";
	$row = $db->query($sql)->getAll();
	
	//fetching a multiples rows using Table class
	$row = $db->table('customer')->run()->getAll();
	
**ResultSet operations**

    //get all rows from customer table
    $resultSet = $db->table('customer')->run();
    foreach($resultSet as $row) {
        print_r($row);
    }
    echo $resultSet->count(); //returned rows

    //get some rows from customer table (where name like '%jhon%')
    $resultSet = $db->table('customer')
		->where("name like ?", array('%jhon%'))
		->run();
    foreach($resultSet as $row) {
        print_r($row);
    }
    echo count($resultSet); //returned rows
	
	//change the returned format
    $resultSet = $db->table('customer')->run();
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
    $resultSet = $db->table('customer')
		->insert($data)
		->run();
    echo $resultSet->lastInsertId();
	echo $resultSet->count(); //affected rows
    
    //update customer where id=1 set name='Jhon Doe'
    $data = array( 'name'=>'Jhon Doe' );
    $resultSet = $db->table('customer')
		->update($data)
		->where("id=?", array(1))
		->run();
    echo $resultSet->count(); //affected rows

    //delete where id=1 or category=9
    $db->table('customer')
		->delete()
		->where("id=? or category=?", array(1, 9))
		->run();
    echo $resultSet->count(); //affected rows

**Select short-cuts operations**

Note: The short-cut methods makes an implicit call to `run()`.

`first()`  method.
This method return a RecordSet object.
`First("id = 1")` it's equal to `where("id = 1")->limit(1)->run()`. Example:

	//classic way
	$row = $db->table('customer')
		->where("id = 1")
		->limit(1)
		->run()
		->getRow();
	//short-cut
	$row = $db->table('customer')->first("id = 1")->getRow();

`count()`  method.
This method return an integer value. 
Example:

	//select count(*) from customer
	$total = $db->table('customer')->count();
	
	//select count(*) from customer where age > 18
	$total = $db->table('customer')->count("age > 18");

**Complex select operations**

	//get the first 10 customers from company id = 1, ordered by customer's name.
	$result = Db()->table('customer')
		->distinct()
		->select("customer.*, company.name")
		->join('company', 'company.id = customer.id')
		->where('company.name like ?', array("%acme%"))
		->orderBy('customer.name')
		->limit(10)
		->run();