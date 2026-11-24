/**
 * Saving from any screen: POST the changed parts of the settings, put the
 * answer in the app's state, and say how it went.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import api from '../api';
import { useToast } from '../components/ToastProvider';

export function useSave( setData ) {
	const toast = useToast();
	const [ saving, setSaving ] = useState( false );

	const save = ( body, onSaved ) => {
		setSaving( true );
		return api( '/settings', 'POST', body )
			.then( ( next ) => {
				setData( next );
				if ( onSaved ) {
					onSaved( next );
				}
				toast( __( 'Settings saved.', 'nhrrob-social-login' ) );
			} )
			.catch( ( e ) => toast( e.message || String( e ), 'error' ) )
			.finally( () => setSaving( false ) );
	};

	return [ save, saving ];
}

export function copy( text ) {
	if ( window.navigator.clipboard ) {
		return window.navigator.clipboard.writeText( text );
	}
	return Promise.reject( new Error( 'clipboard' ) );
}
