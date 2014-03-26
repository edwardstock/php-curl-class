<?php
/**
 * php-curl-class. 2014
 * @author Eduard Maksimovich <edward.vstock@gmail.com>
 *
 * Class: CaseInsensitiveArray
 */

namespace EdwardStock\Curl;


class CaseInsensitiveArray implements \ArrayAccess, \Countable, \Iterator
{
	private $container = array();

	public function offsetSet($offset, $value) {
		if ( is_null($offset) ) {
			$this->container[] = $value;
		} else {
			$index = array_search(strtolower($offset), array_keys(array_change_key_case($this->container, CASE_LOWER)));
			if ( !($index === false) ) {
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
}