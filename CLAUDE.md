# NHR Social Login — project notes

Slug `nhrrob-social-login` · display name "NHR Social Login" · settings under **Settings → Social Login**.

Status: 0.2.1, public repo since 2026-11-24, not yet submitted to WordPress.org: the account may have one plugin awaiting review, and NHR Post Insights is (since 2026-11-23). Submit the `wp dist-archive` zip once Post Insights is approved. `.ai/PRD.md` holds the scope and what was left out on purpose, `docs/DECISIONS.md` why things are the way they are: read both before proposing a feature.

**Open work is in GitHub issues**, one per feature: `agent-ready` can be built alone, `robin` waits on Robin. When asked to continue, list the open issues, check the state (`gh`, `git`), and work from #10 (the WordPress.org submission, Robin's) and #11 onwards. Close an issue in the pull request that does it.

**Business documents stay out of this repo.** Prices, competitors, positioning and Pro plans live only in the private repo `nhrrob/nhrrob-social-login-pro`, in its `docs/`. This repo, its issues and its pull requests are public: at most they say that a paid add-on exists.

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
  Admin.php      Settings → Social Login: mounts the React app, the Settings link on the Plugins screen
  Rest.php       GET/POST nhrrob-social-login/v1/settings (manage_options; never the secret)
  Abilities.php  nhrrob-social-login/get-status (read-only)
assets/login.css the Google button, on the login page and above the comment fields
assets/login.js  moves the button to the top of the login form
admin/src/       the settings app (React, @wordpress/scripts): app.js, screens/Setup.js, components/ (shell from Issue Reporter)
admin/build/     compiled, committed
docs/            DECISIONS.md (one row per decision), notes/YYYY-MM-DD.md (one per working session)
tests/           PHPUnit + WP_Mock
.github/workflows/php.yml   PHPCS, then per PHP version: syntax, unit tests, smoke test
.github/workflows/plugin-check.yml  Plugin Check on the files the zip holds
.github/workflows/deploy-on-pushing-a-new-tag.yml  a v<version> tag goes to WordPress.org
.github/workflows/deploy-readme-asset-on-pushing-on-trunk.yml  a push to trunk updates the readme and listing images
.github/ci/smoke.php        runs every read-only ability inside a real WordPress
```

## Hooks for add-ons

- `do_action( 'nhrrob_social_login_signed_in', $user, $context )` after a social sign-in: `provider`, `created`, `redirect_to`.
- `do_action( 'nhrrob_social_login_enqueue', $handle )` after the app's script; the add-on's script adds `{ groups, screens }` through the `nhrrobSocialLogin.app` JS filter.

These stay as they are once an add-on uses them.

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
npm run lint:js && npm run build   # the settings app; commit admin/build
wp eval-file .github/ci/smoke.php   # inside a WordPress install with the plugin active
```

**PHP support: 7.4 and every later version.** `composer.json` pins `config.platform.php` to 7.4.33.

`vendor/autoload.php` and `vendor/composer/*` are committed as the **production (`--no-dev`) autoloader**. Run `composer install --no-dev` before committing anything under `vendor/`.

The codebase is PHPCS-clean, so any new violation is yours; fix it rather than widening `phpcs.xml`.

## Trying it locally

The dev copy is in `~/Sites/nhrrob-dev/wp-content/plugins/`. Without a real Google client, answer `https://oauth2.googleapis.com/token` with a temporary `pre_http_request` filter in a mu-plugin that returns an ID token with the sign-in's nonce, and delete it afterwards. With a real client, register `https://nhrrob-dev.test/wp-admin/admin-post.php?action=nhrrob_social_login` as a redirect URI.

## Releases

Releases are made by hand, as in Secure and Post Insights; nothing is tagged on a version change.

1. On a branch: raise `Version` in the header (and the class constant), `Stable tag` and `package.json`'s `version`, and add a `= x.y.z =` section under `== Changelog ==` in `readme.txt`. Merge it into `main`.
2. Tag `main` and push the tag: `git tag v<version> && git push origin v<version>`. The tag push runs `deploy-on-pushing-a-new-tag.yml`, which sends the version to WordPress.org with the `SVN_USERNAME` and `SVN_PASSWORD` secrets and, in a separate job, makes the GitHub release with that version's changelog from `readme.txt`; don't make one by hand. Until WordPress.org approves the plugin and the secrets are set, the deploy job fails; the tag and the GitHub release still work for the blog.
3. To change only the readme or the listing's images, push to `trunk`. Bring `trunk` up to `main` after each release.

The blog (`nhrrob/nazmulrobin_blog`) requires the plugin by these tags with Composer, so a release reaches it in Dependabot's next pull request.

## WordPress.org listing

`.wordpress-org/` holds the icon, banners and screenshots. The icon and banners are made from `docs/wporg/icon.html` and `banner.html` with `node docs/wporg/render.mjs` (Playwright). Screenshots come from the local dev site with sample data, never from production, one of them in dark mode.
