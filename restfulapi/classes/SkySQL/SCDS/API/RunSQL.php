<?php

/*
 * Part of the SCDS API.
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

namespace SkySQL\SCDS\API;

use \PDO;
use \PDOException;

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
            $this->subjectdb = new PDO("mysql:host=$hostdata->Hostname;dbname=information_schema", $hostdata->Username, $hostdata->passwd);
            $this->subjectdb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->subjectdb->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $statement = $this->subjectdb->prepare($query);
            $statement->execute();
            $this->sendResponse(array("result" => $statement->fetchAll(PDO::FETCH_ASSOC)));
        }
        catch (PDOException $pe) {
            $this->sendErrorResponse($pe->getMessage(), 400);
            exit;
        }
    }

	protected function getHostData () {
		$node = $this->getParam('GET', 'node', 0);
		if ($node) {
			$statement = $this->db->prepare("SELECT Hostname, Username, passwd FROM NodeData WHERE NodeID = :node");
			$statement->execute(array(':node' => $node));
			$noderecord = $statement->fetch(PDO::FETCH_OBJ);
			if ($noderecord) return $noderecord;
		}
		throw new PDOException ('No valid node provided');
	}
}