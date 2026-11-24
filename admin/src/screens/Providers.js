/**
 * Providers: one card per sign-in provider, with its on/off, the redirect
 * URI to register, and its credentials. Secrets are never sent to the
 * browser; only whether one is saved.
 */
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { useToast } from '../components/ToastProvider';
import {
	Button,
	External,
	Field,
	Icon,
	Note,
	Pill,
	ScreenHeader,
	Stat,
	Switch,
} from '../components/ui';
import { copy, useSave } from './save';

const P = 'nhrrob-social-login-';

/**
 * The form's starting values for one provider: on/off and the non-secret fields.
 *
 * @param {Object} provider Provider from the settings route.
 * @return {Object} Values.
 */
function initial( provider ) {
	const values = { enabled: provider.enabled, remove: [] };
	provider.fields.forEach( ( f ) => {
		values[ f.name ] = f.secret ? '' : f.value;
	} );
	return values;
}

function initialAll( providers ) {
	const all = {};
	providers.forEach( ( p ) => {
		all[ p.id ] = initial( p );
	} );
	return all;
}

function FieldInput( { provider, field, value, removing, onChange } ) {
	const id = P + provider.id + '-' + field.name;

	if ( field.in_config ) {
		return (
			<Field id={ id } label={ field.label }>
				<span className={ P + 'set' }>
					<Icon name="lock" size={ 15 } />
					{ __( 'Set in wp-config.php.', 'nhrrob-social-login' ) }
				</span>
			</Field>
		);
	}

	const props = {
		id,
		className: P + 'input is-mono',
		autoComplete: field.secret ? 'new-password' : 'off',
		value,
		disabled: removing,
		placeholder: field.secret && field.set ? '••••••••••••' : '',
		onChange: ( e ) => onChange( e.target.value ),
	};

	return (
		<Field
			id={ id }
			label={ field.label }
			help={
				field.secret && field.set
					? __(
							'Saved. It is never shown again; type a new one to replace it.',
							'nhrrob-social-login'
					  )
					: ''
			}
		>
			{ field.multiline ? (
				<textarea rows={ 5 } { ...props } />
			) : (
				<input
					type={ field.secret ? 'password' : 'text' }
					{ ...props }
				/>
			) }
		</Field>
	);
}

function Status( { provider } ) {
	if ( provider.active ) {
		return <Pill tone="ok">{ __( 'On', 'nhrrob-social-login' ) }</Pill>;
	}
	if ( provider.enabled ) {
		return (
			<Pill tone="warn">
				{ __( 'Needs credentials', 'nhrrob-social-login' ) }
			</Pill>
		);
	}
	return <Pill tone="off">{ __( 'Off', 'nhrrob-social-login' ) }</Pill>;
}

function ProviderCard( { provider, values, setValues } ) {
	const toast = useToast();
	const set = ( name, value ) => setValues( { ...values, [ name ]: value } );
	const toggleRemove = ( name, on ) =>
		set(
			'remove',
			on
				? [ ...values.remove, name ]
				: values.remove.filter( ( r ) => r !== name )
		);

	const snippet = provider.fields
		.map( ( f ) => "define( '" + f.constant + "', '...' );" )
		.join( '\n' );

	const copyUri = () =>
		copy( provider.redirect_uri )
			.then( () =>
				toast( __( 'Redirect URI copied.', 'nhrrob-social-login' ) )
			)
			.catch( () =>
				toast(
					__(
						'Copying did not work. Select the address and copy it.',
						'nhrrob-social-login'
					),
					'error'
				)
			);

	return (
		<section
			className={
				P +
				'panel ' +
				P +
				'provider' +
				( values.enabled ? ' is-on' : '' )
			}
		>
			<header className={ P + 'panel__head' }>
				<h2 className={ P + 'panel__title' }>{ provider.label }</h2>
				<Status provider={ provider } />
				<span className={ P + 'provider__switch' }>
					<Switch
						checked={ values.enabled }
						onChange={ ( on ) => set( 'enabled', on ) }
						label={ sprintf(
							/* translators: %s: provider name, such as Google. */
							__( 'Sign in with %s', 'nhrrob-social-login' ),
							provider.label
						) }
					/>
				</span>
			</header>
			{ values.enabled && (
				<div className={ P + 'panel__body ' + P + 'provider__body' }>
					<p className={ P + 'muted' }>
						{ sprintf(
							/* translators: %s: provider name, such as Google. */
							__(
								'Create an app or client at %s, and add this address to its redirect URIs.',
								'nhrrob-social-login'
							),
							provider.label
						) }{ ' ' }
						<External href={ provider.console_url }>
							{ __( 'Open the console', 'nhrrob-social-login' ) }
						</External>
					</p>
					<Field
						id={ P + provider.id + '-redirect' }
						label={ __( 'Redirect URI', 'nhrrob-social-login' ) }
					>
						<div className={ P + 'copy' }>
							<input
								id={ P + provider.id + '-redirect' }
								className={ P + 'input' }
								type="text"
								readOnly
								value={ provider.redirect_uri }
								onFocus={ ( e ) => e.target.select() }
							/>
							<Button onClick={ copyUri }>
								<Icon name="copy" size={ 15 } />
								{ __( 'Copy', 'nhrrob-social-login' ) }
							</Button>
						</div>
					</Field>
					<div className={ P + 'provider__fields' }>
						{ provider.fields.map( ( f ) => (
							<FieldInput
								key={ f.name }
								provider={ provider }
								field={ f }
								value={ values[ f.name ] }
								removing={ values.remove.includes( f.name ) }
								onChange={ ( v ) => set( f.name, v ) }
							/>
						) ) }
					</div>
					{ provider.fields
						.filter( ( f ) => f.secret && f.set && ! f.in_config )
						.map( ( f ) => (
							<label
								key={ f.name }
								className={ P + 'check' }
								htmlFor={
									P + provider.id + '-remove-' + f.name
								}
							>
								<input
									id={ P + provider.id + '-remove-' + f.name }
									type="checkbox"
									checked={ values.remove.includes( f.name ) }
									onChange={ ( e ) =>
										toggleRemove( f.name, e.target.checked )
									}
								/>
								{ sprintf(
									/* translators: %s: field name, such as Client secret. */
									__(
										'Remove the saved %s',
										'nhrrob-social-login'
									),
									f.label
								) }
							</label>
						) ) }
					<details className={ P + 'provider__config' }>
						<summary>
							{ __(
								'Or keep them in wp-config.php',
								'nhrrob-social-login'
							) }
						</summary>
						<pre className={ P + 'snippet' }>{ snippet }</pre>
					</details>
				</div>
			) }
		</section>
	);
}

export default function Providers( { data, setData } ) {
	const [ save, saving ] = useSave( setData );
	const [ values, setValues ] = useState( () =>
		initialAll( data.providers )
	);

	const active = data.providers.filter( ( p ) => p.active );

	const submit = () =>
		save( { providers: values }, ( next ) =>
			setValues( initialAll( next.providers ) )
		);

	return (
		<div className={ P + 'setup' }>
			<ScreenHeader
				title={ __( 'Providers', 'nhrrob-social-login' ) }
				lede={ __(
					'Let people log in, and comment, with an account they already have.',
					'nhrrob-social-login'
				) }
			/>

			<div className={ P + 'stats' }>
				<Stat
					icon={ active.length ? 'check' : 'alert' }
					tone={ active.length ? 'ok' : 'warn' }
					label={ __( 'Providers on', 'nhrrob-social-login' ) }
					value={ active.length }
					note={
						active.length
							? active.map( ( p ) => p.label ).join( ', ' )
							: __(
									'Switch one on and add its credentials.',
									'nhrrob-social-login'
							  )
					}
				/>
				<Stat
					icon="user"
					tone="accent"
					label={ __( 'Linked users', 'nhrrob-social-login' ) }
					value={ data.linked_users }
					note={ __(
						'Users who signed in with a provider.',
						'nhrrob-social-login'
					) }
				/>
				<Stat
					icon="comment"
					tone={ data.settings.create_accounts ? 'ok' : 'soft' }
					label={ __( 'New accounts', 'nhrrob-social-login' ) }
					value={
						data.settings.create_accounts
							? __( 'On', 'nhrrob-social-login' )
							: __( 'Off', 'nhrrob-social-login' )
					}
					note={
						data.settings.create_accounts
							? __(
									'Readers can comment as themselves.',
									'nhrrob-social-login'
							  )
							: __(
									'Only existing users sign in.',
									'nhrrob-social-login'
							  )
					}
				/>
			</div>

			{ data.providers.map( ( p ) => (
				<ProviderCard
					key={ p.id }
					provider={ p }
					values={ values[ p.id ] }
					setValues={ ( v ) =>
						setValues( { ...values, [ p.id ]: v } )
					}
				/>
			) ) }

			<div className={ P + 'savebar' }>
				<Button
					variant="primary"
					onClick={ submit }
					disabled={ saving }
				>
					{ saving
						? __( 'Saving…', 'nhrrob-social-login' )
						: __( 'Save providers', 'nhrrob-social-login' ) }
				</Button>
			</div>

			{ ! data.ready && (
				<Note>
					{ __(
						'The buttons appear on the login form, and above the comment form, once a provider is on and has its credentials.',
						'nhrrob-social-login'
					) }
				</Note>
			) }
		</div>
	);
}
