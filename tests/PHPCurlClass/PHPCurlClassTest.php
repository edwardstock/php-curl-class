<?php
require_once 'helper.inc.php';
// Usage: phpunit --verbose run.php
use EdwardStock\Curl\Helpers\ArrayHelper;
use EdwardStock\Curl\Helpers\CaseInsensitiveArray;

class CurlTest extends PHPUnit_Framework_TestCase
{

	public function testExtensionLoaded() {
		$this->assertTrue(extension_loaded('curl'));
	}

	public function testArrayAssociative() {
		$this->assertTrue(ArrayHelper::isAssociativeArray([
			'foo' => 'wibble',
			'bar' => 'wubble',
			'baz' => 'wobble',
		]));
	}

	public function testArrayIndexed() {
		$this->assertFalse(ArrayHelper::isAssociativeArray([
			'wibble',
			'wubble',
			'wobble',
		]));
	}

	public function testCaseInsensitiveArrayGet() {
		$array = new CaseInsensitiveArray();
		$this->assertTrue(is_object($array));
		$this->assertCount(0, $array);
		$this->assertNull($array[(string)rand()]);

		$array['foo'] = 'bar';
		$this->assertNotEmpty($array);
		$this->assertCount(1, $array);
	}

	public function testCaseInsensitiveArraySet() {
		$assertions = function ($array, $count = 1){
			PHPUnit_Framework_Assert::assertCount($count, $array);
			PHPUnit_Framework_Assert::assertTrue($array['foo'] === 'bar');
			PHPUnit_Framework_Assert::assertTrue($array['Foo'] === 'bar');
			PHPUnit_Framework_Assert::assertTrue($array['FOo'] === 'bar');
			PHPUnit_Framework_Assert::assertTrue($array['FOO'] === 'bar');
		};

		$array = new CaseInsensitiveArray();
		$array['foo'] = 'bar';
		$assertions($array);

		$array['Foo'] = 'bar';
		$assertions($array);

		$array['FOo'] = 'bar';
		$assertions($array);

		$array['FOO'] = 'bar';
		$assertions($array);

		$array['baz'] = 'qux';
		$assertions($array, 2);
	}

	public function testUserAgent() {
		$test = new Test();
		$test->curl->setUserAgent(\EdwardStock\Curl\Curl::USER_AGENT);
		$this->assertTrue($test->server('server', 'GET', [
				'key' => 'HTTP_USER_AGENT',
			]) === \EdwardStock\Curl\Curl::USER_AGENT);
	}

	public function testGet() {
		$test = new Test();
		$this->assertTrue($test->server('server', 'GET', [
				'key' => 'REQUEST_METHOD',
			]) === 'GET');
	}

	public function testPostRequestMethod() {
		$test = new Test();
		$this->assertTrue($test->server('server', 'POST', [
				'key' => 'REQUEST_METHOD',
			]) === 'POST');
	}

	public function testPostData() {
		$test = new Test();
		$this->assertTrue($test->server('post', 'POST', [
				'key' => 'value',
			]) === 'key=value');
	}

	public function testPostAssociativeArrayData() {
		$test = new Test();
		$this->assertTrue($test->server('post_multidimensional', 'POST', [
				'username'  => 'myusername',
				'password'  => 'mypassword',
				'more_data' => [
					'param1' => 'something',
					'param2' => 'other thing',
					'param3' => 123,
					'param4' => 3.14,
				],
			]) === 'username=myusername&password=mypassword&more_data%5Bparam1%5D=something&more_data%5Bparam2%5D=other%20thing&more_data%5Bparam3%5D=123&more_data%5Bparam4%5D=3.14');
	}

	public function testPostMultidimensionalData() {
		$test = new Test();
		$this->assertTrue($test->server('post_multidimensional', 'POST', [
				'key'  => 'file',
				'file' => [
					'wibble',
					'wubble',
					'wobble',
				],
			]) === 'key=file&file%5B%5D=wibble&file%5B%5D=wubble&file%5B%5D=wobble');
	}

	public function testPostFilePathUpload() {
		$file_path = Test::getPNG();

		$test = new Test();
		$this->assertTrue($test->server('post_file_path_upload', 'POST', [
				'key'   => 'image',
				'image' => '@' . $file_path,
			]) === 'image/png');

		unlink($file_path);
		$this->assertFalse(file_exists($file_path));
	}

	public function testPostCurlFileUpload() {
		if (class_exists('CURLFile')) {
			$file_path = Test::getPNG();

			$test = new Test();
			$this->assertTrue($test->server('post_file_path_upload', 'POST', [
					'key'   => 'image',
					'image' => new CURLFile($file_path),
				]) === 'image/png');

			unlink($file_path);
			$this->assertFalse(file_exists($file_path));
		}
	}

	public function testPutRequestMethod() {
		$test = new Test();
		$this->assertTrue($test->server('request_method', 'PUT') === 'PUT');
	}

	public function testPutData() {
		$test = new Test();
		$this->assertTrue($test->server('put', 'PUT', ['key' => 'value']) === 'key=value');
	}

	public function testPutFileHandle() {
		$png = Test::createPNG();
		$tmp_file = Test::createTmpFile($png);

		$test = new Test();
		$test->curl->setHeader('X-DEBUG-TEST', 'put_file_handle');
		$test->curl->setOption(CURLOPT_PUT, true);
		$test->curl->setOption(CURLOPT_INFILE, $tmp_file);
		$test->curl->setOption(CURLOPT_INFILESIZE, strlen($png));
		$test->curl->put(Test::TEST_URL);

		fclose($tmp_file);

		$this->assertTrue($test->curl->response === 'image/png');
	}

	public function testPatchRequestMethod() {
		$test = new Test();
		$this->assertTrue($test->server('request_method', 'PATCH') === 'PATCH');
	}

	public function testDelete() {
		$test = new Test();
		$this->assertTrue($test->server('server', 'DELETE', [
				'key' => 'REQUEST_METHOD',
			]) === 'DELETE');

		$test = new Test();
		$this->assertTrue($test->server('delete', 'DELETE', [
				'test' => 'delete',
				'key'  => 'test',
			]) === 'delete');
	}

	public function testBasicHttpAuth401Unauthorized() {
		$test = new Test();
		$this->assertTrue($test->server('http_basic_auth', 'GET') === 'canceled');
	}

	public function testBasicHttpAuthSuccess() {
		$username = 'myusername';
		$password = 'mypassword';
		$test = new Test();
		$test->curl->setBasicAuthentication($username, $password);
		$test->server('http_basic_auth', 'GET');
		$json = $test->curl->response;
		$this->assertTrue($json->username === $username);
		$this->assertTrue($json->password === $password);
	}

	public function testReferrer() {
		$test = new Test();
		$test->curl->setReferrer('myreferrer');
		$this->assertTrue($test->server('server', 'GET', [
				'key' => 'HTTP_REFERER',
			]) === 'myreferrer');
	}

	public function testCookies() {
		$test = new Test();
		$test->curl->setCookie('mycookie', 'yum');
		$this->assertTrue($test->server('cookie', 'GET', [
				'key' => 'mycookie',
			]) === 'yum');
	}

	public function testCookieFile() {
		$cookie_file = dirname(__FILE__) . '/cookies.txt';
		$cookie_data = implode("\t", [
			'127.0.0.1', // domain
			'FALSE',     // tailmatch
			'/',         // path
			'FALSE',     // secure
			'0',         // expires
			'mycookie',  // name
			'yum',       // value
		]);
		file_put_contents($cookie_file, $cookie_data);

		$test = new Test();
		$test->curl->setCookieFile($cookie_file);
		$this->assertTrue($test->server('cookie', 'GET', [
				'key' => 'mycookie',
			]) === 'yum');

		unlink($cookie_file);
		$this->assertFalse(file_exists($cookie_file));
	}

	public function testCookieJar() {
		$cookie_file = dirname(__FILE__) . '/cookies.txt';

		$test = new Test();
		$test->curl->setCookieJar($cookie_file);
		$test->server('cookiejar', 'GET');
		$test->curl->close();

		$this->assertTrue(!(strpos(file_get_contents($cookie_file), "\t" . 'mycookie' . "\t" . 'yum') === false));
		unlink($cookie_file);
		$this->assertFalse(file_exists($cookie_file));
	}

	public function testError() {
		$test = new Test();
		$test->curl->setOption(CURLOPT_CONNECTTIMEOUT_MS, 2000);
		$test->curl->get(Test::ERROR_URL);
		$this->assertTrue($test->curl->error);
		$this->assertTrue($test->curl->curlErrorMessage);
		$this->assertTrue($test->curl->curlErrorCode === CURLE_OPERATION_TIMEOUTED);
	}

	public function testErrorMessage() {
		$test = new Test();
		$test->server('errorMessage', 'GET');
		$this->assertTrue($test->curl->errorMessage === 'HTTP/1.1 401 Unauthorized');
	}

	public function testHeaders() {
		$test = new Test();
		$test->curl->setHeader('Content-Type', 'application/json');
		$test->curl->setHeader('X-Requested-With', 'XMLHttpRequest');
		$test->curl->setHeader('Accept', 'application/json');
		$this->assertTrue($test->server('server', 'GET', [
				'key' => 'HTTP_CONTENT_TYPE', // OR "CONTENT_TYPE".
			]) === 'application/json');
		$this->assertTrue($test->server('server', 'GET', [
				'key' => 'HTTP_X_REQUESTED_WITH',
			]) === 'XMLHttpRequest');
		$this->assertTrue($test->server('server', 'GET', [
				'key' => 'HTTP_ACCEPT',
			]) === 'application/json');
	}

	public function testHeaderCaseSensitivity() {
		$content_type = 'application/json';
		$test = new Test();
		$test->curl->setHeader('Content-Type', $content_type);
		$test->server('response_header', 'GET');

		$requestHeaders = $test->curl->requestHeaders;
		$responseHeaders = $test->curl->responseHeaders;

		$this->assertEquals($requestHeaders['Content-Type'], $content_type);
		$this->assertEquals($requestHeaders['content-type'], $content_type);
		$this->assertEquals($requestHeaders['CONTENT-TYPE'], $content_type);
		$this->assertEquals($requestHeaders['cOnTeNt-TyPe'], $content_type);

		$etag = $responseHeaders['ETag'];
		$this->assertEquals($responseHeaders['ETAG'], $etag);
		$this->assertEquals($responseHeaders['etag'], $etag);
		$this->assertEquals($responseHeaders['eTAG'], $etag);
		$this->assertEquals($responseHeaders['eTaG'], $etag);
	}

	public function testRequestURL() {
		$test = new Test();
		$this->assertFalse(substr($test->server('request_uri', 'GET'), -1) === '?');
		$test = new Test();
		$this->assertFalse(substr($test->server('request_uri', 'POST'), -1) === '?');
		$test = new Test();
		$this->assertFalse(substr($test->server('request_uri', 'PUT'), -1) === '?');
		$test = new Test();
		$this->assertFalse(substr($test->server('request_uri', 'PATCH'), -1) === '?');
		$test = new Test();
		$this->assertFalse(substr($test->server('request_uri', 'DELETE'), -1) === '?');
	}

	public function testNestedData() {
		$test = new Test();
		$data = [
			'username'  => 'myusername',
			'password'  => 'mypassword',
			'more_data' => [
				'param1'  => 'something',
				'param2'  => 'other thing',
				'another' => [
					'extra'   => 'level',
					'because' => 'I need it',
				],
			],
		];
		$this->assertTrue(
			$test->server('post', 'POST', $data) === http_build_query($data)
		);
	}

	public function testPostContentTypes() {
		$test = new Test();
		$test->server('server', 'POST', 'foo=bar');
		$this->assertEquals($test->curl->requestHeaders['Content-Type'], 'application/x-www-form-urlencoded');

		$test = new Test();
		$test->server('server', 'POST', [
			'foo' => 'bar',
		]);
		$this->assertEquals($test->curl->requestHeaders['Expect'], '100-continue');
		preg_match('/^multipart\/form-data; boundary=/', $test->curl->requestHeaders['Content-Type'], $content_type);
		$this->assertTrue(!empty($content_type));
	}

	public function testJSONResponse() {
		$assertion = function($key, $value) {
			$test = new Test();
			$test->server('json_response', 'POST', [
				'key'   => $key,
				'value' => $value,
			]);

			$response = $test->curl->response;
			PHPUnit_Framework_Assert::assertNotNull($response);
			PHPUnit_Framework_Assert::assertNull($response->null);
			PHPUnit_Framework_Assert::assertTrue($response->true);
			PHPUnit_Framework_Assert::assertFalse($response->false);
			PHPUnit_Framework_Assert::assertTrue(is_int($response->integer));
			PHPUnit_Framework_Assert::assertTrue(is_float($response->float));
			PHPUnit_Framework_Assert::assertEmpty($response->empty);
			PHPUnit_Framework_Assert::assertTrue(is_string($response->string));
		};

		$assertion('Content-Type', 'application/json; charset=utf-8');
		$assertion('content-type', 'application/json; charset=utf-8');
		$assertion('Content-Type', 'application/json');
		$assertion('content-type', 'application/json');
		$assertion('CONTENT-TYPE', 'application/json');
		$assertion('CONTENT-TYPE', 'APPLICATION/JSON');
	}

	public function testArrayToStringConversion() {
		$test = new Test();
		$test->server('post', 'POST', [
			'foo' => 'bar',
			'baz' => [
			],
		]);
		$this->assertTrue($test->curl->response === 'foo=bar&baz=');

		$test = new Test();
		$test->server('post', 'POST', [
			'foo' => 'bar',
			'baz' => [
				'qux' => [
				],
			],
		]);
		$this->assertTrue(urldecode($test->curl->response) ===
			'foo=bar&baz[qux]='
		);

		$test = new Test();
		$test->server('post', 'POST', [
			'foo' => 'bar',
			'baz' => [
				'qux'    => [
				],
				'wibble' => 'wobble',
			],
		]);
		$this->assertTrue(urldecode($test->curl->response) ===
			'foo=bar&baz[qux]=&baz[wibble]=wobble'
		);
	}

	public function testParallelRequests() {
		$test = new Test();
		$curl = $test->curl;
		$curl->onBeforeSend(function (\EdwardStock\Curl\Curl $instance) {
			$instance->setHeader('X-DEBUG-TEST', 'request_uri');
		});
		$curl->get([
			Test::TEST_URL . 'a/',
			Test::TEST_URL . 'b/',
			Test::TEST_URL . 'c/',
		], [
			'foo' => 'bar',
		]);

		$len = strlen('/a/?foo=bar');
		$this->assertTrue(substr($curl->curls['0']->response, -$len) === '/a/?foo=bar');
		$this->assertTrue(substr($curl->curls['1']->response, -$len) === '/b/?foo=bar');
		$this->assertTrue(substr($curl->curls['2']->response, -$len) === '/c/?foo=bar');
	}

	public function testParallelSetOptions() {
		$test = new Test();
		$curl = $test->curl;
		$curl->setHeader('X-DEBUG-TEST', 'server');
		$curl->setOption(CURLOPT_USERAGENT, 'useragent');
		$curl->onComplete(function (\EdwardStock\Curl\Curl $instance) {
			PHPUnit_Framework_Assert::assertTrue($instance->response === 'useragent');
		});
		$curl->get([
			Test::TEST_URL,
		], [
			'key' => 'HTTP_USER_AGENT',
		]);
	}

	public function testSuccessCallback() {
		$success_called = false;
		$error_called = false;
		$complete_called = false;

		$test = new Test();
		$curl = $test->curl;
		$curl->setHeader('X-DEBUG-TEST', 'get');

		$curl->onSuccess(function (\EdwardStock\Curl\Curl $instance) use (&$success_called, &$error_called, &$complete_called) {
			PHPUnit_Framework_Assert::assertInstanceOf(\EdwardStock\Curl\Curl::class, $instance);
			PHPUnit_Framework_Assert::assertFalse($success_called);
			PHPUnit_Framework_Assert::assertFalse($error_called);
			PHPUnit_Framework_Assert::assertFalse($complete_called);
			$success_called = true;
		});
		$curl->onError(function (\EdwardStock\Curl\Curl $instance) use (&$success_called, &$error_called, &$complete_called, &$curl) {
			PHPUnit_Framework_Assert::assertInstanceOf(\EdwardStock\Curl\Curl::class, $instance);
			PHPUnit_Framework_Assert::assertFalse($success_called);
			PHPUnit_Framework_Assert::assertFalse($error_called);
			PHPUnit_Framework_Assert::assertFalse($complete_called);
			$error_called = true;
		});
		$curl->onComplete(function (\EdwardStock\Curl\Curl $instance) use (&$success_called, &$error_called, &$complete_called) {
			PHPUnit_Framework_Assert::assertInstanceOf(\EdwardStock\Curl\Curl::class, $instance);
			PHPUnit_Framework_Assert::assertTrue($success_called);
			PHPUnit_Framework_Assert::assertFalse($error_called);
			PHPUnit_Framework_Assert::assertFalse($complete_called);
			$complete_called = true;
		});

		$curl->get(Test::TEST_URL);

		$this->assertTrue($success_called);
		$this->assertFalse($error_called);
		$this->assertTrue($complete_called);
	}

	public function testParallelSuccessCallback() {
		$successCalled = false;
		$errorCalled = false;
		$completeCalled = false;

		$successCalledOnce = false;
		$errorCalledOnce = false;
		$completeCalledOnce = false;

		$test = new Test();
		$curl = $test->curl;
		$curl->setHeader('X-DEBUG-TEST', 'get');

		$curl->onSuccess(function (\EdwardStock\Curl\Curl $instance) use (
			&$successCalled,
			&$errorCalled,
			&$completeCalled,
			&$successCalledOnce
		) {
			PHPUnit_Framework_Assert::assertInstanceOf(\EdwardStock\Curl\Curl::class, $instance);
			PHPUnit_Framework_Assert::assertFalse($successCalled);
			PHPUnit_Framework_Assert::assertFalse($errorCalled);
			PHPUnit_Framework_Assert::assertFalse($completeCalled);
			$successCalled = true;
			$successCalledOnce = true;
		});
		$curl->onError(function (\EdwardStock\Curl\Curl $instance) use (
			&$successCalled,
			&$errorCalled,
			&$completeCalled,
			&$curl,
			&$errorCalledOnce
		) {
			PHPUnit_Framework_Assert::assertInstanceOf(\EdwardStock\Curl\Curl::class, $instance);
			PHPUnit_Framework_Assert::assertFalse($successCalled);
			PHPUnit_Framework_Assert::assertFalse($errorCalled);
			PHPUnit_Framework_Assert::assertFalse($completeCalled);
			$errorCalled = true;
			$errorCalledOnce = true;
		});
		$curl->onComplete(function (\EdwardStock\Curl\Curl $instance) use (
			&$successCalled,
			&$errorCalled,
			&$completeCalled,
			&$completeCalledOnce
		) {
			PHPUnit_Framework_Assert::assertInstanceOf(\EdwardStock\Curl\Curl::class, $instance);
			PHPUnit_Framework_Assert::assertTrue($successCalled);
			PHPUnit_Framework_Assert::assertFalse($errorCalled);
			PHPUnit_Framework_Assert::assertFalse($completeCalled);
			$completeCalled = true;
			$completeCalledOnce = true;

			PHPUnit_Framework_Assert::assertTrue($successCalled);
			PHPUnit_Framework_Assert::assertFalse($errorCalled);
			PHPUnit_Framework_Assert::assertTrue($completeCalled);

			$successCalled = false;
			$errorCalled = false;
			$completeCalled = false;
		});

		$curl->get([
			Test::TEST_URL . 'a/',
			Test::TEST_URL . 'b/',
			Test::TEST_URL . 'c/',
		]);

		$this->assertTrue($successCalledOnce || $errorCalledOnce);
		$this->assertTrue($completeCalledOnce);
	}

	public function testErrorCallback() {
		$successCalled = false;
		$errorCalled = false;
		$completeCalled = false;

		$test = new Test();
		$curl = $test->curl;
		$curl->setHeader('X-DEBUG-TEST', 'get');
		$curl->setOption(CURLOPT_CONNECTTIMEOUT_MS, 2000);

		$curl->onSuccess(function (\EdwardStock\Curl\Curl $instance) use (&$successCalled, &$errorCalled, &$completeCalled) {
			PHPUnit_Framework_Assert::assertInstanceOf(\EdwardStock\Curl\Curl::class, $instance);
			PHPUnit_Framework_Assert::assertFalse($successCalled);
			PHPUnit_Framework_Assert::assertFalse($errorCalled);
			PHPUnit_Framework_Assert::assertFalse($completeCalled);
			$successCalled = true;
		});
		$curl->onError(function (\EdwardStock\Curl\Curl $instance) use (&$successCalled, &$errorCalled, &$completeCalled, &$curl) {
			PHPUnit_Framework_Assert::assertInstanceOf(\EdwardStock\Curl\Curl::class, $instance);
			PHPUnit_Framework_Assert::assertFalse($successCalled);
			PHPUnit_Framework_Assert::assertFalse($errorCalled);
			PHPUnit_Framework_Assert::assertFalse($completeCalled);
			$errorCalled = true;
		});
		$curl->onComplete(function (\EdwardStock\Curl\Curl $instance) use (&$successCalled, &$errorCalled, &$completeCalled) {
			PHPUnit_Framework_Assert::assertInstanceOf(\EdwardStock\Curl\Curl::class, $instance);
			PHPUnit_Framework_Assert::assertFalse($successCalled);
			PHPUnit_Framework_Assert::assertTrue($errorCalled);
			PHPUnit_Framework_Assert::assertFalse($completeCalled);
			$completeCalled = true;
		});

		$curl->get(Test::ERROR_URL);

		$this->assertFalse($successCalled);
		$this->assertTrue($errorCalled);
		$this->assertTrue($completeCalled);
	}

	public function testClose() {
		$test = new Test();
		$curl = $test->curl;
		$curl->setHeader('X-DEBUG-TEST', 'post');
		$curl->post(Test::TEST_URL);
		$this->assertTrue(is_resource($curl->curl));
		$curl->close();
		$this->assertFalse(is_resource($curl->curl));
	}

	/**
	 * @expectedException PHPUnit_Framework_Error_Warning
	 */
	public function testRequiredOptionCurlInfoHeaderOutEmitsWarning() {
		$curl = new \EdwardStock\Curl\Curl();
		$curl->setOption(CURLINFO_HEADER_OUT, false);
	}

	/**
	 * @expectedException PHPUnit_Framework_Error_Warning
	 */
	public function testRequiredOptionCurlOptHeaderEmitsWarning() {
		$curl = new \EdwardStock\Curl\Curl();
		$curl->setOption(CURLOPT_HEADER, false);
	}

	/**
	 * @expectedException PHPUnit_Framework_Error_Warning
	 */
	public function testRequiredOptionCurlOptReturnTransferEmitsWarning() {
		$curl = new \EdwardStock\Curl\Curl();
		$curl->setOption(CURLOPT_RETURNTRANSFER, false);
	}

	public function testRequestMethodSuccessiveGetRequests() {
		$test = new Test();
		Test::requestMethod($test, 'GET', 'POST');
		Test::requestMethod($test, 'GET', 'PUT');
		Test::requestMethod($test, 'GET', 'PATCH');
		Test::requestMethod($test, 'GET', 'DELETE');
	}

	public function testRequestMethodSuccessivePostRequests() {
		$test = new Test();
		Test::requestMethod($test, 'POST', 'GET');
		Test::requestMethod($test, 'POST', 'PUT');
		Test::requestMethod($test, 'POST', 'PATCH');
		Test::requestMethod($test, 'POST', 'DELETE');
	}

	public function testRequestMethodSuccessivePutRequests() {
		$test = new Test();
		Test::requestMethod($test, 'PUT', 'GET');
		Test::requestMethod($test, 'PUT', 'POST');
		Test::requestMethod($test, 'PUT', 'PATCH');
		Test::requestMethod($test, 'PUT', 'DELETE');
	}

	public function testRequestMethodSuccessivePatchRequests() {
		$test = new Test();
		Test::requestMethod($test, 'PATCH', 'GET');
		Test::requestMethod($test, 'PATCH', 'POST');
		Test::requestMethod($test, 'PATCH', 'PUT');
		Test::requestMethod($test, 'PATCH', 'DELETE');
	}

	public function testRequestMethodSuccessiveDeleteRequests() {
		$test = new Test();
		Test::requestMethod($test, 'DELETE', 'GET');
		Test::requestMethod($test, 'DELETE', 'POST');
		Test::requestMethod($test, 'DELETE', 'PUT');
		Test::requestMethod($test, 'DELETE', 'PATCH');
	}
}
