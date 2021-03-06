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
 * The Buckets class provides access to Amazon S3 data
 * 
 */

namespace SkySQL\Manager\API\controllers;

class Buckets extends ImplementAPI {
	
	public function getData ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'Bucket data as a stream of possibly binary characters (not JSON)', false, 'bucket, object', 'bucket, object');
		$bucket = $this->getParam('GET', 'bucket');
		$object = $this->getParam('GET', 'object');
		if ($bucket AND $object) {	
			$cmd = "/usr/local/skysql/skysql_aws/S3Control.sh retrieve $bucket $object";
			$file = popen($cmd, 'r');
			while (!feof($file)) echo fgets($file)."</br>";
			pclose($file);
		}
		else $this->sendErrorResponse('Get bucket data requires name of bucket and name of object', 400);
	}
}