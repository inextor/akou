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

	static function getProperty($array,$prop)
	{
		if( isset( $prop ) )
		{
			return $array[ $prop ];
		}
		return NULL;
	}

	static function itemsPropertyToArray($array,$property)
	{
		$result = array();
		foreach($array as $item)
		{
			if( !empty( $item[$property] ) || !empty( $item->{ $property } ) )
			{
				$result[] = is_object( $item ) ? $item->{ $property } : $item[$property];
			}
		}
		return $result;
	}
	static function itemsPropertiesToArrays()
	{
		$array	= func_num_args( 0 );
		$num_args		= func_num_args();
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
				if( $isset( $item->{$index} ) || isset( $item[ $index ] ) )
				{
					$value = is_object( $item ) ? $item->{$index} : $item[$index];
					$resultIndex[ $index ][ $value ] = 1;
				}
			}
		}

		$result = array();
		foreach( $indexes as $index )
		{
			$result[ $index ] = array_keys( $resultIndex[ $index ] );
		}
	}
}
