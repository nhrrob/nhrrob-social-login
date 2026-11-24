<?php
namespace NHRRob\SocialLogin\Tests;

use NHRRob\SocialLogin\Accounts;
use NHRRob\SocialLogin\Login;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * A Google account signs in as the user linked to it, then as the user with
 * its email address, and only when allowed as a new subscriber.
 */
class AccountsTest extends TestCase {

	protected function setUp(): void {
		WP_Mock::setUp();
		WP_Mock::userFunction( 'is_wp_error' )->andReturnUsing(
			function ( $thing ) {
				return $thing instanceof \WP_Error;
			}
		);
		WP_Mock::userFunction( 'get_users' )->andReturn( [] )->byDefault();
		WP_Mock::userFunction( 'get_user_by' )->andReturn( false )->byDefault();
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	private function identity() {
		return [
			'sub'        => 'google-1',
			'email'      => 'reader@example.com',
			'name'       => 'A Reader',
			'given_name' => 'A',
		];
	}

	private function user( $id ) {
		$user     = new \stdClass();
		$user->ID = $id;

		return $user;
	}

	public function testTheLinkedUserSignsIn() {
		$linked = $this->user( 7 );
		WP_Mock::userFunction( 'get_users' )->andReturn( [ $linked ] );
		WP_Mock::userFunction( 'update_user_meta' )->never();
		WP_Mock::userFunction( 'wp_insert_user' )->never();

		$new = true;
		$this->assertSame( $linked, Accounts::user_for( $this->identity(), true, $new ) );
		$this->assertFalse( $new );
	}

	public function testTheUserWithTheEmailIsLinkedOnce() {
		$user = $this->user( 3 );
		WP_Mock::userFunction( 'get_user_by' )->with( 'email', 'reader@example.com' )->andReturn( $user );
		WP_Mock::userFunction( 'get_user_meta' )->with( 3, Login::META_SUB, true )->andReturn( '' );
		WP_Mock::userFunction( 'update_user_meta' )->once()->with( 3, Login::META_SUB, 'google-1' );
		WP_Mock::userFunction( 'wp_insert_user' )->never();

		$this->assertSame( $user, Accounts::user_for( $this->identity(), false ) );
	}

	public function testAUserLinkedToAnotherGoogleAccountIsNotTakenOver() {
		WP_Mock::userFunction( 'get_user_by' )->andReturn( $this->user( 3 ) );
		WP_Mock::userFunction( 'get_user_meta' )->andReturn( 'google-other' );
		WP_Mock::userFunction( 'update_user_meta' )->never();

		$error = Accounts::user_for( $this->identity(), true );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'linked_other', $error->code );
	}

	public function testWithoutPermissionNoAccountIsCreated() {
		WP_Mock::userFunction( 'wp_insert_user' )->never();

		$error = Accounts::user_for( $this->identity(), false );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'no_account', $error->code );
	}

	public function testANewUserIsASubscriberLinkedToGoogle() {
		$created = $this->user( 12 );
		WP_Mock::userFunction( 'sanitize_user' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'username_exists' )->andReturn( false );
		WP_Mock::userFunction( 'wp_generate_password' )->andReturn( 'random-password' );
		WP_Mock::userFunction( 'wp_insert_user' )->once()->with(
			[
				'user_login'   => 'reader',
				'user_email'   => 'reader@example.com',
				'user_pass'    => 'random-password',
				'display_name' => 'A Reader',
				'first_name'   => 'A',
				'role'         => 'subscriber',
			]
		)->andReturn( 12 );
		WP_Mock::userFunction( 'update_user_meta' )->once()->with( 12, Login::META_SUB, 'google-1' );
		WP_Mock::userFunction( 'get_user_by' )->with( 'id', 12 )->andReturn( $created );

		$new = false;
		$this->assertSame( $created, Accounts::user_for( $this->identity(), true, $new ) );
		$this->assertTrue( $new );
	}

	public function testATakenUsernameGetsANumber() {
		WP_Mock::userFunction( 'sanitize_user' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'username_exists' )->andReturnUsing(
			function ( $name ) {
				return in_array( $name, [ 'reader', 'reader2' ], true );
			}
		);

		$this->assertSame( 'reader3', Accounts::username( 'reader@example.com' ) );
	}
}
