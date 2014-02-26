<?php

/*
 ** Part of the MariaDB Manager API.
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
 * Copyright 2013 (c) SkySQL Corporation Ab
 * 
 * Author: Martin Brampton
 * Date: February 2013
 * 
 * The Commands class within the API implements the fetching of commands, command 
 * steps or command states.
 */

namespace SkySQL\SCDS\API\controllers;

use PDOException;
use stdClass;

class RunSQL extends SystemNodeCommon {
    protected $subjectdb = null;

    public function runQuery ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'Result from running SQL query', false, 'systemid, nodeid, sql');
        try {
			$this->systemid = $this->getParam('GET', 'systemid', 0);
			$nodeid = $this->getParam('GET', 'nodeid', 0);
			$query = $this->getParam('GET', 'sql');
            if (!$query) throw new PDOException('No query provided');
            if (strcasecmp('SELECT ', substr($query,0,7)) AND strcasecmp('SHOW ', substr($query,0,5))) {
				throw new PDOException('Query is not a SELECT or SHOW statement');
			}
			$results = $this->targetDatabaseQuery($query, $nodeid);
			$aspairs = $this->getParam('POST', 'aspairs');
			if ($results AND !empty($aspairs)) {
				$fields = array_keys(get_object_vars($results[0]));
				if (2 == count($fields)) {
					$fielda = $fields[0];
					$fieldb = $fields[1];
					$pairs = new stdClass();
					foreach ($results as &$result) {
						$property = $result->$fielda;
						$pairs->$property = $result->$fieldb;
					}
					$this->sendResponse(array("result" => $this->filterSingleResult($pairs)));
				}
			}
            $this->sendResponse(array("results" => $this->filterResults($results)));
		}
        catch (PDOException $pe) {
            $this->sendErrorResponse($pe->getMessage(), 400);
            exit;
        }
    }
	
}