<?php

namespace AKOU;

class RestController
{
	function __construct()
	{
        $this->response = "";
		$this->allow_credentials = true;
	}

    function defaultOptions()
    {
       $this->setAllowHeader();
    }

	function setAllowHeader()
	{
		$all_methods = ['POST','GET','PUT','OPTIONS','HEADER','PATCH','DELETE'];
		$methods = Array();

        foreach($all_methods as $method )
        {
            if( method_exists( $this, \strtolower( $method ) ) )
                $methods[] = $method;
        }

		header('Allow: '.join($methods,","));
	}

	function execute()
	{
		if( $this->allow_credentials )
			header('Access-Control-Allow-Credentials: true');

		$method = strtolower( $_SERVER['REQUEST_METHOD'] );

        if( $method === "get" || $method === "head" )
        {
            if( !method_exists( $this, $method) )
            {
                $this->sendStatus(404)->text('Document not found');
            }
            else
            {
                $this->get();
            }

            if( $method !== "head" )
            {
                echo $this->response;
            }
        }
        else if( method_exists( $this, $method) )
        {
			$this->{$method}();
            if( !empty( $this->response  ) )
            {
                echo $this->response;
            }
        }
		else
		{
			http_response_code(405);
			$this->setAllowHeader();
		}
	}

    function sendStatus($code)
    {
        http_response_code( $code );
        return $this;
    }

    function text($text)
    {
        header('Content-Type: text/plain');
        $this->response = $text;
        header('Content-length: '.strlen( $this->response ) );
        return $text;
    }

    function json($result)
    {
        //error_log( print_r( $result) );
        $this->response = json_encode( $result );
        //error_log('Hader Content-length: '.strlen( $this->response ));
        header('Content-length: '.strlen( $this->response ) );
        //error_log('Header Content-Type: application/json');
        header("Content-type: application/json; charset=utf-8");
        //header('Content-Type: application/json');
        return $result;
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
			return json_decode( file_get_contents("php://input"),true );
		}
	}
}
