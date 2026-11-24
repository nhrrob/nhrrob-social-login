/**
 * Accounts: whether people without an account get one and with which role,
 * where they go after signing in, and which email domains may sign in.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import {
	Button,
	Field,
	Icon,
	Note,
	ScreenHeader,
	Step,
	Switch,
} from '../components/ui';
import { useSave } from './save';

const P = 'nhrrob-social-login-';

function pick( settings ) {
	return {
		create_accounts: settings.create_accounts,
		new_user_role: settings.new_user_role,
		allow_author_roles: settings.allow_author_roles,
		login_redirect: settings.login_redirect,
		signup_redirect: settings.signup_redirect,
		allowed_domains: settings.allowed_domains,
	};
}

export default function Accounts( { data, setData } ) {
	const [ save, saving ] = useSave( setData );
	const [ values, setValues ] = useState( () => pick( data.settings ) );
	const set = ( name, value ) => setValues( { ...values, [ name ]: value } );

	const roles = data.roles.filter(
		( r ) => values.allow_author_roles || ! r.writes
	);

	const submit = () => {
		const settings = { ...values };
		if ( data.settings.domains_in_config ) {
			delete settings.allowed_domains;
		}
		save( { settings }, ( next ) => setValues( pick( next.settings ) ) );
	};

	return (
		<div className={ P + 'setup' }>
			<ScreenHeader
				title={ __( 'Accounts', 'nhrrob-social-login' ) }
				lede={ __(
					'Who gets in, as what, and where they go next.',
					'nhrrob-social-login'
				) }
			/>

			<section className={ P + 'panel ' + P + 'form' }>
				<Step
					n={ 1 }
					title={ __( 'New users', 'nhrrob-social-login' ) }
				>
					<div className={ P + 'toggle' }>
						<Switch
							checked={ values.create_accounts }
							onChange={ ( on ) => set( 'create_accounts', on ) }
							label={ __(
								'Create an account for someone who has none',
								'nhrrob-social-login'
							) }
						/>
						<span className={ P + 'toggle__text' }>
							<strong>
								{ __(
									'Create an account for someone who has none',
									'nhrrob-social-login'
								) }
							</strong>
							<span className={ P + 'muted' }>
								{ __(
									'This also shows the buttons above the comment form. It does not open WordPress\'s registration form ("Anyone can register" under General settings), which can stay off.',
									'nhrrob-social-login'
								) }
							</span>
						</span>
					</div>

					<Field
						id={ P + 'role' }
						label={ __(
							'Role of new accounts',
							'nhrrob-social-login'
						) }
						help={
							data.woocommerce
								? __(
										"Sign-ins from WooCommerce's forms get its Customer role.",
										'nhrrob-social-login'
								  )
								: ''
						}
					>
						<div className={ P + 'select' }>
							<select
								id={ P + 'role' }
								value={ values.new_user_role }
								onChange={ ( e ) =>
									set( 'new_user_role', e.target.value )
								}
							>
								{ roles.map( ( r ) => (
									<option key={ r.name } value={ r.name }>
										{ r.label }
									</option>
								) ) }
							</select>
						</div>
					</Field>

					<label
						className={ P + 'check' }
						htmlFor={ P + 'allow-authors' }
					>
						<input
							id={ P + 'allow-authors' }
							type="checkbox"
							checked={ values.allow_author_roles }
							onChange={ ( e ) => {
								const on = e.target.checked;
								const current = data.roles.find(
									( r ) => r.name === values.new_user_role
								);
								setValues( {
									...values,
									allow_author_roles: on,
									new_user_role:
										! on && current && current.writes
											? 'subscriber'
											: values.new_user_role,
								} );
							} }
						/>
						<span>
							<strong>
								{ __(
									'Also offer roles that can write posts',
									'nhrrob-social-login'
								) }
							</strong>{ ' ' }
							{ __(
								'Anyone with an account at a provider could then sign up as, say, an Author. Roles that can manage the site or its users are never offered.',
								'nhrrob-social-login'
							) }
						</span>
					</label>
				</Step>

				<Step
					n={ 2 }
					title={ __( 'After signing in', 'nhrrob-social-login' ) }
					hint={ __( 'Optional', 'nhrrob-social-login' ) }
				>
					<Field
						id={ P + 'login-redirect' }
						label={ __(
							'From the login form, go to',
							'nhrrob-social-login'
						) }
						help={ __(
							'When the login form was not sent to a particular page. Empty: the dashboard for authors, the home page for everyone else.',
							'nhrrob-social-login'
						) }
					>
						<input
							id={ P + 'login-redirect' }
							className={ P + 'input' }
							type="url"
							placeholder="https://"
							value={ values.login_redirect }
							onChange={ ( e ) =>
								set( 'login_redirect', e.target.value )
							}
						/>
					</Field>
					<Field
						id={ P + 'signup-redirect' }
						label={ __(
							'A new account goes first to',
							'nhrrob-social-login'
						) }
						help={ __(
							'For accounts made from the login form. Sign-ins from a comment form always go back to the post.',
							'nhrrob-social-login'
						) }
					>
						<input
							id={ P + 'signup-redirect' }
							className={ P + 'input' }
							type="url"
							placeholder="https://"
							value={ values.signup_redirect }
							onChange={ ( e ) =>
								set( 'signup_redirect', e.target.value )
							}
						/>
					</Field>
				</Step>

				<Step
					n={ 3 }
					title={ __(
						'Allowed email domains',
						'nhrrob-social-login'
					) }
					hint={ __( 'Optional', 'nhrrob-social-login' ) }
				>
					<Field
						id={ P + 'domains' }
						label={ __(
							'Only these domains may sign in',
							'nhrrob-social-login'
						) }
						help={ __(
							'One per line, such as example.com. Empty: every domain. It applies to existing users too.',
							'nhrrob-social-login'
						) }
					>
						{ data.settings.domains_in_config ? (
							<span className={ P + 'set' }>
								<Icon name="lock" size={ 15 } />
								{ __(
									'Set in wp-config.php:',
									'nhrrob-social-login'
								) }{ ' ' }
								{ data.settings.allowed_domains ||
									__( 'none', 'nhrrob-social-login' ) }
							</span>
						) : (
							<textarea
								id={ P + 'domains' }
								className={ P + 'input is-mono' }
								rows={ 3 }
								value={ values.allowed_domains }
								onChange={ ( e ) =>
									set( 'allowed_domains', e.target.value )
								}
							/>
						) }
					</Field>
				</Step>

				<div className={ P + 'form__foot' }>
					<Button
						variant="primary"
						onClick={ submit }
						disabled={ saving }
					>
						{ saving
							? __( 'Saving…', 'nhrrob-social-login' )
							: __( 'Save settings', 'nhrrob-social-login' ) }
					</Button>
				</div>
			</section>

			<Note>
				{ __(
					'Only email addresses the provider has verified are accepted. A user linked to one account at a provider is never linked to another one there.',
					'nhrrob-social-login'
				) }
			</Note>
		</div>
	);
}
