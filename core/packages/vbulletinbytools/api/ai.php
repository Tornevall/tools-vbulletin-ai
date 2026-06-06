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
        'privacyDebug',
    );

    public function test()
    {
        return array(
            'ok' => true,
            'method' => 'test',
            'message' => 'vbulletinbytools API test method works',
        );
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

    public function privacyDebug()
    {
        try {
            $options = vB::getDatastore()->getValue('options');
            $privacy = $this->resolvePrivacySettings($options);

            return array(
                'ok' => true,
                'userid' => $this->getCurrentUserId(),
                'username' => $this->getUsernameByUserId($this->getCurrentUserId()),
                'ai_enabled' => $privacy['ai_enabled'],
                'ai_enabled_source' => $privacy['ai_enabled_source'],
                'context_mode' => $privacy['context_mode'],
                'context_mode_source' => $privacy['context_mode_source'],
                'global_context_mode' => $privacy['global_context_mode'],
                'profile_ai_enabled_field_id' => $privacy['profile_ai_enabled_field_id'],
                'profile_ai_enabled_raw' => $privacy['profile_ai_enabled_raw'],
                'profile_context_mode_field_id' => $privacy['profile_context_mode_field_id'],
                'profile_context_mode_raw' => $privacy['profile_context_mode_raw'],
            );
        } catch (Throwable $e) {
            return $this->apiSafeError('privacyDebug failed', $e);
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
            return array(
                'ok' => true,
                'gateway_ok' => false,
                'error' => 'Prompt is required.',
                'text' => 'Prompt is required.',
            );
        }

        $options = vB::getDatastore()->getValue('options');

        if (empty($options['tornis_tools_ai_enabled'])) {
            return array(
                'ok' => true,
                'gateway_ok' => false,
                'error' => 'Tornevall Tools AI is disabled.',
                'text' => 'Tornevall Tools AI is disabled.',
            );
        }

        $privacy = $this->resolvePrivacySettings($options);

        if (!$privacy['ai_enabled']) {
            return array(
                'ok' => true,
                'gateway_ok' => false,
                'error' => 'AI is disabled in your profile.',
                'text' => 'AI is disabled in your profile.',
                'vbulletin' => array(
                    'userid' => $this->getCurrentUserId(),
                    'ai_enabled' => false,
                    'ai_enabled_source' => $privacy['ai_enabled_source'],
                    'context_mode' => $privacy['context_mode'],
                    'context_mode_source' => $privacy['context_mode_source'],
                ),
            );
        }

        $token = '';
        if (!empty($options['tornis_tools_gpt_secret'])) {
            $token = trim((string) $options['tornis_tools_gpt_secret']);
        }

        if ($token === '') {
            return array(
                'ok' => true,
                'gateway_ok' => false,
                'error' => 'Tornevall Tools API token is missing.',
                'text' => 'Tornevall Tools API token is missing.',
            );
        }

        $baseUrl = 'https://tools.tornevall.net';
        if (!empty($options['tornis_tools_api_base_url'])) {
            $baseUrl = rtrim((string) $options['tornis_tools_api_base_url'], '/');
        }

        $clientSlug = 'vbulletin_wysiwyg_assistant';
        if (!empty($options['tornis_tools_ai_client_slug'])) {
            $clientSlug = trim((string) $options['tornis_tools_ai_client_slug']);
        }

        $personaFieldId = $this->getOptionInt($options, 'tornis_tools_gpt_persona_field', 0);
        $userid = $this->getCurrentUserId();
        $username = $this->getUsernameByUserId($userid);
        $persona = '';

        if ($personaFieldId > 0) {
            $persona = $this->getCurrentUserPersona($personaFieldId);
        }

        $threadContext = '';
        $contextSentToGateway = true;

        if ($privacy['context_mode'] === 'request_only') {
            $context = 'Forum/editor/thread context omitted because the resolved AI context privacy mode is request_only.';
            $contextSentToGateway = false;
        } else {
            if ($nodeid > 0) {
                $threadContext = $this->getThreadContextFromNodeId($nodeid);
            }

            if ($threadContext !== '') {
                $context = trim($context . "\n\nServer-side vBulletin thread context:\n" . $threadContext);
            }
        }

        $sourceSensitiveRequest = $this->isSourceSensitiveRequest($prompt . "\n" . ($contextSentToGateway ? $context : ''));

        if ($sourceSensitiveRequest) {
            $context = trim($context . "\n\nSource verification rules:\n" . implode("\n", array(
                '- This request asks for sources, references, citations, links, fact checking or verifiable factual claims.',
                '- Use web search to verify source URLs and citation targets before presenting them.',
                '- Do not invent sources, citation labels, article titles or URLs.',
                '- If no verified source is found, say that no verified source was found instead of creating a broken reference.',
                '- Prefer direct, canonical, reachable source URLs when sources are requested.',
            )));
        }

        $fullContext = $this->buildFullContext($context, $persona, $personaFieldId, $userid, $username);

        $useWebSearch = !empty($options['tornis_tools_ai_web_search_enabled']) || $sourceSensitiveRequest;
        $webSearchRequired = !empty($options['tornis_tools_ai_web_search_required']) || $sourceSensitiveRequest;

        require_once(DIR . '/packages/vbulletinbytools/library/TornevallTools/OpenAiClient.php');

        if (!class_exists('TornevallTools_OpenAiClient')) {
            return array(
                'ok' => true,
                'gateway_ok' => false,
                'error' => 'TornevallTools_OpenAiClient was not loaded.',
                'text' => 'TornevallTools_OpenAiClient was not loaded. Check package file path.',
            );
        }

        $client = new TornevallTools_OpenAiClient($baseUrl, $token);

        return $client->respond(array(
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
                'ai_enabled' => $privacy['ai_enabled'],
                'ai_enabled_source' => $privacy['ai_enabled_source'],
                'context_mode' => $privacy['context_mode'],
                'context_mode_source' => $privacy['context_mode_source'],
                'context_sent_to_gateway' => $contextSentToGateway,
                'has_thread_context' => ($threadContext !== ''),
                'thread_context_length' => strlen($threadContext),
                'persona_field_id' => $personaFieldId,
                'persona_field_name' => ($personaFieldId > 0 ? 'field' . $personaFieldId : ''),
                'has_persona' => ($persona !== ''),
                'source_sensitive_request' => $sourceSensitiveRequest,
                'use_web_search' => $useWebSearch,
                'web_search_required' => $webSearchRequired,
            ),
        ));
    }

    private function buildFullContext($context, $persona, $personaFieldId, $userid, $username)
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

    private function resolvePrivacySettings($options)
    {
        $globalContextMode = $this->normalizeContextMode($this->getOptionString($options, 'tornis_tools_ai_context_mode', 'full'));
        $contextMode = $globalContextMode;
        $contextModeSource = 'admincp';

        $profileContextModeFieldId = $this->getOptionInt($options, 'tornis_tools_ai_profile_context_mode_field', 0);
        $profileContextModeRaw = '';

        if ($profileContextModeFieldId > 0) {
            $profileContextModeRaw = $this->getCurrentUserFieldValue($profileContextModeFieldId);
            $profileContextMode = $this->normalizeContextMode($profileContextModeRaw, '');

            if ($profileContextMode !== '') {
                $contextMode = $profileContextMode;
                $contextModeSource = 'profile';
            }
        }

        $aiEnabled = true;
        $aiEnabledSource = 'admincp';
        $profileAiEnabledFieldId = $this->getOptionInt($options, 'tornis_tools_ai_profile_enabled_field', 0);
        $profileAiEnabledRaw = '';

        if ($profileAiEnabledFieldId > 0) {
            $profileAiEnabledRaw = $this->getCurrentUserFieldValue($profileAiEnabledFieldId);
            $profileAiEnabled = $this->normalizeAiEnabled($profileAiEnabledRaw);

            if ($profileAiEnabled !== null) {
                $aiEnabled = $profileAiEnabled;
                $aiEnabledSource = 'profile';
            }
        }

        return array(
            'ai_enabled' => $aiEnabled,
            'ai_enabled_source' => $aiEnabledSource,
            'context_mode' => $contextMode,
            'context_mode_source' => $contextModeSource,
            'global_context_mode' => $globalContextMode,
            'profile_ai_enabled_field_id' => $profileAiEnabledFieldId,
            'profile_ai_enabled_raw' => $profileAiEnabledRaw,
            'profile_context_mode_field_id' => $profileContextModeFieldId,
            'profile_context_mode_raw' => $profileContextModeRaw,
        );
    }

    private function normalizeContextMode($value, $default = 'full')
    {
        $value = $this->lower((string) $value);
        $value = trim(str_replace(array('-', ' '), '_', $value));

        if ($value === '') {
            return $default;
        }

        $requestOnlyValues = array(
            'request_only',
            'prompt_only',
            'instruction_only',
            'only_request',
            'only_prompt',
            'no_context',
            'minimal',
            'none',
            'bara_önskemål',
            'bara_onskemal',
            'endast_önskemål',
            'endast_onskemal',
            'ingen_context',
            'ingen_kontext',
        );

        if (in_array($value, $requestOnlyValues, true)) {
            return 'request_only';
        }

        $fullValues = array(
            'full',
            'full_context',
            'thread_context',
            'complete',
            'all',
            'hela_contexten',
            'hela_kontexten',
            'fullständig',
            'fullstandig',
        );

        if (in_array($value, $fullValues, true)) {
            return 'full';
        }

        return $default;
    }

    private function normalizeAiEnabled($value)
    {
        $value = $this->lower((string) $value);
        $value = trim(str_replace(array('-', ' '), '_', $value));

        if ($value === '') {
            return null;
        }

        $disabledValues = array(
            '0',
            'false',
            'no',
            'nej',
            'off',
            'disabled',
            'disable',
            'av',
            'avstängd',
            'avstangd',
            'stäng_av',
            'stang_av',
            'ai_off',
            'ai_disabled',
        );

        if (in_array($value, $disabledValues, true)) {
            return false;
        }

        $enabledValues = array(
            '1',
            'true',
            'yes',
            'ja',
            'on',
            'enabled',
            'enable',
            'på',
            'pa',
            'aktiv',
            'active',
            'ai_on',
            'ai_enabled',
        );

        if (in_array($value, $enabledValues, true)) {
            return true;
        }

        return null;
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

        $value = $this->readUserFieldWithAssertorSelect($userid, $fieldName);

        if ($value !== '') {
            return $value;
        }

        return '';
    }

    private function readUserFieldWithAssertorGetRow($userid, $fieldName)
    {
        $tablesToTry = array(
            'vBForum:userfield',
            'userfield',
        );

        foreach ($tablesToTry as $tableName) {
            try {
                $row = vB::getDbAssertor()->getRow($tableName, array(
                    'userid' => (int) $userid,
                ));

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
        $tablesToTry = array(
            'vBForum:userfield',
            'userfield',
        );

        foreach ($tablesToTry as $tableName) {
            try {
                if (!class_exists('vB_dB_Query')) {
                    continue;
                }

                $rows = vB::getDbAssertor()->assertQuery($tableName, array(
                    vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
                    'userid' => (int) $userid,
                ));

                if (!$rows) {
                    continue;
                }

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

        $maxPosts = 30;
        $maxChars = 18000;
        $nodes = $this->getThreadNodes($nodeid, $maxPosts);

        if (empty($nodes)) {
            return '';
        }

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

        if (strlen($context) > $maxChars) {
            $context = substr($context, 0, $maxChars) . "\n\n[Server-side thread context truncated]";
        }

        return $context;
    }

    private function getThreadNodes($nodeid, $maxPosts)
    {
        $nodeid = (int) $nodeid;
        $maxPosts = (int) $maxPosts;

        if ($nodeid <= 0) {
            return array();
        }

        if ($maxPosts <= 0) {
            $maxPosts = 30;
        }

        $nodesById = array();
        $root = $this->getNodeRow($nodeid);

        if (is_array($root) && !empty($root['nodeid'])) {
            $nodesById[(int) $root['nodeid']] = $root;
        }

        $starterId = $nodeid;

        if (is_array($root) && !empty($root['starter'])) {
            $starterId = (int) $root['starter'];
        }

        $replySets = array(
            $this->getNodeRowsByField('starter', $starterId, $maxPosts),
            $this->getNodeRowsByField('parentid', $nodeid, $maxPosts),
        );

        foreach ($replySets as $rows) {
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

        return array_slice($nodes, 0, $maxPosts);
    }

    private function getNodeRow($nodeid)
    {
        $tablesToTry = array(
            'vBForum:node',
            'node',
        );

        foreach ($tablesToTry as $tableName) {
            try {
                $row = vB::getDbAssertor()->getRow($tableName, array(
                    'nodeid' => (int) $nodeid,
                ));

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
        $allowedFields = array(
            'starter',
            'parentid',
            'nodeid',
        );

        if (!in_array($fieldName, $allowedFields, true)) {
            return array();
        }

        $value = (int) $value;
        $limit = (int) $limit;

        if ($value <= 0) {
            return array();
        }

        $tablesToTry = array(
            'vBForum:node',
            'node',
        );

        foreach ($tablesToTry as $tableName) {
            try {
                if (!class_exists('vB_dB_Query')) {
                    continue;
                }

                $rows = vB::getDbAssertor()->assertQuery($tableName, array(
                    vB_dB_Query::TYPE_KEY => vB_dB_Query::QUERY_SELECT,
                    $fieldName => $value,
                ));

                if (!$rows) {
                    continue;
                }

                $result = array();

                foreach ($rows as $row) {
                    if (is_array($row)) {
                        $result[] = $row;

                        if (count($result) >= $limit) {
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
        $nodeid = (int) $nodeid;

        if ($nodeid <= 0) {
            return '';
        }

        $tablesToTry = array(
            'vBForum:text',
            'text',
        );

        foreach ($tablesToTry as $tableName) {
            try {
                $row = vB::getDbAssertor()->getRow($tableName, array(
                    'nodeid' => $nodeid,
                ));

                if (!is_array($row)) {
                    continue;
                }

                $textFields = array(
                    'rawtext',
                    'pagetext',
                    'text',
                );

                foreach ($textFields as $textField) {
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

        $tablesToTry = array(
            'vBForum:user',
            'user',
        );

        foreach ($tablesToTry as $tableName) {
            try {
                $row = vB::getDbAssertor()->getRow($tableName, array(
                    'userid' => $userid,
                ));

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
        $value = (string) $value;

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }

        return strtolower($value);
    }
}
