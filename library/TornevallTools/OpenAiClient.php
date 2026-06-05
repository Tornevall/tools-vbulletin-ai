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
            return $this->gatewayFailure('Missing Tornevall Tools API token.', 0, '', '', 0);
        }

        $endpoint = $this->baseUrl . '/api/ai/internal/respond';

        $jsonPayload = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($jsonPayload === false) {
            return $this->gatewayFailure('Could not encode AI request payload.', 0, '', '', 0);
        }

        $ch = curl_init($endpoint);

        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 90,
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
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

        curl_close($ch);

        if ($rawResponse === false) {
            return $this->gatewayFailure('Curl error: ' . $curlError, $httpCode, $contentType, '', strlen($jsonPayload));
        }

        $decoded = json_decode($rawResponse, true);

        if (!is_array($decoded)) {
            $rawPreview = substr((string) $rawResponse, 0, 2000);

            error_log('Tornevall Tools invalid JSON HTTP status: ' . $httpCode);
            error_log('Tornevall Tools invalid JSON content-type: ' . $contentType);
            error_log('Tornevall Tools request bytes: ' . strlen($jsonPayload));
            error_log('Tornevall Tools invalid JSON raw preview: ' . $rawPreview);

            return $this->gatewayFailure(
                'Invalid JSON response from Tornevall Tools.',
                $httpCode,
                $contentType,
                $rawPreview,
                strlen($jsonPayload),
                strlen((string) $rawResponse)
            );
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            return $this->gatewayFailure(
                $decoded['error'] ?? 'Tornevall Tools returned HTTP ' . $httpCode,
                $httpCode,
                $contentType,
                json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                strlen($jsonPayload),
                strlen((string) $rawResponse)
            );
        }

        return array(
            'ok' => true,
            'gateway_ok' => true,
            'status' => $httpCode,
            'request_bytes' => strlen($jsonPayload),
            'response' => $decoded,
        );
    }

    private function gatewayFailure(string $message, int $status, string $contentType, string $rawPreview, int $requestBytes, int $responseBytes = 0): array
    {
        $text = $message;

        if ($status > 0) {
            $text .= "\nHTTP status: " . $status;
        }

        if ($contentType !== '') {
            $text .= "\nContent-Type: " . $contentType;
        }

        if ($requestBytes > 0) {
            $text .= "\nRequest bytes: " . $requestBytes;
        }

        if ($responseBytes > 0) {
            $text .= "\nResponse bytes: " . $responseBytes;
        }

        if ($rawPreview !== '') {
            $text .= "\n\nRaw preview:\n" . $rawPreview;
        }

        return array(
            'ok' => true,
            'gateway_ok' => false,
            'status' => $status,
            'content_type' => $contentType,
            'request_bytes' => $requestBytes,
            'response_bytes' => $responseBytes,
            'error' => $message,
            'raw_preview' => $rawPreview,
            'text' => $text,
        );
    }
}
