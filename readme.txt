=== Omnexivo Connect ===
Version: 0.2.2
Requires WordPress 6.0+, PHP 7.4+

Changes in 0.2.2:
- Incoming customer messages and file uploads automatically reopen resolved or pending conversations. Server acknowledges state update, or reports failure.
- Live agent status synchronization, polling every six seconds while page is visible. Does not erase reply drafts or tags being typed.
- Polished responsive inbox, upgraded conversation bubbles, overview cards, improved mobile navigation, and clearer errors.
- Existing conversations and settings are retained on upgrade.

Install: Back up your WordPress site/database; Plugins > Add New > Upload Plugin > Replace current plugin. Clear browser/site caches. Open Omnexivo Connect > Inbox.
Regression test: resolve a conversation, send a NEW message from customer chat, confirm status returns to Open without manual refresh. Test both text and file messages.
