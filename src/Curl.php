<?php

namespace AKOU;

class Curl
{
	function __construct( $url )
	{
		$this->response_headers 	= array();
		$this->request_headers	= array();
		$this->debug			= FALSE;
		$this->status_code		= 0;

		$this->user				= NULL;
		$this->password			= NULL;
		$this->timeout			= NULL;

		$this->isMultipart		= FALSE;
		$this->timeout			= NULL;
		$this->http_auth		= NULL;
		$this->follow_location 	= FALSE;
		$this->custom_request	= FALSE;
		$this->url				= $url;
		$this->method			= 'GET';
		$this->files			= array();
		$this->ssl_verify_peer	= TRUE;
		$this->response			= NULL;
		$this->error			= NULL;
		$this->raw_response 	= NULL;
	}

	function setFollowLocation( $follow_location = TRUE )
	{
		$this->follow_location = $follow_location;
		return $this;
	}


	function setTimeout( $timeout )
	{
		$this->timeout	= $timeout;
		return $this;
	}

	function sendAsMultipartFormData()
	{
		$this->isMultipart	= true;
		return $this;
	}

	function setPostData( $dataArray )
	{
		$this->method		= 'POST';
		$this->postData		= $dataArray;
		return $this;
	}

	function setHeader( $header, $value )
	{
		$this->request_headers[ strtoupper( $header ) ] = $value;
		return $this;
	}

	function setMethod( $method )
	{
		switch( strtoupper( $method ) )
		{
			case 'GET':
			case 'POST':
				$this->method	= $method;
				$this->custom_request = $method;
				break;
			case 'PUT':
			case 'DELETE':
			default:
				$this->method	= 'POST';
				$this->custom_request = $method;
		}

		return $this;
	}

	function setParameters( $dataArray )
	{
		$this->fields		= $dataArray;
		return $this;
	}

	function setJsonPost( $jsonStringOrArray )
	{
		if( empty( $this->custom_request ) )
		{
			$this->custom_request	= 'POST';
		}
		$this->method			= 'POST';
		$this->postData			= is_array( $jsonStringOrArray ) ? json_encode( $jsonStringOrArray ) : $jsonStringOrArray;
		$this->setHeader('Content-Type','application/json');

		return $this;
	}

	function setHeaders( $headers )
	{
		foreach( $headers as $header=>$value )
		{
			$this->setHeader( $header, $value );
		}
		return $this;
	}

	function getResponseHeaders()
	{
		return $this->response_headers;
	}

	function setUser( $user, $password = NULL )
	{
		$this->user		= $user;
		$this->password	=$password;
		return $this;
	}

	function setUserAgent( $user_agent )
	{
		$this->user_agent	= $user_agent;
		return $this;
	}

	function execute()
	{
		$this->curl		= \curl_init();

		\curl_setopt( $this->curl, CURLOPT_URL,$this->url );
		\curl_setopt( $this->curl, CURLOPT_RETURNTRANSFER, 1);

		if( strtoupper( $this->method )	=== 'POST' || !empty( $this->files) || !empty( $this->postData ) )
		{
			\curl_setopt( $this->curl , CURLOPT_POST, true );
		}
		else
		{
			 \curl_setopt( $this->curl , CURLOPT_HTTPGET, true );
		}

		if( $this->debug )
		{
			curl_setopt($this->curl, CURLOPT_VERBOSE, true);
		}

		if( $this->custom_request )
		{
			 \curl_setopt( $this->curl , CURLOPT_CUSTOMREQUEST, $this->custom_request );
		}

		if( count( $this->files ) )
		{
			$this->setHeader('Content-Type','multipart/form-data');
			\curl_setopt( $this->curl, CURLOPT_POSTFIELDS, $this->postData );
		}
		else if( !empty( $this->postData ) )
		{
			if( empty( $this->request_headers[strtoupper( 'Content-Type' )] ) )
				$this->setHeader('Content-Type','application/x-www-form-urlencoded');

			$postString	= $this->postData;

			if( is_array( $this->postData ) )
			{
 				$postString = http_build_query( $this->postData );
			}

			\curl_setopt( $this->curl, CURLOPT_POSTFIELDS, $postString );
			$size		= strlen( $postString );
			$this->setHeader( 'Content-Length', $size );
		}


		if( !empty( $this->user_agent )  )
		{
			\curl_setopt( $this->curl ,CURLOPT_USERAGENT ,$this->user_agent );
		}

		if( !empty( $this->user ) )
		{
			if( empty( $this->password ) )
			{
				\curl_setopt( $this->curl, CURLOPT_USERPWD, $this->user );
				\curl_setopt( $this->curl, CURLOPT_HTTPAUTH, empty( $this->http_auth ) ? CURLAUTH_BASIC : $this->http_auth );
			}
			else
			{
				\curl_setopt( $this->curl, CURLOPT_USERPWD, "$this->user:$this->password");
				\curl_setopt( $this->curl, CURLOPT_HTTPAUTH, empty( $this->http_auth ) ? CURLAUTH_BASIC : $this->http_auth );
			}
		}

		$headers = array();

		foreach( $this->request_headers as $header=>$value )
		{
			$headers[] = $header.': '.$value;
		}

		\curl_setopt( $this->curl, CURLOPT_HTTPHEADER, $headers );

		if( $this->timeout !== NULL )
			\curl_setopt( $this->curl, CURLOPT_CONNECTTIMEOUT ,$this->timeout );

		if( strpos( $this->url, 'https://' ) === 0 )
		{
			if( $this->ssl_verify_peer !== FALSE )
			{
				\curl_setopt( $this->curl, CURLOPT_SSL_VERIFYPEER, TRUE );
			}
		}

		\curl_setopt( $this->curl, CURLOPT_HEADER, true );

		if( $this->follow_location !== FALSE || !empty( $this->max_redirect ))
		{
			\curl_setopt( $this->curl, CURLOPT_FOLLOWLOCATION, $this->follow_location );

			if( !empty( $this->max_redirect ) )
				\curl_setopt( $this->curl, CURLOPT_MAXREDIRS, $this->max_redirect );
		}
		//MMMM

		$this->raw_response = \curl_exec( $this->curl );
		$this->error	= \curl_error( $this->curl );
		$header_size	= \curl_getinfo($this->curl , CURLINFO_HEADER_SIZE );

		$header			= \substr($this->raw_response, 0, $header_size);
		$this->response	= \substr($this->raw_response, $header_size);
		$this->info		= \curl_getinfo( $this->curl );//, CURLINFO_HTTP_CODE );

		if( $this->info['http_code'] )
			$this->status_code	= $this->info['http_code'];

		$headerArray	= explode("\n", $header );

		for( $i=1;$i<\count($headerArray); $i++ )
		{
			$h		= \trim( $headerArray[ $i ] );
			if( !$h )
				continue;

			$fields = explode( ': ', $h );
			if( isset( $fields[ 1 ] ) )
			{
				$this->response_headers[ strtoupper( \trim($fields[0]) ) ] = \trim($fields[1]);
			}
		}

		//print_r( $this->request_headers );
		\curl_close( $this->curl );
	}
}

