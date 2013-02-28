<?php

/*
 * SkySQL API framework
 * Copyright 2013 (c) SkySQL
 * Author: Martin Brampton
 */

namespace SkySQL\SCDS\API;

// Remove error settings after testing
ini_set('display_errors', 1);
error_reporting(-1);

require (dirname(__FILE__).'/api.php');
API::getInstance()->startup(true);
