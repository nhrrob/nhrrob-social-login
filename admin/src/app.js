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
import Accounts from './screens/Accounts';
import Extras from './screens/Extras';
import Providers from './screens/Providers';

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
						icon: 'key',
						label: __( 'Providers', 'nhrrob-social-login' ),
					},
					{
						id: 'accounts',
						icon: 'user',
						label: __( 'Accounts', 'nhrrob-social-login' ),
					},
					{
						id: 'extras',
						icon: 'settings',
						label: __( 'Extras', 'nhrrob-social-login' ),
					},
				],
			},
		],
		screens: { setup: Providers, accounts: Accounts, extras: Extras },
	} );

	let connection = null;
	if ( data ) {
		connection = {
			ok: data.ready,
			line: data.ready
				? sprintf(
						/* translators: %d: number of users linked to a provider. */
						_n(
							'%d linked user',
							'%d linked users',
							data.linked_users,
							'nhrrob-social-login'
						),
						data.linked_users
				  )
				: __( 'Switch on a provider.', 'nhrrob-social-login' ),
		};
	}

	const Screen = screens[ active ] || Providers;

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
