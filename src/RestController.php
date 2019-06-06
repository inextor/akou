<?php

namespace AKOU;

class RestController
{
	function __construct()
	{
		$response = "";
	}

	function setAllowHeader()
	{
		$all_methods = ['POST','GET','PUT','OPTIONS','HEADER','PATCH','DELETE'];
		$methods = Array();
		header('Allow: '.join($methods,","));
	}

	function execute()
	{
		$method = strtolower( $_SERVER['REQUEST_METHOD'] );

		if( $method == 'HEADER' || $method == 'GET' )
		{
			if( isset( $this->get ) )
			{
				$this->get();
				header('Content-Length: '.strlen($this->response) );

				if( $method == 'GET' )
				{
					echo $this->response;
				}
			}
			else
			{
				$this->sendStatus(404);
				header('Content-length: 0');
			}
		}
		else if( is_callable( $this->{ $method } ) )
		{
			$this->{$method}();
		}
		else
		{
			http_response_code(405);
			$this->setAllowHeader();
		}
	}

	function getMethodParams()
	{
		if( $_SERVER["CONTENT_TYPE"] == 'application/x-www-form-urlencoded' )
		{
			parse_str( file_get_contents("php://input"), $post_vars);
			return $post_vars;
		}

		if( $_SERVER["CONTENT_TYPE"] == 'application/json' )
		{
			return json_decode( file_get_contents("php://input") );
		}
	}

	function sendStatus($code )
	{
		if( $code == 405 )
		{
			$this->setAllowHeader();
		}

		http_response_code( $code );
		return $this;
	}

	function json($value)
	{
		header( 'Content-type: application/json');
		$str = json_encode( $value );
		$size = strlen( $str );
		header( 'Content-Length: '.$size );
		$this->response = $str;
	}
}
