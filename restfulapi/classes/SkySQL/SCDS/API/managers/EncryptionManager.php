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
 * The EncryptionManager class provides encryption and decryption methods
 * 
 */

namespace SkySQL\SCDS\API\managers;

use SkySQL\SCDS\API\Request;

class EncryptionManager extends EntityManager {
	
	public static function decryptOneField ($string, $givenkey) {
	    $key = pack('H*', $givenkey);
    
	    $ciphertext_dec = base64_decode($string);
    
	    # retrieves the IV, iv_size should be created using mcrypt_get_iv_size()
	    $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
		if (strlen($ciphertext_dec) < ($iv_size + 2)) Request::getInstance()->sendErrorResponse("Field requiring decryption is too short", 400); 
	    $iv_dec = substr($ciphertext_dec, 0, $iv_size);
    
	    # retrieves the cipher text (everything except the $iv_size in the front)
	    $ciphertext = substr($ciphertext_dec, $iv_size);

	    # may remove 00h valued characters from end of plain text
	    $decrypt = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $ciphertext, MCRYPT_MODE_CBC, $iv_dec);
		return trim($decrypt, "\0..\32");
	}

	public static function encryptOneField ($plaintext, $givenkey) {
		$key = pack('H*', $givenkey);

	    # create a random IV to use with CBC encoding
	    $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
	    $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);

	    # creates a cipher text compatible with AES (Rijndael block size = 128)
	    # to keep the text confidential
	    # only suitable for encoded input that never ends with value 00h
	    # (because of default zero padding)
	    $ciphertext = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $plaintext, MCRYPT_MODE_CBC, $iv);

	    # prepend the IV for it to be available for decryption
	    $ciphertext = $iv.$ciphertext;

		# encode the resulting cipher text so it can be represented by a string
		return base64_encode($ciphertext);
	}
}