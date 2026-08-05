# VBulletin by Tornevall Tools

AI assistant integration for vBulletin 6.

This package adds an "Ask AI for help" helper near the vBulletin editor. The browser never talks directly to OpenAI or Tornevall Tools. All AI requests go through a server-side vBulletin API class, which reads the private API token from vBulletin options and forwards the request to the selected AI provider.

## Required external service or provider

The integration can use either Tornevall Tools or a direct OpenAI connection, depending on the configured provider.

Tornevall Tools:

```text
https://tools.tornevall.net
```

Direct OpenAI:

```text
https://api.openai.com/v1
```

The browser must never contain either provider token. Tokens are stored server-side in vBulletin options.

Tornevall Tools token option:

```text
tornis_tools_gpt_secret
```

Direct OpenAI token option:

```text
tornis_tools_openai_api_key
```

The token value must be the raw token only. Do not include the `Bearer ` prefix.

Correct:

```text
eyJ...
```

Wrong:

```text
Bearer eyJ...
```

The default Tornevall Tools base URL is:

```text
https://tools.tornevall.net
```

and should normally be stored in:

```text
tornis_tools_api_base_url
```

The Tornevall Tools API route used by the PHP client is:

```text
/api/ai/internal/respond
```

## Current status

Working parts:

- vBulletin API route: `/ajax/api/vbulletinbytools:Ai/respond`
- Server-side Tornevall Tools bridge
- Optional direct OpenAI provider
- Separate credentials for Tornevall Tools and Direct OpenAI
- Bearer-token authentication through vBulletin options
- Editor helper button when frontend assets are loaded correctly
- AI response panel
- Insert answer into editor
- Markdown-to-BBCode conversion before insert
- vBulletin profile-field persona support
- Optional external web search support
- Thread/context support through visible frontend context and backend node context
- Gateway diagnostics for invalid upstream responses
- Privacy and consent settings for context handling

## Package name

The working package id is:

```text
vbulletinbytools
```

Do not use the older package id:

```text
vbulletin_by_tools
```

vBulletin package/API class resolution did not behave correctly with that id in this integration.

## Directory layout

Backend package files:

```text
core/packages/vbulletinbytools/
├── api/
│   └── ai.php
├── library/
│   └── TornevallTools/
│       ├── OpenAiClient.php
│       └── DirectOpenAiClient.php
├── js/
│   ├── vbulletinbytools_ai.js
│   └── vbulletinbytools_ai.css
├── xml/
│   ├── cpnav_vbulletinbytools.xml
│   └── product-vbulletinbytools.xml
├── hooks.php
├── product.php
└── readme.txt
```

Root frontend assets may also exist during development:

```text
js/vbulletinbytools_ai.js
js/vbulletinbytools_ai.css
```

On the tested vBulletin 6 install, the package path is the path that worked reliably:

```text
/core/packages/vbulletinbytools/js/vbulletinbytools_ai.js
/core/packages/vbulletinbytools/js/vbulletinbytools_ai.css
```

## API route

The frontend calls:

```text
POST /ajax/api/vbulletinbytools:Ai/respond
```

Example POST fields:

```text
context=Current editor/thread context
prompt=User instruction
language=sv
nodeid=123
```

The class handling this route is:

```php
class vbulletinbytools_Api_Ai extends vB_Api
```

located at:

```text
core/packages/vbulletinbytools/api/ai.php
```

## Required vBulletin options

The product XML creates the option group and settings in AdminCP. Reimporting or overwriting the product can affect phrase cache and template/hook state, so verify the settings and frontend include after every product import.

Important options:

```text
tornis_tools_ai_enabled
tornis_tools_ai_provider
tornis_tools_gpt_secret
tornis_tools_api_base_url
tornis_tools_openai_api_key
tornis_tools_openai_base_url
tornis_tools_openai_model
tornis_tools_openai_timeout
tornis_tools_ai_client_slug
tornis_tools_gpt_persona_field
tornis_tools_ai_web_search_enabled
tornis_tools_ai_web_search_required
tornis_tools_ai_context_mode
tornis_tools_ai_context_consent_mode
tornis_tools_ai_profile_context_mode_field
tornis_tools_ai_profile_enabled_field
tornis_tools_ai_profile_context_consent_field
tornis_tools_ai_disable_context_in_private_nodes
```

Recommended base values:

```text
tornis_tools_ai_enabled = 1
tornis_tools_ai_provider = tornevall_tools
tornis_tools_api_base_url = https://tools.tornevall.net
tornis_tools_ai_client_slug = vbulletin_wysiwyg_assistant
tornis_tools_gpt_persona_field = 0
tornis_tools_ai_web_search_enabled = 1
tornis_tools_ai_web_search_required = 0
tornis_tools_ai_context_mode = full
tornis_tools_ai_context_consent_mode = require_opt_in
tornis_tools_ai_disable_context_in_private_nodes = 1
```

Provider tokens:

```text
tornis_tools_gpt_secret = Tornevall Tools token, without Bearer prefix
tornis_tools_openai_api_key = OpenAI token, without Bearer prefix
```

Never expose these tokens in JavaScript, templates, HTML, screenshots, logs or public XML exports.

## Manual frontend installation

This product still requires manual frontend installation work unless the product-level hook injection is confirmed to work on the target vBulletin installation.

The AI button only appears if the frontend JavaScript is loaded on editor pages. The settings alone do not create the editor button.

On the tested install, add this to a template that is actually rendered on editor pages, such as the active footer template or another included template:

```html
<link rel="stylesheet" href="/core/packages/vbulletinbytools/js/vbulletinbytools_ai.css?v=22">
<script src="/core/packages/vbulletinbytools/js/vbulletinbytools_ai.js?v=22"></script>
```

Do not put this in a custom template unless that template is included by the active style. A custom template that is not referenced will not render.

After product import or style overwrite, verify that the include still exists. Some imports or overwrites may remove manual template changes.

Browser checks on an editor page:

```js
document.querySelector('script[src*="vbulletinbytools_ai"]')
document.querySelector('link[href*="vbulletinbytools_ai"]')
```

If the first command returns `null`, the AI script is not loaded and no AI button can appear.

Network should show a loaded script like:

```text
/core/packages/vbulletinbytools/js/vbulletinbytools_ai.js?v=22
```

## Privacy, consent and context behavior

The AI button should not be controlled by consent fields. Consent controls what context can be sent, not whether the editor helper exists.

The intended behavior is:

```text
AI button visible:
- AI is enabled globally
- frontend JS/CSS is loaded
- editor is detected
- user has not explicitly disabled AI at profile level

Consent missing:
- button may still be visible
- request may still be allowed depending on consent mode
- context must be limited according to the rules below
```

### Context mode

```text
full
```

Allow full context transfer, but only when the forum area is public and privacy/consent rules allow it.

```text
request_only
```

Send no forum/editor/thread context. Only the user's own AI request should be used.

### Consent mode

```text
require_opt_in
```

The strict mode. The AI helper must not help the current user until that user has actively opted in. If the current user has not made an active consent choice, force request-only or block assistance according to the final implementation decision.

For thread context, include only posts from authors who have explicitly opted in. Missing consent means no consent.

```text
allow_unless_opt_out
```

Help is allowed until the user explicitly opts out. For thread context, include posts unless the author has explicitly opted out.

```text
disabled
```

Consent filtering is disabled as an explicit administrator decision. Other privacy rules, such as private node protection, may still apply.

### Opt-out override

An explicit opt-out must override everything else. If the current user has opted out, AI must not be active for that user. If a post author has opted out, that author's content must not be included as AI context.

### Private and non-public areas

Full context is only allowed for public forum areas. If the forum area is closed, hidden, private, restricted, permission-sensitive, or if the system cannot determine whether it is public, context must be treated as unsafe.

Recommended behavior:

```text
private or unknown public status = force request_only
```

If an open-looking forum area is not truly public, treat it as requiring opt-in.

### Profile fields

The product currently stores profile field IDs in settings. It does not yet create the custom profile fields automatically.

These settings point to existing custom profile fields:

```text
tornis_tools_ai_profile_context_mode_field
tornis_tools_ai_profile_enabled_field
tornis_tools_ai_profile_context_consent_field
```

A value of `0` means no profile field is connected yet.

Future installation/upgrade work should create recommended profile fields automatically, or provide a clear installer step for creating them manually.

## Persona support

The option:

```text
tornis_tools_gpt_persona_field
```

controls which vBulletin custom profile field is used as the user's writing persona.

Example:

```text
tornis_tools_gpt_persona_field = 67
```

means that the integration reads:

```text
userfield.field67
```

The persona is added server-side to the AI context as a mandatory writing persona.

Debug route:

```text
/ajax/api/vbulletinbytools:Ai/personaDebug
```

Run from a logged-in browser console:

```js
vBulletin.AJAX({
    call: "/ajax/api/vbulletinbytools:Ai/personaDebug",
    success: function (response) {
        console.log(response);
    }
});
```

Expected result when persona works:

```json
{
  "ok": true,
  "userid": 1,
  "persona_field_id": 67,
  "persona_field_name": "field67",
  "has_persona": true,
  "persona_length": 149
}
```

## Thread context

The frontend sends the current `nodeid` when it can find one through `pageData`, DOM data attributes or URL hints.

The backend then tries to fetch thread context directly from vBulletin using:

- `node`
- `text`
- `user`

This gives the AI more reliable thread context than DOM scraping alone.

The browser-side visible context may still be sent by the frontend, but the backend must override or discard it when `request_only`, private-node protection or consent rules require it.

Debug route:

```text
/ajax/api/vbulletinbytools:Ai/threadDebug
```

Run from a thread page:

```js
vBulletin.AJAX({
    call: "/ajax/api/vbulletinbytools:Ai/threadDebug",
    data: {
        nodeid: pageData.nodeid
    },
    success: function (response) {
        console.log(response);
    }
});
```

Expected result when server-side thread context works:

```json
{
  "ok": true,
  "nodeid": 123,
  "has_thread_context": true,
  "thread_context_length": 1000
}
```

## Web search

External web search can be enabled with:

```text
tornis_tools_ai_web_search_enabled = 1
```

Recommended default:

```text
tornis_tools_ai_web_search_required = 0
```

That allows external web search when useful, but does not force it for every request.

Thread content should not be fetched through web search. The forum already has the data locally. The backend should read thread posts/comments from vBulletin and pass them as context when allowed. Web search should be used for external context, fact checking and references outside the forum.

## Markdown and BBCode

AI output may contain Markdown. Before inserting the answer into the vBulletin editor, the frontend converts common Markdown to BBCode.

Supported conversions include:

- `[label](url)` to `[url=url]label[/url]`
- `![alt](url)` to `[img]url[/img]`
- `**bold**` to `[b]bold[/b]`
- `*italic*` to `[i]italic[/i]`
- Markdown headings to `[b]heading[/b]`
- Markdown blockquotes to `[quote]...[/quote]`
- Markdown lists to `[list]`, `[list=1]` and `[*]`
- fenced code blocks to `[code]...[/code]`
- inline code to `[icode]...[/icode]`

## Testing backend

Test the route:

```bash
curl -i \
  -X POST \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data "context=Test&prompt=Svara kort hej på svenska&language=sv" \
  https://forum.example.com/ajax/api/vbulletinbytools:Ai/respond
```

Expected result:

```json
{
  "ok": true,
  "status": 200,
  "response": {
    "ok": true,
    "response": "Hej!"
  }
}
```

## Troubleshooting

If the panel shows `Invalid JSON response from Tornevall Tools`, the upstream gateway returned something that was not JSON. The current client should expose diagnostics such as:

```text
HTTP status
Content-Type
Request bytes
Response bytes
Raw preview
```

Common causes:

- Missing or invalid token from Tornevall Tools
- Missing or invalid OpenAI token when Direct OpenAI is selected
- HTML error page from the gateway
- Request too large after adding thread context
- Timeout or upstream 5xx error
- Web search failure upstream
- Frontend script not loaded on the editor page

Check the server log after a failed request:

```bash
tail -n 120 /var/log/apache2/error.log
```

## Security notes

- API tokens must only be read server-side.
- Never expose `tornis_tools_gpt_secret` or `tornis_tools_openai_api_key` in JavaScript.
- Do not commit real tokens to the repository.
- Rotate any token that has been visible in screenshots or logs.
- Keep token defaults empty in product XML exports.
- A closed or safe group must remain closed and safe even when AI tools are enabled.
- External AI use must be explicit, explainable and easy to decline.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Development notes

The package currently has two context sources:

1. Browser-side visible context:
   - page title
   - breadcrumbs
   - visible loaded posts/comments
   - current editor text

2. Server-side vBulletin context:
   - current user id
   - persona field
   - thread node id
   - thread posts/comments fetched from vBulletin database/API

The server-side context should be considered more reliable than DOM scraping, but privacy and consent rules must still override both sources.
