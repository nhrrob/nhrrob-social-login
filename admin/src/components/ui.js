/**
 * Shared building blocks: icons, cards, buttons, pills, fields, segmented
 * controls, switches and notes. Hand-rolled on purpose — no component or icon
 * library enters the bundle. Copied from NHR Issue Reporter.
 */
const P = 'nhrrob-social-login-';

const PATHS = {
	key: 'M15 7a4 4 0 11-3.9 4.9L3 20v-3h3v-3h3l1.1-1.1A4 4 0 0115 7zM16 10h.01',
	copy: 'M9 9h11v11H9zM5 15H4V4h11v1',
	chart: 'M4 20V10M10 20V4M16 20v-7M22 20H2',
	bug: 'M9 7.1V6a3 3 0 116 0v1.1M12 20c-3.3 0-6-2.7-6-6v-3a4 4 0 014-4h4a4 4 0 014 4v3c0 3.3-2.7 6-6 6zM12 20v-9M6 13H2M22 13h-4',
	moon: 'M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z',
	sun: 'M12 8a4 4 0 100 8 4 4 0 000-8zM12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4',
	plus: 'M12 5v14M5 12h14',
	send: 'M22 2L11 13M22 2l-7 20-4-9-9-4z',
	settings:
		'M12 15a3 3 0 100-6 3 3 0 000 6zM12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1',
	alert: 'M12 9v4M12 17h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z',
	info: 'M12 3a9 9 0 100 18 9 9 0 000-18zM12 11v5M12 8h.01',
	check: 'M20 6L9 17l-5-5',
	dot: 'M12 4a8 8 0 100 16 8 8 0 000-16zM12 12h.01',
	link: 'M10 13a5 5 0 007.5.5l3-3a5 5 0 00-7-7l-1.7 1.7M14 11a5 5 0 00-7.5-.5l-3 3a5 5 0 007 7l1.7-1.7',
	repo: 'M4 19.5A2.5 2.5 0 016.5 17H20V3H6.5A2.5 2.5 0 004 5.5v14z',
	plugin: 'M9 2v6M15 2v6M6 8h12v4a6 6 0 01-12 0V8zM12 18v4',
	theme: 'M12 3l9 5-9 5-9-5zM3 13l9 5 9-5',
	search: 'M11 4a7 7 0 100 14 7 7 0 000-14zM20 20l-4-4',
	external: 'M7 17L17 7M8 7h9v9',
	refresh: 'M21 12a9 9 0 11-3-6.7L21 8M21 3v5h-5',
	chevrons: 'M8 9l4-4 4 4M16 15l-4 4-4-4',
	upload: 'M12 16V4M7 9l5-5 5 5M4 20h16',
	x: 'M18 6L6 18M6 6l12 12',
	comment: 'M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z',
	lock: 'M6 11h12v10H6zM8 11V7a4 4 0 018 0v4',
	globe: 'M12 3a9 9 0 100 18 9 9 0 000-18zM3.5 9h17M3.5 15h17M12 3c2.5 2.5 3.5 5.5 3.5 9s-1 6.5-3.5 9c-2.5-2.5-3.5-5.5-3.5-9s1-6.5 3.5-9z',
	code: 'M8 9l-4 3 4 3M16 9l4 3-4 3',
	list: 'M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01',
	file: 'M14 3H6a2 2 0 00-2 2v14a2 2 0 002 2h12a2 2 0 002-2V9zM14 3v6h6',
	inbox: 'M22 12h-6l-2 3h-4l-2-3H2M5.5 5h13L22 12v6a2 2 0 01-2 2H4a2 2 0 01-2-2v-6z',
	widget: 'M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z',
	user: 'M20 21a8 8 0 00-16 0M12 13a4 4 0 100-8 4 4 0 000 8z',
	screen: 'M3 5h18v12H3zM8 21h8M12 17v4',
	clock: 'M12 3a9 9 0 100 18 9 9 0 000-18zM12 7v5l3 2',
	github: 'M9 19c-5 1.5-5-2.5-7-3m14 6v-3.9a3.4 3.4 0 00-.9-2.6c3.1-.4 6.4-1.5 6.4-7A5.4 5.4 0 0020 4.8 5 5 0 0019.9 1S18.7.6 16 2.5a13.4 13.4 0 00-7 0C6.3.6 5.1 1 5.1 1A5 5 0 005 4.8a5.4 5.4 0 00-1.5 3.7c0 5.4 3.3 6.6 6.4 7a3.4 3.4 0 00-.9 2.6V22',
};

export function Icon( { name, size = 18, className = '' } ) {
	return (
		<svg
			className={ P + 'icon ' + className }
			width={ size }
			height={ size }
			viewBox="0 0 24 24"
			fill="none"
			stroke="currentColor"
			strokeWidth="2"
			strokeLinecap="round"
			strokeLinejoin="round"
			aria-hidden="true"
		>
			<path d={ PATHS[ name ] || PATHS.bug } />
		</svg>
	);
}

export function ScreenHeader( { title, lede, actions } ) {
	return (
		<div className={ P + 'screen__head' }>
			<div className={ P + 'screen__text' }>
				<h1 className={ P + 'screen__title' }>{ title }</h1>
				{ lede && <p className={ P + 'screen__lede' }>{ lede }</p> }
			</div>
			{ actions && (
				<div className={ P + 'screen__actions' }>{ actions }</div>
			) }
		</div>
	);
}

/**
 * A card. With a title it gets a header row; `meta` and `actions` sit at its
 * right.
 *
 * @param {Object}  root0          Props.
 * @param {string}  root0.title    Heading.
 * @param {Object}  root0.meta     Muted text at the right of the heading.
 * @param {Object}  root0.actions  Controls at the right of the heading.
 * @param {boolean} root0.flush    No padding around the body.
 * @param {Object}  root0.children Body.
 * @return {Object} Card element.
 */
export function Panel( { title, meta, actions, flush, children } ) {
	return (
		<section className={ P + 'panel' }>
			{ ( title || meta || actions ) && (
				<header className={ P + 'panel__head' }>
					<h2 className={ P + 'panel__title' }>{ title }</h2>
					{ meta && (
						<span className={ P + 'panel__meta' }>{ meta }</span>
					) }
					{ actions }
				</header>
			) }
			<div className={ P + 'panel__body' + ( flush ? ' is-flush' : '' ) }>
				{ children }
			</div>
		</section>
	);
}

/**
 * A numbered part of a form card.
 *
 * @param {Object} root0          Props.
 * @param {number} root0.n        Its number.
 * @param {string} root0.title    Heading.
 * @param {string} root0.hint     Muted text beside the heading.
 * @param {Object} root0.children Body.
 * @return {Object} Section element.
 */
export function Step( { n, title, hint, children } ) {
	return (
		<div className={ P + 'step' }>
			<div className={ P + 'step__head' }>
				<span className={ P + 'step__n' } aria-hidden="true">
					{ n }
				</span>
				<h2 className={ P + 'step__title' }>{ title }</h2>
				{ hint && <span className={ P + 'step__hint' }>{ hint }</span> }
			</div>
			{ children }
		</div>
	);
}

export function Pill( { tone = 'off', children } ) {
	return (
		<span className={ P + 'pill ' + P + 'pill--' + tone }>
			{ children }
		</span>
	);
}

export function Button( {
	variant = 'secondary',
	small,
	className = '',
	...props
} ) {
	return (
		<button
			type="button"
			className={
				P +
				'btn ' +
				P +
				'btn--' +
				variant +
				( small ? ' ' + P + 'btn--sm' : '' ) +
				' ' +
				className
			}
			{ ...props }
		/>
	);
}

/**
 * Buttons that pick one of a few values, drawn as a track.
 *
 * @param {Object}   root0          Props.
 * @param {string}   root0.label    Accessible name of the group.
 * @param {Array}    root0.options  Rows of { value, label, count }.
 * @param {string}   root0.value    The chosen value.
 * @param {Function} root0.onChange Called with the new value.
 * @return {Object} Group element.
 */
export function Segmented( { label, options, value, onChange } ) {
	return (
		<div className={ P + 'seg' } role="group" aria-label={ label }>
			{ options.map( ( o ) => (
				<button
					key={ o.value }
					type="button"
					className={
						P + 'seg__btn' + ( o.value === value ? ' is-on' : '' )
					}
					aria-pressed={ o.value === value }
					onClick={ () => onChange( o.value ) }
				>
					{ o.label }
					{ o.count !== undefined && (
						<span className={ P + 'seg__count' }>{ o.count }</span>
					) }
				</button>
			) ) }
		</div>
	);
}

export function Switch( { checked, onChange, label } ) {
	return (
		<button
			type="button"
			role="switch"
			aria-checked={ checked }
			aria-label={ label }
			className={ P + 'switch' + ( checked ? ' is-on' : '' ) }
			onClick={ () => onChange( ! checked ) }
		/>
	);
}

export function SearchBox( { label, value, onChange, placeholder } ) {
	return (
		<div className={ P + 'search' }>
			<Icon name="search" size={ 15 } />
			<input
				type="search"
				aria-label={ label }
				placeholder={ placeholder || label }
				value={ value }
				onChange={ ( e ) => onChange( e.target.value ) }
			/>
		</div>
	);
}

export function Stat( { icon, tone, label, value, note } ) {
	return (
		<div className={ P + 'stat' }>
			<span className={ P + 'tile ' + P + 'tile--' + tone }>
				<Icon name={ icon } size={ 19 } />
			</span>
			<span className={ P + 'stat__text' }>
				<span className={ P + 'stat__label' }>{ label }</span>
				<span className={ P + 'stat__value' }>{ value }</span>
				{ note && <span className={ P + 'stat__note' }>{ note }</span> }
			</span>
		</div>
	);
}

/**
 * A labelled control with an optional help line under it.
 *
 * @param {Object} root0          Props.
 * @param {string} root0.id       Id of the control the label belongs to.
 * @param {string} root0.label    Label.
 * @param {string} root0.help     Explanation under the control.
 * @param {Object} root0.children The control.
 * @return {Object} Field element.
 */
export function Field( { id, label, help, children } ) {
	return (
		<div className={ P + 'field' }>
			<label className={ P + 'field__label' } htmlFor={ id }>
				{ label }
			</label>
			{ children }
			{ help && <p className={ P + 'field__help' }>{ help }</p> }
		</div>
	);
}

export function Note( { tone = 'info', children } ) {
	return (
		<div className={ P + 'note ' + P + 'note--' + tone }>
			<Icon name={ tone === 'warn' ? 'alert' : 'info' } size={ 16 } />
			<div>{ children }</div>
		</div>
	);
}

export function Loading() {
	return <div className={ P + 'loading' } aria-busy="true" />;
}

/**
 * A link that opens in a new tab.
 *
 * @param {Object} root0           Props.
 * @param {string} root0.href      Address.
 * @param {string} root0.className Extra class.
 * @param {Object} root0.children  Link text.
 * @return {Object} Link element.
 */
export function External( { href, className = '', children } ) {
	return (
		<a
			className={ P + 'external ' + className }
			href={ href }
			target="_blank"
			rel="noopener noreferrer"
		>
			{ children }
			<Icon name="external" size={ 13 } />
		</a>
	);
}
