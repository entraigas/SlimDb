# Using the Orm class

This is a small ORM class.
You can change properties values and then push changes to db with `save()` method.

Examples

    /* 
	 * Load the customer with id=1 from db
	 * Note: both methods get the same result
	 */
	//find the record and return it as Orm object
    $resultSet = $db->Table('customer')->firstById(1);
	$customer = $resultSet->asOrm()->getRow();
	
	//or create an Orm object and load the id=1 customer
	$customer = $db->Orm('customer')->load(1);
	
	
    /*
	 * Get the object properties
	 * Note: both methods get the same result
	 */
	//get customer fields
	print_r( $customer->toArray() );

	//alternative foreach syntax
	foreach($customer as $key=>$value) echo "<br>{$key} => {$value}";
	
    /* 
	 * Introduce some changes
	 * Note: all methods get the same result
	 */
	//property set
    $customer->name = 'John Foo';
    $customer->age = 18;
	
	//set() method 
    $customer->set('name', 'John Foo');
    $customer->set('age', 18);
    
	//alternative set() method syntax 
	$customer->set( 
		array(
			'name' => 'John Foo',
			'age' => '18'
		)
	);
	
	//save changes to db
    $customer->save();
	
	/* 
	 * you can reload the object values from db
	 * Note: any changes made are going to be lost!
	 */
	$customer->reload();
	
	/* 
	 * create a new customer
	 */
	$data = array(
		'name' => 'John Foo',
		'age' => '18'
	);
	$customer = $db->Orm('customer')->set($data);
	$customer->save();
	
	/* 
	 * remove the customer id=1
	 */
	$db->Orm('customer')->load(1)->delete();
	
