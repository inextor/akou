<?php

namespace AKOU;

class Attachment
{
	function formAttachmentSaveToPath( $obj_FILE ,$dirname ,$max_weight=5242880 )
	{
		global $mysqli;

		$min_weight = 0;

		$file_type = $obj_FILE['type'];
		$file_size = $obj_FILE['size'];

		if( $obj_FILE['error'] > 0 )
		{
			//https://www.php.net/manual/en/features.file-upload.errors.php
			//
			$phpFileUploadErrors = array(
			    0 => 'There is no error, the file uploaded with success',
			    1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
			    2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
			    3 => 'The uploaded file was only partially uploaded',
			    4 => 'No file was uploaded',
			    6 => 'Missing a temporary folder',
			    7 => 'Failed to write file to disk.',
			    8 => 'A PHP extension stopped the file upload.',
			);

			throw new ValidationException($phpFileUploadErrors[$obj_FILE['error']]);
		}




		if ( $file_size > $min_weight && $file_size <= $max_weight)
		{
			$uniq_id		= uniqid();
			$path_parts = pathinfo( $obj_FILE['tmp_name'] );


			$file_content	  = file_get_contents( $obj_FILE['tmp_name'] );
			$original_filename = $obj_FILE['name'];


			$file_ext = empty( $path_parts['extension'] ) ? null : $path_parts['extension'];

			if( $file_ext == null )
			{
				$tmp_explode 		= explode(".", $original_filename);
				$extension = end( $tmp_explode );
				$file_ext =  $extension ? $extension : null;
			}

			if( $file_ext == null )
				$file_ext = 'bin';

			$file_name		  = $uniq_id . '.' . $file_ext;

			if( $this->saveAttachmentToFile( $file_name, $file_content, $file_type, $dirname ) )
			{
				return Array(
					"filename" => $file_name
					,"original_filename"=>$original_filename
					,"filenamepath" => $dirname."/".$file_name
					,"content_type" => $file_type
					,"file_size" => strlen( $file_content )
					,"extension" => $file_ext
				);
			}
			return false;
		}


		if ( $file_size <= $min_weight )
		{
			$message	= 'Imagen demasiado pequeña (Menor de 5 kbs)...';
		}
		elseif ( $file_size >= $max_weight )
		{
			$message	= 'Imagen demasiado grande (Mayor de 5 mbs)...';
		}
		else
		{
			$message	= 'Ocurrio un error desconocido';
		}

		throw new SystemException
		(
			$message,
			print_r
			(
				array
				(
					'file'		  => $obj_FILE
					,'file_size'   => $file_size
					,'min_weight'   => $min_weight
					,'max_weight'   => $max_weight
				)
				,true
			)
		);
	}

	function saveAttachmentToFile($filename,$file_content, $file_type, $dirname )
	{
		if( !file_exists($dirname ) )
		{
			if( !mkdir( $dirname ))
			{
				throw new SystemException
				(
					'unable to create path '.$dirname
					,'path to create '.$dirname
				);
			}
		}

		$fp			 = fopen( $dirname.'/'.$filename, 'w' );
		fwrite( $fp, $file_content);
		fclose( $fp );
		return TRUE;
	}

	function get_mime( $file )
	{
		if ( function_exists( 'finfo_file' ) )
		{
			$finfo  = finfo_open( FILEINFO_MIME_TYPE ); // return mime type ala mimetype extension
			$mime   = finfo_file( $finfo, $file );
			finfo_close( $finfo );
			return $mime;
		}
		else if ( function_exists( 'mime_content_type' ) )
		{
			return mime_content_type( $file );
		}
		return false;
	}
}
