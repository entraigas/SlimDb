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
	$row = $db->query($sql)->getRow();

	//fetching a single row using Table class
	$db = new Database('portal');
	$table = $db->Table('customer');
	$resultSet = $table->where("id = 1")->run();
	$row = $resultSet->getRow();
	//alternative chainable syntax
	$row = $db->Table('customer')->where("id = 1")->run()->getRow();
	
	//fetching a multiples rows using Database class
	$sql = "select * from customer";
	$row = $db->query($sql)->getAll();
	
	//fetching a multiples rows using Table class
	$row = $db->Table('customer')->run()->getAll();
	
**Where syntax**

The `$db->Table()->where()` syntax is more flexible than `SlimDb::query()` syntax (and also compatible).
Below are some examples, they all run the same query.

    /* let's run this query
     * "select * from customer where name='john' and surname='doe'"
     */

    //classic approaches example
    $db->Table('customer')->where("name=? and surname=?", array('john', 'doe'));

    //alt approaches examples
    $db->Table('customer')->where("name=? and surname=?", 'john', 'doe');
    $db->Table('customer')->where(array(
        'name'=>'john',
        'surname'=>'doe'
    ));


**ResultSet operations**

    //get all rows from customer table
    $resultSet = $db->Table('customer')->run();
    foreach($resultSet as $row) {
        print_r($row);
    }
    echo $resultSet->count(); //returned rows

    //get some rows from customer table (where name like '%john%')
    $resultSet = $db->Table('customer')
		->where("name like ?", '%john%')
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

    //insert into customer(name,age) values('John Doe',18)
    $data = array( 
		'name'=>'John Doe',
		'age'=>18
	);
    $resultSet = $db->Table('customer')
		->insert($data)
		->run();
    echo $resultSet->lastInsertId();
	echo $resultSet->count(); //affected rows
    
    //update customer where id=1 set name='John Doe'
    $data = array( 'name'=>'John Doe' );
    $resultSet = $db->Table('customer')
		->update($data)
		->where("id=?", 1)
		->run();
    echo $resultSet->count(); //affected rows

    //delete where id=1 or category=9
    $db->Table('customer')
		->delete()
		->where("id=? or category=?", 1, 9)
		->run();
    echo $resultSet->count(); //affected rows

**Select short-cuts operations**

Note: The short-cut methods makes an implicit call to `run()`.

`first()` & `firstById()`  methods.
This method return a RecordSet object.
`first("id = 1")` it's equal to `where("id = 1")->limit(1)->run()`. Example:

	//classic way
	$row = $db->Table('customer')
		->where("id = 1")
		->limit(1)
		->run()
		->getRow();
	//short-cut 1
	$row = $db->Table('customer')->first("id = 1")->getRow();
	//short-cut 2
	$row = $db->Table('customer')->firstById(1)->getRow();

`count()` & `countById()`  methods.
This methods return an integer value.
Example:

	//select count(*) from customer
	$total = $db->Table('customer')->count();
	
	//select count(*) from customer where age > 18
	$total = $db->Table('customer')->count("age > 18");

	//check if the record exist
	$exist = $db->Table('customer')->countById(1);

**Complex select operations**

	//get the first 10 customers from company id = 1, ordered by customer's name.
	$result = Db()->Table('customer')
		->distinct()
		->select("customer.*, company.name")
		->join('company', 'company.id = customer.id')
		->where('company.name like ?', "%acme%")
		->orderBy('customer.name')
		->limit(10)
		->run();
