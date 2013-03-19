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
 * The Monitors class deals with requests to do with monitors
 * 
 */

namespace SkySQL\SCDS\API;

use \PDO;

final class Monitors extends ImplementAPI {
	protected $systemid = 0;
	protected $nodeid = 0;
	protected $monitorid = 0;
	
	public function getTypes () {
		$query = $this->db->query("SELECT MonitorID AS id, Name AS name, 
			Description AS description, Icon AS icon, ChartType AS type FROM Monitors
			WHERE UIOrder IS NOT NULL ORDER BY UIOrder");
        $result = array(
            "monitortypes" => $query->fetchAll(PDO::FETCH_ASSOC)
        );
        $this->sendResponse($result);
	}
	
	public function monitor ($uriparts) {
		$this->systemid = $uriparts[1];
		$this->nodeid = $uriparts[3];
		$this->monitorid = $uriparts[5];
		$unixtime = strtotime(empty($_GET['time']) ? $this->getLatestTime() : $_GET['time']);
		$interval = empty($_GET['interval']) ? 0 : (int) $_GET['interval'];
		if (!$interval) $interval = 30;
		$count = empty($_GET['count']) ? 0 : (int) $_GET['count'];
		if (!$count) $count = 15;
		$monitorinfo = $this->db->prepare('SELECT Value AS value, Start AS start, Latest AS latest
			FROM MonitorData WHERE MonitorID = :monitorid AND SystemID = :systemid AND NodeID = :nodeid
			AND Start < :time AND Latest >= :time');
		$pairs = array();
		while ($count-- > 0) {
			$time = date('Y-m-d H:i:s', $unixtime);
			$unixtime -= $interval;
			$monitorinfo->execute(array(
				':monitorid' => $this->monitorid,
				':systemid' => $this->systemid,
				':nodeid' => $this->nodeid,
				':time' => $time
			));
			$pairs[] = $monitorinfo->fetchAll(PDO::FETCH_ASSOC);
			$this->sendResponse(array('monitor_data' => array_reverse($pairs)));
		}
		
	}
	
	protected function getLatestTime () {
		$latest = $this->db->prepare('SELECT MAX(Latest) FROM MonitorData WHERE
			MonitorID = :monitorid, SystemID = :systemid, NodeID = :nodeid');
		$latest->execute(array(
			':monitorid' => $this->monitorid,
			':systemid' => $this->systemid,
			':nodeid' => $this->nodeid
		));
		$date = $latest->fetch(PDO::FETCH_COLUMN);
		if ($date) return $date;
		$this->sendResponse(array('monitor_data' => array()));
	}
}
