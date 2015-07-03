<?php

namespace EdwardStock\Curl;

use EdwardStock\Curl\Helpers\CaseInsensitiveArray;

class Curl
{


	const USER_AGENT     = 'PHP-Curl-Class/1.0 (+https://github.com/php-curl-class/php-curl-class)';
	const EVENT_ERROR    = 'error';
	const EVENT_SUCCESS  = 'success';
	const EVENT_COMPLETE = 'complete';
	/**
	 * @var resource
	 */
	public $curl;
	/**
	 * @var \EdwardStock\Curl\Curl[]
	 */
	public $curls;
	public $error = false;
	public $errorCode = 0;
	public $errorMessage;
	public $curlError = false;
	public $curlErrorCode = 0;
	public $curlErrorMessage;
	public $httpError = false;
	public $httpStatusCode = 0;
	public $httpErrorMessage;
	public $requestHeaders;
	public $responseHeaders;
	public $response;

	private $cookies = [];
	private $headers = [];
	private $options = [];
	private $multiParent = false;
	private $multiChild = false;
	private $eventName;
	private $data = [];
	private $context;

	/**
	 * @var Callable
	 */
	private $beforeSendCallback;

	/**
	 * @var Callable
	 */
	private $successCallback;

	/**
	 * @var Callable
	 */
	private $errorCallback;

	/**
	 * @var Callable
	 */
	private $completeCallback;


	public function __construct($context = null) {
		if (!extension_loaded('curl')) {
			throw new \ErrorException('cURL library is not loaded');
		}

		$this->context = $context;
		$this->curl = curl_init();
		$this->setUserAgent(self::USER_AGENT);
		$this->setOption(CURLINFO_HEADER_OUT, true);
		$this->setOption(CURLOPT_HEADER, true);
		$this->setOption(CURLOPT_RETURNTRANSFER, true);
	}

	public function setUserAgent($user_agent) {
		$this->setOption(CURLOPT_USERAGENT, $user_agent);
	}

	public function setOption($option, $value, $_ch = null) {
		$ch = is_null($_ch) ? $this->curl : $_ch;

		$requiredOptions = [
			CURLINFO_HEADER_OUT    => 'CURLINFO_HEADER_OUT',
			CURLOPT_HEADER         => 'CURLOPT_HEADER',
			CURLOPT_RETURNTRANSFER => 'CURLOPT_RETURNTRANSFER',
		];

		if (in_array($option, array_keys($requiredOptions), true) && !($value === true)) {
			trigger_error($requiredOptions[$option] . ' is a required option', E_USER_WARNING);
		}

		$this->options[$option] = $value;

		return curl_setopt($ch, $option, $value);
	}

	public function get($urlMixed, $data = []) {
		$this->data = $data;

		if (is_array($urlMixed)) {
			$curlMulti = curl_multi_init();
			$this->multiParent = true;

			$this->curls = [];

			foreach ($urlMixed as $url) {
				$curl = new Curl();
				$curl->multiChild = true;
				$curl->setOption(CURLOPT_URL, $this->buildURL($url, $data), $curl->curl);
				$curl->setOption(CURLOPT_CUSTOMREQUEST, 'GET');
				$curl->setOption(CURLOPT_HTTPGET, true);
				$this->call($this->beforeSendCallback, $curl);
				$this->curls[] = $curl;

				$curlMultiErrorCode = curl_multi_add_handle($curlMulti, $curl->curl);
				if (!($curlMultiErrorCode === CURLM_OK)) {
					throw new \ErrorException('cURL multi add handle error: ' . $curlMultiErrorCode);
				}
			}

			/** @var \edwardstock\curl\Curl $ch */
			foreach ($this->curls as $ch) {
				foreach ($this->options as $key => $value) {
					$ch->setOption($key, $value);

				}
			}

			do {
				$status = curl_multi_exec($curlMulti, $active);
			} while ($status === CURLM_CALL_MULTI_PERFORM || $active);

			foreach ($this->curls as $ch) {
				$this->exec($ch);
			}
		} else {
			$this->setOption(CURLOPT_URL, $this->buildURL($urlMixed, $data));
			$this->setOption(CURLOPT_CUSTOMREQUEST, 'GET');
			$this->setOption(CURLOPT_HTTPGET, true);

			return $this->exec();
		}

		return null;
	}

	private function buildURL($url, $data = []) {
		return $url . (empty($data) ? '' : '?' . http_build_query($data));
	}

	private function call($function) {
		if (is_callable($function)) {
			$args = func_get_args();
			array_shift($args);
			call_user_func_array($function, $args);
		}
	}

	protected function exec($_ch = null) {
		/** @var Curl $ch */
		$ch = $_ch === null ? $this : $_ch;

		if ($ch->multiChild) {
			$ch->response = curl_multi_getcontent($ch->curl);
		} else {
			$ch->response = curl_exec($ch->curl);
		}

		$ch->curlErrorCode = curl_errno($ch->curl);
		$ch->curlErrorMessage = curl_error($ch->curl);
		$ch->curlError = !($ch->curlErrorCode === 0);
		$ch->httpStatusCode = curl_getinfo($ch->curl, CURLINFO_HTTP_CODE);
		$ch->httpError = in_array(floor($ch->httpStatusCode / 100), [4, 5]);
		$ch->error = $ch->curlError || $ch->httpError;
		$ch->errorCode = $ch->error ? ($ch->curlError ? $ch->curlErrorCode : $ch->httpStatusCode) : 0;

		$ch->requestHeaders = $this->parseRequestHeaders(curl_getinfo($ch->curl, CURLINFO_HEADER_OUT));
		$ch->responseHeaders = '';
		if (!(strpos($ch->response, "\r\n\r\n") === false)) {
			list($responseHeader, $ch->response) = explode("\r\n\r\n", $ch->response, 2);
			if ($responseHeader === 'HTTP/1.1 100 Continue') {
				list($responseHeader, $ch->response) = explode("\r\n\r\n", $ch->response, 2);
			}
			$ch->responseHeaders = $this->parseResponseHeaders($responseHeader);

			if (isset($ch->responseHeaders['Content-Type'])) {
				if (preg_match('/^application\/json/i', $ch->responseHeaders['Content-Type'])) {
					$json_obj = json_decode($ch->response, false);
					if (!is_null($json_obj)) {
						$ch->response = $json_obj;
					}
				}
			}
		}

		$ch->httpErrorMessage = '';
		if ($ch->error) {
			if (isset($ch->responseHeaders['Status-Line'])) {
				$ch->httpErrorMessage = $ch->responseHeaders['Status-Line'];
			}
		}
		$ch->errorMessage = $ch->curlError ? $ch->curlErrorMessage : $ch->httpErrorMessage;

		if ($ch->error) {
			$ch->call($this->errorCallback, $ch, $this->data, $this->context);
			$this->eventName = 'error';
		} else {
			$ch->call($this->successCallback, $ch, $this->data, $this->context);
			$this->eventName = 'success';
		}


		$ch->call($this->completeCallback, $ch, $this->data, $this->context);
		$this->eventName = 'complete';

		return $ch->errorCode;
	}

	private function parseRequestHeaders($raw_headers) {
		$requestHeaders = new CaseInsensitiveArray();
		list($firstLine, $headers) = $this->parseHeaders($raw_headers);
		$requestHeaders['Request-Line'] = $firstLine;
		foreach ($headers as $key => $value) {
			$requestHeaders[$key] = $value;
		}

		return $requestHeaders;
	}

	private function parseHeaders($rawHeaders) {
		$rawHeaders = preg_split('/\r\n/', $rawHeaders, null, PREG_SPLIT_NO_EMPTY);
		$httpHeaders = new CaseInsensitiveArray();

		for ($i = 1; $i < count($rawHeaders); $i++) {
			list($key, $value) = explode(':', $rawHeaders[$i], 2);
			$key = trim($key);
			$value = trim($value);
			if (array_key_exists($key, $httpHeaders)) {
				$httpHeaders[$key] .= ',' . $value;
			} else {
				$httpHeaders[$key] = $value;
			}
		}

		return [isset($rawHeaders['0']) ? $rawHeaders['0'] : '', $httpHeaders];
	}

	private function parseResponseHeaders($raw_headers) {
		$responseHeaders = new CaseInsensitiveArray();
		list($first_line, $headers) = $this->parseHeaders($raw_headers);
		$responseHeaders['Status-Line'] = $first_line;
		foreach ($headers as $key => $value) {
			$responseHeaders[$key] = $value;
		}

		return $responseHeaders;
	}

	public function post($url, $data = []) {
		$this->data = $data;
		$this->setOption(CURLOPT_URL, $this->buildURL($url));
		$this->setOption(CURLOPT_CUSTOMREQUEST, 'POST');
		$this->setOption(CURLOPT_POST, true);
		$this->setOption(CURLOPT_POSTFIELDS, $this->postFields($data));

		return $this->exec();
	}

	private function postFields($data) {
		if (is_array($data)) {
			if (helpers\ArrayHelper::isMultidimensional($data)) {
				$data = helpers\HttpHelper::httpBuildMultiQuery($data);
			} else {
				foreach ($data as $key => $value) {
					// Fix "Notice: Array to string conversion" when $value in
					// curl_setopt($ch, CURLOPT_POSTFIELDS, $value) is an array
					// that contains an empty array.
					if (is_array($value) && empty($value)) {
						$data[$key] = '';
						// Fix "curl_setopt(): The usage of the @filename API for
						// file uploading is deprecated. Please use the CURLFile
						// class instead".
					} elseif (is_string($value) && strpos($value, '@') === 0) {
						if (class_exists('CURLFile')) {
							$data[$key] = new \CURLFile(substr($value, 1));
						}
					}
				}
			}
		}

		return $data;
	}

	public function put($url, $data = []) {
		$this->data = $data;
		$this->setOption(CURLOPT_URL, $url);
		$this->setOption(CURLOPT_CUSTOMREQUEST, 'PUT');
		$this->setOption(CURLOPT_POSTFIELDS, http_build_query($data));

		return $this->exec();
	}

	public function patch($url, $data = []) {
		$this->data = $data;
		$this->setOption(CURLOPT_URL, $this->buildURL($url));
		$this->setOption(CURLOPT_CUSTOMREQUEST, 'PATCH');
		$this->setOption(CURLOPT_POSTFIELDS, $data);

		return $this->exec();
	}

	public function delete($url, $data = []) {
		$this->data = $data;
		$this->setOption(CURLOPT_URL, $this->buildURL($url, $data));
		$this->setOption(CURLOPT_CUSTOMREQUEST, 'DELETE');

		return $this->exec();
	}

	public function setBasicAuthentication($username, $password) {
		$this->setOption(CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		$this->setOption(CURLOPT_USERPWD, $username . ':' . $password);
	}

	public function setHeader($key, $value) {
		$this->headers[$key] = $key . ': ' . $value;
		$this->setOption(CURLOPT_HTTPHEADER, array_values($this->headers));
	}

	public function setReferrer($referrer) {
		$this->setOption(CURLOPT_REFERER, $referrer);
	}

	public function setCookie($key, $value) {
		$this->cookies[$key] = $value;
		$this->setOption(CURLOPT_COOKIE, http_build_query($this->cookies, '', '; '));
	}

	public function setCookieFile($cookie_file) {
		$this->setOption(CURLOPT_COOKIEFILE, $cookie_file);
	}

	public function setCookieJar($cookie_jar) {
		$this->setOption(CURLOPT_COOKIEJAR, $cookie_jar);
	}

	public function verbose($on = true) {
		$this->setOption(CURLOPT_VERBOSE, $on);
	}

	public function onBeforeSend($function) {
		$this->beforeSendCallback = $function;
	}

	public function onSuccess($callback) {
		$this->successCallback = $callback;
	}

	public function onError($callback) {
		$this->errorCallback = $callback;
	}

	public function onComplete($callback) {
		$this->completeCallback = $callback;
	}

	public function getEventName() {
		return $this->eventName;
	}

	public function __destruct() {
		$this->close();
	}

	public function close() {
		if ($this->multiParent) {
			foreach ($this->curls as $curl) {
				$curl->close();
			}
		}

		if (is_resource($this->curl)) {
			curl_close($this->curl);
		}
	}
}
