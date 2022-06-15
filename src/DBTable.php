<?php
//Never set LOG_LEVEL != LOG_LEVEL_TRACE inside this class
//it breaks the internet
namespace AKOU;


class DBTable
{

	public static $connection	= NULL;
	public static $_attrFlags	= array();
	public static $_parse_data_types = true;
	static $_control_variable_names = array( '_sqlCmp', '_lastQuery', '_attrFlags', '_conn', '_is_duplicated_error' );

	const STARTS_WITH_SYMBOL='^';
	const ENDS_WITH_SYMBOL='$';
	const LIKE_SYMBOL='~~';
	const CSV_SYMBOL=',';
	const LT_SYMBOL='<';
	const LE_SYMBOL='<~=';
	const GE_SYMBOL='>~=';
	const EQ_SYMBOL='';
	const GT_SYMBOL='>';
	const NOT_NULL_SYMBOL = '@';
	const NULL_SYMBOL = '_NULL';
	const DIFFERENT_THAN_SYMBOL	= '!';


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
	const EMAIL_VALUE			= 9;	//8 + 1 TRIM_ON_SAVE ✓
	const DOMAIN_VALUE			= 17;	//16 + 1 TRIM_ON_SAVE ✓
	const URL_VALUE				= 33;	//32 + 1 TRIM_ON_SAVE ✗	//Not implemented YET
	const TIMESTAMP_VALUE		= 64;	// ✓
	const PHONE_VALUE			= 129;	//128 + 1 TRIM_ON_SAVE ✓
	const ENUM_VALUE			= 256;	// ✓
	const FLOAT_VALUE			= 512;	// ✓
	const IGNORE_ON_INSERT		= 1024; //
	const IGNORE_ON_UPDATE		= 2048;
	const REQUIRED_ON_INSERT	= 4096;
	const REQUIRED_ON_UPDATE	= 8192;

	const CREDIT_CARD_VALUE		= 16384;
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
	const DISCOUNTINUED			=	131072; //DO_NOT_EXPORT_EXTERNAL
	const IS_SENSITIVE_DATA		=	131072; //DO_NOT_EXPORT_EXTERNAL

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
	var $_is_duplicated_error=false;

	public static function init( $host, $user, $password, $db )
	{
		$mysqli = new \mysqli( $host, $user, $password, $db );

		if( $mysqli->connect_errno )
		{
			echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
			exit();
		}

		$mysqli->query( "SET NAMES 'utf8'" );
		$mysqli->set_charset( 'utf8' );

		DBTable::$connection				= $mysqli;
		return $mysqli;
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

	public static function commit( $flags=0, $name=NULL )
	{
		return self::$connection->commit( $flags, $name );
	}

	public static function rollback( $flags=0, $name =NULL )
	{
		error_log( 'Rolling back' );
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

	static function escapeArrayValues( $array, $mysqli = NULL )
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

	public static function getValueFromQuery( $sql )
	{
		$result = DBTable::$connection->query( $sql );

		if( !$result )
			return NULL;

		$fields_info = DBTable::getFieldsInfo( $result );

		$row	= $result->fetch_assoc();
		if( $row === NULL )
			return NULL;

		$rowData = DBTable::getRowWithDataTypes( $row, $fields_info );
		$keys = array_keys( $rowData );
		return $rowData[ $keys[0] ];
	}

	public static function getAllProperties()
	{
		$args= func_get_args();
		return static::getAllPropertiesExcept( ...$args );
	}

	public static function getAllPropertiesExcept()
	{
		$indexes		= array();

		if( func_num_args() > 0 )
		{
			$array			= func_get_arg( 0 );
			$indexes = is_array( $array ) ? $array : func_get_args();
		}

		$obj = new static();
		$merged = array_merge( DBTable::$_control_variable_names, $indexes );
		$properties = array();
		foreach( $obj as $i=>$value )
		{
			if( !in_array( $i, $merged ) )
				$properties[] = $i;
		}
		return $properties;
	}

	public static function getTotalRows( $conn = NULL )
	{
		$mysqli		= empty( $conn ) ? self::$connection : $conn;
		$resTotal	= $mysqli->query( 'SELECT FOUND_ROWS()' );

		if( !$resTotal )
			throw new SystemException
			(
				'An error occurred please try again later',
				'Make sure SQL_CALC_FOUND_ROWS was added to the previous query'
			);

		$totalRow	= $resTotal->fetch_row();
		return intVal( $totalRow[ 0 ]);
	}

	public static function query( $sql_query )
	{
		return self::$connection->query( $sql_query );
	}

	public static function getBaseClassName()
	{
		$class = explode( '\\', get_called_class() );
		return array_pop( $class );
	}

	public static function setAttrFlags( $array=array() )
	{
		if( is_array( $array ) )
			self::$_attrFlags = $array;
	}

	public static function getArrayFromQueryGroupByIndex( $query, $index, $connection=NULL )
	{
		$className	= static::getBaseClassName();
		$asArray	= $className === 'DBTable';

		$conn	= $connection ?: self::$connection;
		$resSql	= $conn->query( $query );

		if( !$resSql )
		{
			throw new SystemException( 'An error occours please try gain later', $query );
		}

		$result = array();

		$fields_info = DBTable::getFieldsInfo( $resSql );

		while( $tmp_d = $resSql->fetch_assoc() )
		{
			$row = DBTable::getRowWithDataTypes($tmp_d,$fields_info );
			//$data = $asArray ? $row : $obj = static::createFromArray( $tmp_d );
			$data = $asArray ? $row : $obj = static::createFromArray( $row );

			if( isset( $result[ $row[ $index ] ]) )
			{
				$result[ $row[ $index] ][] = $data;
			}
			else
			{
				$result[ $row[ $index ] ] = array( $data );
			}
		}

		return $result;
	}

	public static function getFieldsInfo( $result )
	{
		$finfo = $result->fetch_fields();
		$field_info = array();

		foreach( $finfo as $val )
		{
			$field_info[ $val->name ] = $val->type;
		}
		return $field_info;
	}

	static function getRowWithDataTypes($row,$fields_info )
	{
		$result = array();
		foreach( $fields_info as $name=>$type )
		{
			if( !isset( $row[ $name ] ) || $row[ $name ] === null )
			{
				$result[ $name ] = null;
			}
			else
			{
				switch( $type )
				{
					case 16: //bit
					case 1: //tinyint bool
					case 2: //smallint
					case 3: //integer 3
					case 9: //mediumint
					case 8: //bigint serial
							$result[ $name ] = intVal( $row[ $name ] );
							break;
					case 4: //float
					case 5: //double
					case 246: //decimal numeric 246
							$result[ $name ] = floatVal( $row[ $name ] );
							break;
					default:
						$result[ $name ] = $row[ $name ];
						break;
				}
			}
		}
		return $result;
	}

	/*
	* $dictionary_index the index dictionary example dictionary by id
	* if false return a simple array
	*/
	static function getArrayFromQuery( $sql, $dictionary_index = FALSE, $connection = NULL )
	{
		$className	= static::getBaseClassName();
		$asArray	= $className === 'DBTable';

		$conn	= $connection ?: self::$connection;
		$resSql = $conn->query( $sql );

		if( !$resSql )
		{
			throw new SystemException( 'An error occours please try gain later', $sql );
		}

		$result = array();

		$types_info = DBTable::$_parse_data_types ? DBTable::getFieldsInfo( $resSql ): NULL;

		while( $data = $resSql->fetch_assoc() )
		{
			$row = DBTable::$_parse_data_types ? DBTable::getRowWithDataTypes( $data, $types_info ) : $data;

			if( $asArray )
			{
				if( $dictionary_index )
				{
					if( !empty( $row[ $dictionary_index ] ) )
						$result[ $row[ $dictionary_index ] ] = $row;
				}
				else
				{
					$result[] = $row;
				}
			}
			else
			{
				$_obj = static::createFromArray( $row );

				if( $dictionary_index && !empty( $_obj->{ $dictionary_index } ) )
				{
					$result[ $_obj->{$dictionary_index} ] = $_obj;
				}
				else
					$result[] = $_obj;
			}
		}

		return $result;
	}


	public static function createFromQuery( $query, $connection = NULL )
	{
		$conn = $connection ?: self::$connection;

		$result = $conn->query( $query );


		if( $result && $data = $result->fetch_assoc( ))
		{
			$types_info = DBTable::$_parse_data_types ? self::getFieldsInfo( $result ) : NULL;
			$row = DBTable::$_parse_data_types ? self::getRowWithDataTypes( $data, $types_info ) : $data;

			$_obj = new static( $connection );
			$_obj->assignFromArray( $row );
			$_obj->setWhereString( isset( $_obj->id ) );
			return $_obj;
		}
		return FALSE;
	}

	public static function createFromUniqArray( $array, $asTableName=null )
	{

		$_obj = new static();
		$_obj->assignFromUniqueSelect( $array, $asTableName );

		return $_obj;
	}

	function assignFromUniqueSelect( $array, $asTableName=null )
	{
		$_clas_name	= $asTableName == null ?self::getBaseClassName() : $asTableName;
		$c			= 0;
		$obj		= $this;

		foreach ( $obj as $name => $value )
		{
			if( in_array( $name, DBTable::$_control_variable_names ) )
				continue;

			if(array_key_exists( $_clas_name.'__'.$c, $array))
			{
				$this->{$name} =	$array[ $_clas_name.'__'.$c ];
			}
			$c++;
		}
		$obj->setWhereString();
	}

	public static function getUniqSelect( $asTableName=null )
	{
		$fields		= array();
		$c				= 0;
		$_clas_name	= $asTableName == null ? self::getBaseClassName() : $asTableName;
		$obj			= new static();

		foreach ($obj as $name => $value )
		{
			if( in_array( $name, DBTable::$_control_variable_names ) )
				continue;

			$fields[] ='`'.$_clas_name.'`.`'.$name.'` AS '.$_clas_name.'__'.($c++);
		}
		return join( ',', $fields );
	}

	function getLastQuery()
	{
		return $this->_lastQuery;
	}

	function setWhereString( $only_id = true )
	{
		$cmp_a		= array();
		$name_class = get_class( $this );

		if( $only_id && property_exists( $name_class, 'id' ) && isset( $this->id ) )
		{
			$this->_sqlCmp = '`id` = "'.$this->_conn->real_escape_string( $this->id ).'"';
			return;
		}

		foreach ( $this as $name => $value )
		{
			if( in_array( $name, DBTable::$_control_variable_names ) )
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

		$name_class= get_class($this);

		foreach ( $this as $name => $value )
		{
			if( in_array( $name, DBTable::$_control_variable_names ) )
				continue;

			if( property_exists( $name_class, $name ) && isset( $this->{$name} ) )
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
		$args		= func_get_args();
		$args		= ArrayUtils::getArguments(...$args);

		$array		= $args['object'];
		$indexes	= $args['arguments'];

		if( empty( $args ) || !is_array( $array ) )
			return FALSE;

		$class_name		= get_class($this);
		$i				= 0;
		foreach( $this as $name => $value )
		{
			if(
				//!(isset( $array[ $name ] ) && !is_null( $array[ $name ] ) )
				!( array_key_exists($name,  $array ) )
				|| in_array( $name, DBTable::$_control_variable_names )
				|| !property_exists( $class_name, $name )
			)

			{
				continue;
			}

			if( !empty( $indexes ) && ! in_array( $name, $indexes,TRUE ) )
				continue;

			$this->{$name} = $array[ $name ];
			$i++;
		}

		if( $i === 0 )
		{
			error_log( 'WARNING zero assigns from array '.get_class( $this ) );
		}

		return $i;
	}

	public static function create($array, $connection = NULL )
	{
		$_obj = new static( $connection);
		$_obj->assignFromArray( $array );
		if( $_obj->insertDb() )
			return $_obj;
		return NULL;
	}

	public static function createFromArray( $array, $connection=NULL )
	{
		$_obj = new static($connection);
		$_obj->assignFromArray( $array );
		$_obj->setWhereString();
		return $_obj;
	}

	function insert()
	{
		$args = func_get_args();
		return $this->insertDb( ...$args );
	}

	function insertDb( $ignore = FALSE )
	{
		$this->_lastQuery	= $this->getInsertSql( $ignore );
		$result			= $this->_conn->query( $this->_lastQuery );
		$class_name		= get_class( $this );

		if( $result && property_exists( $class_name, 'id' ) && empty( $this->id ) )
		{
			$this->id = $this->_conn->insert_id;
		}

		$this->setWhereString();

		if( $this->_conn->error )
		{
			$this->_is_duplicated_error = $this->_conn->errno == 1062;

			if( strpos( $this->_conn->error, 'column' ) !== FALSE )
			{
				$firstIndex	= strpos( $this->_conn->error,'\'' )+1;
				$lastIndex	= strrpos( $this->_conn->error,'\'' );
				$varName = substr( $this->_conn->error,$firstIndex,$lastIndex-$firstIndex);
				error_log( 'Error in "'.$class_name.'"->'.$varName.' And values >>>"'.($this->{$varName} ).'"<<<<<' );
			}
			else
			{
				error_log( $this->_conn->error );
			}
		}

		return $result;
	}

	function getInsertSql( $ignore = FALSE )
	{
		$array_fields	= array();
		$array_values	= array();
		$class_name		= get_class($this);
		$arrayFlags	= empty( self::$_attrFlags[ self::getBaseClassName() ] ) ? FALSE : self::$_attrFlags[ self::getBaseClassName() ];

		foreach ( $this as $name => $value )
		{
			if( property_exists($class_name,$name))
			{
				if( in_array( $name, DBTable::$_control_variable_names) )
				{
					continue;
				}

				$attr_flags			= 0;
				$validation_value	= array();

				if( !empty( $arrayFlags[ $name ] ) )
				{
					if( is_array( $arrayFlags[ $name ] ) )
					{
						if( !empty( $arrayFlags[$name]['flags'] ))
							$attr_flags	= $arrayFlags[$name]['flags']?:0;

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
					$this->{$name}	= date( 'Y-m-d H:i:s' );
					$array_values[] = '"'.$this->{$name}.'"';
					$array_fields[] = '`'.$name.'`';
					continue;
				}

				if( ( $attr_flags & DBTable::IGNORE_ON_INSERT )	!= 0 )
				{
					Utils::addLog
					(
						Utils::LOG_LEVEL_PARANOID,
						'DBTable',
						'IGNORE Because is IGNORE ON INSERT '.self::getBaseClassName().'->'.$name
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
							Utils::LOG_LEVEL_PARANOID,
							'DBTable',
							'IGNORE Because is empty '.self::getBaseClassName().'->'.$name
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
				else if( $value === 'CURRENT_TIMESTAMP' )
				{
					$this->{$name}	= date( 'Y-m-d H:i:s' );
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

		$sql_insert_fields = implode( ',', $array_fields );
		$sql_insert_values = implode( ',', $array_values );
		$sql_insert_string = 'INSERT'.( $ignore ? ' IGNORE ' :' ' ).'INTO `'.self::getBaseClassName().'` ( '.$sql_insert_fields.' )
			VALUES ( '.$sql_insert_values.' )';

		return $sql_insert_string;
	}

	function delete()
	{
		return $this->deleteDb();
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
		$this->deleteDb();
	}

	function update()
	{
		$args = func_get_args();
		return $this->updateDB( ...$args );
	}

	function updateDb()
	{
		$num_args		= func_num_args();
		$indexes		= array();

		if( $num_args )
		{
			$arg_list	= func_get_args();
			$indexes = is_array( $arg_list[ 0 ] ) ? $arg_list[0] : $arg_list;
		}

		$this->_lastQuery	= $this->getUpdateSql( $indexes );
		return $this->_conn->query( $this->_lastQuery );
	}

	function getUpdateSql( $fieldsToUpdate = array() )
	{
		$_tmp			= $this;
		$update_array	= array();
		$name_class		= get_class( $this );
		$arrayFlags	= empty( self::$_attrFlags[ self::getBaseClassName() ] ) ? FALSE : self::$_attrFlags[ self::getBaseClassName() ];

		foreach ( $_tmp as $name => $value )
		{
			if( in_array( $name, DBTable::$_control_variable_names ) || ( !isset( $this->{$name} ) && !is_null( $this->{$name} ) ) )
			{
				continue;
			}

			if( !empty( $fieldsToUpdate ) && !in_array( $name, $fieldsToUpdate ) )
			{
				continue;
			}

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
				{
					continue;
				}

				if( $this->{$name} === 'NULL' || $this->{$name} === NULL )
				{
					$update_array[]		= '`'.$name.'`=NULL';
				}
				else if( $this->{$name} === 'EMPTY' )
				{
					$update_array[]		= '`'.$name.'`= ""';
				}
				else if( $value === 'CURRENT_TIMESTAMP' )
				{
					$this->{$name}		= date('Y-m-d H:i:s' );
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

		$updatefields		= implode( ',', $update_array );
		return 'UPDATE `'.self::getBaseClassName().'` SET '.$updatefields.' WHERE '.$this->_sqlCmp.' LIMIT 1';
	}

	function toArrayExcluding()
	{
		$args = func_get_args();
		return $this->toArrayExclude( ...$args );
	}

	function toArrayExclude()
	{
		$num_args		= func_num_args();
		$indexes		= array();

		if( $num_args )
		{
			$arg_list	= func_get_args();
			$indexes = is_array( $arg_list[ 0 ] ) ? $arg_list[ 0 ] : $arg_list;
		}

		$_array = array();
		$obj	= $this;

		$arrayFlags	= empty( self::$_attrFlags[ self::getBaseClassName() ] ) ? FALSE : self::$_attrFlags[ self::getBaseClassName() ];

		foreach ($obj as $name => $value )
		{
			if( in_array( $name, DBTable::$_control_variable_names ) )
				continue;

			if(in_array( $name, $indexes ))
				continue;

			$flags = 0;

			if( !empty( $arrayFlags ) && isset( $arrayFlags[ $name ] ) )
			{
				$flags = $arrayFlags[ $name ];

				if( is_array( $arrayFlags[ $name ] ) )
					$flags	= $arrayFlags[ $name ][ 'flags' ] ?:0;

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

	public static function getAttributes()
	{
		$class_name	= get_called_class();
		$vars		= get_class_vars( $class_name );

		foreach( DBTable::$_control_variable_names as $value )
		{
			if( isset( $vars[ $value ] ) )
				unset( $vars[ $value ] );
		}

		return $vars;
	}

	function toArray()
	{
		$num_args		= func_num_args();
		$indexes		= array();

		if( $num_args )
		{
			$arg_list	= func_get_args();
			$indexes = is_array( $arg_list[ 0 ] ) ? $arg_list[ 0 ] : $arg_list;
		}

		$_array = array();
		$obj	= $this;

		$arrayFlags	= empty( self::$_attrFlags[ self::getBaseClassName() ] ) ? FALSE : self::$_attrFlags[ self::getBaseClassName() ];

		foreach ($obj as $name => $value )
		{
			if( in_array( $name, DBTable::$_control_variable_names ) )
				continue;

			if( !empty( $indexes ) && !in_array( $name, $indexes ))
				continue;

			$flags = 0;

			if( !empty( $arrayFlags ) && isset( $arrayFlags[ $name ] ) )
			{
				$flags	= $arrayFlags[ $name ];

				if( is_array( $arrayFlags[ $name ] ) )
					$flags	= $arrayFlags[ $name][ 'flags' ]?:0;
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

	function load( $only_id = true, $for_update = false, $with_data_types = false )
	{
		$this->setWhereString($only_id);

		if( $this->_sqlCmp != '' )
		{
			$_sql	= 'SELECT * FROM `'.self::getBaseClassName().'` WHERE '.$this->_sqlCmp.' LIMIT 1';

			if( $for_update )
				$_sql .= ' FOR UPDATE ';

			$this->_lastQuery	= $_sql;
			$result				= $this->_conn->query( $_sql );

			if( $result && $row = $result->fetch_assoc( ) )
			{
				if( DBTable::$_parse_data_types || $with_data_types )
				{
					$fields_info = static::getFieldsInfo( $result );
					$this->assignFromArray( static::getRowWithDataTypes( $row, $fields_info ) );
				}
				else
				{
					$this->assignFromArray( $row );
				}

				if( $only_id )
					$this->setWhereString();

				return TRUE;
			}
		}
		return FALSE;
	}

	function assignFromArrayExclude()
	{
		$args= func_get_args();
		$this->assignFromArrayExcluding( ...$args );
	}

	function assignFromArrayExcluding()
	{
		$args		= func_get_args();
		$args		= ArrayUtils::getArguments(...$args);

		$array		= $args[ 'object' ];
		$indexes	= $args[ 'arguments' ];

		if( empty( $args ) || !is_array( $array ) )
			return FALSE;

		$class_name	= get_class($this);
		$i				= 0;
		foreach( $this as $name => $value )
		{

			if(
				!array_key_exists(  $name ,$array )
				|| in_array( $name, DBTable::$_control_variable_names )
				|| in_array( $name, $indexes )
			)
			{
				continue;
			}

			$this->{$name} = $array[ $name ];
			$i++;
		}

		if( $i === 0 )
		{
			error_log('WARNING zero assigns from array '.get_class( $this ));
		}

		return $i;
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
					$altMessage.'System Error ',
					'There is no property key "'.$key.'" in table '.$class_name.' report to developer inmediately '
				);
			}


			$attr_flags			= 0;
			$validation_value	= array();
			$params				= array();

			if( is_array( $arrayFlags[ $key ] ) )
			{
				if( !empty(	$arrayFlags[ $key ][ 'flags' ] ))
					$attr_flags = $arrayFlags[ $key ][ 'flags' ];

				if( !empty( $arrayFlags[ $key ] ) )
					$params	= $arrayFlags[ $key ];
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
						$altMessage.$key.' cant be empty ',
						'Automatic field validation'
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
				throw new ValidationException( $altMessage.$key.' is not a valid number ' );
			}

			if( !empty( $params[ 'min' ] ) && intval( $this->{$key} ) < intval( $params[ 'min' ] ) )
			{
				throw new ValidationException
				(
					$alternateMsg.'The minimun value for '.$key.' is '.$params[ 'min' ]
				);
			}

			if( !empty( $params[ 'max' ] ) && intval( $this->{$key} ) > intval( $params[ 'max' ] ) )
			{
				throw new ValidationException
				(
					$alternateMsg.'The maximun value for '.$key.' is '.$params[ 'min' ]
				);
			}
		}
		elseif( ( DBTable::STRING_VALUE & $flags ) != 0 )
		{
			if( !empty( $params[ 'min' ] ) && mb_strlen( $this->{$key} ) < intval( $params[ 'min' ] ) )
			{
				throw new ValidationException
				(
					$altMessage.'The minimun value for '.$key.' is '.$params[ 'min' ]
				);
			}
			if( !empty( $params[ 'max' ] ) && mb_strlen( $this->{$key} ) > intval( $params[ 'max' ] ) )
			{
				throw new ValidationException
				(
					$altMessage.'The maximun value for '.$key.' is '.$params[ 'min' ]
				);
			}
		}
		elseif( ( DBTable::FLOAT_VALUE & $flags ) != 0 )
		{
			if( !is_numeric( $this->{$key} ) )
			{
				throw new ValidationException( $altMessage.' '.$key.'"'.$this->{$key}.'" is not a valid float number' );
			}
		}
		elseif( ( DBTable::PHONE_VALUE & $flags ) != 0 )
		{
			$tmp = array();

			if( preg_match_all( '/[0-9]/', $this->{$key}, $tmp ) < 10 )
			{
				throw new ValidationException( $altMessage.$key.' "'.$this->{$key}.'" is not a valid phone number' );
			}
		}
		elseif( ( DBTable::EMAIL_VALUE & $flags ) != 0 )
		{
			if ( !filter_var( $this->{$key}, FILTER_VALIDATE_EMAIL ) === false )
			{
				throw new ValidationException( $altMessage.$key.' "'.$this->{$key}.'" is not a valid email' );
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

			if( !preg_match( "/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/", $this->{$key} ) )
			{
				if(	strtotime( $this->{$key})===FALSE )
				{
					throw new ValidationException( $altMessage.$key.'"'.$this->{$key}.'" is not a valid date time' );
				}
			}
		}
		elseif( ( DBTable::DATE_VALUE ) & $flags != 0 )
		{
			if( !preg_match("/^\d{4}-\d{2}-\d{2}$/", $this->{$key}))
			{
				if(	strtotime( $this->{$key} )===FALSE )
				{
					throw new ValidationException( $altMessage.$key.'"'.$this->{$key}.'" is not a valid date time' );
				}
			}
		}
		elseif( ( DBTable::TIME_VALUE ) & $flags != 0 )
		{

			if( !preg_match("/(2[0-3]|[01][0-9]):([0-5][0-9])/", $this->{$key} ) )
			{
				throw new ValidationException($altMessage.$key.'"'.$this->{$key}.'" is not a valid time value' );
			}
		}
		elseif( ( DBTable::ENUM_VALUE & $flags ) != 0 )
		{
			if(! in_array( $this->{$key}, $params['values'] ) )
			{
				throw new ValidationException
				(
					$altMessage.$key.' is not valid', 'Value is '.$this->{$key}.print_r( $params[ 'values' ], true )
				);
			}
		}
	}

	public static function importDbSchema( $namespace = '' )
	{

		$res	= self::$connection->query( 'SHOW TABLES' );

		$phpCode = $namespace ? "namespace $namespace;".PHP_EOL : '';
		$phpCode.= 'use \akou\DBTable;'.PHP_EOL;

		while( $row = $res->fetch_row() )
		{
			$tableName	= $row[ 0 ];
			$phpCode	.= 'class '.$tableName.' extends \akou\DBTable'.PHP_EOL.'{'.PHP_EOL;

			$fieldsRes	= self::query( 'describe `'.self::$connection->real_escape_string( $tableName ).'`' );

			$field_rows = array();

			while( $fieldRow = $fieldsRes->fetch_object() )
			{
				$field_rows[] = $fieldRow->Field;
				//$phpCode .= '	var $'.$fieldRow->Field.';'.PHP_EOL;
			}

			sort($field_rows );
			foreach($field_rows as $field)
			{
				$phpCode .= '	var $'.$field.';'.PHP_EOL;
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
			$params = [];

			while ($field = $meta->fetch_field())
			{
				$row[ $field->name ]	= '';
				$params[]				= &$row[ $field->name ];
			}

			call_user_func_array( array( $stmt, 'bind_result' ), $params );
			return $stmt;
		}

		return FALSE;
	}

	public static function getStmtBindRawRowResult( $query,&$row,&$row_header, $mysqli=NULL )
	{
		$conn			= $mysqli ?: self::$connection;
		$addHeader		= is_array( $row_header );

		if( $stmt = $conn->prepare( $query ))
		{
			$stmt->execute();
			$meta = $stmt->result_metadata();

			$i = 0;
			$params = [];
			while ($field = $meta->fetch_field())
			{
				if( $addHeader )
					$row_header[]		= $field->name;

				$row	[] = '';
				$params	[] = &$row[ $i ];
				$i++;
			}
			//$stmt->bind_result( $row );
			call_user_func_array(array($stmt, 'bind_result' ), $params );
			return $stmt;
		}

		return FALSE;
	}

	public static function get($id, $for_update = FALSE )
	{
		$obj = new static();
		$obj->id = $id;
		if( $obj->load( true, $for_update ) )
		{
			return $obj;
		}
		return NULL;
	}

	public static function getSearchFirstSql($searchKeys, $for_update = false )
	{
		return static::getSearchSql( $searchKeys, $for_update, 1 );
	}

	public static function searchFirst($searchKeys,$as_objects=TRUE, $for_update = false )
	{
		$sql	= static::getSearchSql($searchKeys, $for_update, 1 );
		$info	= $as_objects ? static::getArrayFromQuery( $sql ) : DBTable::getArrayFromQuery( $sql );
		if( count( $info ) )
			return $info[0];
		return NULL;
	}

	static function endsWith( $haystack, $needle )
	{
		$length = strlen( $needle );

		if( !$length ) {
			return true;
		}
		return substr( $haystack, -$length ) === $needle;
	}

	/*
	*   searchFullComparison(array('user_id'.DBTABLE::NOT_NULL_SYMBOL => true, 'size>':12, 'age<=':18,'name$':' leon', 'name^':'next'));
	*
	public static function search($searchKeys,$as_objects=TRUE, $dictionary_index =FALSE, $for_update = FALSE )
	public static function getSearchSql( $searchKeys, $for_update = FALSE )
	*/
	public static function getSearchSql( $array, $for_update=FALSE, $limit=FALSE )
	{
		$properties = static::getAllProperties();
		$props = array
		(
			DBTABLE::LT_SYMBOL,
			DBTABLE::LE_SYMBOL,
			DBTABLE::GE_SYMBOL,
			DBTABLE::DIFFERENT_THAN_SYMBOL,
			DBTABLE::GT_SYMBOL,
			DBTABLE::LIKE_SYMBOL,
			DBTable::STARTS_WITH_SYMBOL,
			DBTable::ENDS_WITH_SYMBOL,
			DBTABLE::NOT_NULL_SYMBOL,
			DBTABLE::NULL_SYMBOL,
		);

		$valid_keys = array();
		foreach($properties as $p)
		{
			foreach($props as $pp)
			{
				$valid_keys[] = $p.''.$pp;
			}
		}

		$comparison_keys = array_keys( $array );
		$constraints = array();

		foreach($comparison_keys as $key )
		{
			//Comparing with regular equal or in array
			if( in_array( $key, $properties ) )
			{

				$value = $array[ $key ];

				if( is_array( $array[ $key ] ) )
				{
					if( count( $value ) )
					{
						$constraints[] = '`'.$key.'` IN ('.DBTable::escapeArrayValues( $value ).')';
					}
					else
					{
						//Is set but is empty is searching elements but are empty so none record must match
						$constraints[] = '1>2';
						break;
					}
				}
				else
				{
					$constraints[] = '`'.$key.'`= "'.DBTable::escape( $value ).'"';
				}
			} //Comparing with the dirty comparisons ,LIKE not null,etc.
			elseif( in_array( $key, $valid_keys ) )
			{
				if( static::endsWith(  $key, DBTable::LIKE_SYMBOL ) )
				{
					$f_key = str_replace( DBTable::LIKE_SYMBOL, "", $key );
					$constraints[] = '`'.$f_key.'` LIKE "%'.static::escape( $array[ $key ] ).'%"';
				}
				else if( static::endsWith( $key,DBTable::STARTS_WITH_SYMBOL ) )
				{
					$f_key = str_replace( DBTable::STARTS_WITH_SYMBOL, "", $key );
					$constraints[] = '`'.$f_key.'` LIKE "'.static::escape( $array[ $key ] ).'%"';
				}
				else if( static::endsWith( $key, DBTable::ENDS_WITH_SYMBOL ) )
				{
					$f_key = str_replace( $key, DBTable::ENDS_WITH_SYMBOL, "", $key );
					$constraints[] = '`'.$f_key.'` LIKE "'.static::escape( $array[ $key ] ).'%"';
				}
				else if( static::endsWith( $key, DBTable::LT_SYMBOL ) )
				{
					$f_key = str_replace( DBTable::LT_SYMBOL, "", $key );
					$constraints[] = '`'.$f_key.'` < "'.static::escape( $array[ $key ] ).'"';
				}
				elseif( static::endsWith( $key, DBTable::LE_SYMBOL ) )
				{
					$f_key = str_replace( DBTable::LE_SYMBOL, "", $key );
					$constraints[] = '`'.$f_key.'` <= "'.static::escape( $array[ $key ] ).'"';
				}
				elseif( static::endsWith( $key, DBTable::DIFFERENT_THAN_SYMBOL) )
				{
					$f_key = str_replace( DBTable::DIFFERENT_THAN_SYMBOL, "", $key );


					if( is_array( $array[ $key ] ) )
					{
						if( empty( $array[ $key ] ) )
						{
							$constraints[] = '2>1';
						}
						else
						{
							$constraints[] = '`'.$f_key.'` NOT IN ('.static::escapeArrayValues( $array[ $key ] ).')';
						}
					}
					else
					{
						$constraints[] = '`'.$f_key.'` != "'.static::escape( $array[ $key ] ).'"';
					}
				}
				elseif( static::endsWith( $key, DBTable::GE_SYMBOL ) )
				{
					$f_key = str_replace( DBTable::GE_SYMBOL, "", $key );
					$constraints[] = '`'.$f_key.'` >= "'.static::escape( $array[ $key ] ).'"';
				}
				elseif( static::endsWith( $key, DBTable::GT_SYMBOL ) )
				{
					$f_key = str_replace( DBTable::GT_SYMBOL, "", $key );
					$constraints[] = '`'.$f_key.'` > "'.static::escape( $array[ $key ] ).'"';
				}
				elseif( static::endsWith( $key, DBTable::NULL_SYMBOL ) && $array[ $key ] )
				{
					$f_key = str_replace( DBTable::NULL_SYMBOL, "", $key );
					$constraints[] = '`'.$f_key.'` IS NULL';
				}
				elseif( static::endsWith( $key, DBTable::NOT_NULL_SYMBOL ) && $array[ $key ] )
				{
					$f_key = str_replace( DBTable::NOT_NULL_SYMBOL, "", $key );
					$constraints[] = '`'.$f_key.'` IS NOT NULL';
				}
				else
				{
					error_log('Something happen PUT ATTENTION, it must never happen'. $key );
				}
			}
			else
			{
				$backtrace	= debug_backtrace(  );
				error_log('Key "'.$key.'" is not a comparison property, File'.$backtrace[1]['file'].'::'.$backtrace[1]['function'].' at line'.$backtrace[1]['line']);
			}
		}

		$where_str = count( $constraints ) > 0 ? join(' AND ',$constraints ) : '1';
		$sql = 'SELECT * FROM `'.self::getBaseClassName().'` WHERE '.$where_str ;

		if( $for_update )
			$sql .= ' FOR UPDATE ';

		if( $limit && is_int( $limit) )
			$sql .= ' LIMIT '.intval( $limit ).' ';

		return $sql;
	}

	public static function search($searchKeys,$as_objects=TRUE, $dictionary_index =FALSE, $for_update = FALSE, $limit=FALSE )
	{

		$sql = static::getSearchSql($searchKeys, $for_update, $limit);

		return $as_objects
			? static::getArrayFromQuery( $sql, $dictionary_index )
			: DBTable::getArrayFromQuery( $sql, $dictionary_index );
	}

	public static function searchGroupByIndex($searchKeys,$as_objects=TRUE, $dictionary_index =FALSE, $for_update = FALSE )
	{
		$sql = static::getSearchSql( $searchKeys, $for_update );

		return $as_objects
			? static::getArrayFromQueryGroupByIndex( $sql, $dictionary_index )
			: DBTable::getArrayFromQueryGroupByIndex( $sql, $dictionary_index );
	}

	public function unsetEmptyValues( $flag = DBTable::UNSET_ALL_BUT_ZEROS )
	{
		$obj			= $this;

		$unsetZeros		= ( $flag & DBTable::UNSET_ZEROS ) !== 0;
		$unsetNulls		= ( $flag & DBTable::UNSET_ZEROS ) !== 0;
		$unsetBlanks	= ( $flag & DBTable::UNSET_BLANKS ) !== 0;
		$trimValues		= ( $flag & DBTable::UNSET_TRIMED_VALUES ) !== 0;
		$unsetInvalidDates = ( $flag & DBTable::UNSET_TRIMED_VALUES ) !== 0 ;

		foreach ( $obj as $name => $value )
		{
			if( in_array( $name, DBTable::$_control_variable_names ) )
				continue;

			$trimValue = $trimValues ? $value : trim( $value );

			if( empty( $trimValue ) )
			{
				if( !$unsetZeros && ( $trimValue === 0 || $trimValue === "0" || $trimValue === 0.0 ) )
					continue;

				if( !$unsetNulls && $value === NULL )
					continue;

				if( !$unsetBlanks && $trimValue === '' )
					continue;

				unset( $this->{ $name } );
			}
			else if( $unsetInvalidDates && $trimValue === '0000-00-00 00:00:00' )
			{
				unset( $this->{ $name } );
			}
		}
	}

	public function getErrorNumber()
	{
		return $this->_conn->errno;
	}

	public function getError()
	{
		return $this->_conn->error;
	}
}
