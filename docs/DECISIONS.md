# Decisions

Why NHR Social Login is the way it is, and whether each choice can be
undone. One row per decision, newest last. A pull request that makes a
decision adds its row; reversing one strikes the old row and adds a new one.
The reasons behind the first version are in [PRD.md](../.ai/PRD.md), "Decisions and why".

| Date | Decision | Why | Can it be undone? | Who |
| --- | --- | --- | --- | --- |
| 2026-10-16 | Account creation is the plugin's own setting, never "Anyone can register"; new accounts are Subscribers | Keeps WordPress's registration form closed to bots; a verified Google address is far harder to fake | Yes | Robin |
| 2026-10-16 | Accounts are linked by Google's `sub`, never relinked to another `sub` | An email address can change hands; the `sub` never does | Hard: existing links would need moving | Robin |
| 2026-10-16 | The ID token's signature is not checked; issuer, audience, expiry and nonce are | It comes straight from Google's token endpoint over TLS, which OpenID Connect allows | Yes | Robin |
| 2026-11-24 | Settings → Social Login is a React app with the shell of the other nhrrob plugins (Issue Reporter's "Aurora"), light and dark, closing #2 | Robin's rule for every plugin's admin screen; Pro adds its screens to the same app | Yes | Robin's rule |
| 2026-11-24 | The free plugin fires `nhrrob_social_login_signed_in( $user, $context )` and `nhrrob_social_login_enqueue`, and the app takes screens through the `nhrrobSocialLogin.app` JS filter | What an add-on needs to count sign-ins and show its screens, without reaching into the plugin | Hard once an add-on uses them: they stay as they are | Claude |
| 2026-11-24 | The login button is moved to the top of the form by `assets/login.js`, enqueued, instead of an inline script | WordPress.org's Plugin Check flags scripts printed inline | Yes | Claude |
| 2026-11-24 | The repo goes public and the plugin to WordPress.org; business documents live only in the private Pro repo | Robin's rule for every plugin: the free repo is public | Making it private again does not unpublish what was seen | Robin |
| 2026-11-24 | Releases by hand (a GitHub release makes the tag), deployed to WordPress.org by the tag; `release.yml` that tagged on a version change is removed | The same as Secure and Post Insights; a tag is now also a WordPress.org release | Yes | Robin's rule |
| 2026-11-24 | `package.json` and `admin/src` ship in the zip | WordPress.org asks for the source of built code | Yes | Claude |
| 2026-11-24 | A pushed tag `v<version>` is the whole release: the tag push deploys to WordPress.org and makes the GitHub release, which is no longer made by hand | Robin: fewer manual steps, the same in every plugin | Yes | Robin |
| 2026-11-24 | Add-ons get two filters, `nhrrob_social_login_button_extra` (markup after the button, through a fixed `wp_kses()` list) and `nhrrob_social_login_start_data` (data kept through the sign-in, back as `data` in the context), instead of a button an add-on draws itself | An opt-in checkbox next to the button has to reach the sign-in it starts; a narrow allow-list keeps add-on markup from printing anything else | Yes, but they stay once an add-on uses them | Claude |
