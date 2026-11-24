=== NHR Social Login ===
Contributors: nhrrob
Tags: social login, google login, login, comments, magic link
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Log in, and comment, with Google, Microsoft, Apple, GitHub, Facebook, X, LinkedIn or a link by email.

== Description ==
NHR Social Login adds "Continue with Google" (and Microsoft, Apple, GitHub, Facebook, X and LinkedIn) to the login form and above the comment form, so nobody has to type a password, or a name and email for every comment.

### How it works
- **Login form**: the buttons at the top of the form. It works on a moved login page too, such as the one NHR Secure sets up.
- **Comment form**: a visitor who is not logged in sees the buttons, and comes back to the same post, logged in.
- **Who gets in**: the user already linked to that account; otherwise the user with its email address, which is then linked; otherwise, when **New users** is on, a new account. Only email addresses the provider has verified are accepted.
- **Registration stays closed**: new accounts are made only through the plugin. WordPress's own registration form ("Anyone can register" under Settings → General) can stay off, which keeps it away from bots.
- **Two-factor**: a social sign-in is not asked for another code, since the provider has already checked the account. WordPress's login event still fires, so activity logs and login alerts record it.

### Everything in it
- **Seven providers**: Google, Microsoft (personal, work and school accounts), Apple, GitHub, Facebook, X and LinkedIn, each with its own on/off, credentials (best kept in `wp-config.php`) and redirect URI.
- **Sign-in links by email**, for readers without an account anywhere: a link that works once, for 15 minutes, with limits per address and per IP address.
- **Google One Tap**, on posts or on every page.
- **Profile screen**: see, link and unlink sign-in accounts, as long as another way in is left.
- **Role of new accounts**: Subscriber by default; roles that can write posts only after a warning, and never a role that manages the site or its users.
- **Redirects** after logging in and after signing up.
- **Allowed email domains**, as a setting or the `NHRROB_SOCIAL_LOGIN_ALLOWED_DOMAINS` constant.
- **The provider's picture as the avatar** for users without a Gravatar.
- **A verified mark** on comments from users who signed in with a provider.
- **An email to you** about each new account.
- **Anywhere**: the "Social login buttons" block and the `[nhrrob_social_login]` shortcode, with `label` and `redirect`.
- **WooCommerce**: the buttons on My account and at checkout; new accounts get the Customer role.
- **Settings → Social Login**: a screen in light and dark for all of it.

### For AI agents and MCP clients
On WordPress 6.9 and later the plugin registers one read-only ability, `nhrrob-social-login/get-status`: which providers are on and set up, their redirect URIs, the options, and how many users are linked. It never returns a secret, an email address or a username.

== Installation ==
1. Install and activate the plugin from Plugins → Add New, or upload the zip under Plugins → Add New → Upload Plugin.
2. Go to **Settings → Social Login → Providers** and switch on a provider. Its card shows the redirect URI and links to the provider's console.
3. At the provider, create an app or OAuth client and add that redirect URI. For Google: APIs & Services → Credentials, an OAuth client ID of type "Web application", with `https://your-site/wp-admin/admin-post.php?action=nhrrob_social_login`. The other providers use `https://your-site/nhrrob-social-login/callback/<provider>/`.
4. Add its credentials to `wp-config.php`, for example:
   `define( 'NHRROB_SOCIAL_LOGIN_GOOGLE_CLIENT_ID', '...' );`
   `define( 'NHRROB_SOCIAL_LOGIN_GOOGLE_CLIENT_SECRET', '...' );`
   Each card shows its constants. You can also type them on the screen, which stores them in the database.
5. To let new visitors comment as themselves, turn on **New users** under **Accounts**.

The buttons appear once a provider is on and has its credentials.

== External services ==
The plugin talks to a sign-in provider only when someone presses its button, and only to the providers you switch on. The person's browser goes to the provider with this site's client ID and redirect URI; the provider sends them back with a one-time code, which the server exchanges, with the client ID and secret, for the account's ID, name, email address, whether the address is verified, and for some providers a picture address.

- **Google** (Google LLC): accounts.google.com, oauth2.googleapis.com. [Terms](https://policies.google.com/terms), [Privacy](https://policies.google.com/privacy).
- **Google One Tap**, when switched on: Google's script from accounts.google.com/gsi/client loads on the pages where it shows, for visitors who are not logged in; Google's public keys are fetched from www.googleapis.com to check a token. Same terms.
- **Microsoft** (Microsoft Corporation): login.microsoftonline.com. [Terms](https://www.microsoft.com/servicesagreement), [Privacy](https://privacy.microsoft.com/privacystatement).
- **Apple** (Apple Inc.): appleid.apple.com. [Terms](https://www.apple.com/legal/internet-services/terms/site.html), [Privacy](https://www.apple.com/legal/privacy/).
- **GitHub** (GitHub, Inc.): github.com, api.github.com. [Terms](https://docs.github.com/site-policy/github-terms/github-terms-of-service), [Privacy](https://docs.github.com/site-policy/privacy-policies/github-general-privacy-statement).
- **Facebook** (Meta Platforms, Inc.): www.facebook.com, graph.facebook.com. [Terms](https://www.facebook.com/terms.php), [Privacy](https://www.facebook.com/privacy/policy/).
- **X** (X Corp.): x.com, api.x.com. [Terms](https://x.com/tos), [Privacy](https://x.com/privacy).
- **LinkedIn** (LinkedIn Corporation): www.linkedin.com, api.linkedin.com. [Terms](https://www.linkedin.com/legal/user-agreement), [Privacy](https://www.linkedin.com/legal/privacy-policy).
- **Gravatar** (Automattic Inc.), when the provider's picture is used as the avatar: once per sign-in, the server asks gravatar.com, with a hash of the email address, whether the user has a Gravatar. [Terms](https://wordpress.com/tos/), [Privacy](https://automattic.com/privacy/).

Pictures from Google, GitHub and X are shown from those services' own addresses. Nothing is sent to any of them on other pages or for visitors who do not press a button.

== Frequently Asked Questions ==

= Can someone take over an account by using an account with its email address? =
Only with an address the provider has verified, which means they control it. A user already linked to one account at a provider is never linked to a second one there; that sign-in is refused. For Microsoft work and school accounts the address is taken only with the `xms_edov` claim, which you add as an optional claim in the app registration, since an organisation can type any address there.

= What is stored? =
The settings in one option. For each linked user, the provider account's ID in user meta (`nhrrob_social_login_<provider>_sub`), and the picture address. Sign-ins and sign-in links in progress are kept for 10 and 15 minutes in transients, with a cookie that ties them to the browser.

= What is removed when I delete the plugin? =
The settings, sign-ins in progress, the links to provider accounts and the pictures. User accounts stay.

= Where is the source code? =
On GitHub: https://github.com/nhrrob/nhrrob-social-login. The settings screen and the block are built from `admin/src` with `npm install` and `npm run build`; both the source and the build are in the plugin.

== Screenshots ==
1. The login form with all seven providers and "Email me a sign-in link".
2. The buttons above the comment form, and the verified mark after a commenter's name.
3. Settings → Social Login → Providers: each provider's switch, redirect URI and credentials.
4. Accounts, in dark mode: new users, their role, redirects and allowed email domains.
5. Extras: Google One Tap, sign-in links by email, the avatar, the verified mark and the email about new accounts.
6. "Sign-in accounts" on the profile screen: link and unlink.

== Changelog ==

= 0.3.0 =
* Microsoft, Apple, GitHub, Facebook, X and LinkedIn, next to Google, each with its own card on the settings screen.
* Sign-in links by email, and Google One Tap.
* Link and unlink sign-in accounts on the profile screen.
* The role of new accounts, redirects after logging in and signing up, and allowed email domains.
* The provider's picture as the avatar, a verified mark on comments, and an email about new accounts.
* The "Social login buttons" block and the `[nhrrob_social_login]` shortcode.
* WooCommerce: the buttons on My account and at checkout.
* The settings screen has three sections: Providers, Accounts and Extras.
* For developers: `nhrrob_social_login_signed_in` gets `source` in its context, and the `nhrrob_social_login_domain_allowed` filter.
* For developers: the `nhrrob_social_login_button_extra` filter adds a checkbox or a note after the sign-in button, and the `nhrrob_social_login_start_data` filter keeps data for the length of a sign-in, passed to `nhrrob_social_login_signed_in` as `data`.

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
