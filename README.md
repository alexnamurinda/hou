# Hotspot Operators Union

A simple union website for hotspot operators and community connectivity stakeholders.

## HOU member accounts

The member portal stores accounts in the `HOU` MySQL database. Create the database itself in your hosting console, then configure the portal's database connection. On the first portal visit, it automatically creates the `members` and `auth_attempts` tables if they do not exist. The database user must have permission to create tables in `HOU`. [`database/HOU.sql`](database/HOU.sql) is also available for manual setup. The local XAMPP setup uses `127.0.0.1`, database user `root`, and an empty password by default.

For any other environment, configure `HOU_DB_HOST`, `HOU_DB_PORT`, `HOU_DB_NAME`, `HOU_DB_USER`, and `HOU_DB_PASSWORD` in the PHP server environment (for PHP-FPM, in its pool configuration, then restart PHP-FPM). Do not put the database password in this repository. Use a database account restricted to the `HOU` database, with permission to create tables on first portal load. Registration and login use the PHP session cookie and redirect authenticated members to the protected one-page portal at `client/member.php`.
