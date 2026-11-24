# NHR Social Login — project notes

Slug `nhrrob-social-login` · display name "NHR Social Login" · settings under **Settings → Social Login**.

Status: 0.3.0 (seven providers, sign-in links by email, One Tap and the rest of the first roadmap), public repo since 2026-11-24, not yet submitted to WordPress.org: the account may have one plugin awaiting review, and NHR Post Insights is (since 2026-11-23). Submit the `wp dist-archive` zip once Post Insights is approved (#10). `.ai/PRD.md` holds the scope and what was left out on purpose, `docs/DECISIONS.md` why things are the way they are: read both before proposing a feature.

**Open work is in GitHub issues**, one per feature: `agent-ready` can be built alone, `robin` waits on Robin. When asked to continue, list the open issues, check the state (`gh`, `git`), and work from #10 (the WordPress.org submission, Robin's) and #11 onwards. Close an issue in the pull request that does it.

**Business documents stay out of this repo.** Prices, competitors, positioning and Pro plans live only in the private repo `nhrrob/nhrrob-social-login-pro`, in its `docs/`. This repo, its issues and its pull requests are public: at most they say that a paid add-on exists.

## Rules that are specific to this plugin

1. **Secrets never leave the server**: no page, ability or REST answer contains a client secret or Apple's private key (fields marked `secret` in a provider's `fields()`). The settings screen never fills a secret field; an empty field keeps the stored one.
2. **Only verified email addresses** are accepted (each provider decides in its class how it knows; Microsoft work accounts need `xms_edov`), and an account is linked by the provider's account ID in `nhrrob_social_login_<provider>_sub` (Google's key is unchanged). A user linked to one ID at a provider is never relinked to another (`linked_other`).
3. **A sign-in is accepted once, in the browser that started it**: state in an HttpOnly, SameSite=Lax cookie and a transient named after its hash, plus PKCE (S256) and an ID-token nonce wherever the provider takes them. Checked in `Login::finish()` and each provider's `check_claims()`. Apple answers with a cross-site POST, which drops the Lax cookie, so `Login::posted_back()` turns it into a 303 GET. One Tap's token comes through the browser, so its RS256 signature is checked against Google's keys, and its nonce against a cookie. A sign-in link works once, after a button press, in the browser that opened it.
4. **New accounts only through the plugin's own setting**, as Subscribers unless the owner picks another role (`Accounts::safe_role()`: never a role with `manage_options`, `promote_users`, `edit_users` or `create_users`; one with `edit_posts` only after the warning is ticked). Never through "Anyone can register", which can stay off.
5. **Redirects**: only `wp_safe_redirect()` after `wp_validate_redirect()`, with an explicit fallback for an empty address (dashboard for authors, home for everyone else).
6. **Identifiers**: namespace `NHRRob\SocialLogin\`, class `NHRRob_Social_Login`, constants `NHRROB_SOCIAL_LOGIN_*`, option and meta prefix `nhrrob_social_login_`, CSS prefix `nhrrob-social-login`, text domain `nhrrob-social-login`.

## Layout

```
nhrrob-social-login.php   main class, constants, FEATURES (one class each); Admin in the dashboard only
uninstall.php             deletes the option, transients, and the plugin's user meta
includes/
  Settings.php      every setting; a provider's fields from wp-config.php constants first
  Providers.php     the registry: all(), get(), active(), linked( $user_id )
  Providers/        Provider (base), Google, Microsoft, Apple, GitHub, Facebook, X, LinkedIn
  Jwt.php           base64url, claims, PKCE, ES256 signing (Apple), RS256 checking (One Tap)
  Login.php         admin-post.php?action=nhrrob_social_login: start every sign-in, finish Google's; log_in(), destination(), errors
  Callback.php      /nhrrob-social-login/callback/<provider>/ (a rewrite rule), where the other providers answer
  Accounts.php      user_for(): linked user, else by email, else a new account; domains, roles
  Buttons.php       the buttons on the login and comment forms; render() for everything else
  Profile.php       "Sign-in accounts" on the profile screen: link, unlink
  Avatar.php        the provider's picture for users without a Gravatar (asked once, at sign-in)
  Shortcode.php     [nhrrob_social_login] and the nhrrob-social-login/buttons block (server-rendered)
  OneTap.php        Google One Tap
  WooCommerce.php   the buttons on My account and checkout
  Notify.php        the email about new accounts
  MagicLink.php     wp-login.php?action=nhrrob_social_login_email: sign-in links by email
  VerifiedMark.php  the mark after a comment author's name
  Admin.php         Settings → Social Login: mounts the React app, the Settings link on the Plugins screen
  Rest.php          GET/POST nhrrob-social-login/v1/settings (manage_options; never a secret)
  Abilities.php     nhrrob-social-login/get-status (read-only)
assets/login.css  the buttons, the verified mark
assets/login.js   moves the buttons to the top of the login form
assets/onetap.js  One Tap's prompt and the post back
admin/src/        the settings app (React, @wordpress/scripts): app.js, screens/ (Providers, Accounts, Extras), components/ (shell from Issue Reporter), block/ (the block's editor script)
admin/build/      compiled, committed: index.* (the app), block.* (the block)
docs/             DECISIONS.md (one row per decision), notes/YYYY-MM-DD.md (one per working session)
tests/            PHPUnit + WP_Mock
.github/workflows/php.yml   PHPCS, then per PHP version: syntax, unit tests, smoke test
.github/workflows/plugin-check.yml  Plugin Check on the files the zip holds
.github/workflows/deploy-on-pushing-a-new-tag.yml  a v<version> tag goes to WordPress.org
.github/workflows/deploy-readme-asset-on-pushing-on-trunk.yml  a push to trunk updates the readme and listing images
.github/ci/smoke.php        runs every read-only ability inside a real WordPress
```

## Hooks for add-ons

- `do_action( 'nhrrob_social_login_signed_in', $user, $context )` after every sign-in: `provider` (or `email` for a sign-in link), `created`, `redirect_to`, `source` (login, comment, button, woocommerce, onetap; `source` since 0.3.0).
- `apply_filters( 'nhrrob_social_login_domain_allowed', $allowed, $email, $user )` to let an address in past the allowed domains.
- `do_action( 'nhrrob_social_login_enqueue', $handle )` after the app's script; the add-on's script adds `{ groups, screens }` through the `nhrrobSocialLogin.app` JS filter.

These stay as they are once an add-on uses them.

## Stored data

| Where | Holds |
|---|---|
| option `nhrrob_social_login_settings` | per provider `<id>_enabled` and its fields (`<id>_client_id`, `<id>_client_secret`; Apple `apple_team_id`, `apple_key_id`, `apple_private_key`) when not in wp-config.php; `create_accounts`, `new_user_role`, `allow_author_roles`, `login_redirect`, `signup_redirect`, `allowed_domains`, `use_avatar`, `one_tap`, `notify_admin`, `magic_link`, `verified_mark` |
| user meta `nhrrob_social_login_<provider>_sub` | the linked account's ID at that provider (Google: `nhrrob_social_login_google_sub`, as before) |
| user meta `nhrrob_social_login_picture`, `_gravatar` | the provider's picture address; whether Gravatar has an image (`yes`/`no`, asked once) |
| user meta `nhrrob_social_login_no_password` | set on accounts the plugin made, until they set a password: unlinking the last account is refused |
| transient `nhrrob_social_login_<sha256 of state>`, 10 minutes | a sign-in in progress: provider, nonce, PKCE verifier, redirect_to, source, link_user |
| transient `nhrrob_social_login_magic_<sha256 of token>`, 15 minutes | a sign-in link: email, redirect_to, source |
| transient `nhrrob_social_login_rate_<email|ip>_<hash>`, 1 hour | sign-in link requests counted |
| transient `nhrrob_social_login_google_keys` | Google's public keys, for One Tap, as long as Google says |
| cookies `nhrrob_social_login_state`, `_onetap`, `_email` | the state, One Tap's nonce, a sign-in link's hash; each deleted when used |

## Commands

```
composer install            # dev toolchain (phpcs + phpunit)
composer install --no-dev   # production autoloader (what is committed under vendor/)
composer run phpcs          # WordPress Coding Standards + PHPCompatibilityWP
composer run test:unit      # PHPUnit (WP_Mock, no DB, no network)
npm run lint:js && npm run build   # the settings app and the block; commit admin/build
wp eval-file .github/ci/smoke.php   # inside a WordPress install with the plugin active
```

**PHP support: 7.4 and every later version.** `composer.json` pins `config.platform.php` to 7.4.33.

`vendor/autoload.php` and `vendor/composer/*` are committed as the **production (`--no-dev`) autoloader**. Run `composer install --no-dev` before committing anything under `vendor/`.

The codebase is PHPCS-clean, so any new violation is yours; fix it rather than widening `phpcs.xml`.

## Trying it locally

The dev copy is in `~/Sites/nhrrob-dev/wp-content/plugins/`. Without real clients, answer the providers' server endpoints with a temporary `pre_http_request` filter in a mu-plugin (ID tokens carrying the sign-in's nonce), and their browser pages with Playwright routes; delete the mu-plugin afterwards. The dev site's login page is at `/access` (NHR Secure). With a real client, register the redirect URI its card shows.

## Releases

Releases are made by hand, as in Secure and Post Insights; nothing is tagged on a version change.

1. On a branch: raise `Version` in the header (and the class constant), `Stable tag` and `package.json`'s `version`, and add a `= x.y.z =` section under `== Changelog ==` in `readme.txt`. Merge it into `main`.
2. Tag `main` and push the tag: `git tag v<version> && git push origin v<version>`. The tag push runs `deploy-on-pushing-a-new-tag.yml`, which sends the version to WordPress.org with the `SVN_USERNAME` and `SVN_PASSWORD` secrets and, in a separate job, makes the GitHub release with that version's changelog from `readme.txt`; don't make one by hand. Until WordPress.org approves the plugin and the secrets are set, the deploy job fails; the tag and the GitHub release still work for the blog.
3. To change only the readme or the listing's images, push to `trunk`. Bring `trunk` up to `main` after each release.

The blog (`nhrrob/nazmulrobin_blog`) requires the plugin by these tags with Composer, so a release reaches it in Dependabot's next pull request.

## WordPress.org listing

`.wordpress-org/` holds the icon, banners and screenshots. The icon and banners are made from `docs/wporg/icon.html` and `banner.html` with `node docs/wporg/render.mjs` (Playwright). Screenshots come from the local dev site with sample data, never from production, one of them in dark mode.
