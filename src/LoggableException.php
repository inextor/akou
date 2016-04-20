<?php
namespace AKOU;

class LoggableException extends \Exception
{
	public $file;
	public $line;
	public $function;
	public $tecnical_message;
	public $date;

	public function toArray()
	{
		return array
		(
			'file'				=> $this->file
			,'line'				=> $this->line
			,'function'			=> $this->function
			,'message'			=> $this->getMessage()
			,'technical_message'	=> $this->tecnical_message
			,'date'				=> $this->date
		);
	}

	public function __construct( $message, $tecnical_message = '', $code = 0, Exception $previous = null)
	{
		parent::__construct($message, $code, $previous);

		$this->file				= '';
		$this->line				= '';
		$this->function			= '';
		$this->tecnical_message	= $tecnical_message;
		$this->date				= date('Y-m-d H:i:s');
		$bt						= debug_backtrace();


		$last = $bt[ 0 ];

		for($i=0; $i<count( $bt ); $i++ )
		{
			if( strpos( $bt[$i]['file'], 'DBTable') === FALSE )
			{
				$last = $bt[ $i ];
				break;
			}
		}

		$this->file		= $last['file'];
		$this->line		= $last['line'];
		$this->function	= $last['function'];

		self::addMysqlError();
		$string = print_r( $this->toArray(),true );

		$this->addLog( $string );
	}

	protected function addLog( $message )
	{
		Utils::addLog
		(
			Utils::LOG_LEVEL_ERROR
			,'LOG_WARN'
			,$message
		);
	}

	/**
	* @esta funcion escribe el error que podria ocasionar un query mal escrito
	**/
	public static function addMysqlError()
	{
		if( DBTable::$connection->error )
		{
			$datos	  = '';
			$arreglo	= debug_backtrace(  );

			if( count(  $arreglo  ) == 0 )
			{
				$datos  = 'No backtrace found';
			}
			else
			{
				if( count( $arreglo ) >= 4  )
				{
					$limite = 4;
				}
				else
				{
					$limite = count( $arreglo );
				}

				for( $i = 0; $i < count( $arreglo ); $i++ )
				{
					$subArreglo = $arreglo[  $i  ];
					$datosTmp	= ''
						. '-[ FILE: ' . $subArreglo[  'file'  ] . ' // '
						. 'LINE: '
						.$subArreglo[  'line'  ]
						.' // '
						. 'FUNCTION: '
						.$subArreglo[  'function'  ]
						.' // '
						. 'ARGS: '
						.print_r( $subArreglo[  'args'  ], true )
						. ' ]	-' .
					'';

					$datos = $datos . $datosTmp;
				}
			}

			Utils::addLog
			(
				Utils::LOG_LEVEL_ERROR
				,'MYSQL_ERROR'
				,'Error No.:'.DBTable::$connection->errno.' '. $datos . ': ' . DBTable::$connection->error
			);
		}
	}
};

class ValidationException extends LoggableException
{
	public function __construct( $message, $tecnical_message = '', $code = 422 , Exception $previous = null)
	{
		parent::__construct($message, $tecnical_message, $code, $previous);
	}
	protected function addLog( $message )
	{
		Utils::addLog
		(
			Utils::LOG_LEVEL_WARN
			,'LOG_WARN'
			,$message
		);
	}
}

class NotFoundException extends LoggableException
{
	public function __construct( $message, $tecnical_message = '', $code = 404 , Exception $previous = null)
	{
		parent::__construct($message, $tecnical_message, $code, $previous);
	}
	protected function addLog( $message )
	{
		Utils::addLog
		(
			Utils::LOG_LEVEL_WARN
			,'LOG_WARN'
			,$message
		);
	}
}

class SessionException extends LoggableException
{
	public function __construct( $message, $tecnical_message = '', $code = 401 , Exception $previous = null)
	{
		parent::__construct($message, $tecnical_message, $code, $previous);
	}

	protected function addLog( $message )
	{
		Utils::addLog
		(
			Utils::LOG_LEVEL_WARN
			,'LOG_WARN'
			,$message
		);
	}
}


class SystemException extends LoggableException
{
	public function __construct( $message, $tecnical_message = '', $code = 500, Exception $previous = null)
	{
		parent::__construct($message, $tecnical_message, $code, $previous);
	}

	protected function addLog($message)
	{
		Utils::addLog
		(
			Utils::LOG_LEVEL_ERROR
			,'LOG_ERROR'
			,$message.' '.$this->tecnical_message
		);
	}
}
