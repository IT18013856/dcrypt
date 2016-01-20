<?php

/**
 * Aes.php
 * 
 * PHP version 5
 * 
 * @category Dcrypt
 * @package  Dcrypt
 * @author   Michael Meyer (mmeyer2k) <m.meyer2k@gmail.com>
 * @license  http://opensource.org/licenses/MIT The MIT License (MIT)
 * @link     https://github.com/mmeyer2k/dcrypt
 */

namespace Dcrypt;

/**
 * Symmetric AES-256-CBC encryption functions powered by OpenSSL.
 * 
 * @category Dcrypt
 * @package  Dcrypt
 * @author   Michael Meyer (mmeyer2k) <m.meyer2k@gmail.com>
 * @license  http://opensource.org/licenses/MIT The MIT License (MIT)
 * @link     https://github.com/mmeyer2k/dcrypt
 * @link     https://apigen.ci/github/mmeyer2k/dcrypt/namespace-Dcrypt.html
 */
class Aes extends Cryptobase
{

    /**
     * AES-256 cipher identifier that will be passed to openssl
     * 
     * @var string
     */
    const CIPHER = 'aes-256-cbc';

    /**
     * Size of initialization vector in bytes
     * 
     * @var int
     */
    const IVSIZE = 16;

    /**
     * Size of checksum in bytes
     * 
     * @var int
     */
    const CKSIZE = 32;

    /**
     * Decrypt cyphertext
     * 
     * @param string $cyphertext Cyphertext to decrypt
     * @param string $password   Password that should be used to decrypt input data
     * @param int    $cost       Number of HMAC iterations to perform on key
     * 
     * @return string|boolean Returns false on checksum validation failure
     */
    final public static function decrypt($cyphertext, $password, $cost = 0)
    {
        // Find the IV at the beginning of the cypher text
        $iv = self::substr($cyphertext, 0, self::IVSIZE);

        // Gather the checksum portion of the cypher text
        $chksum = self::substr($cyphertext, self::IVSIZE, self::CKSIZE);

        // Gather message portion of cyphertext after iv and checksum
        $message = self::substr($cyphertext, self::IVSIZE + self::CKSIZE);

        // Derive key from password
        $key = self::key($password, $iv, $cost);

        // Calculate verification checksum
        $verify = self::checksum($message, $iv, $key);

        // Verify HMAC before decrypting
        if (!self::equal($verify, $chksum)) {
            return static::invalidChecksum();
        }

        // Decrypt message and return
        return \openssl_decrypt($message, self::CIPHER, $key, 1, $iv);
    }

    /**
     * Encrypt plaintext
     * 
     * @param string $plaintext Plaintext string to encrypt.
     * @param string $password  Password used to encrypt data.
     * @param int    $cost      Number of HMAC iterations to perform on key
     * 
     * @return string 
     */
    final public static function encrypt($plaintext, $password, $cost = 0)
    {
        // Generate IV of appropriate size.
        $iv = Random::get(self::IVSIZE);

        // Derive key from password
        $key = self::key($password, $iv, $cost);

        // Encrypt the plaintext
        $message = \openssl_encrypt($plaintext, self::CIPHER, $key, 1, $iv);

        // Create the cypher text prefix (iv + checksum)
        $prefix = $iv . self::checksum($message, $iv, $key);

        // Return prefix + cyphertext
        return $prefix . $message;
    }

    /**
     * By default, \Dcrypt\Aes will will return false when the checksum is invalid.
     * Use AesExp to force an exception to be thrown instead.
     * 
     * @return false
     */
    private static function invalidChecksum()
    {
        return false;
    }

}
