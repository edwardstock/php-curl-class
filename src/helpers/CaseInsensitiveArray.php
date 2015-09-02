<?php
/**
 * php-curl-class. 2014
 * @author Eduard Maksimovich <edward.vstock@gmail.com>
 *
 * Class: CaseInsensitiveArray
 */

namespace EdwardStock\Curl\Helpers;


class CaseInsensitiveArray implements \ArrayAccess, \Countable, \Iterator, \Serializable
{

	private $container = [];

	public function offsetSet($offset, $value) {
		if (is_null($offset)) {
			$this->container[] = $value;
		} else {
			$index = array_search(strtolower($offset), array_keys(array_change_key_case($this->container, CASE_LOWER)));
			if (!($index === false)) {
				unset($this->container[array_keys($this->container)[$index]]);
			}
			$this->container[$offset] = $value;
		}
	}

	public function offsetExists($offset) {
		return array_key_exists(strtolower($offset), array_change_key_case($this->container, CASE_LOWER));
	}

	public function offsetUnset($offset) {
		unset($this->container[$offset]);
	}

	public function offsetGet($offset) {
		$index = array_search(strtolower($offset), array_keys(array_change_key_case($this->container, CASE_LOWER)));

		return $index === false ? null : array_values($this->container)[$index];
	}

	public function count() {
		return count($this->container);
	}

	public function next() {
		return next($this->container);
	}

	public function key() {
		return key($this->container);
	}

	public function valid() {
		return !($this->current() === false);
	}

	public function current() {
		return current($this->container);
	}

	public function rewind() {
		reset($this->container);
	}

	/**
	 * (PHP 5 &gt;= 5.1.0)<br/>
	 * String representation of object
	 * @link http://php.net/manual/en/serializable.serialize.php
	 * @return string the string representation of the object or null
	 */
	public function serialize() {
		return serialize($this->container);
	}

	/**
	 * (PHP 5 &gt;= 5.1.0)<br/>
	 * Constructs the object
	 * @link http://php.net/manual/en/serializable.unserialize.php
	 * @param string $serialized <p>
	 * The string representation of the object.
	 * </p>
	 * @return void
	 */
	public function unserialize($serialized) {
		$this->container = unserialize($serialized);
	}
}