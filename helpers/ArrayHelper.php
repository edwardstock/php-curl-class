<?php
/**
 * php-curl-class. 2014
 * @author Eduard Maksimovich <edward.vstock@gmail.com>
 *
 * Class: ArrayHelper
 */

namespace edwardstock\curl\helpers;


class ArrayHelper
{

	public static function isAssociativeArray($array) {
		return (bool)count(array_filter(array_keys($array), 'is_string'));
	}

	public static function isArrayMultidim($array) {
		if ( !is_array($array) ) {
			return false;
		}

		return !(count($array) === count($array, COUNT_RECURSIVE));
	}


} 