<?php

/**
* @fileoverview GND - Loader for Gravity .gnd file
* @author 2br
* @version 1.0
*/
class Gnd
{
	/**
	 * Script constants
	 */
	private $fp, $size, $version;
	public $width, $height, $zoom;
	
	public $texture_count, $texture_length;
	public $textures = [];
	public $rest;
	
	/**
	 * Constructor
	 * Open a file if specify
	 */
	public function __construct($filename=false)
	{
		if ( $filename ) {
			$this->open( $filename );
		}
	}

	/**
	 * Open a GND file
	 * Do some check before.
	 */
	public function open($filename)
	{
		
		if ( substr( $filename, 0, 7) !== "http://" ) {
			if ( ! file_exists($filename) )
				throw new Exception("GND::open() - Can't find file '{$filename}'.");

			if ( ! is_readable($filename) )
				throw new Exception("GND::open() - Can't read file '{$filename}'.");
		} 
	
		$content = file_get_contents($filename);
		$this->size = strlen($content);
		$this->fp = tmpfile();
		fwrite($this->fp,$content);
		rewind ($this->fp);

		$this->load();
		fclose($this->fp);
	}


	/**
	 * Parse a GND file.
	 */
	private function load()
	{		
		extract( unpack( "a4head/C2ver", fread($this->fp, 0x6 ) ) );
		// Check header
		if ( $head !== 'GRGN' ) {
			throw new Exception("GRGN::load() - Incorrect header, is '{$head}' - should be 'GRGN'");
		}

		// Get version
		$this->version = $ver2/10 + $ver1;
		
		extract( unpack( "Lwidth/Lheight/fzoom", fread($this->fp, 0xc ) ) );
		
		$this->width    = $width;
		$this->height   = $height;
		$this->zoom 	= $zoom;
		
		$this->parseTextures();
		//Doesn't need the rest, just read it
		$this->rest 	= fread( $this->fp, $this->size - ftell($this->fp) );
}
	
	public function parseTextures()
	{
		extract( unpack( "Lcount/Llength", fread($this->fp, 0x8 ) ) );
		$this->texture_count    = $count;
		$this->texture_length   = $length;

		for( $i=0; $i < $count; $i++ ) {
			$this->textures[$i] = current( unpack("a{$length}", fread( $this->fp,  $length ) ) );
			$this->textures[$i] = utf8_encode( $this->textures[$i] );
		}
	}
	
	public function save( $path )
	{
		$f = fopen($path , 'w+b');
		fwrite( $f, pack( "a4", 'GRGN') );
		fwrite( $f, pack('C2', $this->version * 10 % 10, floor($this->version * 10)/10 ) );
		fwrite( $f, pack( "L", $this->width) );
		fwrite( $f, pack( "L", $this->height) );
		fwrite( $f, pack( "f", $this->zoom) );
		//Textures
		fwrite( $f, pack( "L", $this->texture_count) );
		fwrite( $f, pack( "L", $this->texture_length) );
		foreach( $this->textures as $texture )
		{
			fwrite( $f, pack( "a{$this->texture_length}", $texture) );
		}
		// Save the rest untouched
		fwrite( $f, $this->rest);
		fclose($f);
	}
}

class GNDTile
{
	public $uv = array(4);
	public $v1 = array(4);
	public $texture;
	public $color = array(4);
	/**
	 * Tile structure
	 *
	 * @param {resource $handle} fp
	 */
	public function __construct( $fp ) {
		//TODO: read tiles
		
	}
	
}

class GNDFace
{
	public $height = array(4);
	public $tile_up, $tile_front, $tile_right;
	
	/**
	 * Face structure
	 *
	 * @param {resource $handle} fp
	 */
	public function __construct( $fp ) {
		//TODO: read faces
		
	}
	
}
?>