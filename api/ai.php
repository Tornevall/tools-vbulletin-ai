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
        $options = vB::getDatastore()->getValue('options');

        $personaFieldId = 0;
        if (!empty($options['tornis_tools_gpt_persona_field'])) {
            $personaFieldId = (int) $options['tornis_tools_gpt_persona_field'];
        }

        $userid = $this->getCurrentUserId();
        $persona = '';

        if ($personaFieldId > 0) {
            $persona = $this->getCurrentUserPersona($personaFieldId);
        }

        return array(
            'ok' => true,
            'userid' => $userid,
            'persona_field_id' => $personaFieldId,
            'persona_field_name' => ($personaFieldId > 0 ? 'field' . $personaFieldId : ''),
            'has_persona' => ($persona !== ''),
            'persona_length' => strlen($persona),
        );
    }

    public function respond($context = '', $prompt = '', $language = 'sv')
    {
        $context = trim((string) $context);
        $prompt = trim((string) $prompt);
        $language = trim((string) $language);

        if ($prompt === '') {
            throw new vB_Exception_Api('Prompt is required.');
        }

        $options = vB::getDatastore()->getValue('options');

        if (empty($options['tornis_tools_ai_enabled'])) {
            throw new vB_Exception_Api('Tornevall Tools AI is disabled.');
        }

        $token = '';
        if (!empty($options['tornis_tools_gpt_secret'])) {
            $token = trim((string) $options['tornis_tools_gpt_secret']);
        }

        if ($token === '') {
            throw new vB_Exception_Api('Tornevall Tools API token is missing.');
        }

        $baseUrl = 'https://tools.tornevall.net';
        if (!empty($options['tornis_tools_api_base_url'])) {
            $baseUrl = rtrim((string) $options['tornis_tools_api_base_url'], '/');
        }

        $clientSlug = 'vbulletin_wysiwyg_assistant';
        if (!empty($options['tornis_tools_ai_client_slug'])) {
            $clientSlug = trim((string) $options['tornis_tools_ai_client_slug']);
        }

        $personaFieldId = 0;
        if (!empty($options['tornis_tools_gpt_persona_field'])) {
            $personaFieldId = (int) $options['tornis_tools_gpt_persona_field'];
        }

        $userid = $this->getCurrentUserId();
        $persona = '';

        if ($personaFieldId > 0) {
            $persona = $this->getCurrentUserPersona($personaFieldId);
        }

        $fullContext = $this->buildFullContext($context, $persona, $personaFieldId);

        require_once(DIR . '/packages/vbulletinbytools/library/TornevallTools/OpenAiClient.php');

        $client = new TornevallTools_OpenAiClient($baseUrl, $token);

        return $client->respond(array(
            'client_slug' => $clientSlug,
            'context' => $fullContext,
            'user_prompt' => $prompt,
            'response_language' => $language,
            'vbulletin' => array(
                'userid' => $userid,
                'persona_field_id' => $personaFieldId,
                'persona_field_name' => ($personaFieldId > 0 ? 'field' . $personaFieldId : ''),
                'has_persona' => ($persona !== ''),
            ),
        ));
    }

    private function buildFullContext($context, $persona, $personaFieldId)
    {
        $context = trim((string) $context);
        $persona = trim((string) $persona);

        $outputRules = implode("\n", array(
            "Output rules:",
            "- Return only the requested content.",
            "- Do not add introductions such as \"Självklart\", \"Här är\", \"Sure\", \"Here is\", or similar.",
            "- Do not add closing remarks such as \"Hoppas detta hjälper\", \"Hope this helps\", or similar.",
            "- Do not explain what you are doing unless the user explicitly asks for an explanation.",
            "- Write in the same language as the user's instruction.",
            "- If the user's instruction explicitly asks for a specific language, use that requested language instead.",
        ));

        if ($persona !== '') {
            return implode("\n\n", array(
                $outputRules,
                "Mandatory writing persona for the current vBulletin user:",
                $persona,
                "Persona rule:",
                "Apply the mandatory writing persona above to the answer unless the user explicitly asks you to ignore or override it.",
                "Forum/editor context:",
                $context,
            ));
        }

        return implode("\n\n", array(
            $outputRules,
            "Forum/editor context:",
            $context,
        ));
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
        $userid = $this->getCurrentUserId();

        if ($userid <= 0) {
            return '';
        }

        $fieldId = (int) $fieldId;

        if ($fieldId <= 0) {
            return '';
        }

        $fieldName = 'field' . $fieldId;

        $persona = $this->readUserFieldWithAssertorGetRow($userid, $fieldName);

        if ($persona !== '') {
            return $persona;
        }

        $persona = $this->readUserFieldWithAssertorSelect($userid, $fieldName);

        if ($persona !== '') {
            return $persona;
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
                error_log(
                    'vbulletinbytools getRow failed for ' . $tableName . '.' . $fieldName . ': ' . $e->getMessage()
                );
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
                error_log(
                    'vbulletinbytools assertQuery select failed for ' . $tableName . '.' . $fieldName . ': ' . $e->getMessage()
                );
            }
        }

        return '';
    }
}
