<?php
//Never set LOG_LEVEL != LOG_LEVEL_TRACE inside this class
//it breaks the internet
namespace AKOU;


class DBTable
{

	public static $connection	= NULL;
	public static $_attrFlags	= array();


	const UNSET_TRIMED_VALUES	= 1;
	const UNSET_ZEROS			= 2;
	const UNSET_NULLS			= 4;
	const UNSET_BLANKS			= 8;
	const UNSET_INVALID_DATES	= 16;

	const UNSET_ALL				= 31;
	const UNSET_ALL_BUT_ZEROS	= 29;
	const UNSET_ALL_BUT_NULLS	= 27;

	const TRIM_ON_SAVE			= 1;
	const INT_VALUE				= 2;	// ✓
	const STRING_VALUE			= 5;	// ✓
	const EMAIL_VALUE			= 9; 	//8 + 1 TRIM_ON_SAVE ✓
	const DOMAIN_VALUE			= 17; 	//16 + 1 TRIM_ON_SAVE ✓
	const URL_VALUE				= 33; 	//32 + 1 TRIM_ON_SAVE ✗	//Not implemented YET
	const TIMESTAMP_VALUE		= 64; 	// ✓
	const PHONE_VALUE			= 129; 	//128 + 1 TRIM_ON_SAVE ✓
	const ENUM_VALUE			= 256; 	// ✓
	const FLOAT_VALUE			= 512; 	// ✓
	const IGNORE_ON_INSERT		= 1024; //
	const IGNORE_ON_UPDATE		= 2048;
	const REQUIRED_ON_INSERT	= 4096;
	const REQUIRED_ON_UPDATE	= 8192;

	const CREDIT_CARD_VALUE	 	= 16384;
	const DIGITS_VALUE			= 32768; //ONLY DIGITS STRINGS like for example '00012'; OR
	const TIMESTAMP_ON_CREATE	= 65536; //✓
	const DONT_EXPORT_EXTERNAL	= 131072;
	const TIME_VALUE			= 262144;
	const INSERT_EMPTY_DEFAULT	= 524288; //For blob,text,json,etc without default value
	const DATE_VALUE			= 1048576;


	 //Dont add to array when is calle $obj->toArrayExclude()
	//const NO_NULL				=	131072; //Cant be set 'NULL'	//not implemented yet
	//const NO_EMPTY				=	262144; //Cant be set 'EMPTY' //cant be set 'NULL' OR 'EMPTY'

	//
	const DISCOUNTINUED		 =	131072; //DO_NOT_EXPORT_EXTERNAL
	const IS_SENSITIVE_DATA	 =	131072; //DO_NOT_EXPORT_EXTERNAL

	const REQUIRED_ON_SAVE		=	6144;//REQUIRED_ON_INSERT |	REQUIRED_ON_UPDATE;
	const IGNORE_ON_SAVE		=	1536;//IGNORE_ON_INSERT	|	IGNORE_ON_UPDATE;	//No sav ed on insert or update //only read value like an autoincrement or on update CURRENT_TIMESTAMP
	//const NO_EXPORT

/*
20 = 1
21 = 2
22 = 4
23 = 8
24 = 16
25 = 32
26 = 64
27 = 128
28 = 256
29 = 512
210 = 1024
211 = 2048
212 = 4096
213 = 8192
214 = 16384
215 = 32768
216 = 65536
217 = 131072
218 = 262144
219 = 524288
220 = 1048576
221 = 2097152
222 = 4194304
223 = 8388608
224 = 16777216
225 = 33554432
226 = 67108864
227 = 134217728
228 = 268435456
229 = 536870912
230 = 1073741824
231 = 2147483648
232 = 4294967296
*/

	var $_sqlCmp='';
	var $_lastQuery;
	var $_conn;

	public static function init($host, $user, $password ,$db)
	{
		$mysqli = new \mysqli($host, $user, $password, $db );

		if( $mysqli->connect_errno )
		{
			echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
			exit();
		}

		$mysqli->query("SET NAMES 'utf8'");
		$mysqli->set_charset('utf8');

		DBTable::$connection				= $mysqli;
	}

	function __construct( $connection = NULL )
	{
		//parent::__construct();
		if( empty( $connection ) )
		{
			$this->_conn	= self::$connection;
		}
		else
			$this->_conn = $connection;
	}

	public static function autocommit( $autocommit = TRUE )
	{
		self::$connection->autocommit( $autocommit );
	}

	public static function commit($flags=0, $name=NULL)
	{
		return self::$connection->commit( $flags=0,$name);
	}

	public static function rollback( $flags=0,$name =NULL)
	{
		self::$connection->rollback( $flags, $name );
	}

	public static function escape( $param )
	{
		return self::$connection->real_escape_string( $param );
	}

	static function escapeCSV( $string, $mysqli = NULL )
	{
		$conn	= $mysqli ?: self::$connection;
		$array	= str_getcsv( $string );

		return self::escapeArrayValues( $array );
	}

	static function escapeArrayValues( $array, $mysqli = NULL  )
	{
		if( count( $array ) === 0 ) return "";

		$conn			= $mysqli ?: self::$connection;
		$escapedValues	= array();

		foreach( $array as $value )
		{
			$escapedValues[] = $conn->real_escape_string( $value );
		}

		return '"'.implode( '","', $escapedValues ).'"';
	}

	public static function getTotalRows($conn = NULL)
	{
		$mysqli		= empty( $conn ) ? self::$connection : $conn;
		$resTotal	= $mysqli->query('SELECT FOUND_ROWS()');

		if( !$resTotal )
			throw new SystemException
			(
				'An error occurred please try again later'
				,'Make sure SQL_CALC_FOUND_ROWS was added to the previous query'
			);

		$totalRow	= $resTotal->fetch_row();
		return $totalRow[ 0 ];
	}

	public static function query( $sql_query )
	{
		return self::$connection->query( $sql_query );
	}

	public static function getBaseClassName()
	{
			$class = explode('\\', get_called_class());
			return array_pop($class);
	}

	public static function setAttrFlags( $array=array() )
	{
		if( is_array( $array ) )
			self::$_attrFlags = $array;
	}
	/*
	 * $dictionaryIndex the index dictionary example dictionary by id
	 * if false return a simple array
	*/
	public static function getArrayFromQuery( $sql, $dictionaryIndex = FALSE, $connection = NULL)
	{
		$className 	= static::getBaseClassName();
		$asArray	= $className === 'DBTable';

		$conn 	= $connection ?: self::$connection;
		$resSql = $conn->query( $sql );

		if( !$resSql )
		{
			throw new SystemException( 'An error occours please try gain later', $sql );
		}

		$result = array();

		while( $row = $resSql->fetch_assoc() )
		{
			if( $asArray )
			{
				if( $dictionaryIndex )
				{
					if( !empty( $_obj->{ $dictionaryIndex } ) )
						$result[ $row[ $dictionaryIndex ] ] =  $row;
				}
				else
				{
					$result[] =  $row;
				}
			}
			else
			{

				$_obj = static::createFromArray( $row );

				if( $dictionaryIndex && !empty( $_obj->{ $dictionaryIndex } ) )
				{
					$result[ $_obj->{$dictionaryIndex} ] = $_obj;
				}
				else
					$result[] = $_obj;
			}
		}

		return $result;
	}


	public static function createFromQuery( $query, $connection = NULL)
	{

		$conn = $connection ?: self::$connection;

		$result = $conn->query( $query );

		if( $result && $row = $result->fetch_assoc( ))
		{
			$_obj = new static( $connection );
			$_obj->assignFromArray( $row );
			$_obj->setWhereString( isset( $_obj->id ) );
			return $_obj;
		}
		return FALSE;
	}

	public static function createFromUniqArray($array,$asTableName=null)
	{

		$_obj = new static();
		$_obj->assignFromUniqueSelect( $array, $asTableName );

		return $_obj;
	}

	function assignFromUniqueSelect( $array, $asTableName=null )
	{
		$_clas_name	= $asTableName == null ?self::getBaseClassName() : $asTableName;
		$c			= 0;
		$obj		 = $this;
		$array_names = array('_sqlCmp','_lastQuery','_attrFlags','_conn');

		foreach ($obj as $name => $value)
		{
			if( in_array($name , $array_names ) )
				continue;

			if(isset($array[$_clas_name.'__'.$c]))
			{
					$this->{$name} =	$array[ $_clas_name.'__'.$c ];
			}
			$c++;
		}
		$obj->setWhereString();
	}

	public static function getUniqSelect($asTableName=null)
	{
		$fields		 = array();
		$c				= 0;
		$_clas_name	 = $asTableName == null ? self::getBaseClassName() : $asTableName;
		$obj			= new static();

		$array_names = array('_sqlCmp','_lastQuery','_attrFlags','_conn');

		foreach ($obj as $name => $value)
		{
			if( in_array( $name , $array_names ) )
				continue;

			$fields[] ='`'.$_clas_name.'`.`'.$name.'` AS '.$_clas_name.'__'.($c++);
		}
		return join(',',$fields);
	}

	function getLastQuery()
	{
		return $this->_lastQuery;
	}

	function setWhereString($only_id = true )
	{
		$cmp_a		= array();
		$name_class = get_class( $this );

		if( $only_id && property_exists($name_class,'id') && isset( $this->id ))
		{
			$this->_sqlCmp = '`id` = "'.$this->_conn->real_escape_string( $this->id ).'"';
			return;
		}

		$array_names = array('_sqlCmp','_lastQuery','_attrFlags','_conn');

		foreach ($this as $name => $value)
		{
			if( in_array( $name , $array_names ) )
				continue;

			if( property_exists($name_class,$name) && isset($this->{$name}) )
			{
				if( $this->{$name} === NULL )
					$cmp_a[] = '`'.$name.'` IS NULL';
				else
					$cmp_a[] = '`'.$name.'` = "'.$this->_conn->real_escape_string( $value ).'"';
			}
		}
		$this->_sqlCmp = implode(' AND ',$cmp_a );
	}

	function setWhereStringNonEmptyValues()
	{
		$cmp_a			= array();
		$array_names	= array('_sqlCmp','_lastQuery','_attrFlags','_conn');

		$name_class= get_class($this);

		foreach ($this as $name => $value)
		{
			if( in_array( $name, $array_names ) )
				continue;

			if( property_exists($name_class,$name) && isset($this->{$name}) )
			{
				if( !empty( $value )	)
				{
					$cmp_a[] = '`'.$name.'` = "'.$this->_conn->real_escape_string( $value ).'"';
				}
			}
		}
		$this->_sqlCmp = implode(' AND ',$cmp_a );
	}

	/*
	*	@return int number of assignations or FALSE on error. 0 asignations !== FALSE
	*		example
				//only assign vendor_shuttle_id, airport_id and user_id ignores the other index on $_POST
				$booking->assignFromArray($_POST,'vendor_shuttle_id','airport_id','user_id');
				//Assign all the values from $_POST
				$booking->assignFromArray( $_POST );
	*/

	function assignFromArray()
	{
		$num_args		= func_num_args();
		$indexes		= array();
		$array			= func_get_arg( 0 );

		if( empty($num_args) || !is_array( $array ) )
			return FALSE;

		for($i=1;$i<$num_args;$i++)
		{
			$indexes[] = func_get_arg( $i );
		}

		$class_name	 = get_class($this);
		$array_names	= array('_sqlCmp','_lastQuery','_attrFlags','_conn');
		$i				= 0;
		foreach( $this as $name => $value)
		{

			if(
				!isset( $array[ $name ] )
				|| in_array( $name, $array_names )
				|| !property_exists( $class_name, $name )
				)

			{
				continue;
			}

			if( !empty( $indexes ) && ! in_array($name,$indexes,TRUE) )
				continue;

			$this->{$name} = $array[ $name ];
			$i++;
		}

		if( $i === 0 )
		{
			error_log('WARNING zero assigns from array '.get_class( $this ));
		}

		return $i;
	}

	public static function createFromArray($array, $connection=NULL)
	{
		$_obj = new static($connection);
		$_obj->assignFromArray( $array );
		$_obj->setWhereString();
		return $_obj;
	}


	function insertDb( $ignore = FALSE )
	{
		$this->_lastQuery	= $this->getInsertSql( $ignore );
		$result			= $this->_conn->query( $this->_lastQuery );
		$class_name		= get_class( $this );

		if($result && property_exists($class_name,'id') && !empty( $this->id )  )
		{
			$this->id = $this->_conn->insert_id;
		}

		$this->setWhereString();

		if( $this->_conn->error )
		{
			if( strpos( $this->_conn->error,'column') !== FALSE )
			{
				error_log( $this->_conn->error );
				$firstIndex	= strpos( $this->_conn->error,'\'')+1;
				$lastIndex 	= strrpos( $this->_conn->error,'\'');
				$varName = substr( $this->_conn->error,$firstIndex,$lastIndex-$firstIndex);
				error_log('Error in "'.$class_name.'"->'.$varName.' And values >>>"'.($this->{$varName} ).'"<<<<<');
			}
			else
			{
				error_log( $this->_conn->error );
			}
		}

		return $result;
	}

	function getInsertSql($ignore = FALSE)
	{
		$array_fields	= array();
		$array_values	= array();
		$class_name	 	= get_class($this);
		$array_names	= array('_sqlCmp','_lastQuery','_attrFlags','_conn');
		$arrayFlags	= empty( self::$_attrFlags[ self::getBaseClassName() ] ) ? FALSE : self::$_attrFlags[ self::getBaseClassName() ];

		foreach ($this as $name => $value)
		{
			if( property_exists($class_name,$name))
			{
				if( in_array( $name, $array_names ) )
				{
					continue;
				}

				$attr_flags		 	= 0;
				$validation_value	= array();

				if( !empty( $arrayFlags[ $name ] ) )
				{
					if( is_array( $arrayFlags[ $name ] ) )
					{
						if( !empty( $arrayFlags[$name]['flags'] ))
							$attr_flags		 = $arrayFlags[$name]['flags']?:0;

						if( !empty(	$arrayFlags[$name]['values'] ) )
							$validation_value	= $arrayFlags[$name];
					}
					else
					{
						$attr_flags = $arrayFlags[ $name ];
					}
				}

				/* HAS PRECENDENCE OVER IGNORE_ON_INSERT */
				if( ( $attr_flags & DBTable::TIMESTAMP_ON_CREATE ) != 0 )
				{
					$this->{$name}	= date('Y-m-d H:i:s');
					$array_values[] = '"'.$this->{$name}.'"';
					$array_fields[] = '`'.$name.'`';
					continue;
				}

				if( ( $attr_flags & DBTable::IGNORE_ON_INSERT )	!= 0 )
				{
					Utils::addLog
					(
						Utils::LOG_LEVEL_PARANOID
						,'DBTable'
						,'IGNORE Because is IGNORE ON INSERT '.self::getBaseClassName().'->'.$name
					);
					continue;
				}

				if( !isset( $this->{$name} ) )
				{
					if( ($attr_flags & DBTable::INSERT_EMPTY_DEFAULT) != 0 )
					{
						$this->{$name}	= '';
						$array_values[] = '""';
						$array_fields[] = '`'.$name.'`';
					}
					else
					{
						Utils::addLog
						(
							Utils::LOG_LEVEL_PARANOID
							,'DBTable'
							,'IGNORE Because is empty '.self::getBaseClassName().'->'.$name
						);
					}
					continue;
				}

				if( ( $attr_flags & DBTable::TIMESTAMP_VALUE ) != 0 )
				{
					if( !preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/",$this->{$name}))
					{
						$this->{$name} = date('Y-m-d H:i:s', strtotime($this->{$name}));
					}
				}
				elseif( ( $attr_flags & DBTable::DATE_VALUE ) != 0 )
				{
					if( !preg_match("/^\d{4}-\d{2}-\d{2}$/",$this->{$name}))
					{
						$this->{$name} = date('Y-m-d', strtotime($this->{$name}));
					}
				}

				$array_fields[] = '`'.$name.'`';

				if( $this->{$name} === NULL )
				{
					$array_values[] = ' NULL ';
				}
				else if( $value === 'CURRENT_TIMESTAMP')
				{
					$this->{$name}	= date('Y-m-d H:i:s');
					$array_values[] = '"'.$this->{$name}.'"';
				}
				else if( $value	=== '' )
				{
					$array_values[] = '""';
				}
				else
				{
					$new_value = $value;
					if( ($attr_flags & DBTable::TRIM_ON_SAVE)	!= 0 )
					{
						$new_value = trim( $value );
						$this->{$name} = $new_value;
					}

					$array_values[] = '"'.$this->_conn->real_escape_string( $new_value ). '"';
				}
			}
		}

		$sql_insert_fields = implode( ',',$array_fields);
		$sql_insert_values = implode( ',',$array_values);
		$sql_insert_string = 'INSERT'.($ignore ? ' IGNORE ' :' ').'INTO `'.self::getBaseClassName().'` ( '.$sql_insert_fields.' )
								VALUES ( '.$sql_insert_values.' )';

		return $sql_insert_string;
	}

	function deleteDb()
	{
		$this->setWhereStringNonEmptyValues();
		$sql = 'DELETE FROM `'.self::getBaseClassName().'` WHERE '.$this->_sqlCmp.' LIMIT 1';
		$this->_lastQuery = $sql;

		return $this->_conn->query( $sql );
	}

	function deleteFromDb()
	{
		deleteDb();
	}

	function updateDb()
	{
		$num_args		= func_num_args();
		$indexes		= array();

		if( $num_args )
		{
			$arg_list	= func_get_args();
			$indexes	= $arg_list;

			if( is_array( $arg_list[ 0 ] ) )
			{
				$indexes	= $arg_list[ 0 ];
			}
		}

		$this->_lastQuery	= $this->getUpdateSql( $indexes );
		return $this->_conn->query( $this->_lastQuery );
	}

	function getUpdateSql( $fieldsToUpdate = array() )
	{
		$_tmp			= $this;
		$update_array	= array();
		$name_class		= get_class( $this );
		$array_names	= array('_sqlCmp','_lastQuery','_attrFlags','_conn');
		$arrayFlags	= empty( self::$_attrFlags[ self::getBaseClassName() ] ) ? FALSE : self::$_attrFlags[ self::getBaseClassName() ];

		foreach ($_tmp as $name => $value)
		{
			if( in_array( $name, $array_names ) || !isset( $this->{$name} ) )
				continue;

			if( !empty( $fieldsToUpdate ) && !in_array( $name, $fieldsToUpdate ) )
				continue;

			if( property_exists( $name_class, $name ) )
			{
				$attr_flags			= 0;
				$validation_value	= array();

				if( !empty( $arrayFlags[ $name ]) )
				{
					if( is_array( $arrayFlags[ $name ] ) )
					{
						$attr_flags			= $arrayFlags[$name]['flags']	?: 0;
						$validation_value	= $arrayFlags[$name]			?: array();
					}
					else
					{
						$attr_flags			= $arrayFlags[ $name ];
					}
				}


				if( ($attr_flags & DBTable::IGNORE_ON_UPDATE) != 0 )
					continue;

				if( $this->{$name} === 'NULL' )
				{
					$update_array[]		= '`'.$name.'`=NULL';
				}
				else if( $this->{$name} === 'EMPTY' )
				{
					$update_array[]		= '`'.$name.'`= ""';
				}
				else if( $value === 'CURRENT_TIMESTAMP')
				{
					$this->{$name}		= date('Y-m-d H:i:s');
					$update_array[]		= '`'.$name.'`="'.$this->{$name}.'"';
				}
				else
				{
					if( ($attr_flags & DBTable::TRIM_ON_SAVE)	!= 0 )
						$update_array[]	= '`'.$name.'`="'.$this->_conn->real_escape_string(trim($value)).'"';
					else
						$update_array[]	= '`'.$name.'`="'.$this->_conn->real_escape_string($value).'"';
				}
			}
		}

		$updatefields		= implode( ',' ,$update_array );
		return 'UPDATE `'.self::getBaseClassName().'` SET '.$updatefields.' WHERE '.$this->_sqlCmp.' LIMIT 1';
	}

	function toArrayExclude()
	{
		$num_args		= func_num_args();
		$indexes		= array();

		if( $num_args )
		{
			$arg_list	= func_get_args();
			$indexes	= $arg_list;

			if( is_array( $arg_list[ 0 ] ) )
			{
				$indexes	= $arg_list[0];
			}
		}

		$_array = array();
		$obj	= $this;

		$array_names = array('_sqlCmp','_lastQuery','_attrFlags','_conn');
		$arrayFlags	= empty( self::$_attrFlags[ self::getBaseClassName() ] ) ? FALSE : self::$_attrFlags[ self::getBaseClassName() ];

		foreach ($obj as $name => $value)
		{
			if( in_array( $name,$array_names ) )
				continue;

			if(in_array( $name, $indexes ))
				continue;

			$flags = 0;

			if( !empty( $arrayFlags ) && isset( $arrayFlags[ $name ] ) )
			{
				$flags = $arrayFlags[ $name ];

				if( is_array( $arrayFlags[ $name ] ) )
					$flags	= $arrayFlags[$name]['flags']?:0;

				if( $flags & self::IS_SENSITIVE_DATA )
					continue;
			}

			if( $flags & self::INT_VALUE )
				$_array[ $name ] = intVal( $value );
			else if( $flags & self::FLOAT_VALUE )
				$_array[ $name ] = floatVal( $value );
			else
				$_array[ $name ] = $value;
		}
		return $_array;
	}

	function toArray()
	{
		$num_args		= func_num_args();
		$indexes		= array();

		if( $num_args )
		{
			$arg_list	= func_get_args();
			$indexes	= $arg_list;

			if( is_array( $arg_list[ 0 ] ) )
			{
				$indexes	= $arg_list[ 0 ];
			}
		}

		$_array = array();
		$obj	= $this;

		$arrayFlags	= empty( self::$_attrFlags[ self::getBaseClassName() ] ) ? FALSE : self::$_attrFlags[ self::getBaseClassName() ];
		$array_names = array('_sqlCmp','_lastQuery','_attrFlags','_conn');

		foreach ($obj as $name => $value)
		{
			if( in_array( $name ,$array_names ) )
				continue;

			if( !empty( $indexes ) && !in_array( $name, $indexes ))
				continue;

			$flags = 0;

			if( !empty( $arrayFlags ) && isset( $arrayFlags[ $name ] ) )
			{
				$flags = $arrayFlags[ $name ];

				if( is_array( $arrayFlags[ $name ] ) )
					$flags	= $arrayFlags[$name]['flags']?:0;
			}

			if( $flags & self::INT_VALUE )
				$_array[ $name ] = intVal( $value );
			else if( $flags & self::FLOAT_VALUE )
				$_array[ $name ] = floatVal( $value );
			else
				$_array[ $name ] = $value;
		}
		return $_array;
	}

	function load( $only_id = true, $for_update = false )
	{
		$this->setWhereString($only_id);

		if( $this->_sqlCmp != '' )
		{
			$_sql	= 'SELECT * FROM `'.self::getBaseClassName().'` WHERE '.$this->_sqlCmp.' LIMIT 1';

			if( $for_update )
				$_sql .= ' FOR UPDATE ';

			$this->_lastQuery = $_sql;
			$result = $this->_conn->query( $_sql );

			if( $result && $row = $result->fetch_assoc( ) )
			{
				$this->assignFromArray( $row );

				if( $only_id )
					$this->setWhereString();

				return TRUE;
			}
		}
		return FALSE;
	}

	function validateInsert()
	{
		$this->validate( DBTable::REQUIRED_ON_INSERT, DBTable::IGNORE_ON_INSERT);
	}

	function validateUpdate()
	{
		$this->validate( DBTable::REQUIRED_ON_UPDATE, DBTable::IGNORE_ON_UPDATE);
	}

	function validate( $required_on_save, $ignore_on_save, $alternateMsg = '' )
	{
		$class_name = get_class( $this );
		$arrayFlags	= empty( self::$_attrFlags[ self::getBaseClassName() ] ) ? FALSE : self::$_attrFlags[ self::getBaseClassName() ];
		$altMessage	= $alternateMsg ? $alternateMsg.' ' : '';

		if( empty( $arrayFlags ) )
			return;

		foreach( $arrayFlags as $key => $params )
		{
			if( !property_exists($class_name, $key ) )
			{
				throw new SystemException
				(
					$altMessage.'System Error '
					,'There is no property key "'.$key.'" in table '.$class_name.' report to developer inmediately '
				);
			}


			$attr_flags			= 0;
			$validation_value	= array();
			$params				= array();

			if( is_array( $arrayFlags[ $key ] ) )
			{
				if( !empty(	$arrayFlags[$key]['flags'] ))
					$attr_flags = $arrayFlags[$key]['flags'];

				if( !empty( $arrayFlags[$key] ) )
					$params	= $arrayFlags[$key];
			}
			else
			{
				$attr_flags = $arrayFlags[ $key ]?:0;
			}

			if( empty( $attr_flags ) || ($attr_flags & $ignore_on_save) )
				continue;


			if( empty( $this->{$key} ) || $this->{$key} === 'EMPTY' || $this->{$key} === 'NULL' )
			{
				if( ($attr_flags & $required_on_save) )
					throw new ValidationException
					(
						$altMessage.$key.' cant be empty '
						,'Automatic field validation'
					);
			}
			else
			{
				$this->validateField( $key, $attr_flags, $params );
			}
		}
	}

	function validateField( $key, $flags, $params, $alternateMsg = '' )
	{
		$altMessage	= $alternateMsg ? $alternateMsg.' ' : '';

		if( DBTable::INT_VALUE & $flags != 0 )
		{
			if( ! ctype_digit( (string)$this->{$key} ) )
			{
				throw new ValidationException($altMessage.$key.' is not a valid number ');
			}

			if( !empty( $params['min'] ) && intval( $this->{$key} ) < intval( $params['min'] ) )
			{
				throw new ValidationException
				(
					$alternateMsg.'The minimun value for '.$key.' is '.$params['min']
				);
			}

			if( !empty( $params['max'] ) &&  intval( $this->{$key} ) > intval( $params['max'] ) )
			{
				throw new ValidationException
				(
					$alternateMsg.'The maximun value for '.$key.' is '.$params['min']
				);
			}
		}
		elseif( ( DBTable::STRING_VALUE & $flags ) != 0 )
		{
			if( !empty( $params['min'] ) && mb_strlen( $this->{$key} ) < intval( $params['min'] ) )
			{
				throw new ValidationException
				(
					$altMessage.'The minimun value for '.$key.' is '.$params['min']
				);
			}
			if( !empty( $params['max'] ) && mb_strlen( $this->{$key} ) > intval( $params['max'] ) )
			{
				throw new ValidationException
				(
					$altMessage.'The maximun value for '.$key.' is '.$params['min']
				);
			}
		}
		elseif( ( DBTable::FLOAT_VALUE & $flags ) != 0 )
		{
			if( !is_numeric( $this->{$key} ) )
			{
				throw new ValidationException($altMessage.' '.$key.'"'.$this->{$key}.'" is not a valid float number');
			}
		}
		elseif( ( DBTable::PHONE_VALUE & $flags ) != 0 )
		{
			$tmp = array();

			if( preg_match_all( '/[0-9]/', $this->{$key}, $tmp ) < 10 )
			{
				throw new ValidationException($altMessage.$key.' "'.$this->{$key}.'" is not a valid phone number');
			}
		}
		elseif( ( DBTable::EMAIL_VALUE & $flags ) != 0 )
		{
			if (!filter_var($this->{$key}, FILTER_VALIDATE_EMAIL) === false)
			{
				throw new ValidationException($altMessage.$key.' "'.$this->{$key}.'" is not a valid email');
			}
		}
		elseif( ( DBTable::DOMAIN_VALUE & $flags ) != 0 )
		{
			if( !preg_match('/^([a-z0-9]([-a-z0-9]*[a-z0-9])?\\.)+((a[cdefgilmnoqrstuwxz]|aero|arpa)|(b[abdefghijmnorstvwyz]|biz)|(c[acdfghiklmnorsuvxyz]|cat|com|coop)|d[ejkmoz]|(e[ceghrstu]|edu)|f[ijkmor]|(g[abdefghilmnpqrstuwy]|gov)|h[kmnrtu]|(i[delmnoqrst]|info|int)|(j[emop]|jobs)|k[eghimnprwyz]|l[abcikrstuvy]|(m[acdghklmnopqrstuvwxyz]|mil|mobi|museum)|(n[acefgilopruz]|name|net)|(om|org)|(p[aefghklmnrstwy]|pro)|qa|r[eouw]|s[abcdeghijklmnortvyz]|(t[cdfghjklmnoprtvwz]|travel)|u[agkmsyz]|v[aceginu]|w[fs]|y[etu]|z[amw])$/i',$domain))
			{
				throw new ValidationException
				(
					$altMessage.$key.' "'.$this->{$key}.'" is not a valid domain name'
				);
			}
		}
		elseif( ( DBTable::TIMESTAMP_VALUE & $flags ) != 0 )
		{
			if( $this->{$key} == 'CURRENT_TIMESTAMP' )
				return;

			if( !preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/",$this->{$key}))
			{
				if(	strtotime( $this->{$key})===FALSE )
				{
					throw new ValidationException($altMessage.$key.'"'.$this->{$key}.'" is not a valid date time');
				}
			}
		}
		elseif( ( DBTable::DATE_VALUE ) & $flags != 0 )
		{
			if( !preg_match("/^\d{4}-\d{2}-\d{2}$/",$this->{$key}))
			{
				if(	strtotime( $this->{$key})===FALSE )
				{
					throw new ValidationException($altMessage.$key.'"'.$this->{$key}.'" is not a valid date time');
				}
			}
		}
		elseif( ( DBTable::TIME_VALUE ) & $flags != 0 )
		{

			if( !preg_match("/(2[0-3]|[01][0-9]):([0-5][0-9])/", $this->{$key}) )
			{
				throw new ValidationException($altMessage.$key.'"'.$this->{$key}.'" is not a valid time value');
			}
		}
		elseif( ( DBTable::ENUM_VALUE & $flags ) != 0 )
		{
			if(! in_array( $this->{$key}, $params['values'] ) )
			{
				throw new ValidationException
				(
					$altMessage.$key.' is not valid', 'Value is '.$this->{$key}.print_r( $params['values'],true)
				);
			}
		}
	}

	public static function importDbSchema( $namespace = '')
	{

		$res	= self::$connection->query( 'SHOW TABLES' );
		$tables = array();

		$phpCode = $namespace ? "namespace $namespace;\n": '';
		$phpCode.= 'use \akou\DBTable;';

		while( $row = $res->fetch_row()  )
		{
			$tableName	= $row[ 0 ];
			$phpCode	.= 'class '.$tableName.' extends \akou\DBTable'.PHP_EOL.'{'.PHP_EOL;

			$fieldsRes	= self::query( 'describe `'.self::$connection->real_escape_string( $tableName ).'`');

			while( $fieldRow = $fieldsRes->fetch_object() )
			{
				$phpCode .= '	var $'.$fieldRow->Field.';'.PHP_EOL;
			}

			$phpCode .= '}'.PHP_EOL;
		}

		eval( $phpCode ); //The evil one
		return $phpCode;
	}

	/*
		$row	= array();
		$stmt	= DBTable::getStmtBindResult('Select * from XXX whre yyy',$row );

		while( $stmt->fetch() )
		{
			// do something with $row
		}

		$stmt->close();
	 */
	public static function getStmtBindResult( $query,&$row,$mysqli=NULL )
	{
		$conn			= $mysqli ?: self::$connection;

		if( $stmt = $conn->prepare( $query ))
		{
			$stmt->execute();
			$meta = $stmt->result_metadata();

			while ($field = $meta->fetch_field())
			{
				$row[ $field->name ]	= '';
				$params[]				= &$row[ $field->name ];
			}

			call_user_func_array(array($stmt, 'bind_result'), $params);
			return $stmt;
		}

		return FALSE;
	}

	public static function getStmtBindRawRowResult( $query,&$row,&$row_header, $mysqli=NULL )
	{
		$conn			= $mysqli ?: self::$connection;
		$addHeader		= is_array( $row_header );
		$size			= 0;

		if( $stmt = $conn->prepare( $query ))
		{
			$stmt->execute();
			$meta = $stmt->result_metadata();

			$i = 0;
			while ($field = $meta->fetch_field())
			{
				if( $addHeader )
					$row_header[] 		= $field->name;

				$row	[] = '';
				$params	[] = &$row[ $i ];
				$i++;
			}
			//$stmt->bind_result( $row );
			call_user_func_array(array($stmt, 'bind_result'), $params );
			return $stmt;
		}

		return FALSE;
	}

	public  function search()
	{
		$cmp_a		= array();
		$name_class = get_class( $this );

		$array_names = array('_sqlCmp','_lastQuery','_attrFlags','_conn');

		foreach ($this as $name => $value)
		{
			if( in_array( $name , $array_names ) )
				continue;

			if( property_exists($name_class,$name) && isset($this->{$name}) )
			{
				if( $this->{$name} === 'NULL' )
					$cmp_a[] = '`'.$name.'` IS NULL';
				else
					$cmp_a[] = '`'.$name.'` = "'.$this->_conn->real_escape_string( $value ).'"';
			}
		}

		if( count( $cmp_a ) === 0 )
		{
			return FALSE;
		}

		$_sql	= 'SELECT * FROM `'.self::getBaseClassName().'` WHERE '.implode(' OR ',$cmp_a ).' LIMIT 1';

		$result = $this->_conn->query( $_sql );

		if( $result && $row = $result->fetch_assoc( ) )
		{
			$this->assignFromArray( $row );
			$this->setWhereString();
			return TRUE;
		}

		return FALSE;
	}

	public function unsetEmptyValues( $flag = DBTable::UNSET_ALL_BUT_ZEROS )
	{
		$obj			= $this;
		$array_names	= array('_sqlCmp','_lastQuery','_attrFlags','_conn');

		$unsetZeros		= ( $flag & DBTable::UNSET_ZEROS ) !== 0;
		$unsetNulls 	= ( $flag & DBTable::UNSET_ZEROS ) !== 0;
		$unsetBlanks	= ( $flag & DBTable::UNSET_BLANKS ) !== 0;
		$trimValues		= ( $flag & DBTable::UNSET_TRIMED_VALUES ) !== 0;
		$unsetInvalidDates = ( $flag & DBTable::UNSET_TRIMED_VALUES ) !== 0 ;

		foreach ($obj as $name => $value)
		{
			if( in_array($name , $array_names ) )
				continue;

			$trimValue = $trimValues ? $value : trim( $value );

			if( empty( $trimValue ) )
			{
				if( !$unsetZeros && ( $trimValue === 0 || $trimValue === "0" || $trimValue === 0.0 ))
					continue;

				if( !$unsetNulls && $value === NULL )
					continue;

				if( !$unsetBlanks && $trimValue === '' )
					continue;

				unset( $this->{ $name } );
			}
			else if( $unsetInvalidDates &&  $trimValue === '0000-00-00 00:00:00' )
			{
				unset( $this->{ $name } );
			}
		}
	}
}
