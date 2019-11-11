<?php

namespace AKOU;

class RestController
{
	function __construct()
	{
        $this->response = "";
		$this->allow_credentials = true;
		$this->cors = false;
	}

    function defaultOptions()
    {
       $this->setAllowHeader();
    }

	function getPaginationInfo($page,$page_size,$default_page_size=50)
	{
		$obj = new \stdClass();
		$limit = intVal( $default_page_size );

		if( !empty( $page_size ) )
			$limit = intval( $page_size );

		$obj->limit = $limit;
		$obj->offset = 0;

		if( !empty( $page ) )
			$obj->offset = $obj->limit*intVal( $page );

		return $obj;
	}
	function setAllowHeader()
	{
		if( isset( $_SERVER['HTTP_ORIGIN'] ) )
				header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");

		//header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept" />
		$all_methods = ['POST','GET','PUT','OPTIONS','HEADER','PATCH','DELETE'];
		$methods = Array();

        foreach($all_methods as $method )
        {
            if( method_exists( $this, \strtolower( $method ) ) )
                $methods[] = $method;
        }

		if(isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
        //header("Access-Control-Allow-Headers: *");
		header("Access-Control-Allow-Methods: ".join(", ",$methods));
		//header('Allow: '.join($methods,","));
	}

	function execute()
	{
		if( $this->cors )
		{
        //	header('Access-Control-Allow-Origin: *');
		}

		if( $this->allow_credentials )
		{
			header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
			header('Access-Control-Allow-Credentials: true');
		}

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
				error_log("ECHOING METHOD RESPONSE".$this->response );
            }
                echo $this->response;
			return;
        }
		else
		{
		    error_log("METHOD DOES NOT EXISTS".$method );
			http_response_code(405);
			$this->setAllowHeader();
		}
	}

	function raw($str)
	{
        $this->response = $str;
        header('Content-length: '.strlen( $this->response ) );
		return $str;
	}

    function text($text)
    {
        header('Content-Type: text/plain');
        $this->response = $text;
        header('Content-length: '.strlen( $this->response ) );
        return $text;
    }


	function getMethodParams()
	{
		if( $_SERVER["CONTENT_TYPE"] == 'application/x-www-form-urlencoded' )
		{
			$info = file_get_contents("php://input");
			error_log( $info );
			parse_str( $info, $post_vars);
			return $post_vars;
		}

		if( $_SERVER["CONTENT_TYPE"] == 'application/json' )
		{
			return json_decode( file_get_contents("php://input"),true );
		}
		return array();
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

    function json($result,$flag=null)
    {
        //error_log( print_r( $result) );
		$this->response = empty( $flag ) ?  json_encode( $result ) : json_encode( $result, $flag );
        //error_log('Hader Content-length: '.strlen( $this->response ));
        header('Content-length: '.strlen( $this->response ) );
        //error_log('Header Content-Type: application/json');
        header("Content-type: application/json; charset=utf-8");
        //header('Content-Type: application/json');
        return $result;
    }
}
