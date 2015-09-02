<?php
/**
 * php-curl-class. 2014
 * @author Eduard Maksimovich <edward.vstock@gmail.com>
 *
 * Class: HttpHelper
 */

namespace EdwardStock\Curl\Helpers;


class HttpHelper
{

	public static function httpBuildMultiQuery($data, $key = null) {
		$query = array();

		if ( empty($data) ) {
			return $key . '=';
		}

		$isAssocArray = ArrayHelper::isAssociativeArray($data);

		foreach ( $data as $k => $value ) {
			if ( is_string($value) || is_numeric($value) ) {
				$brackets = $isAssocArray ? '[' . $k . ']' : '[]';
				$query[] = urlencode(is_null($key) ? $k : $key . $brackets) . '=' . rawurlencode($value);
			} elseif ( is_array($value) ) {
				$nested = is_null($key) ? $k : $key . '[' . $k . ']';
				$query[] = HttpHelper::httpBuildMultiQuery($value, $nested);
			}
		}

		return implode('&', $query);
	}
} 