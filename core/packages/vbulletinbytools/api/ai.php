<?php
/*========================================================================*\
|| VBulletin by Tornevall Tools
|| AI API class for vBulletin package system.
\*========================================================================*/

if (!class_exists('vB_Api')) {
    if (PHP_SAPI === 'cli') {
        echo "This file is a vBulletin API class and must be loaded by vBulletin.\n";
        echo "Syntax check with: php -l api/ai.php\n";
    } else {
        header('Content-Type: application/json; charset=utf-8', true, 403);
        echo json_encode(array(
            'ok' => false,
            'error' => 'This endpoint must be called through the vBulletin API system.',
        ));
    }

    return;
}

class vbulletinbytools_Api_Ai extends vB_Api
{
    protected $disableWhiteList = array(
        'respond',
        'test',
        'personaDebug',
        'threadDebug',
        'providerDebug',
    );

    public function test()
    {
        return array(
            'ok' => true,
            'method' => 'test',
            'message' => 'vbulletinbytools API test method works',
        );
    }

    public function providerDebug()
    {
        try {
            $options = vB::getDatastore()->getValue('options');
            $provider = $this->getAiProvider($options);

            return array(
                'ok' => true,
                'provider' => $provider,
                'tools_configured' => !empty($options['tornis_tools_gpt_secret']),
                'openai_configured' => !empty($options['tornis_tools_openai_api_key']),
                'openai_base_url' => $this->getOptionString($options, 'tornis_tools_openai_base_url', 'https://api.openai.com/v1'),
                'openai_model' => $this->getOptionString($options, 'tornis_tools_openai_model', 'gpt-4o-mini'),
            );
        } catch (Throwable $e) {
            return $this->apiSafeError('providerDebug failed', $e);
        }
    }

    public function personaDebug()
    {
        try {
            $options = vB::getDatastore()->getValue('options');
            $personaFieldId = $this->getOptionInt($options, 'tornis_tools_gpt_persona_field', 0);
            $userid = $this->getCurrentUserId();
            $username = $this->getUsernameByUserId($userid);
            $persona = '';

            if ($personaFieldId > 0) {
                $persona = $this->getCurrentUserPersona($personaFieldId);
            }

            return array(
                'ok' => true,
                'userid' => $userid,
                'username' => $username,
                'persona_field_id' => $personaFieldId,
                'persona_field_name' => ($personaFieldId > 0 ? 'field' . $personaFieldId : ''),
                'has_persona' => ($persona !== ''),
                'persona_length' => strlen($persona),
            );
        } catch (Throwable $e) {
            return $this->apiSafeError('personaDebug failed', $e);
        }
    }

    public function threadDebug($nodeid = 0)
    {
        try {
            $nodeid = (int) $nodeid;
            $threadContext = '';

            if ($nodeid > 0) {
                $threadContext = $this->getThreadContextFromNodeId($nodeid);
            }

            return array(
                'ok' => true,
                'nodeid' => $nodeid,
                'has_thread_context' => ($threadContext !== ''),
                'thread_context_length' => strlen($threadContext),
                'thread_context_preview' => substr($threadContext, 0, 1200),
            );
        } catch (Throwable $e) {
            return $this->apiSafeError('threadDebug failed', $e);
        }
    }

    public function respond($context = '', $prompt = '', $language = 'sv', $nodeid = 0)
    {
        try {
            return $this->respondInternal($context, $prompt, $language, $nodeid);
        } catch (Throwable $e) {
            error_log('vbulletinbytools respond failed: ' . $e->getMessage());
            return $this->apiSafeError('AI endpoint failed', $e);
        }
    }

    private function respondInternal($context = '', $prompt = '', $language = 'sv', $nodeid = 0)
    {
        $context = trim((string) $context);
        $prompt = trim((string) $prompt);
        $language = trim((string) $language);
        $nodeid = (int) $nodeid;

        if ($prompt === '') {
            return $this->localFailure('Prompt is required.');
        }

        $options = vB::getDatastore()->getValue('options');

        if (empty($options['tornis_tools_ai_enabled'])) {
            return $this->localFailure('Tornevall Tools AI is disabled.');
        }

        $provider = $this->getAiProvider($options);
        $clientSlug = $this->getOptionString($options, 'tornis_tools_ai_client_slug', 'vbulletin_wysiwyg_assistant');
        $personaFieldId = $this->getOptionInt($options, 'tornis_tools_gpt_persona_field', 0);
        $userid = $this->getCurrentUserId();
        $username = $this->getUsernameByUserId($userid);
        $persona = '';

        if ($personaFieldId > 0) {
            $persona = $this->getCurrentUserPersona($personaFieldId);
        }

        $threadContext = '';

        if ($nodeid > 0) {
            $threadContext = $this->getThreadContextFromNodeId($nodeid);
        }

        if ($threadContext !== '') {
            $context = trim($context . "\n\nServer-side vBulletin thread context:\n" . $threadContext);
        }

        $sourceSensitiveRequest = $this->isSourceSensitiveRequest($prompt . "\n" . $context);

        if ($sourceSensitiveRequest) {
            $context = trim($context . "\n\nSource verification rules:\n" . implode("\n", array(
                '- This request asks for sources, references, citations, links, fact checking or verifiable factual claims.',
                '- Use web search to verify source URLs and citation targets before presenting them.',
                '- Do not invent sources, citation labels, article titles or URLs.',
                '- If no verified source is found, say that no verified source was found instead of creating a broken reference.',
                '- Prefer direct, canonical, reachable source URLs when sources are requested.',
            )));
        }

        $fullContext = $this->buildFullContext($context, $persona, $userid, $username);
        $useWebSearch = !empty($options['tornis_tools_ai_web_search_enabled']) || $sourceSensitiveRequest;
        $webSearchRequired = !empty($options['tornis_tools_ai_web_search_required']) || $sourceSensitiveRequest;

        $payload = array(
            'client_slug' => $clientSlug,
            'context' => $fullContext,
            'user_prompt' => $prompt,
            'response_language' => $language,
            'use_web_search' => $useWebSearch,
            'web_search_required' => $webSearchRequired,
            'vbulletin' => array(
                'userid' => $userid,
                'username' => $username,
                'nodeid' => $nodeid,
                'provider' => $provider,
                'has_thread_context' => ($threadContext !== ''),
                'thread_context_length' => strlen($threadContext),
                'persona_field_id' => $personaFieldId,
                'persona_field_name' => ($personaFieldId > 0 ? 'field' . $personaFieldId : ''),
                'has_persona' => ($persona !== ''),
                'source_sensitive_request' => $sourceSensitiveRequest,
                'use_web_search' => $useWebSearch,
                'web_search_required' => $webSearchRequired,
            ),
        );

        if ($provider === 'openai') {
            return $this->respondWithOpenAi($options, $payload);
        }

        return $this->respondWithTornevallTools($options, $payload);
    }

    private function respondWithTornevallTools($options, array $payload)
    {
        $token = $this->getOptionString($options, 'tornis_tools_gpt_secret', '');

        if ($token === '') {
            return $this->localFailure('Tornevall Tools API token is missing.');
        }

        $baseUrl = $this->getOptionString($options, 'tornis_tools_api_base_url', 'https://tools.tornevall.net');
        $baseUrl = rtrim($baseUrl, '/');

        require_once(DIR . '/packages/vbulletinbytools/library/TornevallTools/OpenAiClient.php');

        if (!class_exists('TornevallTools_OpenAiClient')) {
            return $this->localFailure('TornevallTools_OpenAiClient was not loaded. Check package file path.');
        }

        $client = new TornevallTools_OpenAiClient($baseUrl, $token);
        return $client->respond($payload);
    }

    private function respondWithOpenAi($options, array $payload)
    {
        $apiKey = $this->getOptionString($options, 'tornis_tools_openai_api_key', '');

        if ($apiKey === '') {
            return $this->localFailure('OpenAI API key is missing.');
        }

        $baseUrl = $this->getOptionString($options, 'tornis_tools_openai_base_url', 'https://api.openai.com/v1');
        $model = $this->getOptionString($options, 'tornis_tools_openai_model', 'gpt-4o-mini');
        $timeout = $this->getOptionInt($options, 'tornis_tools_openai_timeout', 60);

        require_once(DIR . '/packages/vbulletinbytools/library/TornevallTools/DirectOpenAiClient.php');

        if (!class_exists('TornevallTools_DirectOpenAiClient')) {
            return $this->localFailure('TornevallTools_DirectOpenAiClient was not loaded. Check package file path.');
        }

        $client = new TornevallTools_DirectOpenAiClient($baseUrl, $apiKey, $model, $timeout);
        return $client->respond($payload);
    }

    private function buildFullContext($context, $persona, $userid, $username)
    {
        $context = trim((string) $context);
        $persona = trim((string) $persona);
        $userid = (int) $userid;
        $username = trim((string) $username);

        $currentUserLines = array(
            'Current vBulletin user identity:',
            'User ID: ' . $userid,
        );

        if ($username !== '') {
            $currentUserLines[] = 'Username: ' . $username;
        }

        $currentUserLines[] = 'When drafting, rewriting or answering in a thread, write as the current vBulletin user above.';
        $currentUserLines[] = 'Do not describe the current user in third person unless the user explicitly asks for that.';
        $currentUserLines[] = 'Use first person when the requested text is meant to be posted by the current user.';

        $outputRules = implode("\n", array(
            'Output rules:',
            '- Return only the requested content.',
            '- Do not add introductions such as "Självklart", "Här är", "Sure", "Here is", or similar.',
            '- Do not add closing remarks such as "Hoppas detta hjälper", "Hope this helps", or similar.',
            '- Do not explain what you are doing unless the user explicitly asks for an explanation.',
            '- Write in the same language as the user instruction.',
            '- If the user instruction explicitly asks for a specific language, use that requested language instead.',
        ));

        if ($persona !== '') {
            return implode("\n\n", array(
                $outputRules,
                implode("\n", $currentUserLines),
                'Mandatory writing persona for the current vBulletin user:',
                $persona,
                'Persona rule:',
                'Apply the mandatory writing persona above to the answer unless the user explicitly asks you to ignore or override it.',
                'Forum/editor context:',
                $context,
            ));
        }

        return implode("\n\n", array(
            $outputRules,
            implode("\n", $currentUserLines),
            'Forum/editor context:',
            $context,
        ));
    }

    private function getAiProvider($options)
    {
        $provider = $this->getOptionString($options, 'tornis_tools_ai_provider', 'tornevall_tools');
        $provider = strtolower(trim(str_replace(array('-', ' '), '_', $provider)));

        if (in_array($provider, array('openai', 'direct_openai', 'openai_direct'), true)) {
            return 'openai';
        }

        return 'tornevall_tools';
    }

    private function isSourceSensitiveRequest($text)
    {
        $text = $this->lower((string) $text);

        $needles = array(
            'källa', 'källor', 'källhänvisning', 'källhänvisningar', 'referens', 'referenser',
            'citat', 'citera', 'länk', 'länkar', 'url', 'artikel', 'artiklar', 'fakta', 'faktakoll',
            'verifiera', 'bekräfta', 'belägg', 'source', 'sources', 'citation', 'citations',
            'reference', 'references', 'link', 'links', 'article', 'articles', 'fact check',
            'fact-check', 'verify', 'verified', 'evidence',
        );

        foreach ($needles as $needle) {
            if (strpos($text, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function localFailure($message)
    {
        return array(
            'ok' => true,
            'gateway_ok' => false,
            'error' => $message,
            'text' => $message,
        );
    }

    private function apiSafeError($prefix, Throwable $e)
    {
        $message = $prefix . ': ' . $e->getMessage();

        return array(
            'ok' => true,
            'gateway_ok' => false,
            'error' => $message,
            'text' => $message,
            'exception_class' => get_class($e),
            'exception_file' => $e->getFile(),
            'exception_line' => $e->getLine(),
        );
    }

    private function getCurrentUserId()
    {
        try {
            $session = vB::getCurrentSession();

            if ($session && method_exists($session, 'get')) {
                $userid = (int) $session->get('userid');

                if ($userid > 0) {
                    return $userid;
                }
            }
        } catch (Throwable $e) {
            error_log('vbulletinbytools could not get current user id from session: ' . $e->getMessage());
        }

        try {
            $userContext = vB::getUserContext();

            if ($userContext && method_exists($userContext, 'fetchUserId')) {
                $userid = (int) $userContext->fetchUserId();

                if ($userid > 0) {
                    return $userid;
                }
            }
        } catch (Throwable $e) {
            error_log('vbulletinbytools could not get current user id from user context: ' . $e->getMessage());
        }

        return 0;
    }

    private function getCurrentUserPersona($fieldId)
    {
        return $this->getCurrentUserFieldValue($fieldId);
    }

    private function getCurrentUserFieldValue($fieldId)
    {
        $userid = $this->getCurrentUserId();

        if ($userid <= 0) {
            return '';
        }

        $fieldId = (int) $fieldId;

        if ($fieldId <= 0) {
            return '';
        }

        $fieldName = 'field' . $fieldId;
        $value = $this->readUserFieldWithAssertorGetRow($userid, $fieldName);

        if ($value !== '') {
            return $value;
        }

        return $this->readUserFieldWithAssertorSelect($userid, $fieldName);
    }

    private function readUserFieldWithAssertorGetRow($userid, $fieldName)
    {
        foreach (array('vBForum:userfield', 'userfield') as $tableName) {
            try {
                $row = vB::getDbAssertor()->getRow($tableName, array('userid' => (int) $userid));

                if (is_array($row) && array_key_exists($fieldName, $row)) {
                    return trim((string) $row[$fieldName]);
                }
            } catch (Throwable $e) {
                error_log('vbulletinbytools getRow failed for ' . $tableName . '.' . $fieldName . ': ' . $e->getMessage());
            }
        }

        return '';
    }

    private function readUserFieldWithAssertorSelect($userid, $fieldName)
    {
        foreach (array('vBForum:userfield', 'userfield') as $tableName) {
            try {
                if (!class_exists('vB_dB_Query')) {
                    continue;
                }

                $rows = vB::getDbAssertor()->assertQuery($tableName, array(
                    vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
                    'userid' => (int) $userid,
                ));

                foreach ($rows as $row) {
                    if (is_array($row) && array_key_exists($fieldName, $row)) {
                        return trim((string) $row[$fieldName]);
                    }
                }
            } catch (Throwable $e) {
                error_log('vbulletinbytools assertQuery select failed for ' . $tableName . '.' . $fieldName . ': ' . $e->getMessage());
            }
        }

        return '';
    }

    private function getThreadContextFromNodeId($nodeid)
    {
        $nodeid = (int) $nodeid;

        if ($nodeid <= 0) {
            return '';
        }

        $nodes = $this->getThreadNodes($nodeid, 30);
        $parts = array();

        foreach ($nodes as $index => $node) {
            $postNodeId = !empty($node['nodeid']) ? (int) $node['nodeid'] : 0;

            if ($postNodeId <= 0) {
                continue;
            }

            $title = !empty($node['title']) ? trim((string) $node['title']) : '';
            $userid = !empty($node['userid']) ? (int) $node['userid'] : 0;
            $author = $this->getUsernameByUserId($userid);
            $text = $this->getNodeText($postNodeId);

            if ($text === '') {
                continue;
            }

            $entry = array('Post ' . ($index + 1) . ':');

            if ($title !== '') {
                $entry[] = 'Title: ' . $title;
            }

            if ($author !== '') {
                $entry[] = 'Author: ' . $author;
            } elseif ($userid > 0) {
                $entry[] = 'User ID: ' . $userid;
            }

            $entry[] = 'Node ID: ' . $postNodeId;
            $entry[] = 'Text:';
            $entry[] = $this->cleanContextText($text);
            $parts[] = implode("\n", $entry);
        }

        $context = implode("\n\n", $parts);

        if (strlen($context) > 18000) {
            $context = substr($context, 0, 18000) . "\n\n[Server-side thread context truncated]";
        }

        return $context;
    }

    private function getThreadNodes($nodeid, $maxPosts)
    {
        $nodesById = array();
        $root = $this->getNodeRow($nodeid);

        if (is_array($root) && !empty($root['nodeid'])) {
            $nodesById[(int) $root['nodeid']] = $root;
        }

        $starterId = (is_array($root) && !empty($root['starter'])) ? (int) $root['starter'] : (int) $nodeid;

        foreach (array(
            $this->getNodeRowsByField('starter', $starterId, $maxPosts),
            $this->getNodeRowsByField('parentid', $nodeid, $maxPosts),
        ) as $rows) {
            foreach ($rows as $row) {
                if (is_array($row) && !empty($row['nodeid'])) {
                    $nodesById[(int) $row['nodeid']] = $row;
                }
            }
        }

        $nodes = array_values($nodesById);

        usort($nodes, function ($a, $b) {
            $aDate = !empty($a['publishdate']) ? (int) $a['publishdate'] : 0;
            $bDate = !empty($b['publishdate']) ? (int) $b['publishdate'] : 0;

            if ($aDate === $bDate) {
                return ((int) $a['nodeid']) <=> ((int) $b['nodeid']);
            }

            return $aDate <=> $bDate;
        });

        return array_slice($nodes, 0, (int) $maxPosts);
    }

    private function getNodeRow($nodeid)
    {
        foreach (array('vBForum:node', 'node') as $tableName) {
            try {
                $row = vB::getDbAssertor()->getRow($tableName, array('nodeid' => (int) $nodeid));

                if (is_array($row) && !empty($row['nodeid'])) {
                    return $row;
                }
            } catch (Throwable $e) {
                error_log('vbulletinbytools getNodeRow failed for ' . $tableName . ': ' . $e->getMessage());
            }
        }

        return array();
    }

    private function getNodeRowsByField($fieldName, $value, $limit)
    {
        if (!in_array($fieldName, array('starter', 'parentid', 'nodeid'), true)) {
            return array();
        }

        foreach (array('vBForum:node', 'node') as $tableName) {
            try {
                if (!class_exists('vB_dB_Query')) {
                    continue;
                }

                $rows = vB::getDbAssertor()->assertQuery($tableName, array(
                    vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
                    $fieldName => (int) $value,
                ));

                $result = array();

                foreach ($rows as $row) {
                    if (is_array($row)) {
                        $result[] = $row;

                        if (count($result) >= (int) $limit) {
                            break;
                        }
                    }
                }

                if (!empty($result)) {
                    return $result;
                }
            } catch (Throwable $e) {
                error_log('vbulletinbytools getNodeRowsByField failed for ' . $tableName . '.' . $fieldName . ': ' . $e->getMessage());
            }
        }

        return array();
    }

    private function getNodeText($nodeid)
    {
        foreach (array('vBForum:text', 'text') as $tableName) {
            try {
                $row = vB::getDbAssertor()->getRow($tableName, array('nodeid' => (int) $nodeid));

                if (!is_array($row)) {
                    continue;
                }

                foreach (array('rawtext', 'pagetext', 'text') as $textField) {
                    if (!empty($row[$textField])) {
                        return trim(strip_tags((string) $row[$textField]));
                    }
                }
            } catch (Throwable $e) {
                error_log('vbulletinbytools getNodeText failed for ' . $tableName . ': ' . $e->getMessage());
            }
        }

        return '';
    }

    private function getUsernameByUserId($userid)
    {
        $userid = (int) $userid;

        if ($userid <= 0) {
            return '';
        }

        foreach (array('vBForum:user', 'user') as $tableName) {
            try {
                $row = vB::getDbAssertor()->getRow($tableName, array('userid' => $userid));

                if (is_array($row) && !empty($row['username'])) {
                    return trim((string) $row['username']);
                }
            } catch (Throwable $e) {
                error_log('vbulletinbytools getUsernameByUserId failed for ' . $tableName . ': ' . $e->getMessage());
            }
        }

        return '';
    }

    private function cleanContextText($text)
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));
    }

    private function getOptionInt($options, $name, $default)
    {
        if (!empty($options[$name])) {
            return (int) $options[$name];
        }

        return (int) $default;
    }

    private function getOptionString($options, $name, $default)
    {
        if (isset($options[$name]) && trim((string) $options[$name]) !== '') {
            return trim((string) $options[$name]);
        }

        return (string) $default;
    }

    private function lower($value)
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower((string) $value, 'UTF-8');
        }

        return strtolower((string) $value);
    }
}
