channl.sagejyoung.com
======================

Connect • Clean • Communicate

MVP stack: PHP 8+, MySQL 8, Vanilla JS, Bootstrap (optional). No Composer, no vendor SDKs.

Features
- Auth (login, register, password reset TBD)
- Contacts (CSV upload, validate, dedupe, tag) [upcoming]
- Campaigns (SMS/WhatsApp/Email) [upcoming]
- Credit wallet + transactions [upcoming]
- Reporting and secure data exchange [upcoming]

Quick start
1) Copy `core/config.php` and fill in credentials and secrets.
2) Create a MySQL database and run `sql/schema.sql`.
3) Configure your web server to serve `public/` as the document root.

Dev notes
- Sessions are strict (HttpOnly + SameSite=Strict). CSRF tokens required for POST.
- PDO with parameterized queries. No string concatenation for SQL.
- CSP headers are set to a safe baseline; adjust if you add external assets.


