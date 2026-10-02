# Changelog

All notable changes to JauntyM Messenger are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.3.7

### Privacy
- Online status and "last seen" now follow phpBB core rules (`phpbb_show_profile()`):
  a member who enables "Hide my online status", or hides it for a single session at
  login, is shown as offline with no last-seen time unless the viewer has the
  `u_viewonline` permission. Nobody is shown as online when the board's online tracking
  is switched off. Previously the sessions table was read directly, bypassing the
  member's setting.
- Blocking now hides presence in both directions: between two members where either has
  blocked the other, online status, read receipts and typing indicators are no longer
  shown, and typing pings are not recorded.

### Changed
- "You:" (conversation list) and "99+" (unread badge) are now language strings, so they
  can be translated.
- All templates now use Twig syntax throughout (`{{ VAR }}`, `{{ lang('KEY') }}`).
- Opening a conversation no longer marks it read as a side effect of a GET request. The
  client sends a separate POST `read` action, protected like every other write, once
  unread messages are on screen and the tab is visible.
- The conversation list is built from a fixed number of queries instead of several per
  conversation, which matters because it is polled.
- Starting a conversation with yourself now reports "You cannot message yourself."
- Removed the unused `JAUNTYM_M_DELIVERED` language string.

### Fixed
- Two simultaneous requests could create two direct conversations for the same pair of
  members. Conversations now carry a canonical `conv_key` with a unique index, and
  creation runs in a transaction that falls back to the existing conversation if a
  concurrent request wins. The upgrade merges any duplicates already on the board.

## 1.3.6

### Security
- Every state-changing AJAX action (`send`, `start`, `typing`, `delete_message`, `block`,
  `unblock`, `hide`) now requires a POST request carrying a valid link hash. Previously only
  `send` verified the token, so the others could be triggered cross-site with a logged-in
  member's session (e.g. via an `<img>` tag pointing at the AJAX route).
- The JavaScript sends all of those actions as POST with the token attached.
- The AJAX error handler no longer returns raw exception text to the client, which could
  disclose SQL or filesystem paths.

## 1.3.5

- Added explicit integer casts to all remaining ID and timestamp values used in SQL, fully clearing the pre-validator's potential-SQL-injection warnings (no behavioural change; all values were already integers).

## 1.3.4

- Renamed license file to `license.txt` to meet phpBB EPV packaging requirements.
- Added explicit integer casts on ID values used in SQL to satisfy the pre-validator (no behavioural change; values were already integers).

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
