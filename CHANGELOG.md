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
- Merged AI context privacy controls into the generic provider branch:
  - `privacyDebug` is available together with `providerDebug`.
  - `tornis_tools_ai_context_mode` works with both Tornevall Tools and Direct OpenAI.
  - `tornis_tools_ai_profile_context_mode_field` can override AdminCP context mode per user.
  - `tornis_tools_ai_profile_enabled_field` can disable AI per user.
  - Profile-level settings take precedence over AdminCP defaults.
  - Product XML now includes both provider settings and privacy settings in the same settings group.
- Added consent-aware context filtering:
  - New AdminCP option `tornis_tools_ai_context_consent_mode` with `require_opt_in`, `allow_unless_opt_out` and `disabled`.
  - New AdminCP option `tornis_tools_ai_profile_context_consent_field` for per-user consent through a custom profile field.
  - New AdminCP option `tornis_tools_ai_disable_context_in_private_nodes`, default enabled.
  - Thread context is now filtered per post author before it is sent to the selected provider.
  - `require_opt_in` fails closed and only includes posts from users who explicitly opted in.
  - `allow_unless_opt_out` excludes posts from users who explicitly opted out.
  - `disabled` turns off consent filtering as an explicit admin decision.
  - Private, restricted or unknown node privacy status blocks thread context and forces request-only behavior for that request.
  - Quotes are stripped from server-side thread context before sending.
  - Hidden, moderated, deleted or unavailable posts are excluded from AI context.
  - `privacyDebug`, `threadDebug` and AI payload metadata now include consent mode, private-node blocking and context filtering counters.

### Fixed

- Fixed vBulletin AdminCP option rendering for dropdown settings by replacing inline PHP/HTML optioncode with `select:piped` optioncode. This fixes `syntax error, unexpected variable "$setting"` when opening the product options page.

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
