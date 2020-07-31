<?php
namespace AKOU;

class ArrayUtils
{

	static function getArguments()
	{
		$num_args		= func_num_args();
		$array			= func_get_arg( 0 );
		$indexes		= array();
		$resultIndex	= array();

		if( is_array( func_get_arg( 1 ) ) )
		{
			$indexes = func_get_arg( 1 );
		}
		else
		{
			for($i=1;$i<$num_args;$i++)
			{
				$indexes[] = func_get_arg( $i );
			}
		}
		return array("object"=>$array,'arguments'=>$indexes);
	}

	static function groupByProperty($array,$prop)
	{
		$result = array();
		foreach( $array as $item )
		{
			$key = static::getProperty( $item, $prop );
			if( isset( $result[ $key ] ) )
				$result[ $key ][] = $item;
			else
				$result[ $key ] = array( $item );
		}
		return $result;
	}

	static function getProperty($x,$prop)
	{
		if( is_object( $x ) )
		{
			if( isset( $x->{ $prop } ) )
				return $x->{ $prop };
			return null;
		}
		if( isset( $x[ $prop ]) )
		{
			return $x[ $prop ];
		}
		return NULL;
	}

	static function getItemsProperty($array,$property)
	{
		return static::itemsPropertyToArray( $array, $property );
	}
	static function itemsPropertyToArray($array,$property)
	{

		$result = array();
		foreach($array as $item)
		{
			if( is_object( $item ) )
			{
				if( !empty( $item->{ $property } ) )
						$result[] = $item->{ $property };
			}
			else if( !empty( $item[$property] ) )
			{
				$result[] = $item[$property];
			}
		}
		return $result;
	}

	static function getItemsProperties()
	{

		$args= func_get_args();
		return static::itemsPropertiesToArrays( ...$args);
	}

	static function itemsPropertiesToArrays()
	{
		$args = func_get_args();
		$props = static::getArguments( ...$args );

		$indexes	= $props['arguments'];
		$array		= $props['object'];

		foreach( $indexes as $index )
		{
			$resultIndex[ $index ] = array();
		}

		foreach( $array as $item )
		{
			foreach( $indexes as $index )
			{
				$value = ArrayUtils::getProperty( $item, $index );
				if( $value !== null )
				{
					$resultIndex[ $index ][ $value ] = 1;
				}
			}
		}

		$result = array();
		foreach( $indexes as $index )
		{
			$result[ $index ] = array_keys( $resultIndex[ $index ] );
		}
		return $result;
	}

	static function toAssociative( $array, $prop )
	{
		$result = array();
		foreach( $array as $item )
		{
			$key = is_object( $item ) ? $item->{$prop } : $item[ $prop ];
			if( isset( $result[ $key ] ) )
				$result[ $key ][] = $item;
			else
				$result[ $key ] = array( $item );

		}
		return $result();
	}

	/*
	  results = array(
		   0 => array('key1' => '1', 'key2' => 2, 'key3' => 3),
		   1 => array('key1' => '12', 'key2' => 22, 'key3' => 32)
		);

	  $nResults = filter_by_value($results, 'key2', '2');


	Output :
		array( 0 => array('key1' => '1', 'key2' => 2, 'key3' => 3));
	*/
	static function filterByValue( $array, $index, $value )
	{
		$newarray = array();
		$temp = array();

		if(is_array($array) && count($array)>0)
		{
			foreach(array_keys($array) as $key){
				$temp[$key] = $array[$key][$index];

				if ($temp[$key] == $value){
					$newarray[$key] = $array[$key];
				}
			}
		}
		return $newarray;
	}

	static function sortByIndexAsc($index,$array)
	{
		return usort( $array, function($a,$b) use($index ){
			$aa = ArrayUtils::getItemsProperty( $a, $index );
			$bb = ArrayUtils::getItemsProperty( $b, $index );
			return $bb < $aa ? 1 : -1;
		});
	}
}
