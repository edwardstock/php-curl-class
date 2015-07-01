<?php

class Test
{

	const TEST_URL  = 'http://127.0.0.1:8000/';
	const ERROR_URL = 'https://1.2.3.4/';

	/**
	 * @var \EdwardStock\Curl\Curl
	 */
	public $curl;

	function __construct() {
		$this->curl = new \EdwardStock\Curl\Curl();
		$this->curl->setOption(CURLOPT_SSL_VERIFYPEER, false);
		$this->curl->setOption(CURLOPT_SSL_VERIFYHOST, false);
	}

	function server($test, $request_method, $data = []) {
		$this->curl->setHeader('X-DEBUG-TEST', $test);
		$request_method = strtolower($request_method);
		$this->curl->$request_method(self::TEST_URL, $data);
		return $this->curl->response;
	}

	public static function requestMethod(Test $instance, $before, $after) {
		$instance->server('request_method', $before);
		PHPUnit_Framework_Assert::assertTrue($instance->curl->response === $before);
		$instance->server('request_method', $after);
		PHPUnit_Framework_Assert::assertTrue($instance->curl->response === $after);
	}

	public static function createPNG() {
		// PNG image data, 1 x 1, 1-bit colormap, non-interlaced
		ob_start();
		imagepng(imagecreatefromstring(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7')));
		$raw_image = ob_get_contents();
		ob_end_clean();
		return $raw_image;
	}

	public static function createTmpFile($data) {
		$tmp_file = tmpfile();
		fwrite($tmp_file, $data);
		rewind($tmp_file);
		return $tmp_file;
	}

	public static function getPNG() {
		$tmp_filename = tempnam('/tmp', 'php-curl-class.');
		file_put_contents($tmp_filename, self::createPNG());
		return $tmp_filename;
	}
}
