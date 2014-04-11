<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

namespace SkySQL\Manager\API;

use SkySQL\COMMON\CACHE\CachedSingleton;

class RequestParser extends CachedSingleton {
	protected static $instance = null;
	protected $apibase = array();
	protected $uriTable = array();
	protected $uriStructuredTable = array();
	
	protected function __construct () {
		$prototypes = new RequestPrototypes();
		$this->apibase = $prototypes->getBaseParts();
		$this->uriTable = $prototypes->getUriTable();
		$this->uriStructuredTable = $prototypes->getUriStructuredTable();
	}

	public static function getInstance () {
		return self::$instance instanceof self ? self::$instance : self::$instance = parent::getCachedSingleton(__CLASS__);
	}

	public function getBaseParts () {
		return $this->apibase;
	}
	
	public function sendOptions ($uriparts) {
		if ($this->getLinkByURI($uriparts, 'GET')) {
			$options[] = 'GET';
			$options[] = 'HEAD';
		}
		foreach (array('PUT', 'POST', 'DELETE') as $method) if ($this->getLinkByURI($uriparts, $method)) $options[] = $method;
		header('Allow: '.implode(', ', $options));
		exit;
	}

	public function getLinkByURI ($uriparts, $requestmethod) {
		if ('HEAD' == $requestmethod) $requestmethod = 'GET';
		$partcount = count($uriparts);
		if ($partcount) {
			foreach ((array) @$this->uriStructuredTable[$requestmethod][array_shift($uriparts)] as $link) {
				if (count($link['uriparts']) != $partcount - 1) continue;
				$matched = true;
				foreach ($link['uriparts'] as $i=>$part) if ($matched) {
					if (!($part == $uriparts[$i]) AND !$this->uriMatch($part, $uriparts[$i])) $matched = false;
				}
				if ($matched) return $link;
			}
		}
		return false;
	}

	protected function uriMatch ($pattern, $actual) {
		return @preg_match("/^$pattern$/", $actual);
	}
}
