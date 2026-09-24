<?php
declare(strict_types=1);

namespace Vendor\MemberInfo\Model\Encryptor;

use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Thin wrapper around Magento's own EncryptorInterface (AES-256-GCM/CBC keyed off
 * app/etc/env.php's crypt/key) so DOB/SSN are never encrypted with a custom cipher
 * and key rotation stays consistent with core Magento practice.
 */
class MemberDataEncryptor
{
    public function __construct(
        private EncryptorInterface $encryptor
    ) {
    }

    public function encrypt(string $plaintext): string
    {
        return $this->encryptor->encrypt($plaintext);
    }

    public function decrypt(string $ciphertext): string
    {
        return $this->encryptor->decrypt($ciphertext);
    }
}
