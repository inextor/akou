<?php

namespace AKOU;

class Curl 
{

	function __construct( $url )
	{
		$this->responseHeaders 	= array();
		$this->requestHeaders	= array();

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
	}

	function setFollowLocation( $follow_location = TRUE )
	{
		$this->follow_location = $follow_location;
	}


	function setTimeout( $timeout )
	{
		$this->timeout	= $timeout;
	}

	function sendAsMultipartFormData()
	{
		$this->isMultipart	= true;
	}

	function setPostData( $dataArray )
	{
		$this->method		= 'POST';
		$this->setParameters( $dataArray );
	}

	function addHeader( $header, $value )
	{
		$this->requestHeaders[ strtoupper( $header ) ] = $value;
	}

	function setMethod( $method )
	{
		$this->method	= strtoupper( $method );
	}

	function setParameters( $dataArray )
	{
		$this->fields		= $dataArray;
	}

	function setJsonPost( $jsonStringOrArray )
	{
		$this->custom_request	= 'POST';
		$this->method			= 'POST';
		$this->postData			= is_array( $jsonStringOrArray ) ? json_encode( $jsonStringOrArray ) : $jsonStringOrArray;
		$this->addHeader('Content-Type','application/json');
	}

	function addHeaders( $headers )
	{
		foreach( $headers as $header=>$value )
		{
			$this->addHeader( $header, $value );
		}
	}

	function getResponseHeaders()
	{
		return $this->responseHeaders;
	}

	function setUser( $user, $password = NULL )
	{
		$this->user		= $user;
		$this->password	=$password;
	}

	function setUserAgent( $user_agent )
	{
		$this->user_agent	= $user_agent;
	}

	function execute()
	{
		$this->curl		= \curl_init();

		\curl_setopt( $this->curl, CURLOPT_URL,$this->url );
		\curl_setopt( $this->curl, CURLOPT_RETURNTRANSFER, 0);

		if( strtoupper( $this->method )	=== 'POST' || !empty( $this->files) || !empty( $this->postData ) )
		{
			\curl_setopt( $this->curl , CURLOPT_POST, true );
		}
		else
		{
			 \curl_setopt( $this->curl , CURLOPT_HTTPGET, true );
		}

		if( $this->custom_request )
		{
			 \curl_setopt( $this->curl , CURLOPT_CUSTOMREQUEST, $this->custom_request );
		}

		if( count( $this->files ) )
		{
			$this->addHeader('Content-Type','multipart/form-data'); 
			\curl_setopt( $this->curl, CURLOPT_POSTFIELDS, $this->postData );
		}
		else if( !empty( $this->postData ) )
		{
			if( empty( $this->requestHeaders[strtoupper( 'Content-Type' )] ) )
				$this->addHeader('Content-Type','application/x-www-form-urlencoded');

			$postString	= $this->postData;

			if( is_array( $this->postData ) )
			{
 				$postString = http_build_query( $this->postData );
			}

			\curl_setopt( $this->curl, CURLOPT_POSTFIELDS, $postString );
			$size		= strlen( $postString );
			$this->addHeader( 'Content-Length', $size );
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

		foreach( $this->requestHeaders as $header=>$value )
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

		$result 		= \curl_exec( $this->curl );
		$header_size	= \curl_getinfo($this->curl , CURLINFO_HEADER_SIZE );

		$header			= \substr($result, 0, $header_size);
		$this->response	= \substr($result, $header_size);
		$this->info		= \curl_getinfo( $this->curl );//, CURLINFO_HTTP_CODE );

		if( $this->info['http_code'] )
			$this->status	= $this->info['http_code'];

		$headerArray	= explode("\n", $header );

		for( $i=1;$i<\count($headerArray); $i++ )
		{
			$h		= \trim( $headerArray[ $i ] );
			if( !$h )
				continue;

			$fields = explode( ': ', $h );
			if( isset( $fields[ 1 ] ) )
			{
				$this->responseHeaders[ strtoupper( \trim($fields[0]) ) ] = \trim($fields[1]);
			}
		}

		//print_r( $this->requestHeaders );
		\curl_close( $this->curl );
	}
}
