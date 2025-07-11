<?php
// app/Services/Jwt.php

class Jwt
{
    /**
     * Encode un payload en JWT HS256
     *
     * @param array  $payload Données à embarquer (sub, iat, exp…)
     * @param string $secret  Clé secrète
     * @return string         Le token JWT
     */
    public static function encode(array $payload, string $secret): string
    {
        $header   = ['alg'=>'HS256','typ'=>'JWT'];
        $segments = [
            self::base64UrlEncode(json_encode($header)),
            self::base64UrlEncode(json_encode($payload))
        ];
        $signingInput = implode('.', $segments);
        $signature    = hash_hmac('sha256', $signingInput, $secret, true);
        $segments[]   = self::base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * Décode et vérifie un JWT HS256
     *
     * @param string $token  Token à vérifier
     * @param string $secret Clé secrète
     * @return array         Payload décodé
     * @throws Exception si format invalide, signature KO ou expiré
     */
    public static function decode(string $token, string $secret): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new Exception('Format JWT invalide');
        }
        list($b64Header, $b64Payload, $b64Sig) = $parts;
        $header  = json_decode(self::base64UrlDecode($b64Header),  true);
        $payload = json_decode(self::base64UrlDecode($b64Payload), true);
        $sig     = self::base64UrlDecode($b64Sig);

        if (empty($header['alg']) || $header['alg'] !== 'HS256') {
            throw new Exception('Algorithme JWT inattendu');
        }

        $expected = hash_hmac('sha256', "{$b64Header}.{$b64Payload}", $secret, true);
        if (!hash_equals($expected, $sig)) {
            throw new Exception('Échec de la vérification de la signature JWT');
        }

        if (isset($payload['exp']) && time() > $payload['exp']) {
            throw new Exception('JWT expiré');
        }

        return $payload;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $pad = 4 - (strlen($data) % 4);
        if ($pad < 4) {
            $data .= str_repeat('=', $pad);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
