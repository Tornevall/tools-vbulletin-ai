<?php
/*========================================================================*\
|| VBulletin by Tornevall Tools
|| Direct OpenAI client for vBulletin AI provider selection.
\*========================================================================*/

class TornevallTools_DirectOpenAiClient
{
    private $baseUrl;
    private $apiKey;
    private $model;
    private $timeout;

    public function __construct($baseUrl, $apiKey, $model, $timeout = 60)
    {
        $this->baseUrl = rtrim((string) $baseUrl, '/');
        $this->apiKey = trim((string) $apiKey);
        $this->model = trim((string) $model);
        $this->timeout = (int) $timeout;

        if ($this->baseUrl === '') {
            $this->baseUrl = 'https://api.openai.com/v1';
        }

        if ($this->model === '') {
            $this->model = 'gpt-4o-mini';
        }

        if ($this->timeout <= 0) {
            $this->timeout = 60;
        }
    }

    public function respond(array $payload)
    {
        if ($this->apiKey === '') {
            return $this->gatewayFailure(0, '', 'OpenAI API key is missing.', '', array());
        }

        $context = isset($payload['context']) ? trim((string) $payload['context']) : '';
        $prompt = isset($payload['user_prompt']) ? trim((string) $payload['user_prompt']) : '';
        $language = isset($payload['response_language']) ? trim((string) $payload['response_language']) : '';
        $useWebSearch = !empty($payload['use_web_search']);
        $webSearchRequired = !empty($payload['web_search_required']);

        if ($prompt === '') {
            return $this->gatewayFailure(0, '', 'Prompt is required.', '', array());
        }

        $system = $context;

        if ($language !== '') {
            $system = trim($system . "\n\nResponse language: " . $language);
        }

        $body = array(
            'model' => $this->model,
            'input' => array(
                array(
                    'role' => 'system',
                    'content' => $system,
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt,
                ),
            ),
        );

        if ($useWebSearch || $webSearchRequired) {
            $body['tools'] = array(
                array(
                    'type' => 'web_search_preview',
                ),
            );
        }

        $requestBody = json_encode($body);

        if ($requestBody === false) {
            return $this->gatewayFailure(0, '', 'Could not encode OpenAI request JSON.', '', array());
        }

        $url = $this->baseUrl . '/responses';
        $ch = curl_init($url);

        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ),
            CURLOPT_POSTFIELDS => $requestBody,
        ));

        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($raw === false) {
            return $this->gatewayFailure(0, '', 'OpenAI request failed: ' . $curlError, '', array(
                'request_bytes' => strlen($requestBody),
            ));
        }

        $headers = substr($raw, 0, $headerSize);
        $responseBody = substr($raw, $headerSize);
        $contentType = $this->extractHeaderValue($headers, 'content-type');
        $decoded = json_decode($responseBody, true);

        if (!is_array($decoded)) {
            return $this->gatewayFailure($status, $contentType, 'Invalid JSON response from OpenAI.', $responseBody, array(
                'request_bytes' => strlen($requestBody),
                'response_bytes' => strlen($responseBody),
            ));
        }

        if ($status < 200 || $status >= 300) {
            $message = 'OpenAI request failed.';

            if (!empty($decoded['error']['message'])) {
                $message = (string) $decoded['error']['message'];
            }

            return $this->gatewayFailure($status, $contentType, $message, $responseBody, array(
                'request_bytes' => strlen($requestBody),
                'response_bytes' => strlen($responseBody),
                'openai_error' => isset($decoded['error']) ? $decoded['error'] : null,
            ));
        }

        $text = $this->extractOutputText($decoded);

        return array(
            'ok' => true,
            'status' => $status,
            'provider' => 'openai',
            'response' => array(
                'ok' => true,
                'request_id' => isset($decoded['id']) ? $decoded['id'] : null,
                'model' => isset($decoded['model']) ? $decoded['model'] : $this->model,
                'response' => $text,
                'usage' => isset($decoded['usage']) ? $decoded['usage'] : null,
                'used_fallback_model' => false,
                'web_search' => array(
                    'requested' => $useWebSearch,
                    'required' => $webSearchRequired,
                    'used' => $useWebSearch || $webSearchRequired,
                    'citations' => $this->extractAnnotations($decoded),
                ),
                'client' => array(
                    'slug' => 'direct_openai',
                    'name' => 'Direct OpenAI',
                    'description' => 'Direct OpenAI Responses API provider',
                ),
                'applied_settings' => array(
                    'response_language' => $language,
                ),
            ),
        );
    }

    private function extractOutputText(array $response)
    {
        if (isset($response['output_text']) && is_string($response['output_text'])) {
            return $response['output_text'];
        }

        $parts = array();

        if (!empty($response['output']) && is_array($response['output'])) {
            foreach ($response['output'] as $outputItem) {
                if (empty($outputItem['content']) || !is_array($outputItem['content'])) {
                    continue;
                }

                foreach ($outputItem['content'] as $contentItem) {
                    if (isset($contentItem['text']) && is_string($contentItem['text'])) {
                        $parts[] = $contentItem['text'];
                    }
                }
            }
        }

        return trim(implode("\n", $parts));
    }

    private function extractAnnotations(array $response)
    {
        $annotations = array();

        if (empty($response['output']) || !is_array($response['output'])) {
            return $annotations;
        }

        foreach ($response['output'] as $outputItem) {
            if (empty($outputItem['content']) || !is_array($outputItem['content'])) {
                continue;
            }

            foreach ($outputItem['content'] as $contentItem) {
                if (empty($contentItem['annotations']) || !is_array($contentItem['annotations'])) {
                    continue;
                }

                foreach ($contentItem['annotations'] as $annotation) {
                    $annotations[] = $annotation;
                }
            }
        }

        return $annotations;
    }

    private function gatewayFailure($status, $contentType, $error, $rawResponse, array $extra)
    {
        $payload = array(
            'ok' => true,
            'gateway_ok' => false,
            'provider' => 'openai',
            'status' => (int) $status,
            'content_type' => (string) $contentType,
            'error' => (string) $error,
            'text' => 'OpenAI provider error: ' . (string) $error,
            'raw_preview' => substr((string) $rawResponse, 0, 2000),
        );

        foreach ($extra as $key => $value) {
            $payload[$key] = $value;
        }

        return $payload;
    }

    private function extractHeaderValue($headers, $name)
    {
        $headers = (string) $headers;
        $name = strtolower((string) $name);
        $lines = preg_split('/\r\n|\r|\n/', $headers);

        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);

            if (count($parts) !== 2) {
                continue;
            }

            if (strtolower(trim($parts[0])) === $name) {
                return trim($parts[1]);
            }
        }

        return '';
    }
}
