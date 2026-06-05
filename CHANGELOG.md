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

### Pending

- Add automatic source verification mode for prompts that ask for sources, citations, references, links, facts or fact checking.
- Force Tornevall Tools web search for source-sensitive requests instead of only relying on the global `tornis_tools_ai_web_search_enabled` option.
- Add stricter prompt rules that forbid invented references and require either verified links/citations or an explicit note that no verified source was found.

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
