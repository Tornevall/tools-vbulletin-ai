# Changelog

All notable changes to this project are documented here.

This changelog starts from the first repository commit and includes the implementation and fixes made during the initial development of the vBulletin AI integration.

## Unreleased

### Added

- Added this changelog.
- Updated README with explicit Tornevall Tools requirements:
  - `https://tools.tornevall.net` is required.
  - API/bearer tokens must be created in Tornevall Tools.
  - The token must be stored in vBulletin option `tornis_tools_gpt_secret`.
  - The token must be stored without the `Bearer ` prefix.
  - The default gateway URL is `https://tools.tornevall.net`.
  - The backend client calls `/api/ai/internal/respond`.
- Added automatic source verification mode for prompts that ask for sources, citations, references, links, facts or fact checking.
- Added forced Tornevall Tools web search for source-sensitive requests instead of only relying on the global `tornis_tools_ai_web_search_enabled` option.
- Added stricter prompt rules that forbid invented references and require either verified links/citations or an explicit note that no verified source was found.
- Added generic AI provider selection:
  - New AdminCP option `tornis_tools_ai_provider` selects between `tornevall_tools` and `openai`.
  - Existing Tornevall Tools settings are kept unchanged.
  - Direct OpenAI settings were added: `tornis_tools_openai_api_key`, `tornis_tools_openai_base_url`, `tornis_tools_openai_model` and `tornis_tools_openai_timeout`.
  - New `TornevallTools_DirectOpenAiClient` uses the OpenAI Responses API directly.
  - New `providerDebug` API method reports the selected provider and whether each provider has credentials configured.
  - Source-sensitive requests continue to force web search. With Direct OpenAI selected, web search is sent through OpenAI `web_search_preview` tooling.

## 2026-06-05

### Initial implementation - `3d493b35`

Initial commit: `OpenAI implementation via Tools in vB6.`

Added the first vBulletin 6 package-style implementation for connecting the vBulletin editor to Tornevall Tools/OpenAI.

Initial contents included:

- Basic installation notes in `INSTALL.txt`.
- Initial vBulletin package layout under `core/packages/vbulletin_by_tools`.
- Initial API class `api/ai.php`.
- Initial hook file `hooks.php`.
- Initial frontend JavaScript helper for the editor.
- Initial frontend CSS for the AI helper panel.
- Early package files such as `product.php`, `readme.txt` and XML/package scaffolding.
- Early direct endpoint idea under `core/packages/vbulletin_by_tools/api/ai.php`.
- Early UI concept for adding an AI helper near the WYSIWYG editor.

### Added README - `3d977bb`

Added the first full `README.md` for the vBulletin AI integration.

Documented:

- Purpose of the package.
- Current working API route.
- Server-side token handling.
- Package name decision.
- Directory layout.
- Required vBulletin options.
- Persona support.
- Thread context concept.
- Web search concept.
- Frontend installation.
- Backend testing.
- Security notes.

### Added package API with persona and thread context - `5012b5f`

Added the vBulletin package API implementation under:

```text
core/packages/vbulletinbytools/api/ai.php
```

Added:

- API class `vbulletinbytools_Api_Ai`.
- API method `respond`.
- API method `test`.
- API method `personaDebug`.
- Persona support through `tornis_tools_gpt_persona_field`.
- User profile field reading from `userfield.fieldXX`.
- Server-side context building.
- Output rules for avoiding unwanted AI introductions and closing remarks.
- Server-side Tornevall Tools client loading.
- vBulletin metadata in the AI payload.

### Added OpenAI client diagnostics - `4fbce32`

Added the Tornevall Tools OpenAI client with raw response diagnostics.

Added:

- `library/TornevallTools/OpenAiClient.php`.
- Server-side POST to Tornevall Tools endpoint `/api/ai/internal/respond`.
- Bearer token authentication.
- JSON request encoding.
- Curl-based gateway request.
- Handling of non-JSON upstream responses.
- Initial `raw` response return for diagnostics.

### Improved gateway diagnostics - `c2675a8`

Improved diagnostics for Tornevall Tools responses.

Added:

- HTTP status in error responses.
- Content-Type in error responses.
- Request byte size.
- Response byte size.
- Raw response preview.
- Server-side `error_log` output for invalid JSON responses.
- Longer timeout handling for AI calls.

### Added thread context and current user identity - `04166e1`

Expanded the package API with thread context and current user identity handling.

Added:

- API method `threadDebug`.
- `respond($context, $prompt, $language, $nodeid)` signature.
- Server-side `nodeid` handling.
- Server-side vBulletin thread context fetch attempt through:
  - `node`
  - `text`
  - `user`
- Thread context metadata in payload:
  - `nodeid`
  - `has_thread_context`
  - `thread_context_length`
- Current user identity in context:
  - userid
  - username
- Rules telling the AI to write as the current vBulletin user, not describe the user in third person.
- Web search payload flags:
  - `use_web_search`
  - `web_search_required`

### Returned gateway failures as API-safe payloads - `6563559`

Changed the root OpenAI client so Tornevall Tools gateway failures are returned as API-safe payloads instead of hard failures.

Added:

- `gateway_ok` flag.
- Human-readable `text` field for frontend display.
- API-safe response shape for upstream failures.
- Detailed gateway failure payload containing:
  - `status`
  - `content_type`
  - `request_bytes`
  - `response_bytes`
  - `error`
  - `raw_preview`

### Returned gateway failures as API-safe package payloads - `3da4f2f`

Applied the same API-safe gateway failure handling to the package client under:

```text
core/packages/vbulletinbytools/library/TornevallTools/OpenAiClient.php
```

This fixed the deployed package path so vBulletin would not only show its own generic `Invalid server response` dialog.

### Markdown to BBCode conversion - `6a38e75`

Added Markdown-to-BBCode conversion before inserting AI output into the vBulletin editor.

Added conversions:

- Markdown links to `[url=...]...[/url]`.
- Markdown images to `[img]...[/img]`.
- Bold Markdown to `[b]...[/b]`.
- Italic Markdown to `[i]...[/i]`.
- Markdown headings to bold BBCode.
- Markdown blockquotes to `[quote]...[/quote]`.
- Markdown unordered lists to `[list]` and `[*]`.
- Markdown ordered lists to `[list=1]` and `[*]`.
- Fenced code blocks to `[code]...[/code]`.
- Inline code to `[icode]...[/icode]`.

Also added:

- `nodeid` detection in frontend from `pageData`, DOM attributes and URL hints.
- Sending `nodeid` in AI requests.
- Prompt note that Markdown is allowed because the client converts it to BBCode before insertion.

### Fetch-based AI calls and gateway diagnostics - `989e8ff`

Changed the frontend AI call to use `fetch()` instead of the vBulletin AJAX wrapper.

Reason:

- vBulletin's AJAX wrapper could swallow or transform gateway errors into generic `Invalid JSON response` messages.
- Direct `fetch()` allows the frontend to read raw endpoint responses and show better diagnostics.

Added:

- Raw response parsing.
- Better handling of invalid JSON from the vBulletin endpoint.
- `formatAjaxError()` for more readable frontend errors.
- Better display of `gateway_ok: false` payloads.

### README update for Tornevall Tools token requirements - `e58e0a5`

Updated README with explicit external service requirements.

Added documentation that:

- Tornevall Tools is required.
- Tokens must be created in Tornevall Tools.
- Tokens are stored in `tornis_tools_gpt_secret`.
- Tokens must be stored without `Bearer `.
- `https://tools.tornevall.net` is the default gateway URL.
- The PHP client calls `/api/ai/internal/respond`.

### Package-path JS loader - `bb6862b`

Added a small package-path JavaScript loader under:

```text
core/packages/vbulletinbytools/js/vbulletinbytools_ai.js
```

The loader forwards old package-path template includes to the public frontend asset path:

```text
/js/vbulletinbytools_ai.js
```

This was added to keep existing manual Style Manager includes working while the frontend asset path is being cleaned up.

### API-safe backend errors - `c286865`

Updated `core/packages/vbulletinbytools/api/ai.php` so `respond()`, `personaDebug()` and `threadDebug()` return API-safe payloads when exceptions occur.

Added:

- `try/catch` wrappers around public API methods.
- `apiSafeError()` helper.
- Error payloads containing:
  - `gateway_ok: false`
  - `exception_class`
  - `exception_file`
  - `exception_line`
- Safer handling of local failures before calling Tornevall Tools.

### vBulletin AJAX headers in fetch - `6cca5fa`

Updated frontend fetch requests to look more like vBulletin AJAX requests.

Added request headers:

- `X-Requested-With: XMLHttpRequest`
- `Accept: application/json, text/javascript, */*; q=0.01`
- `Content-Type: application/x-www-form-urlencoded; charset=UTF-8`

### Package loader cache bump - `f817b1a`

Updated the package-path loader to point at a newer public JS asset version.

This was done to reduce cache issues while the manual Style Manager include still points at the package-path loader.

### Security token support in fetch requests - `765bd00`

Updated the frontend fetch-based AI request to include vBulletin's `securitytoken` when available.

Added token lookup from:

- `window.SECURITYTOKEN`
- `window.securitytoken`
- `window.pageData.securitytoken`
- `window.pageData.securityToken`
- `window.vBulletin.securitytoken`
- `window.vBulletin.securityToken`
- `window.vBulletin.getSecurityToken()`
- hidden form fields
- meta tags

This fixed vBulletin errors like:

```text
securitytoken missing
```

### Source-sensitive web search enforcement - `fc1fd8f`

Updated the backend to detect source-sensitive requests and force web search through Tornevall Tools.

Added detection for prompts/context containing words such as:

- `källa`
- `källor`
- `källhänvisning`
- `referens`
- `citat`
- `länk`
- `artikel`
- `fakta`
- `faktakoll`
- `verifiera`
- `belägg`
- `source`
- `citation`
- `reference`
- `link`
- `article`
- `fact check`
- `verify`
- `evidence`

When such a request is detected:

- `use_web_search` is forced to `true`.
- `web_search_required` is forced to `true`.
- Additional source verification rules are appended to the context.
- The payload includes `source_sensitive_request`, `use_web_search` and `web_search_required` in the `vbulletin` metadata.

## Notes

### Package ID change

The early package path used:

```text
vbulletin_by_tools
```

The working package id is now:

```text
vbulletinbytools
```

The underscore variant caused problems with vBulletin package/API class resolution in this integration.

### Context strategy

The integration now uses two context layers:

1. Frontend visible context:
   - page title
   - breadcrumbs
   - visible posts/comments
   - current editor text

2. Backend vBulletin context:
   - current user id
   - username
   - persona profile field
   - node id
   - server-side thread posts/comments where available

The backend context should be considered more reliable than DOM scraping.

### Web search strategy

Forum content should be read from vBulletin, not external web search.

Web search should only be used for external context, fact checking and references outside the forum.
