VBulletin by Tornevall Tools - vB6 package skeleton
==================================================

Target path:
/core/packages/vbulletin_by_tools/

Package layout:
/api/ai.php
/js/tornis_tools_ai.js
/js/tornis_tools_ai.css
/library/TornevallTools/OpenAiClient.php
/xml/cpnav_vbulletin_by_tools.xml
/hooks.php
/product.php
/readme.txt

Required vBulletin options/settings:
- tornis_tools_gpt_secret
- tornis_tools_api_base_url
- tornis_tools_ai_enabled
- tornis_tools_ai_client_slug

Recommended values:
tornis_tools_api_base_url = https://tools.tornevall.net
tornis_tools_ai_enabled = 1
tornis_tools_ai_client_slug = vbulletin_wysiwyg_assistant

Important:
- The token must only be read server-side.
- Do not expose tornis_tools_gpt_secret in JavaScript, templates or HTML.
- If the token has been visible in screenshots, rotate it before production use.

Testing:
1. Upload this package to /core/packages/vbulletin_by_tools/.
2. Make sure the settings above exist in AdminCP.
3. Ensure hooks.php is loaded by the product system.
4. Open a page with an editor and check whether tornis_tools_ai.js is loaded.
5. If the JS does not load, add the script/css manually in the relevant template while locating the exact vBulletin hook.

Endpoint:
POST /core/packages/vbulletin_by_tools/api/ai.php

Example payload:
{
  "prompt": "Förbättra texten.",
  "context": "Text from the editor",
  "language": "sv"
}
