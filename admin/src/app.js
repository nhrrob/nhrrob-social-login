/**
 * Root component: app shell, section routing and the settings state.
 */
import { useState, useEffect } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';

import api from './api';
import AppShell from './components/AppShell';
import { ToastProvider } from './components/ToastProvider';
import { Loading, Note } from './components/ui';
import Setup from './screens/Setup';

export default function App( { boot } ) {
	const [ active, setActive ] = useState( 'setup' );
	const [ data, setData ] = useState( null );
	const [ failed, setFailed ] = useState( '' );

	useEffect( () => {
		api( '/settings' )
			.then( setData )
			.catch( ( e ) => setFailed( e.message || String( e ) ) );
	}, [] );

	/**
	 * The app's sidebar groups and screens. Another plugin adds its own here,
	 * from a script enqueued on the `nhrrob_social_login_enqueue` action:
	 * a group row is { label, items: [ { id, icon, label, count } ] }, and
	 * screens maps an item's id to a component that gets { boot, data,
	 * setData, navigate }.
	 */
	const { groups, screens } = applyFilters( 'nhrrobSocialLogin.app', {
		groups: [
			{
				label: __( 'Sign-in', 'nhrrob-social-login' ),
				items: [
					{
						id: 'setup',
						icon: 'settings',
						label: __( 'Setup', 'nhrrob-social-login' ),
					},
				],
			},
		],
		screens: { setup: Setup },
	} );

	let connection = null;
	if ( data ) {
		connection = {
			ok: data.ready,
			line: data.ready
				? sprintf(
						/* translators: %d: number of users linked to a Google account. */
						_n(
							'%d linked user',
							'%d linked users',
							data.linked_users,
							'nhrrob-social-login'
						),
						data.linked_users
				  )
				: __( 'Add a client ID and secret.', 'nhrrob-social-login' ),
		};
	}

	const Screen = screens[ active ] || Setup;

	let content = <Loading />;
	if ( failed ) {
		content = <Note tone="warn">{ failed }</Note>;
	} else if ( data ) {
		content = (
			<Screen
				boot={ boot }
				data={ data }
				setData={ setData }
				navigate={ setActive }
			/>
		);
	}

	return (
		<ToastProvider>
			<AppShell
				groups={ groups }
				active={ active }
				onNavigate={ setActive }
				site={ boot.siteName }
				connection={ connection }
				title={ __( 'Social Login', 'nhrrob-social-login' ) }
			>
				{ content }
			</AppShell>
		</ToastProvider>
	);
}
