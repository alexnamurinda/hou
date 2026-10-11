# Hotspot Operators Union

A simple union website for hotspot operators and community connectivity stakeholders.

## HOU member accounts

The member portal stores accounts in the `HOU` MySQL database. Create the database itself in your hosting console, then configure the portal's database connection. On the first portal visit, it automatically creates the `members`, `auth_attempts`, and `member_announcements` tables if they do not exist. The database user must have permission to create tables in `HOU`. [`database/HOU.sql`](database/HOU.sql) is also available for manual setup. The local XAMPP setup uses `127.0.0.1`, database user `root`, and an empty password; production defaults to the restricted `hou_user` account.

Registration is a three-step process: collect the member's name, phone, and email; verify the email with a short-lived one-time code; then create a password. OTP email is sent through Brevo's HTTPS API, so the production PHP installation must include the cURL extension. Configure `HOU_BREVO_API_KEY` in the PHP server environment using a protected secret; never commit the API key to this repository. The verified sender is `info@hotspotoperators.org`.

### Namecheap email setup

Choose either Namecheap Private Email or the email service included with your cPanel hosting; Namecheap does not allow both services to be active for the same domain at once. Create the `info@hotspotoperators.org` mailbox with the selected service. If the domain uses Namecheap DNS, open **Domain List → Manage → Advanced DNS → Mail Settings** and select **Private Email** for Private Email hosting. If the domain uses external nameservers, make equivalent DNS changes at the active DNS provider. Add or verify the MX and SPF records shown by the email service, then publish the DKIM record supplied in its dashboard. Do not create a second SPF record if one already exists; merge the authorized senders into one SPF value.

Namecheap Private Email may continue to be used for receiving mail, while Brevo handles OTP delivery. Authenticate `hotspotoperators.org` in Brevo, verify `info@hotspotoperators.org` as a sender, and configure the Brevo API key only in the PHP server environment. Allow DNS time to propagate and test delivery before enabling registration.

For any other environment, configure `HOU_DB_HOST`, `HOU_DB_PORT`, `HOU_DB_NAME`, `HOU_DB_USER`, and `HOU_DB_PASSWORD` in the PHP server environment (for PHP-FPM, in its pool configuration, then restart PHP-FPM). Do not put database or SMTP passwords in this repository. Use a database account restricted to the `HOU` database, with permission to create tables on first portal load. Registration and login use the PHP session cookie and redirect authenticated members to the protected one-page portal at `client/member.php`.

## Member portal web app manifest

[`client/manifest.json`](client/manifest.json) provides browser installation and launch details for the HOU member portal. It defines the app name, description, start page, launch scope, standalone display mode, portrait orientation, theme and background colors, language, category, HOU logo icon, and shortcuts for Log In and Sign Up. Browsers use these settings when a user installs or launches the portal as a web app. The manifest is not required for ordinary website visits, registration, or login.
