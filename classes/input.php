<?php
/**
 * Fuel is a fast, lightweight, community driven PHP5 framework.
 *
 * @package    Fuel
 * @version    1.0
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2011 Fuel Development Team
 * @link       http://fuelphp.com
 */

namespace Fuel\Core;

/**
 * Input class
 *
 * The input class allows you to access HTTP parameters, load server variables
 * and user agent details.
 *
 * @package   Fuel
 * @category  Core
 * @link      http://fuelphp.com/docs/classes/input.html
 */
class Input {


	/**
	 * @var  $detected_uri  The URI that was detected automatically
	 */
	protected static $detected_uri = null;

	/**
	 * @var  $input  All of the input (GET, POST, PUT, DELETE)
	 */
	protected static $input = null;

	/**
	 * @var  $put_delete  All of the put or delete vars
	 */
	protected static $put_delete = null;

	/**
	 * Detects and returns the current URI based on a number of different server
	 * variables.
	 *
	 * @return  string
	 */
	public static function uri()
	{
		if (static::$detected_uri !== null)
		{
			return static::$detected_uri;
		}

		if (\Fuel::$is_cli)
		{
			if ($uri = \Cli::option('uri') !== null)
			{
				static::$detected_uri = $uri;
			}
			else
			{
				static::$detected_uri = \Cli::option(1);
			}

			return static::$detected_uri;
		}

		// We want to use PATH_INFO if we can.
		if ( ! empty($_SERVER['PATH_INFO']))
		{
			$uri = $_SERVER['PATH_INFO'];
		}
		// Only use ORIG_PATH_INFO if it contains the path
		elseif ( ! empty($_SERVER['ORIG_PATH_INFO']) and ($path = str_replace($_SERVER['SCRIPT_NAME'], '', $_SERVER['ORIG_PATH_INFO'])) != '')
		{
			$uri = $path;
		}
		else
		{
			// Fall back to parsing the REQUEST URI
			if (isset($_SERVER['REQUEST_URI']))
			{
				// Some servers require 'index.php?' as the index page
				// if we are using mod_rewrite or the server does not require
				// the question mark, then parse the url.
				if (\Config::get('index_file') != 'index.php?')
				{
					$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
				}
				else
				{
					$uri = $_SERVER['REQUEST_URI'];
				}
			}
			else
			{
				throw new \Fuel_Exception('Unable to detect the URI.');
			}

			// Remove the base URL from the URI
			$base_url = parse_url(\Config::get('base_url'), PHP_URL_PATH);
			if ($uri != '' and strncmp($uri, $base_url, strlen($base_url)) === 0)
			{
				$uri = substr($uri, strlen($base_url));
			}

			// If we are using an index file (not mod_rewrite) then remove it
			$index_file = \Config::get('index_file');
			if ($index_file and strncmp($uri, $index_file, strlen($index_file)) === 0)
			{
				$uri = substr($uri, strlen($index_file));
			}

			// Lets split the URI up in case it containes a ?.  This would
			// indecate the server requires 'index.php?' and that mod_rewrite
			// is not being used.
			preg_match('#(.*?)\?(.*)#i', $uri, $matches);

			// If there are matches then lets set set everything correctly
			if ( ! empty($matches))
			{
				$uri = $matches[1];
				$_SERVER['QUERY_STRING'] = $matches[2];
				parse_str($matches[2], $_GET);
			}
		}

		// Strip the defined url suffix from the uri if needed
		$ext = \Config::get('url_suffix');
		strrchr($uri, '.') === $ext and $uri = substr($uri,0,-strlen($ext));

		// Do some final clean up of the uri
		static::$detected_uri = \Security::clean_uri(str_replace(array('//', '../'), '/', $uri));

		return static::$detected_uri;
	}

	/**
	 * Get the public ip address of the user.
	 *
	 * @return  string
	 */
	public static function ip($default = '0.0.0.0')
	{
		if (static::server('REMOTE_ADDR') !== null)
		{
			return static::server('REMOTE_ADDR');
		}
		else
		{
			// detection failed, return the default
			return \Fuel::value($default);
		}
	}

	/**
	 * Get the real ip address of the user.  Even if they are using a proxy.
	 *
	 * @return  string  the real ip address of the user
	 */
	public static function real_ip($default = '0.0.0.0')
	{
		if (static::server('HTTP_X_CLUSTER_CLIENT_IP') !== null)
		{
			return static::server('HTTP_X_CLUSTER_CLIENT_IP');
		}
		
		if (static::server('HTTP_X_FORWARDED_FOR') !== null)
		{
			return static::server('HTTP_X_FORWARDED_FOR');
		}
		
		if (static::server('HTTP_CLIENT_IP') !== null)
		{
			return static::server('HTTP_CLIENT_IP');
		}
		
		if (static::server('REMOTE_ADDR') !== null)
		{
			return static::server('REMOTE_ADDR');
		}
		
		// detection failed, return the default
		return \Fuel::value($default);
	}

	/**
	 * Return's the protocol that the request was made with
	 *
	 * @return  string
	 */
	public static function protocol()
	{
		return (static::server('HTTPS') !== null and static::server('HTTPS') != 'off') ? 'https' : 'http';
	}

	/**
	 * Return's whether this is an AJAX request or not
	 *
	 * @return  bool
	 */
	public static function is_ajax()
	{
		return (static::server('HTTP_X_REQUESTED_WITH') !== null) and strtolower(static::server('HTTP_X_REQUESTED_WITH')) === 'xmlhttprequest';
	}

	/**
	 * Return's the referrer
	 *
	 * @return  string
	 */
	public static function referrer($default = '')
	{
		return static::server('HTTP_REFERER', $default);
	}

	/**
	 * Return's the input method used (GET, POST, DELETE, etc.)
	 *
	 * @return  string
	 */
	public static function method($default = 'GET')
	{
		return static::server('REQUEST_METHOD', $default);
	}

	/**
	 * Return's the user agent
	 *
	 * @return  string
	 */
	public static function user_agent($default = '')
	{
		return static::server('HTTP_USER_AGENT', $default);
	}

	/**
	 * Returns all of the GET, POST, PUT and DELETE variables.
	 *
	 * @return  array
	 */
	public static function all()
	{
		if (is_null(static::$input))
		{
			static::hydrate();
		}

		return static::$input;
	}

	/**
	 * Gets the specified GET variable.
	 *
	 * @param   string  $index    The index to get
	 * @param   string  $default  The default value
	 * @return  void
	 */
	public static function get($index = null, $default = null)
	{
		return (is_null($index) and func_num_args() === 0) ? $_GET : \Arr::get($_GET, $index, $default);
	}

	/**
	 * Fetch an item from the POST array
	 *
	 * @param   string  The index key
	 * @param   mixed   The default value
	 * @return  string|array
	 */
	public static function post($index = null, $default = null)
	{
		return (is_null($index) and func_num_args() === 0) ? $_POST : \Arr::get($_POST, $index, $default);
	}

	/**
	 * Fetch an item from the php://input for put arguments
	 *
	 * @param   string  The index key
	 * @param   mixed   The default value
	 * @return  string|array
	 */
	public static function put($index = null, $default = null)
	{
		if (is_null(static::$put_delete))
		{
			static::hydrate();
		}

		return (is_null($index) and func_num_args() === 0) ? static::$put_delete : \Arr::get(static::$put_delete, $index, $default);
	}

	/**
	 * Fetch an item from the php://input for delete arguments
	 *
	 * @param   string  The index key
	 * @param   mixed   The default value
	 * @return  string|array
	 */
	public static function delete($index = null, $default = null)
	{
		if (is_null(static::$put_delete))
		{
			static::hydrate();
		}

		return (is_null($index) and func_num_args() === 0) ? static::$put_delete : \Arr::get(static::$put_delete, $index, $default);
	}

	/**
	 * Fetch an item from the FILE array
	 *
	 * @param   string  The index key
	 * @param   mixed   The default value
	 * @return  string|array
	 */
	public static function file($index = null, $default = null)
	{
		return (is_null($index) and func_num_args() === 0) ? $_FILE : \Arr::get($_FILE, $index, $default);
	}

	/**
	 * Fetch an item from either the GET, POST, PUT or DELETE array
	 *
	 * @param   string  The index key
	 * @param   mixed   The default value
	 * @return  string|array
	 */
	public static function param($index = null, $default = null)
	{
		if (is_null(static::$input))
		{
			static::hydrate();
		}

		return \Arr::get(static::$input, $index, $default);
	}

	/**
	 * Fetch an item from either the GET array or the POST
	 *
	 * @param   string  The index key
	 * @param   mixed   The default value
	 * @return  string|array
	 * @deprecated until 1.2
	 */
	public static function get_post($index = null, $default = null)
	{
		return static::param($index, $default);
	}

	/**
	 * Fetch an item from the COOKIE array
	 *
	 * @param    string  The index key
	 * @param    mixed   The default value
	 * @return   string|array
	 */
	public static function cookie($index = null, $default = null)
	{
		return (is_null($index) and func_num_args() === 0) ? $_COOKIE : \Arr::get($_COOKIE, $index, $default);
	}

	/**
	 * Fetch an item from the SERVER array
	 *
	 * @param   string  The index key
	 * @param   mixed   The default value
	 * @return  string|array
	 */
	public static function server($index = null, $default = null)
	{
		return (is_null($index) and func_num_args() === 0) ? $_SERVER : \Arr::get($_SERVER, $index, $default);
	}

	/**
	 * Hydrates the input array
	 *
	 * @return  void
	 */
	protected static function hydrate()
	{
		static::$input = array_merge($_GET, $_POST);
		
		if (\Input::method() == 'PUT' or \Input::method() == 'DELETE')
		{
			static::$put_delete = parse_str(file_get_contents('php://input'));
			static::$input = array_merge(static::$input, static::$put_delete);
		}
	}
}
