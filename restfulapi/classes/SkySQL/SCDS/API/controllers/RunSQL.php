<?php

/*
 ** Part of the SkySQL Manager API.
 * 
 * This file is distributed as part of the SkySQL Cloud Data Suite.  It is free
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
 * The Commands class within the API implements the fetching of commands, command 
 * steps or command states.
 */

namespace SkySQL\SCDS\API\controllers;

use PDO;
use PDOException;
use stdClass;

class RunSQL extends ImplementAPI {
    protected $subjectdb = null;

    public function runQuery () {
        try {
			$query = $this->getParam('GET', 'sql');
            if (!$query) throw new PDOException('No query provided');
            if (strcasecmp('SELECT ', substr($query,0,7)) AND strcasecmp('SHOW ', substr($query,0,5))) {
				throw new PDOException('Query is not a SELECT or SHOW statement');
			}
			$hostdata = $this->getHostData();
            $hostparts = explode(':', $hostdata->Hostname);
			$connection = "mysql:host={$hostparts[0]};dbname=information_schema";
			if (count($hostparts) > 1) $connection .= ";port={$hostparts[1]}";
            $this->subjectdb = new PDO($connection, $hostdata->Username, $hostdata->passwd);
            $this->subjectdb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->subjectdb->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
			$this->subjectdb->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
			$statement = $this->subjectdb->prepare($query);
            $statement->execute();
			$results = $statement->fetchAll();
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
	
	protected function getHostData () {
		$systemid = $this->getParam('GET', 'systemid', 0);
		$nodeid = $this->getParam('GET', 'nodeid', 0);
		if ($systemid AND $nodeid) {
			$statement = $this->db->prepare("SELECT Hostname, Username, passwd FROM NodeData 
				WHERE SystemID = :systemid AND NodeID = :nodeid");
			$statement->execute(array(
				':systemid' => $systemid,
				':nodeid' => $nodeid
			));
			$noderecord = $statement->fetch(PDO::FETCH_OBJ);
			if ($noderecord) return $noderecord;
		}
		throw new PDOException ('System ID and Node ID must be provided');
	}
}