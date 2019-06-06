<?php

namespace AKOU;

class RestController
{
	function __construct()
	{
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

		if( is_callable( $this->{ $method } ) )
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
}
