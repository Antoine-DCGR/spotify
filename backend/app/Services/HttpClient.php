<?php
// app/Services/HttpClient.php

class HttpClient
{
    /**
     * Envoie une requête POST en x-www-form-urlencoded et renvoie le tableau PHP
     *
     * @param string $url        URL cible
     * @param array  $formParams Paramètres à envoyer
     * @return array
     * @throws Exception en cas d’erreur HTTP ou JSON invalide
     */
    public static function post(string $url, array $formParams): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST,        true);
        curl_setopt($ch, CURLOPT_POSTFIELDS,  http_build_query($formParams));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new Exception("cURL error on POST {$url} : {$err}");
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception("HTTP {$httpCode} from {$url} : {$resp}");
        }

        $data = json_decode($resp, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response from {$url}");
        }

        return $data;
    }


    public static function get(string $url, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new Exception("cURL error on GET {$url} : {$err}");
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception("HTTP {$httpCode} from {$url} : {$resp}");
        }

        $data = json_decode($resp, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response from {$url}");
        }

        return $data;
    }
}

