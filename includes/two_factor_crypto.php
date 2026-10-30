
<?php

/**
 * MeetSync two-factor authentication secret encryption.
 *
 * The encryption key is stored outside the public website directory.
 */

function meetsyncGetTwoFactorEncryptionKey(): string
{
    $keyFile = 'C:\\xampp\\meetsync-private\\2fa_key.txt';

    if (!is_file($keyFile) || !is_readable($keyFile)) {
        throw new RuntimeException(
            'The MeetSync 2FA encryption key is missing or unreadable.'
        );
    }

    $encodedKey = trim(file_get_contents($keyFile));
    $key = base64_decode($encodedKey, true);

    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException(
            'The MeetSync 2FA encryption key is invalid.'
        );
    }

    return $key;
}

/**
 * Encrypt an authenticator secret using AES-256-GCM.
 */
function meetsyncEncryptTwoFactorSecret(string $secret): string
{
    $key = meetsyncGetTwoFactorEncryptionKey();
    $iv = random_bytes(12);
    $tag = '';

    $encrypted = openssl_encrypt(
        $secret,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if ($encrypted === false) {
        throw new RuntimeException(
            'Unable to encrypt the MeetSync 2FA secret.'
        );
    }

    // Store the IV, authentication tag, and ciphertext together.
    return base64_encode($iv . $tag . $encrypted);
}

/**
 * Decrypt an authenticator secret previously encrypted by MeetSync.
 */
function meetsyncDecryptTwoFactorSecret(string $encryptedSecret): string
{
    $key = meetsyncGetTwoFactorEncryptionKey();

    $data = base64_decode($encryptedSecret, true);

    // 12 bytes for IV, 16 bytes for authentication tag.
    if ($data === false || strlen($data) < 29) {
        throw new RuntimeException(
            'The stored MeetSync 2FA secret is invalid.'
        );
    }

    $iv = substr($data, 0, 12);
    $tag = substr($data, 12, 16);
    $ciphertext = substr($data, 28);

    $secret = openssl_decrypt(
        $ciphertext,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if ($secret === false) {
        throw new RuntimeException(
            'Unable to decrypt the MeetSync 2FA secret.'
        );
    }

    return $secret;
}