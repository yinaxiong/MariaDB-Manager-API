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
 * The Directory class provides tools for caching, and is
 * derived from the Aliro class aliroDirectory which is GNU GPL code
 * that that is copyrighted by Aliro Software Limited (http://aliro.org).
 * 
 */

namespace SkySQL\COMMON\CACHE;

if (basename(@$_SERVER['REQUEST_URI']) == basename(__FILE__)) die ('This software is for use within a larger system');

class Directory {
    protected $path = '';

    public function __construct ($path) {
        $path = str_replace('\\', '/', $path);
        $this->path = ('/' == substr($path,-1)) ? $path : $path.'/';
	}

	public function getPath () {
		return $this->path;
	}

    public function listAll ($type='file', $recurse=false, $fullpath=false) {
        $results = array();
        clearstatcache();
		$dir = @opendir($this->path);
        if ($dir) {
            while ($file = readdir($dir)) {
                if (empty($file) OR '.' == $file OR '..' == $file) continue;
                if (is_dir($this->path.$file)) {
                    if ($recurse) {
                        $subdir = new self($this->path.$file);
                        $results = array_merge($results, $subdir->listAll($type, $recurse, $fullpath));
                        unset($subdir);
                    }
                    if ($type == 'file') continue;
                }
                elseif ($type == 'dir') continue;
                if ($fullpath) $results[] = $this->path.$file;
                else $results[] = $file;
            }
            closedir($dir);
        }
        return $results;
    }

	public function structure () {
		$result['files'] = $this->listAll();
		$result['directories'] = array();
		$directories = $this->listAll('dir');
		foreach ($directories as $directory) {
			$subdir = new self($this->path.'/'.$directory);
			$result['directories'][$directory] = $subdir->structure();
			unset ($subdir);
		}
		return $result;
	}

    public function soleDir () {
        $found = '';
        clearstatcache();
		$dir = @opendir($this->path);
        if ($dir) {
            while ($file = readdir($dir)) {
                if ($file == '.' OR $file == '..') continue;
                if (is_dir($this->path.$file)) {
                    if ($found) return '';
                    else $found = $file;
                }
                else return '';
            }
            closedir($dir);
        }
        return $found;
    }

    public function deleteAll ($keepstandard=false) {
		$this->deleteContents($keepstandard);
        $this->delete();
    }

	public function delete () {
    	clearstatcache();
        if (file_exists($this->path)) {
            if (is_dir($this->path)) return rmdir($this->path);
            else return false;
        }
        return true;
    }

	public function deleteContents ($keepstandard=false) {
		clearstatcache();
        if (!file_exists($this->path)) return;
        $subdirs = $this->listAll ('dir', false, true);
        foreach ($subdirs as $subdir) {
            $subdirectory = new self($subdir);
            $subdirectory->deleteAll($keepstandard);
            unset($subdirectory);
        }
		$this->deleteFiles($keepstandard);
	}

	public function deleteFiles ($keepstandard=false) {
        $files = $this->listAll ('file', false, true);
        foreach ($files as $file) {
			$filename = basename($file);
			if (!$keepstandard OR ('index.html' != $filename AND '.' != $filename[0])) @unlink($file);
		}
	}

	public function create ($dir='', $onlyCheck=false) {
		if (!$dir) $dir = $this->path;
        if (file_exists($dir)) {
            if (is_dir($dir) AND is_writable($dir)) return true;
            else return false;
        }
        list($upDirectory, $count) = $this->containingDirectory($dir);
        if ($count > 1 AND !file_exists($upDirectory) AND !($result = $this->create($upDirectory, $onlyCheck))) return false;
        if ($onlyCheck AND isset($result)) return true;
        if (!is_dir($upDirectory) OR !is_writable($upDirectory)) return false;
        if ($onlyCheck) return true;
		return @mkdir($dir);
	}

    protected function containingDirectory ($dir) {
        $dirs = preg_split('*[/|\\\]*', $dir);
        while (count($dirs)) {
            if (trim(array_pop($dirs))) break;
        }
        $result2 = count($dirs);
        $result1 = implode('/',$dirs).($result2 > 1 ? '' : '/');
        return array($result1, $result2);
	}

    public function createFresh () {
        $this->deleteAll();
        $this->create();
        return true;
    }

    public function createIfNeeded () {
        return file_exists($this->path) ? true : $this->create();
    }

    public function listFiles ($pattern='', $type='file', $recurse=false, $fullpath=false) {
        $results = array();
        $all = $this->listAll($type, $recurse, $fullpath);
        foreach ($all as $file) {
            $name = basename($file);
            if ($pattern AND !preg_match( "/$pattern/", $name )) continue;
            if (($name != 'index.html') AND ($name[0] != '.')) $results[] = $file;
        }
        return $results;
    }

	// The extension parameter can be a string e.g. 'gif' or an array of strings
	public function selectiveMove ($topath, $extension) {
		if ('/' != substr($topath,-1)) $topath .= '/';
		$newdir = new Directory($topath);
		$newdir->createIfNeeded();
		if (empty($extension)) $pattern = '';
		else {
			$exts = array_map(array($this, 'addPeriod'), (array) $extension);
			$pattern = '.*'.implode('|', $exts).'$';
		}
		$files = $this->listFiles($pattern);
		foreach ($files as $file) if (!rename($this->path.$file, $topath.$file)) return false;
		$dirs = $this->listFiles('', 'dir');
		foreach ($dirs as $dir) {
			$newdir = new self($this->path.$dir);
			$this->create($topath.$dir);
			if (!$newdir->selectiveMove($topath.$dir, $extension)) return false;
		}
		$this->deleteAll();
		return true;
	}

	private function addPeriod ($string) {
		return '.'.$string;
    }

    public function getSize () {
        $totalsize = 0;
        $files = $this->listFiles();
        foreach ($files as $file) $totalsize += filesize($this->path.$file);
        return $totalsize;
    }

	// This uses the PHP ZipArchive class that is only available around version 5.2.5
	public function newzip ($zipname) {
        $zipfile = $this->path.$zipname;
		$files = $this->listAll('file', true, true);
		$zip = new ZipArchive();
		if (!$zip->open($zipfile, ZIPARCHIVE::CREATE)) trigger_error(T_('Unable to open zip file ').$zipfile);
		else foreach ($files as $file) {
			$zip->addFile($file,substr($file,strlen($this->path)));
		}
		$zip->close();
		return $zipfile;
	}

	public function zip ($zipname) {
		$zipper = new zipfile();
		$files = $this->listAll('file', true, true);
		foreach ($files as $file) {
			$zipper->addFile(file_get_contents($file), substr($file,strlen($this->path)));
		}
		$zipper->output($this->path.$zipname);
		return $this->path.$zipname;
	}
}
