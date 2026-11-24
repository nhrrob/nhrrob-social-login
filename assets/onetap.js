/**
 * Google One Tap. Keeps a random nonce in a cookie for the server to compare
 * with the token's, then posts the token Google returns to admin-post.php,
 * coming back to this page.
 */
( function () {
	const data = window.nhrrobSocialLoginOneTap;

	function nonce() {
		const bytes = new Uint8Array( 24 );
		window.crypto.getRandomValues( bytes );
		return Array.from( bytes, ( b ) =>
			b.toString( 16 ).padStart( 2, '0' )
		).join( '' );
	}

	function submit( response ) {
		const form = document.createElement( 'form' );
		const fields = {
			action: 'nhrrob_social_login_onetap',
			credential: response.credential,
			redirect_to: window.location.href,
		};

		form.method = 'post';
		form.action = data.action;
		Object.keys( fields ).forEach( ( name ) => {
			const input = document.createElement( 'input' );
			input.type = 'hidden';
			input.name = name;
			input.value = fields[ name ];
			form.appendChild( input );
		} );
		document.body.appendChild( form );
		form.submit();
	}

	function start() {
		if ( ! window.google || ! window.google.accounts ) {
			return;
		}

		const value = nonce();
		document.cookie =
			data.cookie +
			'=' +
			value +
			'; path=' +
			data.path +
			'; max-age=600; samesite=lax' +
			( 'https:' === window.location.protocol ? '; secure' : '' );

		window.google.accounts.id.initialize( {
			client_id: data.clientId,
			nonce: value,
			callback: submit,
			cancel_on_tap_outside: true,
			use_fedcm_for_prompt: true,
		} );
		window.google.accounts.id.prompt();
	}

	if ( ! data ) {
		return;
	}

	if ( window.google && window.google.accounts ) {
		start();
	} else {
		window.addEventListener( 'load', start );
	}
} )();
