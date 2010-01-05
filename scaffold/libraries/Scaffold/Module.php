<?php

/**
 * Module
 *
 * The base class for all the Module
 *
 * @package CSScaffold
 * @author Anthony Short
 */
class Scaffold_Module extends Scaffold_Utils
{
	/**
	 * Sets cache flags
	 *
	 * @return Plugin
	 */
	public static function flag() {}
	
	/**
	 * Place any importing here. This will happen
	 * before everything else. 
	 */
	public static function import_process($css) { return $css; }

	/**
	 * For any preprocessing of the css. Arranging the css,
	 * stripping comments.. etc.
	 */
	public static function pre_process($css) { return $css; }
	
	/**
	 * The main grunt of the processing of the css string
	 */
	public static function process($css) { return $css; }
	
	/**
	 * For formatters, compressors and prettifiers
	 */
	public static function post_process($css) { return $css; }

	/**
	 * For formatters, compressors and prettifiers
	 */
	public static function formatting_process($css) { return $css; }
	
	/**
	 * For loading views and display a page other than the CSS
	 */
	public static function output($css) { return $css; }
}