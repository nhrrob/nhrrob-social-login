=== NHR Social Login ===
Contributors: nhrrob
Tags: google login, social login, login, comments, oauth
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Log in, and comment, with a Google account.

== Description ==
NHR Social Login adds "Continue with Google" to the login form, and "Continue with Google to comment" above the comment form, so nobody has to type a password, or a name and email for every comment.

### How it works
- **Login form**: a "Continue with Google" button at the top of the form. It works on a moved login page too, such as the one NHR Secure sets up.
- **Comment form**: a visitor who is not logged in sees "Continue with Google to comment", and comes back to the same post, logged in.
- **Who gets in**: the user already linked to that Google account; otherwise the user with the Google account's email address, which is then linked; otherwise, when **New users** is on, a new Subscriber. Only Google accounts whose email address Google has verified are accepted.
- **Settings → Social Login**: a screen in light and dark that shows whether sign-in is on and how many users are linked, with the redirect URI ready to copy.
- **Registration stays closed**: new accounts are made only through Google. WordPress's own registration form ("Anyone can register" under Settings → General) can stay off, which keeps it away from bots.
- **Two-factor**: a Google sign-in is not asked for another code, since Google has already checked the account. WordPress's login event still fires, so activity logs and login alerts record it.

### For AI agents and MCP clients
On WordPress 6.9 and later the plugin registers one read-only ability, `nhrrob-social-login/get-status`: whether Google sign-in is set up, whether new users get an account, the redirect URI, and how many users are linked. It never returns the secret, an email address or a username.

== Installation ==
1. Install and activate the plugin from Plugins → Add New, or upload the zip under Plugins → Add New → Upload Plugin.
2. In the Google Cloud Console, under APIs & Services → Credentials, create an OAuth client ID of type "Web application".
3. Add the redirect URI shown under **Settings → Social Login** to its authorized redirect URIs. It is `https://your-site/wp-admin/admin-post.php?action=nhrrob_social_login`.
4. Add the client ID and secret to `wp-config.php`:
   `define( 'NHRROB_SOCIAL_LOGIN_GOOGLE_CLIENT_ID', '...' );`
   `define( 'NHRROB_SOCIAL_LOGIN_GOOGLE_CLIENT_SECRET', '...' );`
   You can also type them under Settings → Social Login, which stores them in the database.
5. To let new visitors comment with Google, turn on **New users** on the same screen.

The buttons appear once both the client ID and the secret are set.

== External services ==
This plugin uses Google's sign-in service (OpenID Connect), a service of Google LLC, only when someone presses one of its buttons:

- their browser is sent to accounts.google.com with this site's client ID and redirect URI, and Google asks them to choose an account;
- Google sends them back with a one-time code, which the server exchanges at oauth2.googleapis.com, with the client ID and secret, for the account's ID, name, email address and whether that address is verified.

Nothing is sent to Google on any other page or for visitors who do not press the button.

- [Google Terms of Service](https://policies.google.com/terms)
- [Google Privacy Policy](https://policies.google.com/privacy)

== Frequently Asked Questions ==

= Can someone take over an account by using a Google account with its email address? =
Only with a Google account whose email address Google has verified, which means they control that address. A user already linked to one Google account is never linked to a second one with the same address; that sign-in is refused.

= What is stored? =
The settings in one option, and for each linked user the Google account's ID in the `nhrrob_social_login_google_sub` user meta. A sign-in in progress is kept for ten minutes in a transient, with a cookie that ties it to the browser.

= What is removed when I delete the plugin? =
The settings, sign-ins still in progress and the links to Google accounts. User accounts stay.

= Where is the source code? =
On GitHub: https://github.com/nhrrob/nhrrob-social-login. The settings screen is built from `admin/src` with `npm install` and `npm run build`; both the source and the build are in the plugin.

== Screenshots ==
1. "Continue with Google" at the top of the login form.
2. "Continue with Google to comment" above the comment form.
3. Settings → Social Login: the Google client, the redirect URI and new users.
4. The same screen in dark mode.

== Changelog ==

= 0.2.1 =
* Deleting the plugin also removes sign-ins still in progress.
* The readme has installation steps, screenshots and where the source is.

= 0.2.0 =
* Settings → Social Login is a new screen, in light and dark, with the sign-in status and how many users are linked.
* A Settings link on the Plugins screen.
* The login button is moved to the top of the form by a script file instead of an inline script.
* For developers: the `nhrrob_social_login_signed_in` action after each sign-in.

= 0.1.0 =
* First version: Google sign-in on the login form and above the comment form, accounts for new Google users as an option, and the get-status ability.
