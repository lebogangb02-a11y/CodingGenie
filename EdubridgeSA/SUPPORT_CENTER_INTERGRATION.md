# EduBridge SA Support Center – Integration Guide

This guide explains how to deploy and integrate the new Support Center into the existing PHP/MySQL student portal on Hostinger.

## 1) Database Schema

- File: `support_center_schema.sql`
- Import into your MySQL database using phpMyAdmin or CLI.
- Creates tables:
  - `support_chats`, `support_chat_messages`
  - `support_tickets`, `support_ticket_messages`
  - `faq_entries` (with a few seed FAQs)

## 2) Files Added

- `support.php` – Main support page: Live chat, FAQ search, contact grid, quick links
- `chat-api.php` – Backend chat handler (REST-like endpoints)
- `support-tickets.php` – Ticket creation and listing for students
- `css/support.css` – Styling consistent with site branding
- `js/support.js` – Real-time chat UI, FAQ search, escalation
- `help.php` – Redirect legacy `/help.php` to Support (or login)

## 3) Configuration

- Ensure `session_config.php` and `config.php` work on these pages (PDO `$pdo` connection and session).
- Update WhatsApp number in `support.php` contact grid.
- Ensure uploads directory exists for chat attachments: `uploads/support_attachments/`.
- File permissions on Hostinger should allow uploads (typically 755 directories, 644 files).

## 4) Navigation

- A “Support” link was added in `includes/navigation.php` under the main navigation links.
- Students can also reach Support from dashboard quick links.

## 5) Security & Auth

- `support.php`, `support-tickets.php`, and `chat-api.php` require a logged-in student (`$_SESSION['student_logged_in']`).
- `help.php` redirects guests to login with `next=support.php`.
- Chat uploads are sanitized and stored under `uploads/support_attachments/`.

## 6) Chat API Endpoints

- `GET chat-api.php?action=history` – Returns messages and student context
- `POST chat-api.php?action=send` – Sends a message; supports `attachment`
- `GET chat-api.php?action=faq-search&q=<term>` – Returns FAQ matches
- `POST chat-api.php?action=escalate` – Converts current chat into a support ticket

## 7) FAQ Management

- Manage entries in `faq_entries`. Expand tags and content to improve responses.

## 8) Hostinger Deployment Notes

- Upload files to your site root.
- Import `support_center_schema.sql` via phpMyAdmin.
- Verify PHP version and `file_uploads=On`.
- Test `support.php` while logged in; ensure navigation link appears.

## 9) Optional Integrations

- Email notifications for new tickets can be added using `email_functions.php`.
- WhatsApp Business API: replace `wa.me` link with your official API endpoint.
- Live agent handoff: add an admin view to monitor chats and reply as `agent`.