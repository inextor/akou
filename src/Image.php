<?php

namespace AKOU;

class Image
{
    function getImageInfo()
	{
		$info = array();
	}

	function formImageSaveToPath($obj_FILE,$to_save_dirname, $max_height=500,$max_width=500, $max_weight=55242880,$min_width=50,$min_height=50,$filename_prefix="",$force_webp=false)
	{
		global $mysqli;

		$min_weight = 0;

		$image_type = $obj_FILE['type'];
		$image_size = $obj_FILE['size'];

		if( $obj_FILE['error'] == 1 )
		{
			throw new SystemException('Ocurrió un error de red al subir la imagen, posiblemente el tamaño de la imagen es mayor a 5 Mb');
		}


		if( !strstr( $image_type , 'image' ) )
		{
			throw new SystemException('Not an image');
		}

		if
		(
				$image_size > $min_weight
				&&
				$image_size <= $max_weight
		)
		{
			$uniq_id			= uniqid($filename_prefix);
			$file_ext		   = 'jpg';
			$image_type_defined = IMAGETYPE_JPEG;
			$image_obj		  = FALSE;

			$content_types	  = array( IMAGETYPE_GIF => 'image/gif', IMAGETYPE_JPEG=>'image/jpeg', IMAGETYPE_PNG=>'image/png' );
			$exif_type		  = \exif_imagetype( $obj_FILE['tmp_name'] );

			if( isset( $content_types[ $exif_type] ) )
			{
				$image_type	 = $content_types[ $exif_type ];
			}
			else
			{
				$image_type = $obj_FILE['type'];
			}

			switch ( $image_type )
			{
				case 'image/jpg':
					$file_ext = 'jpg';
					$image_type_defined = IMAGETYPE_JPEG;
					$image_obj		  = \imagecreatefromjpeg( $obj_FILE['tmp_name'] );
					break;
				case 'image/jpeg':
					$file_ext = 'jpg';
					$image_type_defined = IMAGETYPE_JPEG;
					$image_obj		  = \imagecreatefromjpeg( $obj_FILE['tmp_name'] );
					break;
				case 'image/gif':
					$file_ext = 'gif';
					$image_type_defined = IMAGETYPE_GIF;
					$image_obj		  = \imagecreatefromgif( $obj_FILE['tmp_name'] );
					break;
				case 'image/png':
					$file_ext = 'png';
					$image_type_defined = IMAGETYPE_PNG;
					$image_obj		  = \imagecreatefrompng( $obj_FILE['tmp_name'] );
					break;
				case 'image/bmp':
					$file_ext = 'bmp';
					$image_type_defined = IMAGETYPE_BMP;
					$image_obj		  = \imagecreatefromwbmp( $obj_FILE['tmp_name'] );
					break;
				case 'image/webp':
					$file_ext = 'webp';
					$image_type_defined = IMAGETYPE_WEBP;
					$image_obj		  = \imagecreatefromwebp( $obj_FILE['tmp_name'] );
					break;

				default:
					$file_ext = 'jpg';
					$image_type_defined = IMAGETYPE_JPEG;
					$image_obj		  = \imagecreatefromjpeg( $obj_FILE['tmp_name'] );
					break;
			}

			if( !$image_obj )
			{
				throw new SystemException
				(
					'not a supported image type(jpeg,png)','current image type is'.$image_type
				);
			}

			$file_name		  = $uniq_id . '.' . $file_ext;
			if( $force_webp )
			{
				$filename = $uniq_id.'.webp';
			}

			$image_content	  = file_get_contents( $obj_FILE['tmp_name'] );
			$original_filename = $obj_FILE['name'];
			$image_real_size = strlen( $image_content );

			$image_width	= imagesx( $image_obj );
			$image_height   = imagesy( $image_obj );

			if(($image_width < $min_width || $image_height < $min_height ))
			{
				throw new SystemException
				(
					'to small image dimensions' . $min_width . 'x' . $min_height
					,"imagen_widht $image_width height is $image_height minwidht $min_width min_height $min_height $image_type $image_type_defined".print_r($image_obj,true)
				);
			}

			if($force_webp || $image_real_size > $max_weight || ( $image_width > $max_width || $image_height > $max_height) )
			{
				//No hay pedo tiene un modo idiota
				$image_obj	  = $this->resize_image2( $image_obj , $max_width, $max_height );
				$image_width	= imagesx( $image_obj );
				$image_height   = imagesy( $image_obj );

				if( $force_webp )
				{
					$image_type = 'image/webp';
				}

				$image_content  = $this->imageByContentType( $image_obj, $image_type );
			}

			if( $this->saveImageToFile( $file_name, $image_content, $image_type, $to_save_dirname) )
			{
				return Array(
					"filename" => $file_name
					,"original_filename"=> $original_filename
					,"filenamepath" => $to_save_dirname."/".$file_name
					,"content_type" => $image_type
					,"size" => strlen( $image_content )
					,"width" => $image_width
					,"height" => $image_height
				);
			}
			return false;
		}


		if ( $image_size <= $min_weight )
		{
			$message	= 'Imagen demasiado pequeña (Menor de 5 kbs)...';
		}
		elseif ( $image_size >= $max_weight )
		{
			$message	= 'Imagen demasiado grande (Mayor de 300kbs)... '.$image_size.' '.$max_weight;
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
					,'image_size'   => $image_size
					,'min_weight'   => $min_weight
					,'max_weight'   => $max_weight
				)
				,true
			)
		);
	}



	function saveImageToFile($filename,$image_content, $image_type, $dirname )
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
		fwrite( $fp, $image_content);
		fclose( $fp );
		return TRUE;
	}

	function resize_image( $image, $width, $height=0 )
	{
		$imagen_p   = $image;
		// Obtener nuevas dimensiones
		//list($original_width, $original_height) = getimagesize( $image );
		$original_width	 = imagesx( $image );
		$original_height	= imagesy( $image );

		//Prevenir que se redimensione si solicitado es mas grande
		if( $original_width <= $width && $height == 0 )
			return $image;


		/*RULE OF 3
			IF A = B
			X = Y

			Y = (B*X)/A


			$original_width = $original_height
			$width		  = $height

			$height		 = $original_height * $width/$original_width
			1280x1022
			453*361
		*/


		$ratio_original	 = $original_width / $original_height;


		// Not tested yet
		//
		if( $height == 0 )
		{
			$solicitado_height = $original_height*$width / $original_width;
		}
		else
		{
			$solicitado_height  = $height;
		}

		$solicitado_width   = $width;
		$ratio_nuevo		= $solicitado_width / $solicitado_height;

		if( $ratio_nuevo < $ratio_original )
		{
			// resize en heigth
			$porcentaje		 = $this->getPercentage( $original_height, $solicitado_height );
			$R_width			= $original_width * $porcentaje;
			$R_height		   = $original_height * $porcentaje;
			// crop
			$excedente_width	= $R_width-$solicitado_width;
			$margin_left		= $excedente_width / 2;
			// Redimensionar
			$imagen_p		   = imagecreatetruecolor( $solicitado_width, $solicitado_height );
			imagealphablending( $imagen_p, true);
			imagesavealpha( $imagen_p,true);

			$transparent		= imagecolorallocatealpha($imagen_p, 255, 255, 255, 127);
			$transparent1	   = imagecolortransparent( $imagen_p, $transparent );

			imagefill($imagen_p, 0, 0, $transparent1);

			//crea la imagen
			imagecopyresampled
			(
				$imagen_p,
				$image,
				0,
				0,
				$margin_left / $porcentaje,
				0,
				$R_width,
				$solicitado_height,
				$original_width,
				$original_height
			);
			$image  = $imagen_p;
		}
		else
		{
			// resize en heigth
			$porcentaje		 = $this->getPercentage( $original_width,$solicitado_width );
			$R_width			= $original_width * $porcentaje;
			$R_height		   = $original_height * $porcentaje;
			// crop
			$excedente_height   = $R_height-$solicitado_height;
			$margin_top		 = $excedente_height / 2;
			// Redimensionar
			$imagen_p = imagecreatetruecolor( $solicitado_width, $solicitado_height );

			imagealphablending( $imagen_p, true);
			imagesavealpha( $imagen_p,true);

			$transparent		= imagecolorallocatealpha($imagen_p, 255, 255, 255, 127);
			$transparent1	   = imagecolortransparent( $imagen_p, $transparent );
			imagefill($imagen_p, 0, 0, $transparent1);
			//imagefilledrectangle( $imagen_p, 0, 0, $solicitado_width, $solicitado_height, $transparent1 );


			/***
			echo ''.
				'imagen_destino : '				. $imagen_p				 . '<br>' .
				'$imagen_origen : '				   . $image					. '<br>' .
				'destino x	  : '						. 0						 . '<br>' .
				'destino y	  : '						. 0						 . '<br>' .
				'origen x	   : '						. 0						 . '<br>' .
				'origen y	   : '. $margin_top / $porcentaje . '<br>' .
				'destino_width  : '		. $solicitado_width		 . '<br>' .
				'destino_height : '				. $R_height				 . '<br>' .
				'origen_width   : '		  . $original_width		   . '<br>' .
				'origen_height  : '		 . $original_height
			;
			/**/

			imagecopyresampled
			(
				$imagen_p,
				$image,
				0,
				0,
				0,
				$margin_top / $porcentaje,
				$solicitado_width,
				$R_height,
				$original_width,
				$original_height
			);
			$image  = $imagen_p;
		}
		return $imagen_p;
	}

	function getPercentage( $original ='', $custom ='')
	{
		$temp   = ( $custom * 100) / $original;
		$temp   = $temp / 100; // decimal
		return $temp;
	}

	function imageByContentType( $image, $content_type )
	{
		$return_var = '';
		ob_start();
			switch ( $content_type )
			{
				case 'image/jpeg':
					imagejpeg( $image, NULL, 95 );
					break;
				case 'image/jpg':
					imagejpeg( $image, NULL, 95 );
					break;
				case 'image/png':
					imagepng( $image );
					break;
				case 'image/bmp':
					imagewbmp( $image );
					break;
				case 'image/gif':
					imagegif( $image );
					break;
				case 'image/webp':
					imagewebp($image,NULL, 95);
				default:
					imagejpeg( $image );
					break;
			}
			$return_var   = ob_get_contents();
		ob_end_clean();
		return $return_var;
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

	function resize_image2( $image, $max_width, $max_height )
	{
		$imagen_p		   = $image;
		$original_width	 = imagesx( $image );
		$original_height	= imagesy( $image );

		//A prueba de idiotas
		if( $original_width <= $max_width && $original_height<= $max_height )
			return $image;

		$data_resize		= $this->getResizeData( $original_width, $original_height, $max_width, $max_height );

		$solicitado_width   = $data_resize['new_width'];
		$solicitado_height  = $data_resize['new_height'];

			// Redimensionar
		$imagen_p		   = imagecreatetruecolor( $solicitado_width, $solicitado_height );
		imagealphablending( $imagen_p ,false );
		imagesavealpha( $imagen_p ,true);
		$transparent		= imagecolorallocatealpha( $imagen_p, 255, 255, 255, 127 );
		imagefilledrectangle( $imagen_p, 0, 0, $solicitado_width, $solicitado_height, $transparent );

		imagecopyresampled
		(
			$imagen_p,
			$image,
			0, //dest X
			0, //des Y
			0, //src X
			0, //src Y
			$solicitado_width,
			$solicitado_height,
			$original_width,
			$original_height
		);

		$image  = $imagen_p;
		return $imagen_p;
	}

	function getResizeData($image_width, $image_height, $max_width, $max_height)
	{
		$new_width  = $image_width;
		$new_height = $image_height;

		if( $image_width > $max_width )
		{
			$ratio	  = $max_width / $image_width;
			$new_height = $image_height * $ratio;
			$new_width  = $max_width;
		}

		if( $new_height > $max_height )
		{
			$ratio	  = $max_height / $new_height;
			$new_width  = $new_width * $ratio;
			$new_height = $max_height;
		}
		return array('new_width'=>$new_width, 'new_height'=>$new_height );
	}
}
