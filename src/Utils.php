<?php

namespace AKOU;


if ( !isset( $HTTP_RAW_POST_DATA ) )
{
	$HTTP_RAW_POST_DATA = file_get_contents( 'php://input' );
}

class  Utils
{
	const LOG_LEVEL_NONE			= 0; //always goes to dblog reportin always ONLY on echo before header problems //never active just in case of error in production
	const LOG_LEVEL_ERROR			= 5; //always goes to dblog reporting always
	const LOG_LEVEL_WARN			= 6; //DEFAULT if to much logs change to ERROR
	const LOG_LEVEL_DEBUG			= 7; //
	const LOG_LEVEL_TRACE			= 8;
	const LOG_LEVEL_PARANOID		= 9;

	const DEBUG_EMAIL				= 'nextor@shuttlewizard.com';
	const DEBUG_USERNAME			= 'Nextor Leon';

	const GENERIC_MESSAGE_ERROR		= '';

	public static $LOG_LEVEL			= self::LOG_LEVEL_DEBUG;//
	public static $DB_MAX_LOG_LEVEL		= self::LOG_LEVEL_DEBUG;
	public static $DEBUG_SERVER			= FALSE;
	public static $DEBUG_VIA_ERROR_LOG	= FALSE;

	public static $DEBUG				= FALSE;
	public static $LOG_CLASS			= FALSE;
	public static $LOG_CLASS_KEY_ATTR	= 'keyword';
	public static $LOG_CLASS_DATA_ATTR	= 'data';

	static function isDebugEnviroment()
	{
		if( self::$DEBUG )
			return TRUE;

		if( self::isPrivateIp() )
			return TRUE;

		return FALSE;
	}

	/**
	 * Calculates the great-circle distance between two points, with
	 * the Vincenty formula.
	 * @param float $latitudeFrom Latitude of start point in [deg decimal]
	 * @param float $longitudeFrom Longitude of start point in [deg decimal]
	 * @param float $latitudeTo Latitude of target point in [deg decimal]
	 * @param float $longitudeTo Longitude of target point in [deg decimal]
	 * @param float $earthRadius Mean earth radius in [m]
	 * @return float Distance between points in [m] (same as earthRadius)
	 */
	static function vincentyGreatCircleDistance($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $miles = true)
	{
		$earthRadius = 6371000;
		// convert from degrees to radians
		$latFrom  = deg2rad($latitudeFrom);
		$lonFrom  = deg2rad($longitudeFrom);
		$latTo	= deg2rad($latitudeTo);
		$lonTo	= deg2rad($longitudeTo);

		$lonDelta = $lonTo - $lonFrom;
		$a		= pow(cos($latTo) * sin($lonDelta), 2) + pow(cos($latFrom) * sin($latTo) - sin($latFrom) * cos($latTo) * cos($lonDelta), 2);
		$b		= sin($latFrom)   * sin($latTo) + cos($latFrom) * cos($latTo) * cos($lonDelta);

		$angle	= atan2(sqrt($a), $b);
		$result   = $angle * $earthRadius;

		return $miles ? $result * .000621371 : $result;
	}
	static function isPrivateIp( $ip = NULL )
	{
		$test	= $ip;
		if( empty( $ip ) )
		{
			if(empty( $_SERVER['REMOTE_ADDR']) )
				return true;

			$test	= $_SERVER['REMOTE_ADDR'];
		}

		if( strpos($test, '127.0.0.1') === 0 )
			return TRUE;

		if( strpos($test, '192.168') === 0 )
			return TRUE;

		if( strpos($test, '172.16.') === 0 )
			return TRUE;

		if( $test=='::1')
			return TRUE;

		return FALSE;
	}
	/**
	* SOLO USAR PARA INFORMAR DE ERRORES,O EVENTOS
	* IMPORTANTES,O CONDICIONES QUE NO DEBERIAN DE SUCEDER
	* @param keywork: es para el tipo de error que se inserta en la DB.
	* @param description: es para poner la descripcion
	*
	* @return void
	**/
	public static function addLog($log_level, $keyword="", $message="")
	{
		if( $keyword == "" || $log_level > self::$LOG_LEVEL )
			return;

		if( self::$DEBUG_SERVER )
		{
			include_once( __DIR__.'/ChromePhp.php' );

			//switch( $log_level )
			//{
			//	case self::LOG_LEVEL_ERROR:
			//}

			\ChromePhp::log( $keyword, $message );
		}

		if( $log_level <= self::$DB_MAX_LOG_LEVEL && $log_level != self::LOG_LEVEL_PARANOID && self::$LOG_CLASS )
		{
			$log			= new self::$LOG_CLASS;
			$log->{ self::$LOG_CLASS_KEY_ATTR }		= $keyword;
			$log->{ self::$LOG_CLASS_DATA_ATTR }	= $message;
			$log->insertDb();
		}

		if( self::$DEBUG_VIA_ERROR_LOG )
		{
			error_log( $keyword );
			error_log( $message );
		}
	}

	static function getPasswordHash( $timestamp , $password, $salt = 'iLikeRandom' )
	{
	    $thehash    = sha1( $timestamp . $salt . $password );
	    return $thehash;
	}
	/**
	* Est funcion sirve para hace un dump de la variables que se reciben asi como algunas de ambiente
	* es util para debug y para eventos importantes, asi como la notificación de errores graves
	* @param $keyword el nombre con el que se va a guardar el log
	*/
	public static function addFullLog($log_level, $keyword )
	{
		if( isset( $HTTP_RAW_POST_DATA ) )
			$str_log	= '_CUSTOM_POST='.print_r( $HTTP_RAW_POST_DATA, true );
		else
		{
			global $HTTP_RAW_POST_DATA;
			$str_log	= '_CUSTOM_POST='.print_r( $HTTP_RAW_POST_DATA, true );
		}
		$str_log	.= PHP_EOL.'---------------------------'.PHP_EOL;
		$str_log	.= '_POST='.print_r( $_POST, true );
		$str_log	.= PHP_EOL.'---------------------------'.PHP_EOL;
		$str_log	.= '_GET='.print_r( $_GET, true );
		$str_log	.= PHP_EOL.'---------------------------'.PHP_EOL;
		$str_log	.= '_REQUEST='.print_r( $_REQUEST, true );
		$str_log	.= PHP_EOL.'---------------------------'.PHP_EOL;
		$str_log	.= '_SERVER='.print_r( $_SERVER, true );
		$str_log	.= PHP_EOL.'---------------------------'.PHP_EOL;

		if( !empty( $_SESSION ))
		{
			$str_log	.= PHP_EOL.'---------------------------'.PHP_EOL;
			$str_log	.= '_SESSION='.print_r( $_SESSION, true );
		}

		self::addLog( $log_level, $keyword, $str_log );
	}
	static function arrayToObject($array)
	{
		if( !is_array($array) )
		{
			return $array;
		}

		$object = new \stdClass();
		if (is_array($array) && count($array) > 0)
		{
			foreach ($array as $name => $value)
			{
				$name = trim($name);
				if (!empty($name))
				{
					$object->$name	= self::arrayToObject($value);
				}
			}
			return $object;
		}
		else
		{
			return FALSE;
		}
	}

	/*

	*/
	static function getRandomStrings($length=10, $charaters_include = 36)
	{
		$characters	= '0123456789abcdefghijklmnopqrstuvwxyz';
		$string		= '';
		$i			= $length+1;
		$str_length	= strlen( $characters )-1;

		while( $i-- )
		{
			$string .= $characters[mt_rand(0, $str_length)];
		}
		return $string;
	}
	static function curl_post( $url, $params = array(),$headers=array(),&$responseHeaders = NULL, &$status = NULL, &$responseCookies=NULL )
	{
		$http_params	= array();

		Utils::addLog
		(
			Utils::LOG_LEVEL_DEBUG
			,'DEBUG_CURL_POST_ARRAY'
			,print_r( $params, true )
		);

		//foreach($array_params as $key=>$value)
		//{
		//	$http_params[$key]	= $key."=".urlencode($value);
		//}

		//$post_body	= join('&',$http_params);

		Utils::addLog
		(
			Utils::LOG_LEVEL_DEBUG
			,'DEBUG_CURL_POST'
			,$url.PHP_EOL.print_r( $params, true )
		);

		$ch			= \curl_init();

		\curl_setopt( $ch, CURLOPT_URL,$url);
		\curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);

		if( !empty( $params ) )
		{
			$headers[]	= 'Content-Type: multipart/form-data';
			\curl_setopt( $ch, CURLOPT_POST, TRUE );

			if( !is_array( $params ) )
			{
				$length		= \strlen( $params );
				$headers[]	= 'Content-length: '.$length;
			}
			\curl_setopt( $ch, CURLOPT_POSTFIELDS, $params);
		}

		\curl_setopt( $ch, CURLOPT_HTTPHEADER,$headers);
		\curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT ,120);
		\curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, TRUE );
		\curl_setopt( $ch, CURLOPT_HEADER, 1 );
		\curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

		$result 		= \curl_exec($ch);
		$header_size	= \curl_getinfo($ch, CURLINFO_HEADER_SIZE);

		$header			= \substr($result, 0, $header_size);
		$body			= \substr($result, $header_size);
		$status			= \curl_getinfo( $ch , CURLINFO_HTTP_CODE );
		$headerArray	= explode("\n", $header );

		for( $i=1;$i<\count($headerArray); $i++ )
		{
			$h		= \trim( $headerArray[ $i ] );
			if( !$h )
				continue;

			$fields = explode( ': ', $h );
			if( isset( $fields[ 1 ] ) )
			{
				$responseHeaders[ \trim($fields[0]) ] = \trim($fields[1]);
			}
		}

		\curl_close( $ch );

		//Utils::addLog
		//(
		//	Utils::LOG_LEVEL_DEBUG
		//	,'DEBUG_CURL_RESPONSE'
		//	,print_r( $result, true )
		//);

		return $body;
	}

	/*
	Utils::LogIfFailsInsert
	(
		$an
		,Utils::LOG_LEVEL_ERROR
		,'Fails to insert agent notification, on userCancels Ride'
	);
	*/
	static function logIfFailsInsert( $dbObject, $log_level, $log_message )
	{
		$result = $dbObject->insertDb();

		if( !$result )
		{
			self::addLog
			(
				$log_level
				,'INSERTION_FAILS'
				,$log_message
			);
		}
		return $result;
	}
	static function convertTimestampToTimeZone( $dateTimestamp, $fromTimezone ='UTC', $toTimezone = 'America/Los_Angeles' ,$format = 'Y-m-d H:i:s' )
	{
		if( $dateTimestamp == NULL )
			return NULL;

		$local = \DateTime::createFromFormat( 'Y-m-d H:i:s', $dateTimestamp , new \DateTimeZone( $fromTimezone ) );
		$local->setTimeZone(new \DateTimeZone($toTimezone));

		return $local->format( $format ); // output: 2011-04-26 22:45:00
	}

	static function convertUTC2Timezone( $dateTimestamp, $toTimezone = 'America/Los_Angeles', $format = 'Y-m-d H:i:s'  )
	{
		return self::convertTimestampToTimeZone( $dateTimestamp,'UTC', $toTimezone, $format );
	}

	static function startsWith( $toFind, $haystack )
	{
	     $length = strlen($toFind);
	     return (substr($haystack, 0, $length) === $toFind);
	}

	static function endsWith( $toFind, $haystack)
	{
	    $length = strlen($toFind);

	    return $length === 0 || (substr($haystack, -$length) === $toFind);
	}
}
