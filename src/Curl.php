<?php namespace JFusion\Curl;
/**
 * curl login model
 *
 * PHP version 5
 *
 * @category  JFusion
 * @package   Models
 * @author    Henk Wevers <henk@wevers.net>
 * @copyright 2008 - 2011  JFusion - Henk Wevers. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
use JFusion\Factory;
use Joomla\Language\Text;

/**
 * Singleton static only class that creates instances for each specific JFusion plugin.
 *
 * @category  JFusion
 * @package   Models
 * @author    Henk Wevers <henk@wevers.net>
 * @copyright 2008 JFusion - Henk Wevers. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class Curl
{
	var $options = array();
	/**
	 * @var $ch resource
	 */
	var $ch = null;
	var $location = null;
	var $cookies = array();
	var $cookiearr = array();
	var $status = array();

	/**
	 * @params array $options
	 */
	function __construct($options=array())
	{
		$this->options = $options;

		// check parameters and set defaults
		if (!isset($this->options['integrationtype'])) {
			$this->options['integrationtype'] = 1;
		}
		if (!isset($this->options['relpath'])) {
			$this->options['relpath'] = false;
		}
		if (!isset($this->options['hidden'])) {
			$this->options['hidden'] = false;
		}
		if (!isset($this->options['buttons'])) {
			$this->options['buttons'] = false;
		}
		if (!isset($this->options['override'])) {
			$this->options['override'] = null;
		}
		if (!isset($this->options['cookiedomain'])) {
			$this->options['cookiedomain'] = '';
		}
		if (!isset($this->options['cookiepath'])) {
			$this->options['cookiepath'] = '';
		}
		if (!isset($this->options['expires'])) {
			$this->options['expires'] = 1800;
		}
		if (!isset($this->options['input_username_id'])) {
			$this->options['input_username_id'] = '';
		}
		if (!isset($this->options['input_password_id'])) {
			$this->options['input_password_id'] = '';
		}
		if (!isset($this->options['secure'])) {
			$this->options['secure'] = 0;
		}
		if (!isset($this->options['httponly'])) {
			$this->options['httponly'] = 0;
		}
		if (!isset($this->options['verifyhost'])) {
			$this->options['verifyhost'] = 2;
		}
		if (!isset($this->options['debug'])) {
			$this->options['debug'] = false;
		}
		if (!isset($this->options['leavealone'])) {
			$this->options['leavealone'] = null;
		}
		if (!isset($this->options['postfields'])) {
			$this->options['postfields'] = '';
		}

		$this->status = array();
		$this->status['error'] = array();
		$this->status['debug'] = array();
		$this->status['cURL']=array();
		$this->status['cURL']['moodle'] = '';
		$this->status['cURL']['data']= array();
	}

	/**
	 * Translate function, mimics the php gettext (alias _) function
	 *
	 * Do not overload when used within Joomla, the function simply calls Jtext::_
	 * When you use the JFusionCurl class outside Joomla, f.i. as part of an DSSO extension in an integration
	 * then you have to overload this function to provide the translated strings probably using native code
	 *
	 * @param string  $string The string to translate
	 * @param boolean $jsSafe Make the result javascript safe
	 *
	 * @return string The translation of the string
	 **/
	public static function _($string, $jsSafe = false)
	{
		return Text::_($string, $jsSafe);
	}

	/**
	 * Retrieve the cookies as a string cookiename=cookievalue; or as an array
	 *
	 * @param string $type the type
	 *
	 * @return string or array
	 */
	public function buildCookie($type = 'string')
	{
		switch ($type) {
			case 'array':
				return $_COOKIE;
				break;
			case 'string':
			default:
				return $this->implodeCookies($_COOKIE, ';');
				break;
		}
	}

	/**
	 * Can implode an array of any dimension
	 * Uses a few basic rules for implosion:
	 *        1. Replace all instances of delimeters in strings by '/' followed by delimeter
	 *        2. 2 Delimeters in between keys
	 *        3. 3 Delimeters in between key and value
	 *        4. 4 Delimeters in between key-value pairs
	 *
	 * @param array  $array     array
	 * @param string $delimeter delimeter
	 * @param string $keyssofar keyssofar
	 *
	 * @return string imploded cookies
	 */
	function implodeCookies($array, $delimeter, $keyssofar = '')
	{
		$output = '';
		foreach ($array as $key => $value) {
			if (!is_array($value)) {
				if ($keyssofar) {
					$pair = $keyssofar . '[' . $key . ']=' . urlencode($value) . $delimeter;
				} else {
					$pair = $key . '=' . urlencode($value) . $delimeter;
				}
				if ($output != '') {
					$output .= ' ';
				}
				$output .= $pair;
			} else {
				if ($output != '') {
					$output .= ' ';
				}
				$output .= $this->implodeCookies($value, $delimeter, $key . $keyssofar);
			}
		}
		return $output;
	}


	/**
	 * curlRedirectExec
	 *
	 * @return string something
	 */
	function curlRedirectExec()
	{
		static $curl_loops = 0;
		static $curl_max_loops = 20;
		if ($curl_loops++ >= $curl_max_loops) {
			$curl_loops = 0;
			return false;
		}

		curl_setopt($this->ch, CURLOPT_HEADER, true);
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
		$data = curl_exec($this->ch);
		$lastdata = $data;
		$data = str_replace("\r", '', $data);
		list($header, $data) = explode("\n\n", $data, 2);
		$http_code = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
		if ($http_code == 301 || $http_code == 302) {
			$matches = array();
			preg_match('/Location:(.*?)\n/', $header, $matches);
			$url = @parse_url(trim(array_pop($matches)));
			if (!$url) {
				//couldn't process the url to redirect to
				$curl_loops = 0;
				return $data;
			}
			/*
			$last_url = parse_url(curl_getinfo($this->ch, CURLINFO_EFFECTIVE_URL));
			if (!$url['scheme']) {
				$url['scheme'] = $last_url['scheme'];
			}
			if (!$url['host']) {
				$url['host'] = $last_url['host'];
			}
			if (!$url['path']) {
				$url['path'] = $last_url['path'];
			}
			*/
			$new_url = $url['scheme'] . '://' . $url['host'] . $url['path'] . ($url['query'] ? '?' . $url['query'] : '');
			curl_setopt($this->ch, CURLOPT_URL, $new_url);
			return $this->curlRedirectExec();
		} else {
			$curl_loops=0;
			return $lastdata;
		}
	}

	/**
	 * function readHeader
	 * Basic  code was found on Svetlozar Petrovs website http://svetlozar.net/page/free-code.html.
	 * The code is free to use and similar code can be found on other places on the net.
	 *
	 * @param resource $ch     ch
	 * @param string $string string
	 *
	 * @return string something
	 */
	function readHeader($ch, $string)
	{
		$length = strlen($string);
		if (!strncmp($string, 'Location:', 9)) {
			$this->location = trim(substr($string, 9, -1));
		}
		if (!strncmp($string, 'Set-Cookie:', 11)) {
			//	header($string, false);
			$cookiestr = trim(substr($string, 11, -1));
			$cookie = explode(';', $cookiestr);
			$this->cookies[] = $cookie;
			$cookie = explode('=', $cookie[0]);
			$cookiename = trim(array_shift($cookie));
			$this->cookiearr[$cookiename] = trim(implode('=', $cookie));
		}

		$cookie = '';
		if (!empty($this->cookiearr) && (trim($string) == '')) {
			foreach ($this->cookiearr as $key => $value) {
				$cookie .= $key . '=' . $value . '; ';
			}
			curl_setopt($this->ch, CURLOPT_COOKIE, $cookie);
		}
		return $length;
	}

	/**
	 * function parseURL
	 * out[0] = full url
	 * out[1] = scheme or '' if no scheme was found
	 * out[2] = username or '' if no auth username was found
	 * out[3] = password or '' if no auth password was found
	 * out[4] = domain name or '' if no domain name was found
	 * out[5] = port number or '' if no port number was found
	 * out[6] = path or '' if no path was found
	 * out[7] = query or '' if no query was found
	 * out[8] = fragment or '' if no fragment was found
	 *
	 * @param string $url url
	 *
	 * @return array output
	 */
	function parseUrl($url)
	{
		$r = '!(?:(\w+)://)?(?:(\w+)\:(\w+)@)?([^/:]+)?';
		$r .= '(?:\:(\d*))?([^#?]+)?(?:\?([^#]+))?(?:#(.+$))?!i';
		preg_match($r, $url, $out);
		return $out;
	}

	/**
	 * parses cookies
	 *
	 * @param array $cookielines cookies
	 *
	 * @return array parsed cookies
	 */
	function parsecookies($cookielines)
	{
		$cookies=array();
		foreach ($cookielines as $line) {
			$cdata = array();
			foreach ($line as $data) {
				$cinfo = explode('=', $data);
				$cinfo[0] = trim($cinfo[0]);
				if (!isset($cinfo[1])) {
					$cinfo[1] = '';
				}
				if (strcasecmp($cinfo[0], 'expires') == 0) {
					$cinfo[1] = strtotime($cinfo[1]);
				}
				if (strcasecmp($cinfo[0], 'secure') == 0) {
					$cinfo[1] = 'true';
				}
				if (strcasecmp($cinfo[0], 'httponly') == 0) {
					$cinfo[1] = 'true';
				}
				if (in_array(strtolower($cinfo[0]), array('domain', 'expires', 'path', 'secure', 'comment', 'httponly'))) {
					$cdata[trim($cinfo[0])] = $cinfo[1];
				} else {
					$cdata['value']['key'] = $cinfo[0];
					$cdata['value']['value'] = $cinfo[1];
				}
			}
			$cookies[] = $cdata;
		}
		return $cookies;
	}

	/**
	 * Adds a cookie to the php header
	 *
	 * @param string $name            cookie name
	 * @param string $value           cookie value
	 * @param int    $expires         cookie expiry time
	 * @param string $cookiepath      cookie path
	 * @param string $cookiedomain    cookie domain
	 * @param int $secure          secure
	 * @param int $httponly        is the cookie http only
	 *
	 * @return array nothing
	 */
	public static function addCookie($name, $value = '', $expires = 0, $cookiepath = '', $cookiedomain = '', $secure = 0, $httponly  = 0)
	{
		if (strpos($cookiedomain, 'http://') === 0 || strpos($cookiedomain, 'https://') === 0) {
			$jc = Factory::getCookies();
			$now = time();
			if ($expires) {
				if ($expires>$now) {
					$expires = $expires-$now;
				} else {
					$expires = $now-$expires;
				}
			}
			$debug = $jc->addCookie($name, $value, $expires, $cookiepath, $cookiedomain, $secure, $httponly);
		} else {
			// Versions of PHP prior to 5.2 do not support HttpOnly cookies
			// IE is buggy when specifying a blank domain so set the cookie manually
			// solve the empty cookiedomain IE problem by specifying a domain in the plugins parameters. <------
			if (version_compare(phpversion(), '5.2.0', '>=')) {
				setcookie($name, $value, $expires, $cookiepath, $cookiedomain, $secure, $httponly);
			} else {
				setcookie($name, $value, $expires, $cookiepath, $cookiedomain, $secure);
			}

			$debug = array();
			$debug[static::_('COOKIE')][static::_('JFUSION_CROSS_DOMAIN_URL')] = null;
			$debug[static::_('COOKIE')][static::_('COOKIE_DOMAIN')] = $cookiedomain;
			$debug[static::_('COOKIE')][static::_('NAME')] = $name;
			$debug[static::_('COOKIE')][static::_('VALUE')] = $value;
			if (($expires) == 0) {
				$expires = 'Session_cookie';
			} else {
				$expires = date('d-m-Y H:i:s', $expires);
			}
			$debug[static::_('COOKIE')][static::_('COOKIE_EXPIRES')] = $expires;
			$debug[static::_('COOKIE')][static::_('COOKIE_PATH')] = $cookiepath;
			$debug[static::_('COOKIE')][static::_('COOKIE_SECURE')] = $secure;
			$debug[static::_('COOKIE')][static::_('COOKIE_HTTPONLY')] = $httponly;
		}
		return $debug;
	}

	/**
	 * @param     $cookiedomain
	 * @param     $cookiepath
	 * @param int $expires
	 * @param int $secure
	 * @param int $httponly
	 *
	 * @return array
	 */
	public function setCookies($cookiedomain, $cookiepath, $expires=0, $secure=0, $httponly=1)
	{
		$cookies = $this->parsecookies($this->cookies);
		foreach ($cookies as $cookie) {
			$name = '';
			$value = '';
			if ($expires == 0) {
				$expires_time = 0;
			} else {
				$expires_time = time()+$expires;
			}
			if (isset($cookie['value']['key'])) {
				$name = $cookie['value']['key'];
			}
			if (isset($cookie['value']['value'])) {
				$value = $cookie['value']['value'];
			}
			if (isset($cookie['expires'])) {
				$expires_time = $cookie['expires'];
			}
			if (!$cookiepath) {
				if (isset($cookie['path'])) {
					$cookiepath = $cookie['path'];
				}
			}
			if (!$cookiedomain) {
				if (isset($cookie['domain'])) {
					$cookiedomain = $cookie['domain'];
				}
			}
			$this->status['debug'][] = $this->addCookie($name, urldecode($value), $expires_time, $cookiepath, $cookiedomain, $secure, $httponly);
			if ($name == 'MOODLEID_') {
				$this->status['cURL']['moodle'] = urldecode($value);
			}
		}
		return $this->status;
	}

	/**
	 * sets my cookies
	 *
	 * @param string $status           status
	 * @param string $cookies     cookies
	 * @param string $cookiedomain     cookie domain
	 * @param string $cookiepath       cookie path
	 * @param int $expires          expires
	 * @param int $secure           secure
	 * @param int $httponly         is the cookie http only
	 *
	 * @return string nothing
	 */
	public static function setmycookies($status, $cookies, $cookiedomain, $cookiepath, $expires=0, $secure=0, $httponly=1)
	{
		$options = array();
		$options['cookiedomain'] = $cookiedomain;
		$options['cookiepath'] = $cookiepath;
		$options['expires'] = $expires;
		$options['secure'] = $secure;
		$options['httponly'] = $httponly;

		$curl = new Curl($options);
		$curl->cookies = $cookies;
		$curl->status = $status;
		return $curl->setCookies($cookiedomain, $cookiepath, $expires, $secure, $httponly);
	}

	/**
	 * delete my cookies
	 *
	 * @param string $cookiedomain     cookie domain
	 * @param string $cookiepath       cookie path
	 * @param string $leavealone       leavealone
	 * @param int $secure           secure
	 * @param int $httponly         is the cookie http only
	 *
	 * @return string nothing
	 */
	public function deleteCookies($cookiedomain, $cookiepath, $leavealone, $secure=0, $httponly=1)
	{
		$cookies = $this->parsecookies($this->cookies);
		// leavealone keys/values while deleting
		// the $leavealone is an array of key=value that controls cookiedeletion
		// key = value
		// if key is an existing cookiename then that cookie will be affected depending on the value
		// if value = '>' then the 'name' cookies with an expiration date/time > now() will not be deleted
		// if value = '0' then  the 'name' cookies will never be deleted at all
		// if name is a string than the cookie with that name will be affected
		// if name = '0' then all cookies will be affected according to the value
		// thus
		// MOODLEID_=> keeps the cookie with the name MOODLEID_ if expirationtime lies after now()
		// 0=> will keep all cookies that are not sessioncookies
		// 0=0 will keep all cookies

		$leavealonearr = array();
		if (trim($leavealone)) {
			$lines = explode(',', $leavealone);
			$i = 0;

			foreach ($lines as $line) {
				$cinfo = explode('=', $line);
				if (isset($cinfo[1])) {
					$leavealonearr[$i]['name']  = $cinfo[0];
					$leavealonearr[$i]['value'] = $cinfo[1];
					$i++;
				}
			}
		}

		foreach ($cookies as $cookie) {
			// check if we should leave the cookie alone
			$leaveit = false;
			if ($leavealone) {
				for ($i=0;$i<count($leavealonearr);$i++) {
					if (isset($cookie['value']['key'])) {
						if (($cookie['value']['key'] == $leavealonearr[$i]['name']) || ($leavealonearr[$i]['name'] == '0')) {
							if (($leavealonearr[$i]['value'] == '0')||($cookie['expires'] > time())) {
								$leaveit = true;
							}
						}
					}
				}
			}
			$name = '';
			if (isset($cookie['value']['key'])) {
				$name= $cookie['value']['key'];
			}
			if (isset($cookie['expires'])) {
				$expires_time = $cookie['expires'];
			} else {
				$expires_time = 0;
			}
			if (!$cookiepath) {
				if (isset($cookie['path'])) {
					$cookiepath = $cookie['path'];
				}
			}
			if (!$cookiedomain) {
				if (isset($cookie['domain'])) {
					$cookiedomain = $cookie['domain'];
				}
			}
			if ($name == 'MOODLEID_') {
				$this->status['cURL']['moodle'] = urldecode($cookie['value']['value']);
			}

			if (!$leaveit) {
				$expires_time = time()-30*60;
				$this->status['debug'][] = $this->addCookie($name, urldecode(''), $expires_time, $cookiepath, $cookiedomain, $secure, $httponly);
			} else {
				$this->status['debug'][] = $this->addCookie($name, urldecode($cookie['value']['value']), $expires_time, $cookiepath, $cookiedomain, $secure, $httponly);
			}
		}
		return $this->status;
	}


	/**
	 * delete my cookies
	 *
	 * @param string $status           cookie name
	 * @param string $cookies          cookies
	 * @param string $cookiedomain     cookie domain
	 * @param string $cookiepath       cookie path
	 * @param string $leavealone       leavealone
	 * @param int $secure              secure
	 * @param int $httponly            is the cookie http only
	 *
	 * @return string nothing
	 */
	public function deletemycookies($status, $cookies, $cookiedomain, $cookiepath, $leavealone, $secure=0, $httponly=1)
	{
		$options = array();
		$options['cookiedomain'] = $cookiedomain;
		$options['cookiepath'] = $cookiepath;
		$options['secure'] = $secure;
		$options['httponly'] = $httponly;

		$curl = new Curl($options);
		$curl->cookies = $cookies;
		$curl->status = $status;
		return $curl->deleteCookies($cookiedomain, $cookiepath, $leavealone, $secure, $httponly);
	}

	/**
	 * function ReadPage
	 * This function will read a page of an integration
	 * Caller should make sure that the Curl extension is loaded
	 *
	 * @param bool $curlinit
	 *
	 * @return string page read
	 */

	public function ReadPage($curlinit=true)
	{
		$open_basedir = ini_get('open_basedir');
		$safe_mode = ini_get('safe_mode');

		// read the page
		if ($curlinit) {
			$this->ch = curl_init();
		}
		$ip = $_SERVER['REMOTE_ADDR'];
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, array('REMOTE_ADDR: ' . $ip, 'X_FORWARDED_FOR: ' . $ip));
		curl_setopt($this->ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
		curl_setopt($this->ch, CURLOPT_URL, $this->options['post_url']);
		curl_setopt($this->ch, CURLOPT_REFERER, "");
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, $this->options['verifyhost']);
		curl_setopt($this->ch, CURLOPT_FAILONERROR, 1);
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->ch, CURLOPT_VERBOSE, $this->options['debug']); // Display communication with server
		if (empty($open_basedir) && empty($safe_mode)) {
			curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 1);
		}
		curl_setopt($this->ch, CURLOPT_MAXREDIRS, 2);
		curl_setopt($this->ch, CURLOPT_HEADERFUNCTION, array($this, 'readHeader'));
		if (empty($this->options['brute_force'])) {
			curl_setopt($this->ch, CURLOPT_COOKIE, $this->buildCookie());
		}

		if (!empty($this->options['httpauth'])) {
			curl_setopt($this->ch, CURLOPT_USERPWD, $this->options['httpauth_username'] . ':' . $this->options['httpauth_password']);

			switch ($this->options['httpauth']) {
				case "basic":
					$this->options['httpauth'] = CURLAUTH_BASIC;
					break;
				case "gssnegotiate":
					$this->options['httpauth'] = CURLAUTH_GSSNEGOTIATE;
					break;
				case "digest":
					$this->options['httpauth'] = CURLAUTH_DIGEST;
					break;
				case "ntlm":
					$this->options['httpauth'] = CURLAUTH_NTLM;
					break;
				case "anysafe":
					$this->options['httpauth'] = CURLAUTH_ANYSAFE;
					break;
				case "any":
				default:
					$this->options['httpauth'] = CURLAUTH_ANY;
			}

			curl_setopt($this->ch, CURLOPT_HTTPAUTH, $this->options['httpauth']);
		}

		if (empty($open_basedir) && empty($safe_mode)) {
			$remotedata = curl_exec($this->ch);
		} else {
			$remotedata = $this->curlRedirectExec();
		}
		if ($this->options['debug']) {
			$this->status['cURL']['data'][] = $remotedata;
			$this->status['debug'][] = 'CURL_INFO: ' . print_r(curl_getinfo($this->ch), true);
		}
		if (curl_error($this->ch)) {
			$this->status['error'][] = static::_('CURL_ERROR_MSG') . ': ' . curl_error($this->ch);
			curl_close($this->ch);
			$remotedata =  null;
		} else if ($this->options['integrationtype'] == 1) {
			curl_close($this->ch);
		}
		return $remotedata;
	}

	/**
	 * preforms a login
	 */
	public function login()
	{
		// extra lines for passing curl options to other routines, like ambrasubs payment processor
		// we are using the super global $_SESSION to pass data in $_SESSION[$var]
		if (isset($this->options['usesessvar'])) {
			$var = 'curl_options';
			if(!array_key_exists($var, $_SESSION)) $_SESSION[$var] = '';
			$_SESSION[$var] = $this->options;
			$GLOBALS[$var] = &$_SESSION[$var];
		}
		// end extra lines
		$overridearr = array();

		// find out if we have a SSL enabled website
		if (strpos($this->options['post_url'], 'https://') === false) {
			$ssl_string = 'http://';
		} else {
			$ssl_string = 'https://';
		}

		// check if curl extension is loaded
		if (!isset($this->options['post_url']) || !isset($this->options['formid'])) {
			$this->status['error'][] = static::_('CURL_FATAL');
		} else {
			if (!extension_loaded('curl')) {
				$this->status['error'][] = static::_('CURL_NOTINSTALLED');
			} else {
				$this->status['debug'][] = static::_('CURL_POST_URL_1') . ' ' . $this->options['post_url'];
				$remotedata = $this->ReadPage(true);
				if (empty($this->status['error'])) {
					$this->status['debug'][] = static::_('CURL_PHASE_1');
					$this->setCookies($this->options['cookiedomain'], $this->options['cookiepath'], $this->options['expires'], $this->options['secure'], $this->options['httponly']);
					//find out if we have the form with the name/id specified
					$parser = new HtmlFormParser($remotedata);
					$result = $parser->parseForms();
					$frmcount = count($result);
					$myfrm = -1;
					$i = 0;
                    // HJW changed 12 january 2013 to accommodate from identifiers changing every
                    // new access to the page containing the form. Like logonform123 , loginform a2s
                    // etc.

                    do {
                        if (isset($result[$i]['form_data']['name'])) {
                            if (strpos(strtolower($result[$i]['form_data']['name']),strtolower($this->options['formid'] )) !== false){
                                $myfrm = $i;
                                break;
                            }
                        }
                        if (isset($result[$i]['form_data']['id'])) {
                            if (strpos(strtolower($result[$i]['form_data']['id']),strtolower($this->options['formid'] )) !== false){
                                $myfrm = $i;
                                break;
                            }
                        }
	                    /** HJW 14-7-2014
	                      * we really should use the action field if there is no name field and no id field,
	                      * otherwise we may trigger a false match too easily
	                      * (eg match login with www.mydomain.com/login/index.php)
	                     */
                        if (isset($result[$i]['form_data']['action']) && !isset($result[$i]['form_data']['id']) && !isset($result[$i]['form_data']['name'])) {
                            if (strpos(strtolower(htmlspecialchars_decode($result[$i]['form_data']['action'])),strtolower($this->options['formid'] )) !== false){
                                $myfrm = $i;
                                break;
                            }
                        }
                        $i +=1;
                    } while ($i<$frmcount);

					if ($myfrm == -1) {
						$helpthem = '';
						if ($frmcount >0) {
							$i = 0;
							$helpthem = 'I found';
							do {
								if (isset($result[$i]['form_data']['id'])) {
									$helpthem = $helpthem . ' -- Name=' . $result[$i]['form_data']['name'] . ' &ID=' . $result[$i]['form_data']['id'];
								}
								$i +=1;
							} while ($i<$frmcount);
						}
						$this->status['debug'][] = static::_('CURL_NO_LOGINFORM') . ' ' . $helpthem;
					} else {
						$this->status['debug'][] = static::_('CURL_VALID_FORM');


						// by now we have the specified  login/logout form, lets get the data needed to login/logout
						// we went to all this trouble to get to the hidden input entries.
						// The stuff is there to enhance security and is, yes, hidden
						$form_action = htmlspecialchars_decode($result[$myfrm]['form_data']['action']);
						$elements_keys = array_keys($result[$myfrm]['form_elements']);
						$elements_values = array_values($result[$myfrm]['form_elements']);
						$elements_count  = count($result[$myfrm]['form_elements']);

						// override keys/values from hidden inputs
						// the $override is an array of keys/values that override existing keys/values

						if (empty($this->options['logout'])) {

							if ($this->options['override']) {
								$lines = explode(',', $this->options['override']);
								foreach ($lines as $line) {
									$cinfo = explode('=', $line);
									$overridearr[$cinfo[0]]['value'] = $cinfo[1];
									$overridearr[$cinfo[0]]['type'] = 'hidden';
								}
								$newhidden = array_merge($result[$myfrm]['form_elements'], $overridearr);
								$elements_keys = array_keys($newhidden);
								$elements_values = array_values($newhidden);
								$elements_count  = count($newhidden);
							}
						}
						// now construct the action parameter

						// we have 4 possible options:
						// case 0 Form action is without http.. and relpath = 0 , special case
						// case 1 Form action is without http.. and relpath = 1 , just construct the action
						// case 2 form_action is a full url, eg http..... and relpath = 0 This is easy, we do nothing at all
						// case 3 form_action is a full url, eg http..... and relpath = 1 special case

						$rel = (int)($this->options['relpath']);
						//      if (substr($form_action, 0, strlen($ssl_string))== $ssl_string) $hashttp = 2; else $hashttp = 0;
						if (substr($form_action, 0, strlen('http')) == 'http') {
							$hashttp = 2;
						} else {
							$hashttp = 0;
						}

						switch($rel+$hashttp) {
							case 0:
								//add a / in front of form_action
								if (substr($form_action, 0, 1) != '/') {
									$form_action = '/' . $form_action;
								}
								// we need to correct various situations like
								// relative url from basedir, relative url from post dir etc
								$tmpurl   = static::parseUrl($this->options['post_url']);
								$pathinfo1  = pathinfo($form_action);
								$pathinfo = pathinfo($tmpurl[6]);
								//$this->status['debug'][] = 'post_url   : ' . print_r($this->options['post_url'], true);
								//$this->status['debug'][] = 'tmpurl     : ' . print_r($tmpurl, true);
								//$this->status['debug'][] = 'form_action: ' . print_r($form_action, true);
								//$this->status['debug'][] = 'pathinfo1  : ' . print_r($pathinfo1, true);
								//$this->status['debug'][] = 'pathinfo   : ' . print_r($pathinfo, true);
								if ($pathinfo['dirname'] == $pathinfo1['dirname']) {
									$pathinfo['dirname'] = '';
								} //prevent double directory

								// replace windows DIRECTORY_SEPARATOR bt unix DIRECTORY_SEPARATOR
								$pathinfo['dirname'] = str_replace("\\", '/', $pathinfo['dirname']);
								// get rid of the trailing /  in dir
								rtrim($pathinfo['dirname'], '/');
								$port = !empty($tmpurl[5]) ? ':' . $tmpurl[5] : '';
								$form_action = $ssl_string . $tmpurl[4] . $port . $pathinfo['dirname'] . $form_action;
								//$this->status['debug'][] = 'form_action_final: ' . print_r($form_action, true);
								break;
							case 1:
								//add a / in front of form_action
								if (substr($form_action, 0, 1) != '/') {
									$form_action = '/' . $form_action;
								}
								$this->options['post_url'] = rtrim($this->options['post_url'], '/');
								$form_action = $this->options['post_url'] . $form_action;
								break;
							case 2:
								//do nothing at all
								break;
							case 3:
								// reserved, maybe something pops up, then we use this
								break;
						}

						$input_username_name = '';
						$input_password_name = '';
						if (empty($this->options['logout'])) {
							for ($i = 0; $i <= $elements_count-1; $i++) {
								if ($this->options['input_username_id']) {
									if (strtolower($elements_keys[$i]) == strtolower($this->options['input_username_id'])) {
										$input_username_name = $elements_keys[$i];
										break;
									}
								}
								if ($input_username_name == '') {
									if (strpos(strtolower($elements_keys[$i]), 'user') !== false) {
										$input_username_name = $elements_keys[$i];
									}
									if (strpos(strtolower($elements_keys[$i]), 'name') !== false) {
										$input_username_name = $elements_keys[$i];
									}
								}
							}


							if ($input_username_name == '') {
								$this->status['error'][] = static::_('CURL_NO_NAMEFIELD');
								return $this->status;
							}

							for ($i = 0; $i <= $elements_count-1; $i++) {
								if ($this->options['input_password_id']) {
									if (strtolower($elements_keys[$i]) == strtolower($this->options['input_password_id'])) {
										$input_password_name = $elements_keys[$i];
										break;
									}
								}
								if (strpos(strtolower($elements_keys[$i]), 'pass') !== false) {
									$input_password_name = $elements_keys[$i];
								}
							}

							if ($input_password_name == '') {
								$this->status['error'][] = static::_('CURL_NO_PASSWORDFIELD');
								return $this->status;
							}
							$this->status['debug'][] = static::_('CURL_VALID_USERNAME');
						}
						// we now set the submit parameters. These are:
						// all form_elements name=value combinations with value != '' and type hidden
						$strParameters = '';
						if ($this->options['hidden']) {
							for ($i = 0; $i <= $elements_count-1; $i++) {
								if (($elements_values[$i] ['value'] != '') && ($elements_values[$i]['type'] == 'hidden')) {
									$strParameters .= '&' . $elements_keys[$i] . '=' . urlencode($elements_values[$i]['value']);
								}
							}
						}

						// code for buttons submitted by Daniel Baur
						if ($this->options['buttons']) {
							if (isset($result[$myfrm] ['buttons'][0]['type'])) {
								if ($result[$myfrm] ['buttons'][0]['type'] == 'submit') {
									if ($result[$myfrm]['buttons'][0]['name']) {
										$strParameters .= '&' . $result[$myfrm]['buttons'][0]['name'] . '=' . urlencode($result[$myfrm]['buttons'][0]['value']);
									} else {
										$strParameters .= '&' . 'submit=' . urlencode($result[$myfrm]['buttons'][0]['value']);
									}
								}
							}
						}

						// extra post parameter to avoid endless loop when more then one jFusion is installed
						if (isset($this->options['jnodeid'])) {
							$strParameters .= '&jnodeid=' . urlencode($this->options['jnodeid']);
						}

						// extra post parameter to signal a host calling
						if (isset($this->options['jhost'])) {
							$strParameters .= '&jhost=true';
						}

						if (empty($this->options['logout'])) {
							$post_params = $input_username_name . '=' . urlencode($this->options['username']) . '&' . $input_password_name . '=' . urlencode($this->options['password']);
							$post_params_debug = $input_username_name . '=' . urlencode($this->options['username']) . '&' . $input_password_name . '=xxxxxx';
							$this->status['debug'][] = static::_('CURL_STARTING_LOGIN') . ' ' . $form_action . ' parameters= ' . $post_params_debug . $strParameters;
						} else {
							$post_params = '';
							$this->status['debug'][] = static::_('CURL_STARTING_LOGOUT') . ' ' . $form_action . ' parameters= ' . $strParameters;
						}

						// finally submit the login/logout form:
						if ($this->options['integrationtype'] == 1) {
							$this->ch = curl_init();
							$ip = $_SERVER['REMOTE_ADDR'];
							curl_setopt($this->ch, CURLOPT_HTTPHEADER, array('REMOTE_ADDR: ' . $ip, 'X_FORWARDED_FOR: ' . $ip));
							curl_setopt($this->ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
							curl_setopt($this->ch, CURLOPT_REFERER, '');
							curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, 0);
							curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, $this->options['verifyhost']);
							curl_setopt($this->ch, CURLOPT_FAILONERROR, 1);
							curl_setopt($this->ch, CURLOPT_HEADERFUNCTION, array($this, 'readHeader'));
							if (empty($this->options['brute_force'])) {
								curl_setopt($this->ch, CURLOPT_COOKIE, $this->buildCookie());
							}
							curl_setopt($this->ch, CURLOPT_VERBOSE, $this->options['debug']); // Display communication with server
						}
						curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 0);
						curl_setopt($this->ch, CURLOPT_URL, $form_action);
						curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
						curl_setopt($this->ch, CURLOPT_POST, 1);
						curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post_params . $strParameters);
						if (!empty($this->options['httpauth'])) {
							curl_setopt($this->ch, CURLOPT_USERPWD, $this->options['httpauth_username'] . ':' . $this->options['httpauth_password']);
							curl_setopt($this->ch, CURLOPT_HTTPAUTH, $this->options['httpauth']);
						}

						$remotedata = curl_exec($this->ch);
						if ($this->options['debug']) {
							$this->status['cURL']['data'][] = $remotedata;
							$this->status['debug'][] = 'CURL_INFO: ' . print_r(curl_getinfo($this->ch), true);
						}
						if (curl_error($this->ch)) {
							$this->status['error'][] = static::_('CURL_ERROR_MSG') . ': ' . curl_error($this->ch);
						} else {
							//we have to set the cookies now

							if (empty($this->options['logout'])) {
								$this->status['debug'][] = static::_('CURL_LOGIN_FINISHED');
								$this->setCookies($this->options['cookiedomain'], $this->options['cookiepath'], $this->options['expires'], $this->options['secure'], $this->options['httponly']);
							} else {
								$this->status['debug'][] = static::_('CURL_LOGOUT_FINISHED');
								$this->deleteCookies($this->options['cookiedomain'], $this->options['cookiepath'], $this->options['expires'], $this->options['secure'], $this->options['httponly']);
							}
						}
						curl_close($this->ch);
					}
				}
			}
		}
		return $this->status;
	}

	/**
	 * RemoteLogout
	 *
	 * @return string something
	 */
	public function logout()
	{
		// check parameters and set defaults
		if (!isset($this->options['post_url'])) {
			$this->status['error'][] = 'Fatal programming error : no post_url!';
		} else {
			// prevent user error by not supplying trailing backslash.
			// make sure that when parameters are sent we do not add a backslash
			if (strpos($this->options['post_url'], '?') === false) {
				if (!(substr($this->options['post_url'], -1) == '/')) {
					$this->options['post_url'] = $this->options['post_url'] . '/';
				}
			}
			$this->ch = curl_init();
			$ip = $_SERVER['REMOTE_ADDR'];
			curl_setopt($this->ch, CURLOPT_HTTPHEADER, array('REMOTE_ADDR: ' . $ip, 'X_FORWARDED_FOR: ' . $ip));
			curl_setopt($this->ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
			curl_setopt($this->ch, CURLOPT_REFERER, '');
			curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, $this->options['verifyhost']);
			curl_setopt($this->ch, CURLOPT_FAILONERROR, 1);
			curl_setopt($this->ch, CURLOPT_HEADERFUNCTION, array($this, 'readHeader'));
			curl_setopt($this->ch, CURLOPT_URL, $this->options['post_url']);
			curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($this->ch, CURLOPT_VERBOSE, $this->options['debug']); // Display communication with server

			if (!empty($this->options['httpauth'])) {
				curl_setopt($this->ch, CURLOPT_USERPWD, $this->options['httpauth_username'] . ':' . $this->options['httpauth_password']);

				switch ($this->options['httpauth']) {
					case "basic":
						$this->options['httpauth'] = CURLAUTH_BASIC;
						break;
					case "gssnegotiate":
						$this->options['httpauth'] = CURLAUTH_GSSNEGOTIATE;
						break;
					case "digest":
						$this->options['httpauth'] = CURLAUTH_DIGEST;
						break;
					case "ntlm":
						$this->options['httpauth'] = CURLAUTH_NTLM;
						break;
					case "anysafe":
						$this->options['httpauth'] = CURLAUTH_ANYSAFE;
						break;
					case "any":
					default:
						$this->options['httpauth'] = CURLAUTH_ANY;
				}

				curl_setopt($this->ch, CURLOPT_HTTPAUTH, $this->options['httpauth']);
			}

			$remotedata = curl_exec($this->ch);
			if ($this->options['debug']) {
				$this->status['cURL']['data'][] = $remotedata;
				$this->status['debug'][] = 'CURL_INFO: ' . print_r(curl_getinfo($this->ch), true);
			}
			if (curl_error($this->ch)) {
				$this->status['error'][] = static::_('CURL_ERROR_MSG') . ': ' . curl_error($this->ch);
			} else {
				//we have to delete the cookies now
				$this->deleteCookies($this->options['cookiedomain'], $this->options['cookiepath'], $this->options['leavealone'], $this->options['secure'], $this->options['httponly']);
			}
			curl_close($this->ch);
		}
		return $this->status;
	}

	/**
	 * remote logout url
	 *
	 * @return string something
	 */
	public function logoutUrl()
	{
		$open_basedir = ini_get('open_basedir');
		$safe_mode = ini_get('safe_mode');

		// check parameters and set defaults
		if (!isset($this->options['post_url'])) {
			$status['error'][] = 'Fatal programming error : no post_url!';
		} else {
			$this->ch = curl_init();
			$ip = $_SERVER['REMOTE_ADDR'];
			curl_setopt($this->ch, CURLOPT_HTTPHEADER, array('REMOTE_ADDR: ' . $ip, 'X_FORWARDED_FOR: ' . $ip));
			curl_setopt($this->ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
			curl_setopt($this->ch, CURLOPT_REFERER, '');
			curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, $this->options['verifyhost']);
			curl_setopt($this->ch, CURLOPT_FAILONERROR, 1);
			curl_setopt($this->ch, CURLOPT_HEADERFUNCTION, array($this, 'readHeader'));
			curl_setopt($this->ch, CURLOPT_URL, $this->options['post_url']);
			curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($this->ch, CURLOPT_COOKIE, $this->buildCookie());
			curl_setopt($this->ch, CURLOPT_VERBOSE, $this->options['debug']); // Display communication with server
			if (empty($open_basedir) && empty($safe_mode)) {
				curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 1);
			}

			if (isset($this->options['jnodeid'])) {
				if ($this->options['postfields']) {
					$this->options['postfields'] = $this->options['postfields'] . '&jnodeid=' . $this->options['jnodeid'];
				} else {
					$this->options['postfields'] = 'jnodeid=' . $this->options['jnodeid'];
				}
			}

			if ($this->options['postfields']) {
				curl_setopt($this->ch, CURLOPT_POST, 1);
				curl_setopt($this->ch, CURLOPT_POSTFIELDS, $this->options['postfields']);
			}
			curl_setopt($this->ch, CURLOPT_MAXREDIRS, 2);
			curl_setopt($this->ch, CURLOPT_HEADERFUNCTION, array($this, 'readHeader'));
			curl_setopt($this->ch, CURLOPT_COOKIE, $this->buildCookie());

			if (!empty($this->options['httpauth'])) {
				curl_setopt($this->ch, CURLOPT_USERPWD, $this->options['httpauth_username'] . ':' . $this->options['httpauth_password']);

				switch ($this->options['httpauth']) {
					case "basic":
						$this->options['httpauth'] = CURLAUTH_BASIC;
						break;
					case "gssnegotiate":
						$this->options['httpauth'] = CURLAUTH_GSSNEGOTIATE;
						break;
					case "digest":
						$this->options['httpauth'] = CURLAUTH_DIGEST;
						break;
					case "ntlm":
						$this->options['httpauth'] = CURLAUTH_NTLM;
						break;
					case "anysafe":
						$this->options['httpauth'] = CURLAUTH_ANYSAFE;
						break;
					case "any":
					default:
						$this->options['httpauth'] = CURLAUTH_ANY;
				}

				curl_setopt($this->ch, CURLOPT_HTTPAUTH, $this->options['httpauth']);
			}

			if (empty($open_basedir) && empty($safe_mode)) {
				$remotedata = curl_exec($this->ch);
			} else {
				$remotedata = $this->curlRedirectExec();
			}
			if ($this->options['debug']) {
				$this->status['cURL']['data'][] = $remotedata;
				$this->status['debug'][] = 'CURL_INFO: ' . print_r(curl_getinfo($this->ch), true);
			}
			$this->status['debug'][] = static::_('CURL_LOGOUT_URL') . ': ' .  $this->options['post_url'];
			if (curl_error($this->ch)) {
				$this->status['error'][] = static::_('CURL_ERROR_MSG') . ': ' . curl_error($this->ch);
			} else {
				$this->setCookies($this->options['cookiedomain'], $this->options['cookiepath'], $this->options['expires'], $this->options['secure'], $this->options['httponly']);
			}
			curl_close($this->ch);
		}
		return $this->status;
	}
}
