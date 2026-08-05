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

    public function privacyDebug($nodeid = 0)
    {
        try {
            $options = vB::getDatastore()->getValue('options');
            $privacy = $this->resolvePrivacySettings($options);
            $userid = $this->getCurrentUserId();
            $nodeid = (int) $nodeid;
            $privateDecision = $this->getPrivateNodeContextDecision($nodeid, $options);
            $stats = $this->createContextStats();
            $threadContext = '';

            if ($nodeid > 0 && !$privateDecision['blocked']) {
                $threadContext = $this->getThreadContextFromNodeId($nodeid, $privacy, $stats);
            }

            return array(
                'ok' => true,
                'userid' => $userid,
                'username' => $this->getUsernameByUserId($userid),
                'provider' => $this->getAiProvider($options),
                'ai_enabled' => $privacy['ai_enabled'],
                'ai_enabled_source' => $privacy['ai_enabled_source'],
                'context_mode' => $privacy['context_mode'],
                'context_mode_source' => $privacy['context_mode_source'],
                'global_context_mode' => $privacy['global_context_mode'],
                'context_consent_mode' => $privacy['context_consent_mode'],
                'context_consent_mode_source' => $privacy['context_consent_mode_source'],
                'current_user_consent' => $privacy['current_user_consent'],
                'current_user_consent_raw' => $privacy['current_user_consent_raw'],
                'disable_context_in_private_nodes' => $privacy['disable_context_in_private_nodes'],
                'private_node_context_blocked' => $privateDecision['blocked'],
                'context_blocked_reason' => $privateDecision['reason'],
                'private_node_detection' => $privateDecision,
                'profile_ai_enabled_field_id' => $privacy['profile_ai_enabled_field_id'],
                'profile_ai_enabled_raw' => $privacy['profile_ai_enabled_raw'],
                'profile_context_mode_field_id' => $privacy['profile_context_mode_field_id'],
                'profile_context_mode_raw' => $privacy['profile_context_mode_raw'],
                'profile_context_consent_field_id' => $privacy['profile_context_consent_field_id'],
                'has_thread_context' => ($threadContext !== ''),
                'thread_context_length' => strlen($threadContext),
                'thread_context_preview' => substr($threadContext, 0, 1200),
                'context_stats' => $stats,
            );
        } catch (Throwable $e) {
            return $this->apiSafeError('privacyDebug failed', $e);
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
            $options = vB::getDatastore()->getValue('options');
            $privacy = $this->resolvePrivacySettings($options);
            $nodeid = (int) $nodeid;
            $threadContext = '';
            $stats = $this->createContextStats();
            $privateDecision = $this->getPrivateNodeContextDecision($nodeid, $options);

            if ($nodeid > 0 && !$privateDecision['blocked']) {
                $threadContext = $this->getThreadContextFromNodeId($nodeid, $privacy, $stats);
            }

            return array(
                'ok' => true,
                'nodeid' => $nodeid,
                'has_thread_context' => ($threadContext !== ''),
                'thread_context_length' => strlen($threadContext),
                'thread_context_preview' => substr($threadContext, 0, 1200),
                'private_node_context_blocked' => $privateDecision['blocked'],
                'context_blocked_reason' => $privateDecision['reason'],
                'context_consent_mode' => $privacy['context_consent_mode'],
                'context_stats' => $stats,
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
        $contextSentToGateway = true;
        $contextBlockedReason = '';
        $contextStats = $this->createContextStats();
        $privateDecision = $this->getPrivateNodeContextDecision($nodeid, $options);

        if ($privacy['context_mode'] === 'request_only') {
            $context = 'Forum/editor/thread context omitted because the resolved AI context privacy mode is request_only.';
            $contextSentToGateway = false;
            $contextBlockedReason = 'request_only';
        } elseif ($privateDecision['blocked']) {
            $context = 'Forum/editor/thread context omitted because private or restricted node protection blocked context.';
            $contextSentToGateway = false;
            $contextBlockedReason = $privateDecision['reason'];
            $contextStats['context_posts_excluded_private_node'] = $contextStats['context_posts_total'];
        } else {
            if ($privacy['context_consent_mode'] !== 'disabled') {
                $context = 'Client-side visible forum context omitted because consent-aware server-side filtering is active.';
            }

            if ($nodeid > 0) {
                $threadContext = $this->getThreadContextFromNodeId($nodeid, $privacy, $contextStats);
            }

            if ($threadContext !== '') {
                $context = trim($context . "\n\nConsent-filtered server-side vBulletin thread context:\n" . $threadContext);
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
                'ai_enabled' => $privacy['ai_enabled'],
                'ai_enabled_source' => $privacy['ai_enabled_source'],
                'context_mode' => $privacy['context_mode'],
                'context_mode_source' => $privacy['context_mode_source'],
                'context_consent_mode' => $privacy['context_consent_mode'],
                'context_consent_mode_source' => $privacy['context_consent_mode_source'],
                'current_user_consent' => $privacy['current_user_consent'],
                'context_sent_to_gateway' => $contextSentToGateway,
                'context_blocked_reason' => $contextBlockedReason,
                'private_node_context_blocked' => $privateDecision['blocked'],
                'private_node_detection' => $privateDecision,
                'has_thread_context' => ($threadContext !== ''),
                'thread_context_length' => strlen($threadContext),
                'context_stats' => $contextStats,
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

        $contextConsentMode = $this->normalizeConsentMode($this->getOptionString($options, 'tornis_tools_ai_context_consent_mode', 'require_opt_in'));
        $profileContextConsentFieldId = $this->getOptionInt($options, 'tornis_tools_ai_profile_context_consent_field', 0);
        $currentUserConsentRaw = '';
        $currentUserConsent = null;

        if ($profileContextConsentFieldId > 0) {
            $currentUserConsentRaw = $this->getCurrentUserFieldValue($profileContextConsentFieldId);
            $currentUserConsent = $this->normalizeConsentValue($currentUserConsentRaw);
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
            'context_consent_mode' => $contextConsentMode,
            'context_consent_mode_source' => 'admincp',
            'profile_context_consent_field_id' => $profileContextConsentFieldId,
            'current_user_consent_raw' => $currentUserConsentRaw,
            'current_user_consent' => $currentUserConsent,
            'disable_context_in_private_nodes' => $this->getOptionBool($options, 'tornis_tools_ai_disable_context_in_private_nodes', true),
        );
    }

    private function normalizeContextMode($value, $default = 'full')
    {
        $value = $this->lower((string) $value);
        $value = trim(str_replace(array('-', ' '), '_', $value));

        if ($value === '') {
            return $default;
        }

        if (in_array($value, array(
            'request_only', 'prompt_only', 'instruction_only', 'only_request', 'only_prompt',
            'no_context', 'minimal', 'none', 'bara_önskemål', 'bara_onskemal',
            'endast_önskemål', 'endast_onskemal', 'ingen_context', 'ingen_kontext',
        ), true)) {
            return 'request_only';
        }

        if (in_array($value, array(
            'full', 'full_context', 'thread_context', 'complete', 'all',
            'hela_contexten', 'hela_kontexten', 'fullständig', 'fullstandig',
        ), true)) {
            return 'full';
        }

        return $default;
    }

    private function normalizeConsentMode($value)
    {
        $value = $this->lower((string) $value);
        $value = trim(str_replace(array('-', ' '), '_', $value));

        if (in_array($value, array('allow_unless_opt_out', 'opt_out', 'unless_opt_out'), true)) {
            return 'allow_unless_opt_out';
        }

        if (in_array($value, array('disabled', 'disable', 'off', 'none', 'no_filter'), true)) {
            return 'disabled';
        }

        return 'require_opt_in';
    }

    private function normalizeConsentValue($value)
    {
        $value = $this->lower((string) $value);
        $value = trim(str_replace(array('-', ' '), '_', $value));

        if ($value === '') {
            return null;
        }

        if (in_array($value, array(
            '1', 'true', 'yes', 'ja', 'on', 'allow', 'allowed', 'consent', 'opt_in',
            'ai_context_allowed', 'context_allowed', 'godkänn', 'godkann', 'medgivande',
        ), true)) {
            return 'opt_in';
        }

        if (in_array($value, array(
            '0', 'false', 'no', 'nej', 'off', 'deny', 'denied', 'no_consent', 'opt_out',
            'ai_context_denied', 'context_denied', 'avböj', 'avboj', 'inget_medgivande',
        ), true)) {
            return 'opt_out';
        }

        return null;
    }

    private function normalizeAiEnabled($value)
    {
        $value = $this->lower((string) $value);
        $value = trim(str_replace(array('-', ' '), '_', $value));

        if ($value === '') {
            return null;
        }

        if (in_array($value, array(
            '0', 'false', 'no', 'nej', 'off', 'disabled', 'disable', 'av',
            'avstängd', 'avstangd', 'stäng_av', 'stang_av', 'ai_off', 'ai_disabled',
        ), true)) {
            return false;
        }

        if (in_array($value, array(
            '1', 'true', 'yes', 'ja', 'on', 'enabled', 'enable', 'på', 'pa',
            'aktiv', 'active', 'ai_on', 'ai_enabled',
        ), true)) {
            return true;
        }

        return null;
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

    private function createContextStats()
    {
        return array(
            'context_posts_total' => 0,
            'context_posts_included' => 0,
            'context_posts_excluded_missing_consent' => 0,
            'context_posts_excluded_opt_out' => 0,
            'context_posts_excluded_private_node' => 0,
            'context_posts_excluded_unknown_author' => 0,
            'context_posts_excluded_hidden_or_moderated' => 0,
            'context_posts_excluded_empty_text' => 0,
            'context_quotes_stripped' => 0,
        );
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
        return $this->getUserFieldValue($this->getCurrentUserId(), $fieldId);
    }

    private function getUserFieldValue($userid, $fieldId)
    {
        $userid = (int) $userid;
        $fieldId = (int) $fieldId;

        if ($userid <= 0 || $fieldId <= 0) {
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

    private function getThreadContextFromNodeId($nodeid, array $privacy, array &$stats)
    {
        $nodeid = (int) $nodeid;

        if ($nodeid <= 0) {
            return '';
        }

        $nodes = $this->getThreadNodes($nodeid, 30);
        $parts = array();

        foreach ($nodes as $index => $node) {
            $stats['context_posts_total']++;

            if ($this->isNodeHiddenOrModerated($node)) {
                $stats['context_posts_excluded_hidden_or_moderated']++;
                continue;
            }

            $postNodeId = !empty($node['nodeid']) ? (int) $node['nodeid'] : 0;

            if ($postNodeId <= 0) {
                $stats['context_posts_excluded_hidden_or_moderated']++;
                continue;
            }

            $userid = !empty($node['userid']) ? (int) $node['userid'] : 0;
            $authorAllowed = $this->isAuthorContextAllowed($userid, $privacy);

            if (!$authorAllowed['allowed']) {
                if ($authorAllowed['reason'] === 'unknown_author') {
                    $stats['context_posts_excluded_unknown_author']++;
                } elseif ($authorAllowed['reason'] === 'opt_out') {
                    $stats['context_posts_excluded_opt_out']++;
                } else {
                    $stats['context_posts_excluded_missing_consent']++;
                }

                continue;
            }

            $title = !empty($node['title']) ? trim((string) $node['title']) : '';
            $author = $this->getUsernameByUserId($userid);
            $text = $this->getNodeText($postNodeId, $stats);

            if ($text === '') {
                $stats['context_posts_excluded_empty_text']++;
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
            $stats['context_posts_included']++;
        }

        $context = implode("\n\n", $parts);

        if (strlen($context) > 18000) {
            $context = substr($context, 0, 18000) . "\n\n[Server-side thread context truncated]";
        }

        return $context;
    }

    private function isAuthorContextAllowed($userid, array $privacy)
    {
        $userid = (int) $userid;
        $mode = $privacy['context_consent_mode'];

        if ($mode === 'disabled') {
            return array('allowed' => true, 'reason' => 'consent_filter_disabled');
        }

        if ($userid <= 0) {
            return array('allowed' => false, 'reason' => 'unknown_author');
        }

        $fieldId = (int) $privacy['profile_context_consent_field_id'];
        $raw = '';
        $consent = null;

        if ($fieldId > 0) {
            $raw = $this->getUserFieldValue($userid, $fieldId);
            $consent = $this->normalizeConsentValue($raw);
        }

        if ($mode === 'require_opt_in') {
            if ($consent === 'opt_in') {
                return array('allowed' => true, 'reason' => 'opt_in', 'raw' => $raw);
            }

            return array('allowed' => false, 'reason' => 'missing_consent', 'raw' => $raw);
        }

        if ($mode === 'allow_unless_opt_out') {
            if ($consent === 'opt_out') {
                return array('allowed' => false, 'reason' => 'opt_out', 'raw' => $raw);
            }

            return array('allowed' => true, 'reason' => 'not_opted_out', 'raw' => $raw);
        }

        return array('allowed' => false, 'reason' => 'missing_consent', 'raw' => $raw);
    }

    private function getPrivateNodeContextDecision($nodeid, $options)
    {
        $nodeid = (int) $nodeid;
        $enabled = $this->getOptionBool($options, 'tornis_tools_ai_disable_context_in_private_nodes', true);

        if (!$enabled) {
            return array(
                'blocked' => false,
                'reason' => 'private_node_protection_disabled',
                'nodeid' => $nodeid,
            );
        }

        if ($nodeid <= 0) {
            return array(
                'blocked' => false,
                'reason' => 'no_nodeid',
                'nodeid' => $nodeid,
            );
        }

        $node = $this->getNodeRow($nodeid);

        if (empty($node)) {
            return array(
                'blocked' => true,
                'reason' => 'private_status_unknown_node_not_found',
                'nodeid' => $nodeid,
            );
        }

        $visited = array();
        $current = $node;

        for ($depth = 0; $depth < 10; $depth++) {
            if (empty($current['nodeid'])) {
                break;
            }

            $currentNodeId = (int) $current['nodeid'];

            if (isset($visited[$currentNodeId])) {
                break;
            }

            $visited[$currentNodeId] = true;

            if ($this->isNodePrivateLike($current)) {
                return array(
                    'blocked' => true,
                    'reason' => 'private_node_detected',
                    'nodeid' => $nodeid,
                    'matched_nodeid' => $currentNodeId,
                );
            }

            if (empty($current['parentid'])) {
                break;
            }

            $parent = $this->getNodeRow((int) $current['parentid']);

            if (empty($parent)) {
                return array(
                    'blocked' => true,
                    'reason' => 'private_status_unknown_parent_not_found',
                    'nodeid' => $nodeid,
                    'matched_nodeid' => $currentNodeId,
                );
            }

            $current = $parent;
        }

        return array(
            'blocked' => false,
            'reason' => 'no_private_signal_detected',
            'nodeid' => $nodeid,
        );
    }

    private function isNodePrivateLike(array $node)
    {
        foreach (array('private', 'isprivate', 'protected', 'isprotected', 'restricted', 'isrestricted') as $field) {
            if (isset($node[$field]) && (int) $node[$field] > 0) {
                return true;
            }
        }

        foreach (array('public', 'ispublic') as $field) {
            if (isset($node[$field]) && (string) $node[$field] === '0') {
                return true;
            }
        }

        foreach (array('nodeoptions', 'options') as $field) {
            if (isset($node[$field]) && is_string($node[$field])) {
                $value = $this->lower($node[$field]);

                if (strpos($value, 'private') !== false || strpos($value, 'restricted') !== false || strpos($value, 'protected') !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isNodeHiddenOrModerated(array $node)
    {
        foreach (array('showpublished', 'showapproved', 'approved', 'visible') as $field) {
            if (isset($node[$field]) && (string) $node[$field] === '0') {
                return true;
            }
        }

        foreach (array('deleted', 'softdeleted', 'isdeleted', 'unpublished', 'moderated') as $field) {
            if (isset($node[$field]) && (int) $node[$field] > 0) {
                return true;
            }
        }

        return false;
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

    private function getNodeText($nodeid, array &$stats)
    {
        foreach (array('vBForum:text', 'text') as $tableName) {
            try {
                $row = vB::getDbAssertor()->getRow($tableName, array('nodeid' => (int) $nodeid));

                if (!is_array($row)) {
                    continue;
                }

                foreach (array('rawtext', 'pagetext', 'text') as $textField) {
                    if (!empty($row[$textField])) {
                        $raw = (string) $row[$textField];
                        $withoutQuotes = $this->stripQuotedContent($raw, $stats);
                        return trim(strip_tags($withoutQuotes));
                    }
                }
            } catch (Throwable $e) {
                error_log('vbulletinbytools getNodeText failed for ' . $tableName . ': ' . $e->getMessage());
            }
        }

        return '';
    }

    private function stripQuotedContent($text, array &$stats)
    {
        $text = (string) $text;
        $before = $text;

        for ($i = 0; $i < 5; $i++) {
            $next = preg_replace('/\[quote(?:=[^\]]*)?\].*?\[\/quote\]/is', '', $text);

            if ($next === $text) {
                break;
            }

            $text = $next;
        }

        $text = preg_replace('/<blockquote\b[^>]*>.*?<\/blockquote>/is', '', $text);
        $text = preg_replace('/<div\b[^>]*(?:quote|bbcode_quote)[^>]*>.*?<\/div>/is', '', $text);

        if ($text !== $before) {
            $stats['context_quotes_stripped']++;
        }

        return $text;
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

    private function getOptionBool($options, $name, $default)
    {
        if (!isset($options[$name]) || trim((string) $options[$name]) === '') {
            return (bool) $default;
        }

        $value = $this->lower((string) $options[$name]);
        $value = trim($value);

        if (in_array($value, array('1', 'true', 'yes', 'ja', 'on', 'enabled'), true)) {
            return true;
        }

        if (in_array($value, array('0', 'false', 'no', 'nej', 'off', 'disabled'), true)) {
            return false;
        }

        return (bool) $default;
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
