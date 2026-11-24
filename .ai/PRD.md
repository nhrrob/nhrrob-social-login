# NHR Social Login: product notes

## Who it is for

Site owners who want visitors to log in, and above all to comment, without a password or typing a name and email every time. First site: nazmulrobin.com/blog, where readers comment as guests.

## What it does (0.1)

1. "Continue with Google" at the top of the login form, moved there by a small script (without it, it stays after the password field and still works).
2. "Continue with Google to comment" above the comment fields, for visitors who are not logged in, when new accounts are allowed. It comes back to the post, logged in.
3. A Google identity signs in as the linked user, else the user with its verified email (then linked), else, when allowed, a new Subscriber.
4. Settings → Social Login: client ID and secret (wp-config.php constants first), "Create an account", the redirect URI to copy.
5. A read-only ability, `get-status`.

## Decisions and why

| Decision | Why |
|---|---|
| Account creation is the plugin's own setting, not "Anyone can register" | Opening WordPress's registration form invites bot sign-ups. A Google account with a verified email is far harder to fake. |
| New users are Subscribers | The least that lets someone comment as themselves. |
| No second factor after Google | Google already checked the account, with its own 2-step verification. `wp_login` still fires for logs and alerts. |
| Callback through admin-post.php | Answers logged-out visitors on every site, including one whose login page is moved (NHR Secure keeps admin-post.php open). |
| The ID token's signature is not verified | It comes straight from Google's token endpoint over TLS, which OpenID Connect allows; its issuer, audience, expiry and nonce are checked. Saves fetching Google's keys. |
| A state cookie, PKCE and a nonce | The answer is accepted once, only in the browser that started it, and only for that sign-in. |
| Linked by Google's `sub`, not by email | An email address can change hands; the `sub` never does. A user linked to one Google account is never relinked to another. |
| A classic settings screen | Three fields. A React app like the other plugins' screens can come with more providers. |

## What 0.3.0 adds

The roadmap that the market check put in the free plugin (every item is free in Nextend, miniOrange or rtCamp's plugin), issues #11 to #22:

1. Microsoft, Apple, GitHub, Facebook, X and LinkedIn next to Google. A provider is a class in `includes/Providers/`; `Login` stays the flow.
2. Link and unlink sign-in accounts on the profile screen, while another way in is left.
3. The provider's picture as the avatar for users without a Gravatar.
4. The role of new accounts, with a warning for roles that can write posts.
5. Redirects after logging in and after signing up, from the login form.
6. The "Social login buttons" block and the `[nhrrob_social_login]` shortcode.
7. Google One Tap, off by default.
8. Allowed email domains (setting or constant).
9. The buttons on WooCommerce's My account and checkout.
10. An email to the owner about new accounts.
11. Sign-in links by email.
12. A verified mark on comments.

Settings → Social Login has three screens: Providers, Accounts, Extras.

## Left out on purpose (for now)

- Providers beyond these seven (Discord, Twitch, Yahoo, a code by phone). Another provider is one more class.
- Passkeys: NHR Secure's field.
- Asking for an email address or username when a provider gives none: such sign-ins are refused.
- The paid add-on's features (see its own repo).
