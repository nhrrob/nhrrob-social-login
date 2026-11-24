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
			'picture'    => '',
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
		$this->assertSame( $linked, Accounts::user_for( $this->identity(), Login::META_SUB, true, $new ) );
		$this->assertFalse( $new );
	}

	public function testTheUserWithTheEmailIsLinkedOnce() {
		$user = $this->user( 3 );
		WP_Mock::userFunction( 'get_user_by' )->with( 'email', 'reader@example.com' )->andReturn( $user );
		WP_Mock::userFunction( 'get_user_meta' )->with( 3, Login::META_SUB, true )->andReturn( '' );
		WP_Mock::userFunction( 'update_user_meta' )->once()->with( 3, Login::META_SUB, 'google-1' );
		WP_Mock::userFunction( 'wp_insert_user' )->never();

		$this->assertSame( $user, Accounts::user_for( $this->identity(), Login::META_SUB, false ) );
	}

	public function testAUserLinkedToAnotherGoogleAccountIsNotTakenOver() {
		WP_Mock::userFunction( 'get_user_by' )->andReturn( $this->user( 3 ) );
		WP_Mock::userFunction( 'get_user_meta' )->andReturn( 'google-other' );
		WP_Mock::userFunction( 'update_user_meta' )->never();

		$error = Accounts::user_for( $this->identity(), Login::META_SUB, true );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'linked_other', $error->code );
	}

	public function testWithoutPermissionNoAccountIsCreated() {
		WP_Mock::userFunction( 'wp_insert_user' )->never();

		$error = Accounts::user_for( $this->identity(), Login::META_SUB, false );

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
		WP_Mock::userFunction( 'update_user_meta' )->once()->with( 12, Accounts::META_PASSWORD, 1 );
		WP_Mock::userFunction( 'get_user_by' )->with( 'id', 12 )->andReturn( $created );

		$new = false;
		$this->assertSame( $created, Accounts::user_for( $this->identity(), Login::META_SUB, true, $new ) );
		$this->assertTrue( $new );
	}

	public function testANewAccountGetsTheRoleItIsGiven() {
		WP_Mock::userFunction( 'sanitize_user' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'username_exists' )->andReturn( false );
		WP_Mock::userFunction( 'wp_generate_password' )->andReturn( 'p' );
		WP_Mock::userFunction( 'wp_insert_user' )->once()->with( \Mockery::on( function ( $user ) { return 'customer' === $user['role']; } ) )->andReturn( 5 );
		WP_Mock::userFunction( 'update_user_meta' );
		WP_Mock::userFunction( 'get_user_by' )->with( 'id', 5 )->andReturn( $this->user( 5 ) );

		$this->assertSame( 5, Accounts::user_for( $this->identity(), Login::META_SUB, true, $new, 'customer' )->ID );
	}

	public function testASignInLinkByEmailLinksNothing() {
		$user = $this->user( 3 );
		WP_Mock::userFunction( 'get_users' )->never();
		WP_Mock::userFunction( 'get_user_by' )->with( 'email', 'reader@example.com' )->andReturn( $user );
		WP_Mock::userFunction( 'update_user_meta' )->never();

		$identity        = $this->identity();
		$identity['sub'] = '';

		$this->assertSame( $user, Accounts::user_for( $identity, '', false ) );
	}

	public function testAnAddressOutsideTheAllowedDomainsIsRefused() {
		WP_Mock::onFilter( 'nhrrob_social_login_domain_allowed' )->with( true, 'reader@example.com', null )->reply( false );
		WP_Mock::userFunction( 'wp_insert_user' )->never();

		$this->assertSame( 'domain', Accounts::user_for( $this->identity(), Login::META_SUB, true )->code );
	}

	public function testDomainsMatchExactly() {
		$this->assertTrue( Accounts::in_domains( 'a@example.com', [] ) );
		$this->assertTrue( Accounts::in_domains( 'a@Example.COM', [ 'example.com' ] ) );
		$this->assertFalse( Accounts::in_domains( 'a@mail.example.com', [ 'example.com' ] ) );
		$this->assertFalse( Accounts::in_domains( 'a@example.com.evil.test', [ 'example.com' ] ) );
		$this->assertFalse( Accounts::in_domains( 'no-at-sign', [ 'example.com' ] ) );
	}

	public function testRolesThatManageOrWriteAreNotGivenByDefault() {
		$roles = [
			'subscriber'    => [ 'read' ],
			'author'        => [ 'read', 'edit_posts' ],
			'administrator' => [ 'read', 'edit_posts', 'manage_options' ],
			'shop_manager'  => [ 'read', 'edit_users' ],
		];
		WP_Mock::userFunction( 'get_role' )->andReturnUsing(
			function ( $name ) use ( $roles ) {
				if ( ! isset( $roles[ $name ] ) ) {
					return null;
				}
				$caps = $roles[ $name ];
				$role = \Mockery::mock();
				$role->shouldReceive( 'has_cap' )->andReturnUsing(
					function ( $cap ) use ( $caps ) {
						return in_array( $cap, $caps, true );
					}
				);
				return $role;
			}
		);

		$this->assertTrue( Accounts::safe_role( 'subscriber', false ) );
		$this->assertFalse( Accounts::safe_role( 'author', false ) );
		$this->assertTrue( Accounts::safe_role( 'author', true ) );
		$this->assertFalse( Accounts::safe_role( 'administrator', true ) );
		$this->assertFalse( Accounts::safe_role( 'shop_manager', true ) );
		$this->assertFalse( Accounts::safe_role( 'missing', true ) );
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
