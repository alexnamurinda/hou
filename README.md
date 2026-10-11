# Hotspot Operators Union

A simple union website for hotspot operators and community connectivity stakeholders.

## HOU member accounts

The member portal stores accounts in the `HOU` MySQL database. Create the database itself in your hosting console, then configure the portal's database connection. On the first portal visit, it automatically creates the `members`, `auth_attempts`, and `member_announcements` tables if they do not exist. The database user must have permission to create tables in `HOU`. [`database/HOU.sql`](database/HOU.sql) is also available for manual setup. The local XAMPP setup uses `127.0.0.1`, database user `root`, and an empty password; production defaults to the restricted `hou_user` account.

Registration is a three-step process: collect the member's name, phone, and email; verify the email with a short-lived one-time code; then create a password. Install the mail dependency in the repository root with `composer install --no-dev`. Configure `HOU_SMTP_HOST`, `HOU_SMTP_PORT`, `HOU_SMTP_ENCRYPTION`, `HOU_SMTP_USERNAME`, and `HOU_SMTP_PASSWORD` in the PHP server environment. For Namecheap Private Email, use `mail.privateemail.com`, port `465`, encryption `ssl`, and the full `info@hotspotoperators.org` mailbox as the username. The password must be stored as an environment secret, not in this repository. The verified mailbox is the fixed sender `info@hotspotoperators.org`.

### Namecheap email setup

Choose either Namecheap Private Email or the email service included with your cPanel hosting; Namecheap does not allow both services to be active for the same domain at once. Create the `info@hotspotoperators.org` mailbox with the selected service. If the domain uses Namecheap DNS, open **Domain List → Manage → Advanced DNS → Mail Settings** and select **Private Email** for Private Email hosting. If the domain uses external nameservers, make equivalent DNS changes at the active DNS provider. Add or verify the MX and SPF records shown by the email service, then publish the DKIM record supplied in its dashboard. Do not create a second SPF record if one already exists; merge the authorized senders into one SPF value.

For Private Email, the authenticated SMTP server is `mail.privateemail.com`; use port `465` with SSL or port `587` with TLS/STARTTLS and enable SMTP authentication. Put the mailbox password in the PHP server's environment as `HOU_SMTP_PASSWORD`, not in source code. Namecheap's [Private Email setup guide](https://www.namecheap.com/support/knowledgebase/article.aspx/1337/2176/how-to-start-using-namecheap-private-email/) covers mailbox and DNS activation, and its [mail client settings](https://www.namecheap.com/support/knowledgebase/article.aspx/1179/2175/general-private-email-configuration-for-mail-clients-and-mobile-devices/) lists the SMTP connection details. Allow DNS time to propagate, then test mail delivery before enabling registration.

For any other environment, configure `HOU_DB_HOST`, `HOU_DB_PORT`, `HOU_DB_NAME`, `HOU_DB_USER`, and `HOU_DB_PASSWORD` in the PHP server environment (for PHP-FPM, in its pool configuration, then restart PHP-FPM). Do not put database or SMTP passwords in this repository. Use a database account restricted to the `HOU` database, with permission to create tables on first portal load. Registration and login use the PHP session cookie and redirect authenticated members to the protected one-page portal at `client/member.php`.

## Member portal web app manifest

[`client/manifest.json`](client/manifest.json) provides browser installation and launch details for the HOU member portal. It defines the app name, description, start page, launch scope, standalone display mode, portrait orientation, theme and background colors, language, category, HOU logo icon, and shortcuts for Log In and Sign Up. Browsers use these settings when a user installs or launches the portal as a web app. The manifest is not required for ordinary website visits, registration, or login.
