/**
 * Setup: the Google client and whether a Google user without an account gets
 * one. The secret is never sent to the browser; only whether one is saved.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import api from '../api';
import { useToast } from '../components/ToastProvider';
import {
	Button,
	External,
	Field,
	Icon,
	Note,
	ScreenHeader,
	Stat,
	Step,
	Switch,
} from '../components/ui';

const P = 'nhrrob-social-login-';
const CONSOLE = 'https://console.cloud.google.com/apis/credentials';
const SNIPPET =
	"define( 'NHRROB_SOCIAL_LOGIN_GOOGLE_CLIENT_ID', '...' );\n" +
	"define( 'NHRROB_SOCIAL_LOGIN_GOOGLE_CLIENT_SECRET', '...' );";

function copy( text ) {
	if ( window.navigator.clipboard ) {
		return window.navigator.clipboard.writeText( text );
	}
	return Promise.reject( new Error( 'clipboard' ) );
}

export default function Setup( { data, setData } ) {
	const toast = useToast();
	const [ clientId, setClientId ] = useState( data.client_id );
	const [ secret, setSecret ] = useState( '' );
	const [ removeSecret, setRemoveSecret ] = useState( false );
	const [ create, setCreate ] = useState( data.create_accounts );
	const [ saving, setSaving ] = useState( false );

	const save = () => {
		setSaving( true );
		api( '/settings', 'POST', {
			client_id: clientId,
			client_secret: secret,
			remove_secret: removeSecret,
			create_accounts: create,
		} )
			.then( ( next ) => {
				setData( next );
				setClientId( next.client_id );
				setSecret( '' );
				setRemoveSecret( false );
				toast( __( 'Settings saved.', 'nhrrob-social-login' ) );
			} )
			.catch( ( e ) => toast( e.message || String( e ), 'error' ) )
			.finally( () => setSaving( false ) );
	};

	const copyUri = () =>
		copy( data.redirect_uri )
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
		<div className={ P + 'setup' }>
			<ScreenHeader
				title={ __( 'Setup', 'nhrrob-social-login' ) }
				lede={ __(
					'Let people log in, and comment, with their Google account.',
					'nhrrob-social-login'
				) }
			/>

			<div className={ P + 'stats' }>
				<Stat
					icon={ data.ready ? 'check' : 'alert' }
					tone={ data.ready ? 'ok' : 'warn' }
					label={ __( 'Google sign-in', 'nhrrob-social-login' ) }
					value={
						data.ready
							? __( 'On', 'nhrrob-social-login' )
							: __( 'Off', 'nhrrob-social-login' )
					}
					note={
						data.ready
							? __(
									'The buttons are showing.',
									'nhrrob-social-login'
							  )
							: __(
									'Needs a client ID and secret.',
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
						'Users who signed in with Google.',
						'nhrrob-social-login'
					) }
				/>
				<Stat
					icon="comment"
					tone={ data.create_accounts ? 'ok' : 'soft' }
					label={ __( 'New accounts', 'nhrrob-social-login' ) }
					value={
						data.create_accounts
							? __( 'On', 'nhrrob-social-login' )
							: __( 'Off', 'nhrrob-social-login' )
					}
					note={
						data.create_accounts
							? __(
									'Readers can comment with Google.',
									'nhrrob-social-login'
							  )
							: __(
									'Only existing users sign in.',
									'nhrrob-social-login'
							  )
					}
				/>
			</div>

			<section className={ P + 'panel ' + P + 'form' }>
				<Step
					n={ 1 }
					title={ __(
						'Create a Google client',
						'nhrrob-social-login'
					) }
				>
					<p className={ P + 'muted' }>
						{ __(
							'In the Google Cloud Console, under APIs & Services > Credentials, create an OAuth client ID of type "Web application", and add this address to its authorized redirect URIs.',
							'nhrrob-social-login'
						) }{ ' ' }
						<External href={ CONSOLE }>
							{ __( 'Open the console', 'nhrrob-social-login' ) }
						</External>
					</p>
					<Field
						id={ P + 'redirect' }
						label={ __( 'Redirect URI', 'nhrrob-social-login' ) }
					>
						<div className={ P + 'copy' }>
							<input
								id={ P + 'redirect' }
								className={ P + 'input' }
								type="text"
								readOnly
								value={ data.redirect_uri }
								onFocus={ ( e ) => e.target.select() }
							/>
							<Button onClick={ copyUri }>
								<Icon name="copy" size={ 15 } />
								{ __( 'Copy', 'nhrrob-social-login' ) }
							</Button>
						</div>
					</Field>
				</Step>

				<Step
					n={ 2 }
					title={ __(
						'Client ID and secret',
						'nhrrob-social-login'
					) }
					hint={ __(
						'Best kept in wp-config.php',
						'nhrrob-social-login'
					) }
				>
					<pre className={ P + 'snippet' }>{ SNIPPET }</pre>

					<Field
						id={ P + 'client-id' }
						label={ __( 'Client ID', 'nhrrob-social-login' ) }
					>
						{ data.id_in_config ? (
							<span className={ P + 'set' }>
								<Icon name="lock" size={ 15 } />
								{ __(
									'Set in wp-config.php.',
									'nhrrob-social-login'
								) }
							</span>
						) : (
							<input
								id={ P + 'client-id' }
								className={ P + 'input is-mono' }
								type="text"
								autoComplete="off"
								value={ clientId }
								onChange={ ( e ) =>
									setClientId( e.target.value )
								}
							/>
						) }
					</Field>

					<Field
						id={ P + 'client-secret' }
						label={ __( 'Client secret', 'nhrrob-social-login' ) }
						help={
							data.secret_set && ! data.secret_in_config
								? __(
										'Saved. It is never shown again; type a new one to replace it.',
										'nhrrob-social-login'
								  )
								: ''
						}
					>
						{ data.secret_in_config ? (
							<span className={ P + 'set' }>
								<Icon name="lock" size={ 15 } />
								{ __(
									'Set in wp-config.php.',
									'nhrrob-social-login'
								) }
							</span>
						) : (
							<input
								id={ P + 'client-secret' }
								className={ P + 'input is-mono' }
								type="password"
								autoComplete="new-password"
								placeholder={
									data.secret_set ? '••••••••••••' : ''
								}
								value={ secret }
								disabled={ removeSecret }
								onChange={ ( e ) =>
									setSecret( e.target.value )
								}
							/>
						) }
					</Field>

					{ data.secret_set && ! data.secret_in_config && (
						<label
							className={ P + 'check' }
							htmlFor={ P + 'remove-secret' }
						>
							<input
								id={ P + 'remove-secret' }
								type="checkbox"
								checked={ removeSecret }
								onChange={ ( e ) =>
									setRemoveSecret( e.target.checked )
								}
							/>
							{ __(
								'Remove the saved secret',
								'nhrrob-social-login'
							) }
						</label>
					) }
				</Step>

				<Step
					n={ 3 }
					title={ __( 'New users', 'nhrrob-social-login' ) }
				>
					<div className={ P + 'toggle' }>
						<Switch
							checked={ create }
							onChange={ setCreate }
							label={ __(
								'Create an account for a Google user who has none',
								'nhrrob-social-login'
							) }
						/>
						<span className={ P + 'toggle__text' }>
							<strong>
								{ __(
									'Create an account, as a Subscriber, for a Google user who has none',
									'nhrrob-social-login'
								) }
							</strong>
							<span className={ P + 'muted' }>
								{ __(
									'This also shows "Continue with Google to comment" above the comment form. It does not open WordPress\'s registration form ("Anyone can register" under General settings), which can stay off.',
									'nhrrob-social-login'
								) }
							</span>
						</span>
					</div>
				</Step>

				<div className={ P + 'form__foot' }>
					<Button
						variant="primary"
						onClick={ save }
						disabled={ saving }
					>
						{ saving
							? __( 'Saving…', 'nhrrob-social-login' )
							: __( 'Save settings', 'nhrrob-social-login' ) }
					</Button>
				</div>
			</section>

			{ ! data.ready && (
				<Note>
					{ __(
						'The buttons appear on the login form, and above the comment form, once both the client ID and the secret are set.',
						'nhrrob-social-login'
					) }
				</Note>
			) }
		</div>
	);
}
