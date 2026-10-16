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

## Left out on purpose (for now)

- Providers other than Google (GitHub, Facebook, a code by email or phone). The flow is in `Login`, the provider in `Google`, so another provider is a new class.
- Unlinking a Google account from the profile screen.
- Changing the role of new accounts.
- A shortcode or block for the button.
