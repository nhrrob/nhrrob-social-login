/**
 * The login_form hook prints the sign-in buttons after the password field.
 * Move them to the top of the form, above an "or".
 */
( function () {
	const form = document.getElementById( 'loginform' );
	const buttons = form && form.querySelector( '.nhrrob-social-login' );

	if ( buttons ) {
		buttons.classList.add( 'is-top' );
		form.insertBefore( buttons, form.firstChild );
	}
} )();
