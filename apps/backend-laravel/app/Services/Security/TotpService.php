<?php

namespace App\Services\Security;

use Illuminate\Support\Str;

class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $length = 32): string
    {
        $secret = '';

        for ($i = 0; $i < $length; $i++) {
            $secret .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $secret;
    }

    public function code(string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $counter = intdiv($timestamp, 30);
        $binaryCounter = pack('N*', 0).pack('N*', $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $this->base32Decode($secret), true);
        $offset = ord(substr($hash, -1)) & 0x0f;
        $value = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== 6) {
            return false;
        }

        $timestamp = time();
        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->code($secret, $timestamp + ($offset * 30)), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function recoveryCodes(int $count = 8): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = Str::upper(Str::random(5)).'-'.Str::upper(Str::random(5));
        }

        return $codes;
    }

    public function provisioningUri(string $email, string $secret): string
    {
        return 'otpauth://totp/'.rawurlencode("Billing v3:{$email}")
            .'?secret='.$secret
            .'&issuer='.rawurlencode('Billing v3')
            .'&algorithm=SHA1&digits=6&period=30';
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        $bits = '';

        foreach (str_split($secret) as $char) {
            $value = strpos(self::ALPHABET, $char);
            if ($value === false) {
                continue;
            }

            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $binary .= chr(bindec($byte));
            }
        }

        return $binary;
    }
}
