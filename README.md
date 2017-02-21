#akou
Ass Kicked orm utils, because other orm libs are better

#TODO
	fix the README because is incomplete without format

##DBTable

the main class has the next functions

###CRUD

Defining the class


	class user extends \akou\DBTable
	{
		var $name;
		var $password;
		var $created;
		var $phone;
	}


Or you can use


```php
DBTable::$connection = $mysqli_connection;
DBTable::importDBSchema();//This will be create the classes for you db;
DBTable::importDBSchema('\my\super\lib');//Clases will be created in the namespace \my\super\lib
```

its recommended that you save the clases in a file

```php
$phpCode = DBTable::importDbSchema();//Save phpCode in a file like tables.php
```

And later use it

```php
	include_once(__DIR__.'/akou/src/DBTable.php');
	include_once(__DIR__.'/akou/src/LoggableException.php');
	include_once(__DIR__.'/akou/src/Utils.php');
	include_once('tables.php');//dont forget to add <?php to the beginning of the file
```

##basic usage

```php
	$user			= new user();
	//$user->id		= 1;
	$user->name		='';
	$user->password	= hashFunction('password');
	$user->created  = 'CURRENT_TIMESTAMP';
	$user->insertDb();
```

it will insert in table user the following values
id=1
password='hashstring'
lcreated='2016-10-12 14:00:03'

name and phone will be ignored in the insert
and the mysql will fail or set the default values


To force name to be empty

```php
$user->name = 'EMPTY';
```


To force name to be NULL

```php
$user->phone = NULL
```

	$user->updateDb();//Will set in db name = '' and phone = NULL
	$user->name	= 'Jhon Doe';
	$user->updateDb('name') //Only updates name
	$user->deleteDb();//delete the record with id = 1

##Utils:

		//query
```php
$mysqli_res = DBTable::query( $sql_query );
```

		//getArrayFromQuery( $sql, $keyIndex, $mysqli_connection );
```php
$arrayOfUsers	= getArrayFromQuery( 'SELECT * FROM users','id');
```
		//$arrayOfUsers   = array( 3=>new user(..., 15=> new user(..., .. )

		//createFromQuery($sql, $connection = NULL)
		$user			= user::createFromQuery('Select user from ....');

You can select from mulitple tables or select the same table multiple times

```php
$sql_bunch_of_tables = 'SELECT '.user::getUniqSelect().'
		,'.user::getUniqSelect().'
		,'.user::getUniqSelect('approvedByUser').'
		,'.image::getUniqSelect().'
		,'.image::getUniqSelect('banerImage').'
		,'.tabl::queUniqSelect().'
	FROM user
	JOIN user as approvedByUser ON ...
	JOIN image as profileImage ON ....
	JOIN image as bannerImage ON ....
	JOIN tabl ON ...
	....';

$res_bunch_of_tables	= DBTable::query( $sql_bunch_of_tables );

while( $row = $res_bunch_of_tables->fetch_assoc() )
{
	//createFromUniqArray($array,$asTableName=null)
	$user			= user	::createFromUniqArray( $row );
	$approvedByUser	= user	::createFromUniqArray( $row, 'aprovedByUser' );
	$profileImage	= image	::createFromUniqArray( $row, 'profileImage' );
	$bannerImage	= image	::createFromUniqArray( $row, 'bannerImage' );
	$tbl			= tbl	::createFromUniqArray( $row );
}
```


####

	function getLastQuery()
	function setWhereString($only_id = true )
	function setWhereStringNonEmptyValues()
	function assignFromArray()
		$user->assignFromArray( $_POST );//Assign all the non empty values from post
		$user->assignFromArray( $_POST, 'name','id' ) //Only assign the non empty values from name and id
		$user->assignFromArray( $_POST, array('name','id') ) //same as previous


	public static function createFromArray($array, $connection=NULL)
	function getInsertSql()
	function deleteFromDb()
	function getUpdateSql( $fieldsToUpdate = array() )

	function toArrayExclude()
	function toArray()
		$user->toArray()  // array( 'id'=>1, 'name'=>'Jhon doe', 'age'=>'20','password'=>'kdlhdshheslks',phone='555-555-5555);
		$user->toArray('id','name') //array( 'id'=1,'name'=>'jhon doe','phone'='555-555-5555)
		$user->toArray(array('id','name') ) //same as previous
		$user->toArrayExclude('password') //array( 'id'=>1, 'name'=>'Jhon doe', 'age'=>'20','phone'='555-555-5555');
		$user->toArrayExclude(array( 'password') ) //Same as previous

		//if attr flags defined password as no exportable
		$user->toArrayExclude() //array( 'id'=>1, 'name'=>'Jhon doe','age'=>20,'phone'='555-555-5555');
		$user->toArrayExclude('age') //array( 'id'=>1, 'name'=>'Jhon doe',,'phone'='555-555-5555');
		$user->toArrayExclude(array('age')) //Same as previous

	function load( $only_id = true, $for_update = false )

	DBTable::setAttrFlags
	(
		array
		(
			'user'=>array
			(
				//is ignored on insert if is set
				//Throw validation excepton on $user->validateUpdate() if is not set or isnt a integer o integerstring
				'id'		=> DBTable::INT_VALUE | DBTable::IGNORE_ON_INSERT | DBTable::REQUIRED_ON_UPDATE
				//Throws validation excepton on $user->validateInsert() if is not set
				,'name' 	=> DBTable::STRING_VALUE | DBTable::REQUIRED_ON_INSERT
				//sets the timestamp on insert but cant be assigned or modified
				,'created'	=> DBTable::TIMESTAMP_VALUE | DBTable::TIMESTAMP_ON_CREATE | DBTable::IGNORE_ON_INSERT | DBTable::IGNORE_ON_UPDATE;
				//Throws validation if is not a phone or if is not set on insert
				,'phone'	=> DBTable::PHONE_VALUE | DBTable::REQUIRED_ON_INSERT
			)
		)
	);
	function validateInsert()
	function validateUpdate()



	function validate( $required_on_save, $ignore_on_save )
	function validateField( $key, $flags, $params )
	public static function importDbSchema( $namespace = '')

##Example Insert/Update

```php

//includes here

use \akou\ApiResponse;
use \akou\DBTable;
use \akou\Utils;
use \akou\LoggableException;
use \akou\SystemException;
use \akou\ValidationException;
use \akou\SessionException;

$apiResponse	= new ApiResponse();

try
{
	global $mysqli;

	$requestingUser	= API::getUserFromSessionHash('');

	if(! $requestingUser )
		throw new SessionException('Session has expired');

	$invoice		= new invoice();
	$isUpdate		= !empty( $_POST['id'] );

	if( $isUpdate )
	{
		$invoice->id	= $_POST['id'];

		if( !$invoice->load() )
			throw new NotFoundException
			(
				'The item was not found'
				,'User was looking for id '.$_POST['id']
			);
	}

	$invoice->assignFromArray( $_POST );

	if( $isUpdate )
	{
		$invoide->validateUpdate();

		if( !$invoice->updateDb() )
		{
			throw new SystemException
			(
				"An error occourred please try again later"
				,$invoice->getLastQuery()
			);
		}
	}
	else
	{
		$invoice->validateInsert();

		if( !$invoice->insertDb() )
		{
			throw new SystemException
			(
				"An error occourred please try again later"
				,$invoice->getLastQuery()
			);
		}
	}

	$apiResponse->setData( $invoice->toArrayExclude() );
	$apiResponse->setResult( 1 );
	$apiResponse->output();
}
catch(\Exception $e)
{
	$apiResponse->setError( $e );
	$apiResponse->output();
}

$apiResponse->setMsg('An error occurred please try again later');
$apiResponse->output();
```
