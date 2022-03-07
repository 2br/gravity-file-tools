<?php

/**
* @fileoverview RSW - Loader for Gravity .rsw file
* @author 2br
* @version 1.0
*/
class Rsw
{
	/**
	 * Script constants
	 */
	private $fp, $size, $version;
	public $ini, $gnd, $gat, $src = "";
	public $water, $light;
	public $top, $bottom, $left, $right;
	public $object_count;
	public $models = array();
	public $lights = array();
	public $sounds = array();
	public $effects = array();
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
	 * Open a RSW file
	 * Do some check before.
	 */
	public function open($filename)
	{
		
		if ( substr( $filename, 0, 7) !== "http://" ) {
			if ( ! file_exists($filename) )
				throw new Exception("RSW::open() - Can't find file '{$filename}'.");

			if ( ! is_readable($filename) )
				throw new Exception("RSW::open() - Can't read file '{$filename}'.");

			$this->size = filesize($filename);
		}
	
		$content = file_get_contents($filename);

		$this->fp = tmpfile();
		fwrite($this->fp,$content);
		rewind ($this->fp);
		
		$this->load();
		fclose($this->fp);
	}


	/**
	 * Parse a RSW file.
	 */
	private function load()
	{		
		extract( unpack( "a4head/C2ver", fread($this->fp, 0x6 ) ) );
		// Check header
		if ( $head !== 'GRSW' ) {
			throw new Exception("RSW::load() - Incorrect header, is '{$head}' - should be 'GRSW'");
		}

		// Get version
		$this->version = $ver2/10 + $ver1;

		// Read sub files.
		extract( unpack( "a40ini/a40gnd/a40gat", fread($this->fp, 0x78 ) ) );
		$this->ini  = $ini;
		$this->gnd  = $gnd;
		$this->gat	= $gat;
		if( $this->version >= 1.4 ) {
			extract( unpack( "a40src", fread($this->fp, 0x28 ) ) );
			$this->src = $src;
		}
		
		// Read water info.
		if( $this->version >= 1.3 ) {
			$this->water = new RSWWater($this->fp, $this->version);
		}
		echo $ini." type : ".$this->water->type."<br>";
		// Read light
		if( $this->version >= 1.5 ) {
			$this->light =  new RSWLight($this->fp, $this->version);
		
		}
		// Read ground
		if( $this->version >= 1.6 ) {
			extract( unpack( "ltop/lbottom/lleft/lright", fread($this->fp, 0x10) ) );
			$this->top 		= $top;
			$this->bottom 	= $bottom;
			$this->left 	= $left;
			$this->right	= $right;
		}
		
		// Read Object
		$this->parseObjects();
}
	
	public function parseObjects()
	{
		extract( unpack( "lcount", fread($this->fp, 0x4) ) );
		
		$m = 0;
		$l = 0;
		for( $i=0; $i < $count; $i++ ) {
			
			extract( unpack("ltype", fread($this->fp, 0x4) ) );
			
			switch( $type ) 
			{
				case 1: //Model
					$this->models[$m++] = new ObjectModel( $this->fp, $this->version);
				break;
				
				case 2: //Light
					$this->lights[$l++] = new ObjectLight( $this->fp );
				break;
				
				case 3: //Sound
					fseek( $this->fp, 0xbc, SEEK_CUR );
					if( $this->version >= 2.0 ) {
						fseek( $this->fp, 0x4, SEEK_CUR );
					}
				break;
				
				case 4: //Effect
					fseek( $this->fp, 0x74, SEEK_CUR );
				break;
			}
		}
		$this->object_count = $l + $m;
	}
	
	public function save( $path )
	{
		$f = fopen($path , 'w+b');
		fwrite( $f, pack( "a6", 'GRSW\2\2') );
		
		fwrite( $f, pack( "a40", $this->ini) );
		fwrite( $f, pack( "a40", $this->gnd) );
		fwrite( $f, pack( "a40", $this->gat) );
		fwrite( $f, pack( "a40", $this->src) );
		
		fwrite( $f, pack( "f", $this->water->level) );
		fwrite( $f, pack( "l", $this->water->type) );
		fwrite( $f, pack( "f", $this->water->waveHeight) );
		fwrite( $f, pack( "f", $this->water->waveSpeed) );
		fwrite( $f, pack( "f", $this->water->wavePitch) );
		fwrite( $f, pack( "l", $this->water->animSpeed) );
		
		fwrite( $f, pack( "l", $this->light->longitude) );
		fwrite( $f, pack( "l", $this->light->latitude ) );
		
		fwrite( $f, pack( "f", $this->light->diffuse[0] ) );
		fwrite( $f, pack( "f", $this->light->diffuse[1] ) );
		fwrite( $f, pack( "f", $this->light->diffuse[2] ) );
		
		fwrite( $f, pack( "f", $this->light->ambient[0] ) );
		fwrite( $f, pack( "f", $this->light->ambient[1] ) );
		fwrite( $f, pack( "f", $this->light->ambient[2] ) );
		
		fwrite( $f, pack( "f", $this->light->opacity ) );
		
		fwrite( $f, pack( "l", $this->top) );
		fwrite( $f, pack( "l", $this->bottom) );
		fwrite( $f, pack( "l", $this->left) );
		fwrite( $f, pack( "l", $this->right) );
		
		//Objects
		fwrite( $f, pack( "L", $this->object_count) );
		
		foreach( $this->models as $model )
		{
			if( $model->skip == true ) 
				continue;
			
			fwrite( $f, pack( "l", 1 ) );
			
			fwrite( $f, pack( "a40"	, $model->name ) );
			fwrite( $f, pack( "l"	, $model->animType ) );
			fwrite( $f, pack( "f"	, $model->animSpeed ) );
			fwrite( $f, pack( "l"	, $model->blockType ) );
			fwrite( $f, pack( "a80"	, $model->filename ) );
			fwrite( $f, pack( "a80"	, $model->nodename ) );
			
			fwrite( $f, pack( "f"	, $model->position[0] ) );
			fwrite( $f, pack( "f"	, $model->position[1] ) );
			fwrite( $f, pack( "f"	, $model->position[2] ) );
			
			fwrite( $f, pack( "f"	, $model->rotation[0] ) );
			fwrite( $f, pack( "f"	, $model->rotation[1] ) );
			fwrite( $f, pack( "f"	, $model->rotation[2] ) );
			
			fwrite( $f, pack( "f"	, $model->scale[0] ) );
			fwrite( $f, pack( "f"	, $model->scale[1] ) );
			fwrite( $f, pack( "f"	, $model->scale[2] ) );
		}
		
		foreach( $this->lights as $ligth )
		{
			fwrite( $f, pack( "l", 2 ) );
			
			fwrite( $f, pack( "a40"	, $ligth->name ) );
		
			fwrite( $f, pack( "f"	, $ligth->position[0] ) );
			fwrite( $f, pack( "f"	, $ligth->position[1] ) );
			fwrite( $f, pack( "f"	, $ligth->position[2] ) );
			
			fwrite( $f, pack( "a40"	, $ligth->name ) );
			
			fwrite( $f, pack( "f"	, $ligth->color[0] ) );
			fwrite( $f, pack( "f"	, $ligth->color[1] ) );
			fwrite( $f, pack( "f"	, $ligth->color[2] ) );
			
			fwrite( $f, pack( "f"	, $ligth->range ) );
		}
		fclose($f);
	}
}

class ObjectLight
{
	public $name;
	public $position = array(3);
	public $color 	 = array(3);
	public $range;
	
	/**
	 * Light Object structure
	 *
	 * @param {BinaryReader} fp
	 */
	public function __construct( $fp ) {
				
		extract(unpack("a40name", fread($fp, 0x28)));
		$this->name = $name;

		extract(unpack("fpos0/fpos1/fpos2", fread($fp, 0x0c)));
		$this->position = [$pos0, $pos1, $pos2];
		
		extract(unpack("a40name", fread($fp, 0x28)));
		//$this->name = $name;
		
		extract(unpack("fcol0/fcol1/fcol2", fread($fp, 0x0c)));
		$this->color = [$col0, $col1, $col2];
		
		extract(unpack("frange", fread($fp, 0x04)));
		$this->range = $range;
	}
}

class ObjectModel
{
	public $name;
	public $animType  = 0;
	public $animSpeed = 0;
	public $blockType = 0;
	public $filename;
	public $nodename;
	public $position = array(3);
	public $rotation = array(3);
	public $scale	 = array(3);
	public $skip	 = false;
	/**
	 * Model structure
	 *
	 * @param {BinaryReader} fp
	 * @param {int} version
	 */
	public function __construct( $fp , $version ) {
		if( $version >= 1.3 ) {
			extract(unpack("a40name/ltype/fspeed/lblock", fread($fp, 0x34)));
			$this->name = $name;
			$this->animType  = $type;
			$this->animSpeed = $speed;
			$this->blockType = $block;
		} 
		extract(unpack("a80filename/a80nodename", fread($fp, 0xa0)));
		
		$filename = substr($filename, 0, strpos($filename, "\0" )); 
		$this->filename = utf8_encode( $filename );
		$this->nodename = $nodename;
		
		extract(unpack("fpos0/fpos1/fpos2", fread($fp, 0x0c)));
		$this->position = [$pos0, $pos1, $pos2];
		
		extract(unpack("frot0/frot1/frot2", fread($fp, 0x0c)));
		$this->rotation = [$rot0, $rot1, $rot2];
		
		extract(unpack("fscale0/fscale1/fscale2", fread($fp, 0x0c)));
		$this->scale = [$scale0, $scale1, $scale2];
	}
	
}

class RSWWater
{
	public $level = 0;
	public $type  = 0;
	public $waveHeight = 0;
	public $waveSpeed  = 0;
	public $wavePitch  = 0;
	public $animSpeed  = 0;
	
	/**
	 * Water structure
	 *
	 * @param {BinaryReader} fp
	 * @param {int} version
	 */
	public function __construct( $fp , $version ) {
		extract( unpack( "flevel", fread($fp, 0x4) ) );
		$this->level = $level;
		
		if( $version >= 1.8 ) {
			extract( unpack( "ltype/fheight/fspeed/fpitch", fread($fp, 0x10) ) );
			$this->type 	  = $type;
			$this->waveHeight = $height;
			$this->waveSpeed  = $speed;
			$this->wavePitch  = $pitch;
		}
		
		if( $version >= 1.9 ) { 
			extract( unpack( "lanimSpeed", fread($fp, 0x4) ) );
			$this->animSpeed = $animSpeed;
		}
	}
}

class RSWLight
{
	public $longitude, $latitude;
	public $diffuse = array(3);
	public $ambient = array(3);
	public $opacity = 1.0;
	
	/**
	 * Global Light structure
	 *
	 * @param {BinaryReader} fp
	 * @param {int} version
	 */
	public function __construct( $fp , $version ) {
		extract( unpack( "llongitude/flatitude", fread($fp, 0x8) ) );
		$this->longitude = $longitude;
		$this->latitude = $latitude;
		
		for( $i = 0; $i < 3; $i++ )
			$this->diffuse[$i] = current( unpack("f", fread( $fp, 0x4)) );
		
		for( $i = 0; $i < 3; $i++ )
			$this->ambient[$i] = current( unpack("f", fread( $fp, 0x4)) );
		
		if( $version >= 1.7 ) {
			extract( unpack( "fopacity", fread($fp, 0x4) ) );
			$this->opacity = $opacity;
		}
	}
}
?>