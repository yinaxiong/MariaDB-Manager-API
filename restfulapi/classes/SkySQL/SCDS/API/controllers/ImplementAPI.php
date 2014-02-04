<?php

/*
 ** Part of the SkySQL Manager API.
 * 
 * This file is distributed as part of MariaDB Enterprise.  It is free
 * software: you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software Foundation,
 * version 2.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 * 
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 * 
 * Copyright 2013 (c) SkySQL Ab
 * 
 * Author: Martin Brampton
 * Date: February 2013
 * 
 */

namespace SkySQL\SCDS\API\controllers;

use SkySQL\COMMON\AdminDatabase;
use stdClass;

abstract class ImplementAPI {
	protected $db = null;
	protected $requestor = null;
	protected $config = array();
	protected $fieldnames = array();
	protected $requestmethod = '';
	protected $accept = '';
	protected $limit = 10;
	protected $offset = 0;
	protected $keydata = array();
	protected $ifmodifiedsince = 0;
	protected $modified = false;

	public function __construct ($requestor) {
		$this->db = AdminDatabase::getInstance();
		$this->requestor = $requestor;
		$this->config = $requestor->getConfig();
		$this->requestmethod = $requestor->getMethod();
		$this->accept = $requestor->getAccept();
		$filter = $this->getParam('GET', 'fields');
		if ($filter) $this->fieldnames = array_map('trim', explode(',', $filter));
		$this->setLimits();
		$ifmodifiedheader = $this->requestor->getHeader('If-Modified-Since');
		if ($ifmodifiedheader) $this->ifmodifiedsince = strtotime($ifmodifiedheader);
	}
	
	protected function getParam ($arrname, $name, $def=null, $mask=0) {
		return $this->requestor->getParam($arrname, $name, $def, $mask);
	}
	
	protected function paramEmpty ($arrname, $name) {
		return $this->requestor->paramEmpty($arrname, $name);
	}
	
	protected function setLimits () {
		$configlimit = @$this->config['resultset-defaults']['limit'];
		$this->limit = $this->getParam('GET', 'limit', ($configlimit ? $configlimit : 10));
		$this->offset = $this->getParam('GET', 'offset', 0);
	}
	
	public function getLimit () {
		return $this->limit;
	}
	
	public function getOffset () {
		return $this->offset;
	}
	
	public function getKeyData () {
		return $this->keydata;
	}
	
	protected function filterResults ($results) {
		foreach ($results as $key=>$value) {
			$filtered[$key] = $this->filterSingleResult($value);
		}
		return (array) @$filtered;
	}
	
	protected function filterSingleResult ($result) {
		if (count($this->fieldnames)) {
			return is_array($result) ? $this->filterWordsArray($result) : $this->filterWordsObject($result);
		}
		else return $result;
	}
	
	protected function isFilterWord ($word) {
		return empty($this->fieldnames) OR in_array($word, $this->fieldnames);
	}
	
	protected function filterWordsArray ($value) {
		foreach ($this->fieldnames as $word) if (isset($value[$word])) {
			$hits[$word] = $value[$word];
		}
		return empty($hits) ? null : $hits;
	}
	
	protected function filterWordsObject ($value) {
		$hits = new stdClass();
		foreach ($this->fieldnames as $word) if (isset($value->$word)) {
			$hits->$word = $value->$word;
		}
		return $hits;
	}
	
	protected function beginImmediateTransaction () {
		$this->db->beginImmediateTransaction();
	}
	
	protected function beginExclusiveTransaction () {
		$this->db->beginExclusiveTransaction();
	}
	
	protected function commitTransaction () {
		$this->db->commitTransaction();
	}
	
	protected function sendResponse ($body='', $status=200) {
		$this->db->commitTransaction();
		return $this->requestor->sendResponse($body, $status);
	}

	protected function sendErrorResponse ($errors, $status=200, $content_type='application/json') {
		$this->db->rollbackTransaction();
		return $this->requestor->sendErrorResponse($errors, $status, $content_type);
	}
	
	protected function log ($severity, $message) {
		$this->requestor->log($severity, $message);
	}
	
	protected function returnMetadata ($metadata, $response='', $multiple=false, $parameters='', $mandatory='') {
		if ('response' == $metadata) return $response ? $response : (isset($this->defaultResponse) ? $this->defaultResponse : 'unknown - no default response');
		if ('many' == $metadata) return $multiple ? 'Many' : 'One';
		if ('parameters' == $metadata) {
			if ($parameters) {
				if ($mandatory) $parameters .= sprintf(' (Mandatory: %s)', $mandatory);
				return $parameters;
			}
			else return 'None';
		}
		return 'unknown';
	}
}