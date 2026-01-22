<?php

namespace App\Services\Facebook;

final class FacebookSignedRequest
{
    public static function decodeAndVerify(string $signedRequest, string $appSecret): array
    {
        [$encodedSig, $encodedPayload] = array_pad(explode('.', $signedRequest, 2), 2, null);

        if (!$encodedSig || !$encodedPayload) {
            throw new \RuntimeException('signed_request_invalid');
        }

        $sig = self::base64UrlDecode($encodedSig);
        $payloadJson = self::base64UrlDecode($encodedPayload);

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            throw new \RuntimeException('signed_request_payload_invalid');
        }

        $algo = $payload['algorithm'] ?? '';
        if (strtoupper($algo) !== 'HMAC-SHA256') {
            throw new \RuntimeException('signed_request_algorithm_invalid');
        }

        $expected = hash_hmac('sha256', $encodedPayload, $appSecret, true);

        if (!hash_equals($expected, $sig)) {
            throw new \RuntimeException('signed_request_signature_invalid');
        }

        return $payload;
    }

    private static function base64UrlDecode(string $input): string
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $input .= str_repeat('=', 4 - $remainder);
        }

        $input = strtr($input, '-_', '+/');

        $decoded = base64_decode($input, true);
        if ($decoded === false) {
            throw new \RuntimeException('base64_decode_failed');
        }

        return $decoded;
    }
}
