<?php declare(strict_types=1);

/**
 * OpensslStatic.php
 *
 * PHP version 7
 *
 * @category Dcrypt
 * @package  Dcrypt
 * @author   Michael Meyer (mmeyer2k) <m.meyer2k@gmail.com>
 * @license  http://opensource.org/licenses/MIT The MIT License (MIT)
 * @link     https://github.com/mmeyer2k/dcrypt
 */

namespace Dcrypt;

use \Dcrypt\Exceptions\InvalidChecksumException;

/**
 * Static functions that handle encryption/decryption with openssl.
 *
 * @category Dcrypt
 * @package  Dcrypt
 * @author   Michael Meyer (mmeyer2k) <m.meyer2k@gmail.com>
 * @license  http://opensource.org/licenses/MIT The MIT License (MIT)
 * @link     https://github.com/mmeyer2k/dcrypt
 */
final class OpensslStatic extends OpensslWrapper
{
    /**
     * Decrypt raw data string
     *
     * @param string $data   Data to be decrypted
     * @param string $key    Key material
     * @param string $cipher OpenSSL cipher name
     * @param string $algo   Hashing and key derivation algo name
     * @return string
     * @throws \Exception
     */
    public static function decrypt(string $data, string $key, string $cipher, string $algo): string
    {
        // Calculate the hash checksum size in bytes for the specified algo
        $hsz = Str::hashSize($algo);

        // Find the tag size for this cipher mode. Unless using GCM/CCM this will be zero.
        $tsz = parent::tagRequired($cipher) ? 4 : 0;

        // Ask openssl for the IV size needed for specified cipher
        $isz = parent::ivSize($cipher);

        // Get the IV at the beginning of the ciphertext
        $ivr = Str::substr($data, 0, $isz);

        // Get the checksum after the IV
        $sum = Str::substr($data, $isz, $hsz);

        // Get the AEAD authentication tag (if present) after the checksum
        $tag = Str::substr($data, $isz + $hsz, $tsz);

        // Get the encrypted message payload
        $msg = Str::substr($data, $isz + $hsz + $tsz);

        // Create a new password derivation object
        $key = new OpensslKey($algo, $key, $ivr);

        // Calculate checksum of message payload for verification
        $chk = \hash_hmac($algo, $msg, $key->authenticationKey($cipher), true);

        // Compare given checksum against computed checksum using a time-safe function
        if (!Str::equal($chk, $sum)) {
            throw new InvalidChecksumException('Decryption can not proceed due to invalid ciphertext checksum.');
        }

        // Decrypt message and return
        return parent::openssl_decrypt($msg, $cipher, $key->encryptionKey($cipher), $ivr, $tag);
    }

    /**
     * Encrypt raw string
     *
     * @param string $data   Data to be encrypted
     * @param string $key    Key material
     * @param string $cipher OpenSSL cipher name
     * @param string $algo   Hashing and key derivation algo name
     * @return string
     * @throws \Exception
     */
    public static function encrypt(string $data, string $key, string $cipher, string $algo): string
    {
        // Generate IV of appropriate size
        $ivr = parent::ivGenerate($cipher);

        // Create key derivation object
        $key = new OpensslKey($algo, $key, $ivr);

        // Create a placeholder for the authentication tag to be passed by reference
        $tag = '';

        // Encrypt the plaintext
        $msg = parent::openssl_encrypt($data, $cipher, $key->encryptionKey($cipher), $ivr, $tag);

        // Generate the ciphertext checksum
        $chk = \hash_hmac($algo, $msg, $key->authenticationKey($cipher), true);

        // Return iv + checksum + tag + ciphertext
        return $ivr . $chk . $tag . $msg;
    }
}