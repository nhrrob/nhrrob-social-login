/**
 * Extras: One Tap, sign-in links by email, avatars, the verified mark on
 * comments and the email about new accounts; and where else the buttons go.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import {
	Button,
	Note,
	Panel,
	ScreenHeader,
	Segmented,
	Switch,
} from '../components/ui';
import { useSave } from './save';

const P = 'nhrrob-social-login-';
const NAMES = [
	'one_tap',
	'magic_link',
	'use_avatar',
	'verified_mark',
	'notify_admin',
];

function pick( settings ) {
	const out = {};
	NAMES.forEach( ( n ) => {
		out[ n ] = settings[ n ];
	} );
	return out;
}

function Toggle( { checked, onChange, title, text } ) {
	return (
		<div className={ P + 'toggle' }>
			<Switch checked={ checked } onChange={ onChange } label={ title } />
			<span className={ P + 'toggle__text' }>
				<strong>{ title }</strong>
				<span className={ P + 'muted' }>{ text }</span>
			</span>
		</div>
	);
}

export default function Extras( { data, setData } ) {
	const [ save, saving ] = useSave( setData );
	const [ values, setValues ] = useState( () => pick( data.settings ) );
	const set = ( name, value ) => setValues( { ...values, [ name ]: value } );
	const google = data.providers.find( ( p ) => p.id === 'google' );

	return (
		<div className={ P + 'setup' }>
			<ScreenHeader
				title={ __( 'Extras', 'nhrrob-social-login' ) }
				lede={ __(
					'Optional ways in, and small touches. Each one is off until you switch it on.',
					'nhrrob-social-login'
				) }
			/>

			<Panel title={ __( 'Ways in', 'nhrrob-social-login' ) }>
				<div className={ P + 'extras' }>
					<div className={ P + 'toggle' }>
						<span className={ P + 'toggle__text' }>
							<strong>
								{ __(
									'Google One Tap',
									'nhrrob-social-login'
								) }
							</strong>
							<span className={ P + 'muted' }>
								{ __(
									'A small "Continue as…" prompt for logged-out visitors who are signed in to Google. It loads Google\'s script on the pages where it shows.',
									'nhrrob-social-login'
								) }
							</span>
							{ google && ! google.active && (
								<span className={ P + 'muted' }>
									{ __(
										'Needs Google to be on, under Providers.',
										'nhrrob-social-login'
									) }
								</span>
							) }
						</span>
					</div>
					<Segmented
						label={ __( 'Google One Tap', 'nhrrob-social-login' ) }
						value={ values.one_tap }
						onChange={ ( v ) => set( 'one_tap', v ) }
						options={ [
							{
								value: 'off',
								label: __( 'Off', 'nhrrob-social-login' ),
							},
							{
								value: 'posts',
								label: __( 'On posts', 'nhrrob-social-login' ),
							},
							{
								value: 'site',
								label: __(
									'On every page',
									'nhrrob-social-login'
								),
							},
						] }
					/>
					<Toggle
						checked={ values.magic_link }
						onChange={ ( on ) => set( 'magic_link', on ) }
						title={ __(
							'Sign-in links by email',
							'nhrrob-social-login'
						) }
						text={ __(
							'"Email me a sign-in link" next to the buttons, for readers without an account at any provider. A link works once, for 15 minutes; requests are limited per address and per IP address.',
							'nhrrob-social-login'
						) }
					/>
				</div>
			</Panel>

			<Panel title={ __( 'Touches', 'nhrrob-social-login' ) }>
				<div className={ P + 'extras' }>
					<Toggle
						checked={ values.use_avatar }
						onChange={ ( on ) => set( 'use_avatar', on ) }
						title={ __(
							"Use the provider's picture as the avatar",
							'nhrrob-social-login'
						) }
						text={ __(
							'For users without a Gravatar, the picture from Google, GitHub or X. Gravatar is asked once, at sign-in.',
							'nhrrob-social-login'
						) }
					/>
					<Toggle
						checked={ values.verified_mark }
						onChange={ ( on ) => set( 'verified_mark', on ) }
						title={ __(
							'A verified mark on comments',
							'nhrrob-social-login'
						) }
						text={ __(
							'A small mark after the name of commenters who signed in with a provider, saying which one verified their email address.',
							'nhrrob-social-login'
						) }
					/>
					<Toggle
						checked={ values.notify_admin }
						onChange={ ( on ) => set( 'notify_admin', on ) }
						title={ __(
							'Email me about new accounts',
							'nhrrob-social-login'
						) }
						text={ __(
							'An email to the administration address with the name, the provider and the post it came from.',
							'nhrrob-social-login'
						) }
					/>
				</div>
				<div className={ P + 'panel__foot' }>
					<Button
						variant="primary"
						onClick={ () =>
							save( { settings: values }, ( next ) =>
								setValues( pick( next.settings ) )
							)
						}
						disabled={ saving }
					>
						{ saving
							? __( 'Saving…', 'nhrrob-social-login' )
							: __( 'Save settings', 'nhrrob-social-login' ) }
					</Button>
				</div>
			</Panel>

			<Note>
				{ __(
					'The buttons can go anywhere: the "Social login buttons" block, or the shortcode [nhrrob_social_login label="Sign in with" redirect="https://…"].',
					'nhrrob-social-login'
				) }{ ' ' }
				{ data.woocommerce
					? __(
							'WooCommerce is active, so they also show on My account and at checkout.',
							'nhrrob-social-login'
					  )
					: __(
							'With WooCommerce, they also show on My account and at checkout.',
							'nhrrob-social-login'
					  ) }
			</Note>
		</div>
	);
}
