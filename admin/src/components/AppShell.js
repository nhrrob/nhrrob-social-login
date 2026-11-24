/**
 * App shell: a sidebar with the sections grouped, the Google status card,
 * and a top bar with where you are and the theme switch.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { Icon } from './ui';

const P = 'nhrrob-social-login-';
const THEME_KEY = 'nhrrob-social-login-theme';

function remember( key, value ) {
	try {
		window.localStorage.setItem( key, value );
	} catch ( e ) {
		/* storage unavailable — the choice lasts for this page only */
	}
}

/**
 * @param {Object}   root0            Props.
 * @param {Array}    root0.groups     Rows of { label, items: [ { id, label, icon, count } ] }.
 * @param {string}   root0.active     The open section.
 * @param {Function} root0.onNavigate Called with a section id.
 * @param {string}   root0.title      The app's name.
 * @param {string}   root0.site       The site's name, under the app's name.
 * @param {Object}   root0.connection { ok, line } for the status card at the bottom.
 * @param {Object}   root0.children   The open screen.
 * @return {Object} Shell element.
 */
export default function AppShell( {
	groups,
	active,
	onNavigate,
	title,
	site,
	connection,
	children,
} ) {
	const [ theme, setTheme ] = useState( '' ); // '' follows the system setting

	useEffect( () => {
		try {
			const saved = window.localStorage.getItem( THEME_KEY );
			if ( saved === 'dark' || saved === 'light' ) {
				setTheme( saved );
			}
		} catch ( e ) {
			/* storage unavailable */
		}
	}, [] );

	const prefersDark =
		window.matchMedia &&
		window.matchMedia( '(prefers-color-scheme: dark)' ).matches;
	const isDark = theme ? theme === 'dark' : prefersDark;

	let current = null;
	groups.forEach( ( g ) =>
		g.items.forEach( ( i ) => {
			if ( i.id === active ) {
				current = { group: g.label, label: i.label };
			}
		} )
	);

	return (
		<div className={ P + 'app' } data-theme={ theme || undefined }>
			<aside className={ P + 'side' }>
				<div className={ P + 'brand' }>
					<span className={ P + 'brand__mark' }>
						<Icon name="key" />
					</span>
					<span className={ P + 'brand__text' }>
						<strong>{ title }</strong>
						{ site && <span title={ site }>{ site }</span> }
					</span>
				</div>

				{ groups.map( ( g ) => (
					<nav
						key={ g.label }
						className={ P + 'nav' }
						aria-label={ g.label }
					>
						<span className={ P + 'nav__label' }>{ g.label }</span>
						{ g.items.map( ( m ) => (
							<button
								key={ m.id }
								type="button"
								className={
									P +
									'nav__item' +
									( m.id === active ? ' is-active' : '' )
								}
								aria-current={
									m.id === active ? 'page' : undefined
								}
								onClick={ () => onNavigate( m.id ) }
							>
								<Icon name={ m.icon } size={ 17 } />
								<span className={ P + 'nav__text' }>
									{ m.label }
								</span>
								{ m.count > 0 && (
									<span className={ P + 'nav__count' }>
										{ m.count }
									</span>
								) }
							</button>
						) ) }
					</nav>
				) ) }

				{ connection && (
					<button
						type="button"
						className={ P + 'conn' }
						onClick={ () => onNavigate( 'setup' ) }
					>
						<span className={ P + 'conn__head' }>
							<span
								className={
									P +
									'conn__dot' +
									( connection.ok ? ' is-ok' : '' )
								}
							/>
							{ connection.ok
								? __(
										'Google sign-in on',
										'nhrrob-social-login'
								  )
								: __(
										'Google not set up',
										'nhrrob-social-login'
								  ) }
						</span>
						<span className={ P + 'conn__line' }>
							{ connection.line }
						</span>
					</button>
				) }
			</aside>

			<div className={ P + 'main' }>
				<div className={ P + 'top' }>
					{ current && (
						<nav
							className={ P + 'crumbs' }
							aria-label={ __(
								'Breadcrumb',
								'nhrrob-social-login'
							) }
						>
							<span>{ current.group }</span>
							<span aria-hidden="true">/</span>
							<span className={ P + 'crumbs__here' }>
								{ current.label }
							</span>
						</nav>
					) }
					<span className={ P + 'top__spacer' } />
					<button
						type="button"
						className={ P + 'iconbtn' }
						aria-label={
							isDark
								? __(
										'Switch to light theme',
										'nhrrob-social-login'
								  )
								: __(
										'Switch to dark theme',
										'nhrrob-social-login'
								  )
						}
						onClick={ () => {
							const next = isDark ? 'light' : 'dark';
							remember( THEME_KEY, next );
							setTheme( next );
						} }
					>
						<Icon name={ isDark ? 'sun' : 'moon' } size={ 16 } />
					</button>
				</div>
				<main className={ P + 'content' }>{ children }</main>
			</div>
		</div>
	);
}
