<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Import
 *
 * This allows you to import files before processing for compiling
 * into a single file and later cached. This is done via @import ''
 *
 * @author Anthony Short
 * @dependencies None
 **/
class Import extends Plugins
{
	/**
	 * Stores which files have already been included
	 *
	 * @var array
	 */
	private static $loaded = array();
	
	/**
	 * This function occurs before everything else
	 *
	 * @author Anthony Short
	 * @param $css
	 */
	public static function parse()
	{
		# Find all the @server imports
		CSS::$css = self::server_import(CSS::$css);
	}
	
	/**
	 * Imports css via @import statements
	 * 
	 * @author Anthony Short
	 * @param $css
	 */
	public static function server_import($css, $previous = "")
	{		
		if(preg_match_all('/\@include\s+(?:\'|\")([^\'\"]+)(?:\'|\")\;/', $css, $matches))
		{
			$unique = array_unique($matches[1]);
			$include = str_replace("\\", "/", unquote($unique[0]));
			
			# If they're getting an absolute file
			if($include[0] == "/")
			{
				$include = DOCROOT . ltrim($include, "/");
			}
			
			# Make sure recursion isn't happening
			if($include == $previous)
				throw new Scaffold_Exception("Import.recursion", $include);
			
			# Make sure it's a CSS file
			if(!is_css($include))
				throw new Scaffold_Exception("Import.not_css", $include);
			
			if(file_exists($include))
			{	
				# Make sure it hasn't already been included	
				if(!in_array($include, self::$loaded))
				{
					self::$loaded[] = $include;
					$css = str_replace($matches[0][0], file_get_contents($include), $css);
				}
				
				# Check the file again for more imports
				$css = self::server_import($css, $include);
			}
			else
			{
				throw new Scaffold_Exception("Import.doesnt_exist", $include);
			}
		}
		
		return $css;
	}
}