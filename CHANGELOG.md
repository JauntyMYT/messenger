# Changelog

All notable changes to JauntyM Messenger are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.3] - 2026-06-14

### Changed
- Moved all inline `<style>` and `<script>` blocks out of the templates into
  external asset files loaded via `INCLUDECSS` / `INCLUDEJS`, for compliance with
  the phpBB Extension Pre-Validator (EPV):
  - `styles/all/theme/messenger.css`, `styles/all/template/messenger.js`
  - `styles/all/theme/footer_bubble.css`, `styles/all/template/footer_bubble.js`
- Language strings used by JavaScript are now passed via `data-*` attributes
  instead of being interpolated into inline scripts.
- Folded the navigation-badge style into the shared theme stylesheet.

### Added
- `JAUNTYM_M_CLOSE` language string for the chat-bubble close button (was previously
  a hard-coded label).

## [1.3.2] - 2026-06-01

### Added
- `u_jauntym_dm` permission to separately control who can start chats and send
  direct messages.
- Floating chat bubble dock on every page (mini conversation list, mini chat,
  member search) and hover-to-message buttons on usernames.

### Earlier releases

Versions prior to 1.3.2 were development iterations that built out the core
messenger: conversation list, threaded 1:1 chat, read receipts, typing indicators,
BBCode and smilies, soft-delete, block/unblock, hide conversation, and the ACP
settings panel.
