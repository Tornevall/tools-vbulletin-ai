# VBulletin by Tornevall Tools

AI assistant integration for vBulletin 6.

This package adds an "Ask AI for help" helper near the vBulletin editor. The browser never talks directly to OpenAI or Tornevall Tools. All AI requests go through a server-side vBulletin API class, which reads the private API token from vBulletin options and forwards the request to Tornevall Tools.

## Required external service

This integration requires Tornevall Tools:

```text
https://tools.tornevall.net
```

The vBulletin package does not contain its own OpenAI key handling and does not call OpenAI directly from the browser. It uses Tornevall Tools as the AI gateway.

You must create an API/bearer token in Tornevall Tools before the vBulletin integration can work. That token is then stored in vBulletin AdminCP as:

```text
tornis_tools_gpt_secret
```

The token must be the raw token value only. Do not include the `Bearer ` prefix.

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

The API route used by the PHP client is:

```text
/api/ai/internal/respond
```

## Current status

Working parts:

- vBulletin API route: `/ajax/api/vbulletinbytools:Ai/respond`
- Server-side Tornevall Tools bridge
- Bearer-token authentication through vBulletin options
- Editor helper button
- AI response panel
- Insert answer into editor
- Markdown-to-BBCode conversion before insert
- vBulletin profile-field persona support
- Optional external web search support through Tornevall Tools
- Thread/context support through visible frontend context and backend node context
- Gateway diagnostics for invalid upstream responses

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
│       └── OpenAiClient.php
├── xml/
│   ├── cpnav_vbulletinbytools.xml
│   └── product-vbulletinbytools.xml
├── hooks.php
├── product.php
└── readme.txt
```

Public frontend assets:

```text
js/vbulletinbytools_ai.js
js/vbulletinbytools_ai.css
```

The frontend assets are public files and should be loaded from the forum root `/js/` directory.

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

Create these options in AdminCP and assign them to the `vbulletinbytools` product.

```text
tornis_tools_ai_enabled
tornis_tools_gpt_secret
tornis_tools_api_base_url
tornis_tools_ai_client_slug
tornis_tools_gpt_persona_field
tornis_tools_ai_web_search_enabled
tornis_tools_ai_web_search_required
```

Recommended values:

```text
tornis_tools_ai_enabled = 1
tornis_tools_api_base_url = https://tools.tornevall.net
tornis_tools_ai_client_slug = vbulletin_wysiwyg_assistant
tornis_tools_gpt_persona_field = 67
tornis_tools_ai_web_search_enabled = 1
tornis_tools_ai_web_search_required = 0
```

The secret setting:

```text
tornis_tools_gpt_secret
```

must contain the bearer token created in Tornevall Tools, without the `Bearer ` prefix.

Never expose this token in JavaScript, templates, HTML, screenshots, logs or public XML exports.

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

The browser-side visible context is still sent as a fallback and supplement:

- page title
- breadcrumbs
- visible loaded posts/comments
- current editor text

Server-side thread context should be considered the primary thread source when `nodeid` is available.

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

Thread content should not be fetched through web search. The forum already has the data locally. The backend should read thread posts/comments from vBulletin and pass them as context. Web search should be used for external context, fact checking and references outside the forum.

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

## Frontend installation

Load the frontend assets in a template that is available on editor pages:

```html
<link rel="stylesheet" href="/js/vbulletinbytools_ai.css">
<script src="/js/vbulletinbytools_ai.js"></script>
```

During development, cache-bust manually:

```html
<link rel="stylesheet" href="/js/vbulletinbytools_ai.css?v=14">
<script src="/js/vbulletinbytools_ai.js?v=14"></script>
```

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
- HTML error page from the gateway
- Request too large after adding thread context
- Timeout or upstream 5xx error
- Web search failure upstream

Check the server log after a failed request:

```bash
tail -n 120 /var/log/apache2/error.log
```

## Security notes

- The API token must only be read server-side.
- Never expose `tornis_tools_gpt_secret` in JavaScript.
- Do not commit real tokens to the repository.
- Rotate any token that has been visible in screenshots or logs.
- Keep `tornis_tools_gpt_secret` default empty in product XML exports.

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

The server-side context should be considered more reliable than DOM scraping.
