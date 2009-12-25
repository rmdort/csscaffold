<?php

/**
 * Controller
 *
 * This file handles the caching, logging, config, flags, options
 * and everything else that is the foundation of Scaffold's functionality.
 * 
 * @author Anthony Short
 */
class Scaffold_Core
{
	/**
	 * The config settings
	 */
	public static $config;
	
	/**
	 * Include paths
	 *
	 * @var array
	 */
	public static $include_paths = array();

	/**
	 * Internal cache
	 */
	public static $internal_cache = array();
	
	/**
	 * The location of the cache file
	 *
	 * @var string
	 */
	public static $cache_path; 
	
	/**
	 * Internal cache lifetime
	 *
	 * @var int
	 */
	public static $cache_lifetime;
	
	/**
	 * What parts of the internal cache will be written to file
	 */
	public static $write_cache;

	/**
	 * Stores the flags
	 *
	 * @var array
	 */
	public static $flags = array();

	/**
	 * Options
	 *
	 * @var array
	 */
	public static $options = array();

	/**
	 * Logs
	 *
	 * @var array
	 */
	public static $log = array();
		
	/**
	 * Log Levels
	 *
	 * @var array
	 */
	private static $log_levels = array
	(
		'error',
		'warn',
		'info',
		'debug',
	);
	
	/**
	 * The log directory
	 *
	 * @var string
	 */
	public static $log_directory;
	
	/**
	 * The log threshold
	 *
	 * @var int
	 */
	private static $threshold = 2;
	
	/**
	 * The level of logged message to display as errors.
	 * 0 will only display error logs, 1 will display
	 * error logs and warning logs etc.
	 *
	 * @var int
	 */
	private static $error_level = 0;
	
	/**
	 * Displays an error and halts the parsing.
	 *	
	 * @param $message
	 * @return void
	 */
	public static function error($message)
	{
		self::log($message,0);
		self::log_save();

		if (!headers_sent())
		{
			header('HTTP/1.1 500 Internal Server Error');
		}

		include self::find_file('scaffold_error.php', 'views', true);
		exit;
	}

	/**
	 * Logs a message
	 *
	 * @param $message
	 * @return void
	 */
	public static function log($message,$level = 4)
	{
		if ($level <= self::$threshold)
		{
			self::$log[] = array(date('Y-m-d H:i:s P'), $level, $message);
		}	
	}

	/**
	 * Sets the logging threshold
	 *
	 * @param $level
	 * @return void
	 */
	public static function log_threshold($level)
	{
		self::$threshold = $level;
	}

	/**
	 * Save all currently logged messages.
	 *
	 * @return  void
	 */
	public static function log_save()
	{
		if (empty(self::$log) OR self::$threshold < 1)
			return;

		$filename = self::log_directory().date('Y-m-d').'.log.php';

		if (!is_file($filename))
		{
			touch($filename);
			chmod($filename, 0644);
		}

		// Messages to write
		$messages = array();
		$log = self::$log;

		do
		{
			list ($date, $type, $text) = array_shift($log);
			$messages[] = $date.' --- '.self::$log_levels[$type].': '.$text;
		}
		while (!empty($log));

		file_put_contents($filename, implode(PHP_EOL, $messages).PHP_EOL.PHP_EOL, FILE_APPEND);
	}

	/**
	 * Get or set the logging directory.
	 *
	 * @param   string  new log directory
	 * @return  string
	 */
	public static function log_directory($dir = NULL)
	{
		if (!empty($dir))
		{
			// Get the directory path
			$dir = realpath($dir);

			if (is_dir($dir) AND is_writable($dir))
			{
				// Change the log directory
				self::$log_directory = str_replace('\\', '/', $dir).'/';
			}
			else
			{
				echo "Can't write to log directory - {$dir}";
				exit;
			}
		}
		
		if(isset(self::$log_directory))
		{
			return self::$log_directory;
		}
		else
		{
			echo "No log directory set";
			exit;
		}
	}

	/**
	 * Find a resource file in a given directory. Files will be located according
	 * to the order of the include paths. config and i18n files will be
	 * returned in reverse order.
	 *
	 * @throws  Kohana_Exception  if file is required and not found
	 * @param   string   directory to search in
	 * @param   string   filename to look for (without extension)
	 * @param   boolean  file required
	 * @param   string   file extension
	 * @return  array    if the type is config, i18n or l10n
	 * @return  string   if the file is found
	 * @return  FALSE    if the file is not found
	 */
	public static function find_file($filename, $directory = '', $required = FALSE)
	{
		# Search path
		$search = $directory.'/'.$filename;
		
		if(file_exists($filename))
		{
			return self::$internal_cache['find_file_paths'][$filename] = $filename;
		}
		elseif(file_exists($search))
		{
			return self::$internal_cache['find_file_paths'][$search] = realpath($search);
		}
		
		if (isset(self::$internal_cache['find_file_paths'][$search]))
			return self::$internal_cache['find_file_paths'][$search];

		# Load include paths
		$paths = self::include_paths();

		# Nothing found, yet
		$found = NULL;

		if(in_array($directory, $paths))
		{
			if (is_file($directory.$filename))
			{
				# A matching file has been found
				$found = $search;
			}
		}
		else
		{
			foreach ($paths as $path)
			{
				if (is_file($path.$search))
				{
					# A matching file has been found
					$found = realpath($path.$search);

					# Stop searching
					break;
				}
				elseif (is_file(realpath($path.$search)))
				{
					# A matching file has been found
					$found = realpath($path.$search);

					# Stop searching
					break;
				}
			}
		}

		if ($found === NULL)
		{
			if ($required === TRUE)
			{
				# If the file is required, throw an exception
				throw new Exception("Cannot find the file: " . str_replace($_SERVER['DOCUMENT_ROOT'], '/', $search));
			}
			else
			{
				# Nothing was found, return FALSE
				$found = FALSE;
			}
		}
		
		# Write this cache to file
		if ( ! isset(self::$write_cache['find_file_paths']))
		{
			self::$write_cache['find_file_paths'] = TRUE;
		}

		return self::$internal_cache['find_file_paths'][$search] = $found;
	}

	/**
	 * Returns the value of a key, defined by a 'dot-noted' string, from an array.
	 *
	 * @param   array   array to search
	 * @param   string  dot-noted string: foo.bar.baz
	 * @return  string  if the key is found
	 * @return  void    if the key is not found
	 */
	public static function key_string($array, $keys)
	{
		if (empty($array))
			return NULL;

		# Prepare for loop
		$keys = explode('.', $keys);

		do 
		{
			// Get the next key
			$key = array_shift($keys);

			if (isset($array[$key]))
			{
				if (is_array($array[$key]) AND ! empty($keys))
				{
					# Dig down to prepare the next loop
					$array = $array[$key];
				}
				else
				{
					# Requested key was found
					return $array[$key];
				}
			}
			else
			{
				# Requested key is not set
				break;
			}
		}
		while ( ! empty($keys));

		return NULL;
	}

	/**
	 * Sets values in an array by using a 'dot-noted' string.
	 *
	 * @param   array   array to set keys in (reference)
	 * @param   string  dot-noted string: foo.bar.baz
	 * @return  mixed   fill value for the key
	 * @return  void
	 */
	public static function key_string_set( & $array, $keys, $fill = NULL)
	{
		if (is_object($array) AND ($array instanceof ArrayObject))
		{
			# Copy the array
			$array_copy = $array->getArrayCopy();

			# Is an object
			$array_object = TRUE;
		}
		else
		{
			if ( ! is_array($array))
			{
				# Must always be an array
				$array = (array) $array;
			}

			# Copy is a reference to the array
			$array_copy =& $array;
		}

		if (empty($keys))
			return $array;

		# Create keys
		$keys = explode('.', $keys);

		# Create reference to the array
		$row =& $array_copy;

		for ($i = 0, $end = count($keys) - 1; $i <= $end; $i++)
		{
			# Get the current key
			$key = $keys[$i];

			if ( ! isset($row[$key]))
			{
				if (isset($keys[$i + 1]))
				{
					# Make the value an array
					$row[$key] = array();
				}
				else
				{
					# Add the fill key
					$row[$key] = $fill;
				}
			}
			elseif (isset($keys[$i + 1]))
			{
				# Make the value an array
				$row[$key] = (array) $row[$key];
			}

			# Go down a level, creating a new row reference
			$row =& $row[$key];
		}

		if (isset($array_object))
		{
			# Swap the array back in
			$array->exchangeArray($array_copy);
		}
	}

	/**
	 * Lists all files and directories in a resource path.
	 *
	 * @param   string   directory to search
	 * @param   boolean  list all files to the maximum depth?
	 * @param   string   full path to search (used for recursion, *never* set this manually)
	 * @return  array    filenames and directories
	 */
	public static function list_files($directory, $recursive = FALSE, $path = FALSE)
	{
		$files = array();

		if ($path === FALSE)
		{
			$paths = array_reverse(self::include_paths());

			foreach ($paths as $path)
			{
				// Recursively get and merge all files
				$files = array_merge($files, self::list_files($directory, $recursive, $path.$directory));
			}
		}
		else
		{
			$path = rtrim($path, '/').'/';

			if (is_readable($path))
			{
				$items = (array) glob($path.'*');
				
				if ( ! empty($items))
				{
					foreach ($items as $index => $item)
					{
						$name = pathinfo($item, PATHINFO_BASENAME);
						
						if(substr($name, 0, 1) == '.' || substr($name, 0, 1) == '-')
						{
							continue;
						}
						
						$files[] = $item = str_replace('\\', '/', $item);

						// Handle recursion
						if (is_dir($item) AND $recursive == TRUE)
						{
							// Filename should only be the basename
							$item = pathinfo($item, PATHINFO_BASENAME);

							// Append sub-directory search
							$files = array_merge($files, self::list_files($directory, TRUE, $path.$item));
						}
					}
				}
			}
		}

		return $files;
	}

	/**
	 * Get a config item or group.
	 *
	 * @param   string   item name
	 * @param   boolean  force a forward slash (/) at the end of the item
	 * @param   boolean  is the item required?
	 * @return  mixed
	 */
	public static function config($key, $slash = FALSE, $required = FALSE)
	{
		// Get the group name from the key
		$group = explode('.', $key, 2);
		$group = $group[0];

		// Get the value of the key string
		$value = self::key_string(self::$config, $key);

		if ($slash === TRUE AND is_string($value) AND $value !== '')
		{
			// Force the value to end with "/"
			$value = rtrim($value, '/').'/';
		}

		return $value;
	}
	
	/**
	 * Sets a config item, if allowed.
	 *
	 * @param   string   config key string
	 * @param   string   config value
	 * @return  boolean
	 */
	public static function config_set($key, $value = "")
	{
		if(is_array($key))
		{
			foreach($key as $k => $v)
			{
				self::config_set($k,$v);
			}
			
			return true;
		}
		
		// Do this to make sure that the config array is already loaded
		self::config($key);

		// Convert dot-noted key string to an array
		$keys = explode('.', $key);

		// Used for recursion
		$conf =& self::$config;
		$last = count($keys) - 1;

		foreach ($keys as $i => $k)
		{
			if ($i === $last)
			{
				$conf[$k] = $value;
			}
			else
			{
				$conf =& $conf[$k];
			}
		}
		
		return true;
	}

	/**
	 * Loads a view file and returns it
	 *
	 * @param 	string	The name of the view
	 * @param	boolean	Whether to render the view, or return it
	 */
	public static function load_view( $view, $render = false, $return = false )
	{
		# Find the view file
		$view = self::find_file($view, 'views', true);
		
		# Display the view
		if ($render === true)
		{
			include $view;
			return;
		}

		# Buffering on
		ob_start();
		$view = file_get_contents($view);
		echo $view;
		
		# Fetch the output and close the buffer
		self::$internal_cache['output'] = ob_get_clean();
		
		if($return)
		{
			return self::$internal_cache['output'];
		}
	}
	
	/**
	 * Get all include paths. APPPATH is the first path, followed by module
	 * paths in the order they are configured, follow by the self::config('core.path.system').
	 *
	 * @param   boolean  re-process the include paths
	 * @return  array
	 */
	public static function include_paths()
	{
		if(!isset(self::$internal_cache['include_paths']))
		{
			self::$internal_cache['include_paths'] = array();
		}

		return self::$internal_cache['include_paths'];
	}
	
	/**
	 * Adds a path to the include paths list
	 *
	 * @author Anthony Short
	 * @param $path
	 * @return void
	 */
	public static function add_include_path($path)
	{
		if(!isset(self::$internal_cache['include_paths']))
		{
			self::$internal_cache['include_paths'] = array();
		}
		
		if(func_num_args() > 1)
		{
			$args = func_get_args();
			foreach($args as $inc)
			{
				self::add_include_path($inc);
			}
		}
		else
		{
			self::$internal_cache['include_paths'][] = Scaffold_Utils::fix_path($path);
		}
		
		self::$internal_cache['include_paths'] = array_unique(self::$internal_cache['include_paths']);
	}
	
	/**
	 * Removes an include path
	 *
	 * @author Anthony Short
	 * @param $path
	 * @return void
	 */
	public static function remove_include_path($path)
	{
		if(in_array($path, self::$include_paths))
		{
			unset(self::$include_paths[array_search($path, self::$include_paths)]);
		}
	}

	/**
	 * Sets a cache flag
	 *
	 * @author Anthony Short
	 * @param $name
	 * @return null
	 */
	public static function flag_set($name)
	{
		self::$internal_cache['flags'][] = $name;
		
		# We will write this cache to file
		if ( ! isset(self::$write_cache['flags']))
		{
			self::$write_cache['flags'] = TRUE;
		}
	}
	
	/**
	 * Checks if a flag is set
	 *
	 * @author Anthony Short
	 * @param $flag
	 * @return boolean
	 */
	public static function flag($flag)
	{
		return (in_array($flag,self::$flags)) ? true : false;
	}


	/**
	 * Checks to see if an option is set
	 *
	 * @author Anthony Short
	 * @param $name
	 * @return boolean
	 */
	public static function option($name)
	{
		return isset(self::$options[$name]);
	}
	
/**
	 * Saves the internal caches: configuration, include paths, etc.
	 *
	 * @return  boolean
	 */
	public static function internal_cache_save($single = false)
	{
		if($single)
		{
			$caches[] = $single;
		}
		else
		{
			if ( ! is_array(self::$write_cache))
				return FALSE;

			# Get internal cache names
			$caches = array_keys(self::$write_cache);
		}
		
		# Nothing written
		$written = FALSE;

		foreach ($caches as $cache)
		{
			if ( isset(self::$internal_cache[$cache]) )
			{
				# Write the cache file
				self::cache_write( serialize(self::$internal_cache[$cache]) , self::$cache_path . 'scaffold_'.$cache);
				
				# A cache has been written
				$written = TRUE;
			}
		}

		return $written;
	}

	/**
	 * Load data from a simple cache file.
	 *
	 * @param   string   unique name of cache
	 * @param   integer  expiration in seconds
	 * @return  mixed
	 */
	public static function cache($name, $lifetime)
	{		
		if ($lifetime > 0)
		{
			$path = self::$cache_path.'scaffold_'.$name;
			
			if (is_file($path))
			{
				# Check the file modification time
				if ((time() - filemtime($path)) < $lifetime)
				{
					return unserialize(file_get_contents($path));
				}
				else
				{
					# Cache is invalid, delete it
					unlink($path);
				}
			}
		}

		# No cache found
		return NULL;
	}
		
	/**
	 * Sets the cache path
	 *
	 * @author Anthony Short
	 * @param $path
	 * @return void
	 */
	public static function cache_set($path)
	{
		$path = Scaffold_Utils::fix_path($path);

		# Make sure the files/folders are writeable
		if (!is_dir($path))
			self::error("Cache path does not exist. $path");
			
		if (!is_writable($path))
			self::error("Cache path is not writable. $path");

		self::$cache_path = $path;
	}

	/**
	 * Write to the set cache
	 *
	 * @return void
	 * @author Anthony Short
	 */
	public static function cache_write( $data, $target = '' )
	{
		if($target == '')
		{
			$target = self::$cache_path;
		}
		
		$relative_file = str_replace( self::$cache_path, '', $target );
		$relative_dir = dirname($relative_file);
		 
		# Create the cache file
		self::cache_create($relative_dir);
		
		$target = Scaffold_Utils::join_path(self::$cache_path,$relative_file); 
		
		# Put it in the cache
		file_put_contents($target, $data);
		
		# Set its parmissions
		chmod($target, 0777);
		touch($target, time());
	}
	
	/**
	 * Creates an ID for using the cache
	 *
	 * @author Anthony Short
	 * @param $input
	 * @return string The md5 hashed string
	 */
	public static function cache_id($input)
	{
		if( is_array($input) )
		{
			$input = serialize($input);
		}
		
		return md5($input);
	}
	
	/**
	 * Create the cache file directory
	 */
	public static function cache_create($path)
	{	
		# If the cache path is included, get rid of it.
		$path = preg_replace('#'.self::$cache_path.'#', '', $path);

		# If it already exists
		if(is_dir(self::$cache_path.$path))
		{
			return true;
		}

		# Create the directories inside the cache folder
		$next = "";
				
		foreach(explode('/',$path) as $dir)
		{
			$next = Scaffold_Utils::join_path($next,$dir);

			if(!is_dir(self::$cache_path.$next)) 
			{
				mkdir(self::$cache_path.$next);
				chmod(self::$cache_path.$next, 0777);
			}
		}
		
		return true;
	}
}