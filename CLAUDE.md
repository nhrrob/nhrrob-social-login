# NHR Social Login — project notes

Slug `nhrrob-social-login` · display name "NHR Social Login" · settings under **Settings → Social Login**.

Private repository, version 0.x, not on WordPress.org. `.ai/PRD.md` holds the scope and what was left out on purpose: read it before proposing a feature.

## Rules that are specific to this plugin

1. **The client secret never leaves the server**: no page, ability or REST answer contains it. The settings screen never fills the secret field; an empty field keeps the stored one.
2. **Only verified Google email addresses** are accepted, and an account is linked by Google's `sub`. A user linked to one `sub` is never relinked to another (`linked_other`).
3. **A sign-in is accepted once, in the browser that started it**: state in an HttpOnly, SameSite=Lax cookie and a transient named after its hash, PKCE (S256), and the nonce in the ID token. All three are checked in `Login::finish()` and `Google::check_claims()`.
4. **New accounts only through the plugin's own setting**, as Subscribers. Never through "Anyone can register", which can stay off.
5. **Redirects**: only `wp_safe_redirect()` after `wp_validate_redirect()`, with an explicit fallback for an empty address (dashboard for authors, home for everyone else).
6. **Identifiers**: namespace `NHRRob\SocialLogin\`, class `NHRRob_Social_Login`, constants `NHRROB_SOCIAL_LOGIN_*`, option and meta prefix `nhrrob_social_login_`, CSS prefix `nhrrob-social-login`, text domain `nhrrob-social-login`.

## Layout

```
nhrrob-social-login.php   main class, constants; Login and Abilities always, Admin in the dashboard only
uninstall.php             deletes the option, and the Google links from user meta
includes/
  Settings.php   client ID and secret (constants first), create_accounts, redirect_uri()
  Login.php      the buttons, and admin-post.php?action=nhrrob_social_login: start, finish, errors
  Google.php     auth_url(), identity() (token exchange), check_claims() (pure), PKCE helpers
  Accounts.php   user_for(): linked user, else by email, else a new Subscriber
  Admin.php      Settings → Social Login (Settings API)
  Abilities.php  nhrrob-social-login/get-status (read-only)
assets/login.css the Google button, on the login page and above the comment fields
tests/           PHPUnit + WP_Mock
.github/workflows/php.yml   PHPCS, then per PHP version: syntax, unit tests, smoke test
.github/workflows/release.yml  when Version changes on main: tag v<version> and a GitHub release with readme.txt's changelog
.github/ci/smoke.php        runs every read-only ability inside a real WordPress
```

## Stored data

| Where | Holds |
|---|---|
| option `nhrrob_social_login_settings` | `google_client_id`, `google_client_secret` (when not in wp-config.php), `create_accounts` |
| user meta `nhrrob_social_login_google_sub` | the linked Google account's `sub` |
| transient `nhrrob_social_login_<sha256 of state>`, 10 minutes | a sign-in in progress: nonce, PKCE verifier, redirect_to |
| cookie `nhrrob_social_login_state`, 10 minutes | the state, deleted when the sign-in finishes |

## Commands

```
composer install            # dev toolchain (phpcs + phpunit)
composer install --no-dev   # production autoloader (what is committed under vendor/)
composer run phpcs          # WordPress Coding Standards + PHPCompatibilityWP
composer run test:unit      # PHPUnit (WP_Mock, no DB, no network)
wp eval-file .github/ci/smoke.php   # inside a WordPress install with the plugin active
```

**PHP support: 7.4 and every later version.** `composer.json` pins `config.platform.php` to 7.4.33.

`vendor/autoload.php` and `vendor/composer/*` are committed as the **production (`--no-dev`) autoloader**. Run `composer install --no-dev` before committing anything under `vendor/`.

The codebase is PHPCS-clean, so any new violation is yours; fix it rather than widening `phpcs.xml`.

## Trying it locally

The dev copy is in `~/Sites/nhrrob-dev/wp-content/plugins/`. Without a real Google client, answer `https://oauth2.googleapis.com/token` with a temporary `pre_http_request` filter in a mu-plugin that returns an ID token with the sign-in's nonce, and delete it afterwards. With a real client, register `https://nhrrob-dev.test/wp-admin/admin-post.php?action=nhrrob_social_login` as a redirect URI.

## Releases

Raise `Version` in the header (and the class constant) and `Stable tag`, and add a `= x.y.z =` section under `== Changelog ==` in `readme.txt`. Merging that into `main` runs `release.yml`, which tags `v<version>` and makes the GitHub release. The blog (`nhrrob/nazmulrobin_blog`) requires the plugin by these tags with Composer, so a release reaches it in Dependabot's next pull request.
