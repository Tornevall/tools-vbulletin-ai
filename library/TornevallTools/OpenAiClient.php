<?php
/*========================================================================*\
|| VBulletin by Tornevall Tools
|| Simple HTTP client for Tornevall Tools AI gateway.
\*========================================================================*/

class TornevallTools_OpenAiClient
{
    private string $baseUrl;
    private string $token;

    public function __construct(string $baseUrl, string $token)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = trim($token);
    }

    public function respond(array $payload): array
    {
        if ($this->token === '') {
            return array(
                'ok' => false,
                'error' => 'Missing Tornevall Tools API token.',
            );
        }

        $endpoint = $this->baseUrl . '/api/ai/internal/respond';

        $jsonPayload = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($jsonPayload === false) {
            return array(
                'ok' => false,
                'error' => 'Could not encode AI request payload.',
            );
        }

        $ch = curl_init($endpoint);

        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->token,
            ),
            CURLOPT_POSTFIELDS => $jsonPayload,
        ));

        $rawResponse = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($rawResponse === false) {
            return array(
                'ok' => false,
                'error' => 'Curl error: ' . $curlError,
            );
        }

        $decoded = json_decode($rawResponse, true);

        if (!is_array($decoded)) {
            return array(
                'ok' => false,
                'status' => $httpCode,
                'error' => 'Invalid JSON response from Tornevall Tools.',
                'raw' => $rawResponse,
            );
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            return array(
                'ok' => false,
                'status' => $httpCode,
                'error' => $decoded['error'] ?? 'Tornevall Tools returned HTTP ' . $httpCode,
                'response' => $decoded,
            );
        }

        return array(
            'ok' => true,
            'status' => $httpCode,
            'response' => $decoded,
        );
    }
}
