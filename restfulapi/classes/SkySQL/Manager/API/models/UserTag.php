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
 * Date: May 2013
 * 
 * The UserTag class models a user's tags, used to define selections.
 * 
 */

namespace SkySQL\Manager\API\models;

use PDO;
use SkySQL\COMMON\AdminDatabase;

class UserTag extends EntityModel {
	protected static $setkeyvalues = true;
	
	protected static $updateSQL = 'UPDATE UserTag SET %s WHERE UserName = :username'; /* Not used */
	protected static $countSQL = 'SELECT COUNT(*) FROM UserTag WHERE UserName = :username'; /* Not used */
	protected static $countAllSQL = 'SELECT COUNT(*) FROM UserTag';
	protected static $insertSQL = 'INSERT INTO UserTag (%s) VALUES (%s)';
	protected static $deleteSQL = 'DELETE FROM UserTag WHERE UserName = :username AND TagType = :tagtype AND TagName = :tagname AND Tag = :tag';
	protected static $selectSQL = 'SELECT %s FROM UserTag WHERE UserName = :username AND TagType = :tagtype AND TagName = :tagname AND Tag = :tag';
	protected static $selectAllSQL = 'SELECT %s FROM UserTag %s';
	
	protected static $getAllCTO = array('id');
	
	protected static $keys = array(
		'username' => array('sqlname' => 'UserName', 'desc' => 'Username to which tag applies'),
		'tagtype' => array('sqlname' => 'TagType', 'desc' => 'Type of tag'),
		'tagname' => array('sqlname' => 'TagName', 'desc' => 'Name of tag'),
		'tag' => array('sqlname' => 'Tag', 'desc' => 'Data for tag')
	);

	protected static $fields = array(
		//'systemid' => array(),
		//'nodeid' => array(),
		//'monitor' => array()
	);
	
	public function __construct ($username, $tagtype, $tagname, $tag) {
		$this->username = $username;
		$this->tagtype = $tagtype;
		$this->tagname = $tagname;
		$this->tag = $tag;
	}
	
	public static function getTags ($username, $tagtype, $tagname) {
		$readtags = AdminDatabase::getInstance()->prepare('SELECT Tag FROM UserTag 
			WHERE UserName = :username AND TagType = :tagtype AND TagName = :tagname');
		$readtags->execute(array(
			':username' => $username,
			':tagtype' => $tagtype,
			':tagname' => $tagname
		));
		return $readtags->fetchAll(PDO::FETCH_COLUMN);
	}
	
	public static function getTagNames ($username, $tagtype) {
		$readnames = AdminDatabase::getInstance()->prepare('SELECT DISTINCT TagName FROM UserTag 
			WHERE UserName = :username AND TagType = :tagtype');
		$readnames->execute(array(
			':username' => $username,
			':tagtype' => $tagtype
		));
		return $readnames->fetchAll(PDO::FETCH_COLUMN);
	}
	
	public static function insertTags ($username, $tagtype, $tagname, $tags) {
		$sql = '';
		foreach ($tags as $subscript=>$tag) {
			$sql .=  0 == $subscript ? 'INSERT OR IGNORE INTO UserTag SELECT :username AS UserName, :tagtype AS TagType, :tagname AS TagName, :tag0 AS Tag'
				: " UNION SELECT :username AS UserName, :tagtype AS TagType, :tagname AS TagName, :tag$subscript AS Tag";
			$bind[":tag$subscript"] = $tag;
		}
		if ($sql) {
			$bind[":username"] = $username;
			$bind[":tagtype"] = $tagtype;
			$bind[":tagname"] = $tagname;
			$insert = AdminDatabase::getInstance()->prepare($sql);
			$insert->execute($bind);
		}
	}
	
	public static function deleteTag ($username, $tagtype, $tagname='', $tag='') {
		$sql = 'DELETE FROM UserTag WHERE UserName = :username AND TagType = :tagtype';
		$bind = array(
			':username' => $username,
			':tagtype' => $tagtype
		);
		if ($tagname) {
			$sql .= ' AND TagName = :tagname';
			$bind[":tagname"] = $tagname;
		}
		if ($tag) {
			$sql .= ' AND Tag = :tag';
			$bind[":tag"] = $tag;
		}
		$delete = AdminDatabase::getInstance()->prepare($sql);
		$delete->execute($bind);
		return $delete->rowCount();
	}
}
