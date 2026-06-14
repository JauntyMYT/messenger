# JauntyM Messenger

A modern, Facebook-Messenger-style private messaging experience for phpBB. It adds
a conversation list, threaded 1:1 chat with left/right bubbles, read receipts,
typing indicators, BBCode, member blocking, a floating chat bubble on every page,
and an ACP settings panel.

It is **self-contained** — it uses its own database tables and never touches
phpBB's native private-message inbox.

- **Compatible with:** phpBB 3.2.x and 3.3.x
- **Requires:** PHP 7.2+
- **License:** [GPL-2.0-only](LICENSE)

## Features

- **Conversation list** — avatars, last-message snippet, unread badge, timestamps
- **Member search** to start a new chat, plus a client-side filter for your list
- **Threaded 1:1 chat** — left/right bubbles, newest at the bottom, load-older paging
- **Read receipts** — "Seen" + time on your latest message
- **Typing indicators** (polling-based)
- **BBCode + smilies** in messages, rendered through phpBB's own text engine
- **Soft-delete** your own messages
- **Block / unblock** members (blocks both directions and hides them from search)
- **Hide conversation** from your list
- **Floating chat bubble** in the bottom-right of every page, with a mini conversation
  list, mini chat, and member search
- **Hover-to-message** button on usernames across the forum
- **Deep links** — open a specific conversation with `?c=<id>` on the messenger page
- **ACP settings** — refresh interval, max message length, minimum search characters,
  and toggles for BBCode, smilies, and typing indicators

## Permissions

| Permission | Controls |
|------------|----------|
| `u_jauntym_messenger` | Can use the messenger (view conversations and messages) |
| `u_jauntym_dm` | Can start chats and send direct messages |

Both are available under **ACP → Permissions** and can be assigned per user or per group.

## Installation

1. Download the latest release, or clone this repository:
   ```
   git clone https://github.com/JauntyMYT/messenger.git ext/jauntym/messenger
   ```
   The extension must live at `ext/jauntym/messenger/` inside your board.
2. Go to **ACP → Customise → Manage extensions** and **Enable** *JauntyM Messenger*.
   This creates the tables, config defaults, permissions, and the ACP module.
3. A **Messages** link appears in the header navigation for members who hold the
   `u_jauntym_messenger` permission.
4. Tune behaviour at **ACP → Extensions → JauntyM Messenger → Messenger settings**.

## Refresh model

New messages, read receipts, and typing status update on a **polling interval**
(default 15s, configurable down to 5s), plus a refresh when the browser window
regains focus. True push (WebSockets / SSE) is intentionally not used, as it
requires a persistent server process that typical shared hosting cannot run.

## Security

- Message bodies are processed by phpBB's text engine (`generate_text_for_display`),
  which handles escaping, BBCode, smilies, and censor words.
- Sending and deleting are protected by phpBB CSRF link hashes and ACL checks.
- Conversation membership and block state are verified server-side on every action.
- Member search excludes anyone in a block relationship with you.

## Support

- **Issues / bug reports:** https://github.com/JauntyMYT/messenger/issues
- **Website:** https://www.bejaunty.com

## License

This extension is free software, licensed under the
[GNU General Public License, version 2 (GPL-2.0-only)](LICENSE).
