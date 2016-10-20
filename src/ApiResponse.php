<?php

namespace AKOU;

/**
 * Clase muy basica para manejar la salida estandar de una peticion json en nuestro formato
 */
date_default_timezone_set('UTC');
include_once( __DIR__.'/LoggableException.php' );

class ApiResponse
{
	var $result;
	var $msg;
	var $data;
	var $timestamp;
	var $code;

	function ApiResponse( $argument = null )
	{
		$this->result	= 0;
		$this->msg		= '';
		$this->data		= array();
		$this->code		= 500;
	}

	function setError( $exception )
	{
		$this->result	= 0;
		$this->msg		= $exception->getMessage();

		if( $exception instanceof LoggableException )
		{
			$this->code		= $exception->getCode();
			if( Utils::isDebugEnviroment() )
				$this->data	= $exception->toArray();
		}
		else
		{
			$this->code		= 500;
			$this->msg		= 'An error occurred please try again later';
		}
	}

	function setResult( $value )
	{
		if( $value )
			$this->code	 = 200;

		return $this->result = $value;
	}

	function getResult( )
	{
		return isset( $this->result ) ? $this->result : FALSE;
	}

	function setMsg( $value )
	{
		return $this->msg	= $value;
	}

	function getMsg()
	{
		return isset( $this->msg ) ? $this->msg : NULL;
	}

	function setData( $value )
	{
		$this->data = $value;
	}

	function getData( )
	{
		return isset( $this->data ) ? $this->data : NULL;
	}

	/**
	*	Método para mandar a bufer (echo) el objeto con sus atributos usando json_encode
	**/
	function output( $isCORS = TRUE, $must_exit = true )
	{
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Content-type: application/json');

		$this->timestamp	= date('Y-m-d H:i:s');
		echo json_encode( $this );

		if( $must_exit )
			exit();
	}
}
