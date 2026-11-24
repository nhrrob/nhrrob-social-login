/**
 * The login_form hook prints the "Continue with Google" button after the
 * password field. Move it to the top of the form, above an "or".
 */
( function () {
	const form = document.getElementById( 'loginform' );
	const button = form && form.querySelector( '.nhrrob-social-login' );

	if ( button ) {
		button.classList.add( 'is-top' );
		form.insertBefore( button, form.firstChild );
	}
} )();
