<?php

const VAULT_PAYLOAD_MAGIC = 'NDR2';

function vault_key(): string
{
    $masterKey = getenv('VAULT_MASTER_KEY');
    if ($masterKey === false || $masterKey === '') {
        throw new RuntimeException('VAULT_MASTER_KEY is not configured.');
    }

    return hash('sha256', $masterKey, true);
}

function vault_encrypt_credential(string $plaintext): ?string
{
    $nonce = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt(
        $plaintext,
        'aes-256-gcm',
        vault_key(),
        OPENSSL_RAW_DATA,
        $nonce,
        $tag,
        VAULT_PAYLOAD_MAGIC,
        16
    );

    if ($ciphertext === false || strlen($tag) !== 16) {
        return null;
    }

    return base64_encode(VAULT_PAYLOAD_MAGIC . $nonce . $tag . $ciphertext);
}

function vault_decrypt_credential(?string $payload): ?string
{
    if ($payload === null || $payload === '') {
        return null;
    }

    $decoded = base64_decode($payload, true);
    if ($decoded === false || strlen($decoded) < 32 || substr($decoded, 0, 4) !== VAULT_PAYLOAD_MAGIC) {
        return null;
    }

    $nonce = substr($decoded, 4, 12);
    $tag = substr($decoded, 16, 16);
    $ciphertext = substr($decoded, 32);

    $plaintext = openssl_decrypt(
        $ciphertext,
        'aes-256-gcm',
        vault_key(),
        OPENSSL_RAW_DATA,
        $nonce,
        $tag,
        VAULT_PAYLOAD_MAGIC
    );

    return $plaintext === false ? null : $plaintext;
}