<?php
namespace AKOU;

class ArrayUtils
{

	static function groupByProperty($array,$prop)
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

	static function filterByValue( $array, $index, $value )
	{
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
}
