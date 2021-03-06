<?php
namespace Bitrix\Main;

use Bitrix\Main\Config;
use Bitrix\Main\Type;

/**
 * Class HttpRequest extends Request. Contains http specific request data.
 *
 * @package Bitrix\Main
 */
class HttpRequest
	extends Request
{
	/**
	 * @var Type\ParameterDictionary
	 */
	protected $queryString;

	/**
	 * @var Type\ParameterDictionary
	 */
	protected $postData;

	/**
	 * @var Type\ParameterDictionary
	 */
	protected $files;

	/**
	 * @var Type\ParameterDictionary
	 */
	protected $cookies;

	/**
	 * @var Type\ParameterDictionary
	 */
	protected $cookiesRaw;

	/**
	 * Creates new HttpRequest object
	 *
	 * @param Server $server
	 * @param array $queryString _GET
	 * @param array $postData _POST
	 * @param array $files _FILES
	 * @param array $cookies _COOKIE
	 */
	public function __construct(Server $server, array $queryString, array $postData, array $files, array $cookies)
	{
		$request = array_merge($queryString, $postData);
		parent::__construct($server, $request);

		$this->queryString = new Type\ParameterDictionary($queryString);
		$this->postData = new Type\ParameterDictionary($postData);
		$this->files = new Type\ParameterDictionary($files);
		$this->cookiesRaw = new Type\ParameterDictionary($cookies);
		$this->cookies = new Type\ParameterDictionary($this->prepareCookie($cookies));
	}

	/**
	 * Applies filter to the http request data. Preserve original values.
	 *
	 * @param Type\IRequestFilter $filter Filter object
	 */
	public function addFilter(Type\IRequestFilter $filter)
	{
		$filteredValues = $filter->filter(array(
			"get" => $this->queryString->arValues,
			"post" => $this->postData->arValues,
			"files" => $this->files->arValues,
			"cookie" => $this->cookiesRaw->arValues
			));

		if (isset($filteredValues['get']))
			$this->queryString->setValuesNoDemand($filteredValues['get']);
		if (isset($filteredValues['post']))
			$this->postData->setValuesNoDemand($filteredValues['post']);
		if (isset($filteredValues['files']))
			$this->files->setValuesNoDemand($filteredValues['files']);
		if (isset($filteredValues['cookie']))
		{
			$this->cookiesRaw->setValuesNoDemand($filteredValues['cookie']);
			$this->cookies = new Type\ParameterDictionary($this->prepareCookie($filteredValues['cookie']));
		}

		if (isset($filteredValues['get']) || isset($filteredValues['post']))
			$this->arValues = array_merge($this->queryString->arValues, $this->postData->arValues);
	}

	/**
	 * Returns _GET parameter of the current request.
	 *
	 * @param string $name Parameter name
	 * @return null|string
	 */
	public function getQuery($name)
	{
		return $this->queryString->get($name);
	}

	/**
	 * Return list of _GET parameters of current request.
	 *
	 * @return Type\ParameterDictionary
	 */
	public function getQueryList()
	{
		return $this->queryString;
	}

	/**
	 * 
	 *
	 * @param $name
	 * @return null|string
	 */
	public function getPost($name)
	{
		return $this->postData->get($name);
	}

	public function getPostList()
	{
		return $this->postData;
	}

	public function getFile($name)
	{
		return $this->files->get($name);
	}

	public function getFileList()
	{
		return $this->files;
	}

	public function getCookie($name)
	{
		return $this->cookies->get($name);
	}

	public function getCookieList()
	{
		return $this->cookies;
	}

	public function getCookieRaw($name)
	{
		return $this->cookiesRaw->get($name);
	}

	public function getCookieRawList()
	{
		return $this->cookiesRaw;
	}

	public function getRemoteAddress()
	{
		return $this->server->get("REMOTE_ADDR");
	}

	public function getRequestUri()
	{
		return $this->server->getRequestUri();
	}

	public function getRequestMethod()
	{
		return $this->server->getRequestMethod();
	}

	public function isPost()
	{
		return ($this->getRequestMethod() == "POST");
	}

	public function getUserAgent()
	{
		return $this->server->get("HTTP_USER_AGENT");
	}

	public function getAcceptedLanguages()
	{
		static $acceptedLanguages = array();

		if (empty($acceptedLanguages))
		{
			$acceptedLanguagesString = $this->server->get("HTTP_ACCEPT_LANGUAGE");
			$arAcceptedLanguages = explode(",", $acceptedLanguagesString);
			foreach ($arAcceptedLanguages as $langString)
			{
				$arLang = explode(";", $langString);
				$acceptedLanguages[] = $arLang[0];
			}
		}

		return $acceptedLanguages;
	}

	public function getRequestedPage()
	{
		if ($this->requestedFile != null)
			return $this->requestedFile;

		$page = $this->getRequestUri();
		if (empty($page))
			return $this->requestedFile = parent::getRequestedPage();

		$page = urldecode($page);
		$page = Text\Encoding::convertEncodingToCurrent($page);

		$uri = new Web\Uri($page, Web\UriType::RELATIVE);

		return $this->requestedFile = $uri->convertToPath();
	}

	public function getHttpHost($raw = true)
	{
		if ($raw)
			return $this->server->getHttpHost();

		static $host = null;

		if ($host === null)
		{
			$host = $this->server->getHttpHost();
			$hostScheme = $this->isHttps() ? "https://" : "http://";

			$url = new Web\Uri($hostScheme.$host, Web\UriType::ABSOLUTE);

			$host = $url->parse(Web\UriPart::HOST);
			$host = trim($host, "\t\r\n\0 .");
		}

		return $host;
	}

	public function isHttps()
	{
		$port = $this->server->get("SERVER_PORT");
		$https = $this->server->get("HTTPS");
		return ($port == 443 || (($https != null) && (strtolower($https) == "on")));
	}

	public function modifyByQueryString($queryString)
	{
		if ($queryString != "")
		{
			parse_str($queryString, $vars);

			$this->arValues += $vars;
			$this->queryString->arValues += $vars;
		}
	}

	/**
	 * @param array $cookies
	 * @return array
	 */
	protected function prepareCookie(array $cookies)
	{
		static $cookiePrefix = null;
		if ($cookiePrefix === null)
			$cookiePrefix = Config\Option::get("main", "cookie_name", "BITRIX_SM")."_";

		$cookiePrefixLength = strlen($cookiePrefix);

		$cookiesNew = array();
		foreach ($cookies as $name => $value)
		{
			if (strpos($name, $cookiePrefix) !== 0)
				continue;

			$cookiesNew[substr($name, $cookiePrefixLength)] = $value;
		}
		return $cookiesNew;
	}
}
