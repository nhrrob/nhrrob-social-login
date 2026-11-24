<?php
namespace NHRRob\SocialLogin\Tests;

use NHRRob\SocialLogin\Providers\Apple;
use NHRRob\SocialLogin\Providers\GitHub;
use NHRRob\SocialLogin\Providers\LinkedIn;
use NHRRob\SocialLogin\Providers\Microsoft;
use NHRRob\SocialLogin\Providers\X;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * Each provider gives an identity only for a verified email address, and
 * only for a token issued to this site for this sign-in.
 */
class ProvidersTest extends TestCase {

	const CLIENT = 'client-123';
	const NONCE  = 'nonce-abc';
	const NOW    = 1800000000;
	const TENANT = '72f988bf-86f1-41af-91ab-2d7cd011db47';

	protected function setUp(): void {
		WP_Mock::setUp();
		WP_Mock::userFunction( '__' )->andReturnArg( 0 );
		WP_Mock::passthruFunction( 'sanitize_email' );
		WP_Mock::passthruFunction( 'sanitize_text_field' );
		WP_Mock::passthruFunction( 'esc_url_raw' );
		WP_Mock::userFunction( 'is_email' )->andReturnUsing(
			function ( $email ) {
				return false !== filter_var( $email, FILTER_VALIDATE_EMAIL );
			}
		);
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	private function microsoft( $tid, array $changes = [] ) {
		return array_merge(
			[
				'iss'   => 'https://login.microsoftonline.com/' . $tid . '/v2.0',
				'aud'   => self::CLIENT,
				'sub'   => 'ms-sub',
				'tid'   => $tid,
				'exp'   => self::NOW + 3600,
				'nonce' => self::NONCE,
				'email' => 'reader@example.com',
				'name'  => 'A Reader',
			],
			$changes
		);
	}

	public function testAPersonalMicrosoftAccountIsVerified() {
		$identity = Microsoft::check_claims( $this->microsoft( Microsoft::CONSUMERS ), self::CLIENT, self::NONCE, self::NOW );

		$this->assertSame( 'reader@example.com', $identity['email'] );
		$this->assertSame( 'ms-sub', $identity['sub'] );
	}

	public function testAWorkAccountNeedsTheDomainOwnerVerifiedClaim() {
		$error = Microsoft::check_claims( $this->microsoft( self::TENANT ), self::CLIENT, self::NONCE, self::NOW );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'nhrrob_social_login_email', $error->code );
		$this->assertIsArray( Microsoft::check_claims( $this->microsoft( self::TENANT, [ 'xms_edov' => true ] ), self::CLIENT, self::NONCE, self::NOW ) );
	}

	public function testAMicrosoftIssuerFromAnotherTenantIsRefused() {
		$claims = $this->microsoft( self::TENANT, [ 'iss' => 'https://login.microsoftonline.com/' . Microsoft::CONSUMERS . '/v2.0' ] );

		$this->assertSame( 'nhrrob_social_login_token', Microsoft::check_claims( $claims, self::CLIENT, self::NONCE, self::NOW )->code );
	}

	public function testAMicrosoftTokenForAnotherSignInIsRefused() {
		$this->assertInstanceOf( \WP_Error::class, Microsoft::check_claims( $this->microsoft( Microsoft::CONSUMERS ), self::CLIENT, 'other', self::NOW ) );
	}

	public function testAppleNeedsItsIssuerAndAVerifiedEmail() {
		$claims = [
			'iss'            => 'https://appleid.apple.com',
			'aud'            => self::CLIENT,
			'sub'            => '001234.abcd',
			'exp'            => self::NOW + 600,
			'nonce'          => self::NONCE,
			'email'          => 'x1y2@privaterelay.appleid.com',
			'email_verified' => 'true',
		];

		$this->assertSame( '001234.abcd', Apple::check_claims( $claims, self::CLIENT, self::NONCE, self::NOW )['sub'] );
		$this->assertSame( 'nhrrob_social_login_email', Apple::check_claims( array_merge( $claims, [ 'email_verified' => 'false' ] ), self::CLIENT, self::NONCE, self::NOW )->code );
		$this->assertSame( 'nhrrob_social_login_token', Apple::check_claims( array_merge( $claims, [ 'iss' => 'https://evil.example.com' ] ), self::CLIENT, self::NONCE, self::NOW )->code );
	}

	public function testGitHubTakesOnlyTheVerifiedPrimaryAddress() {
		$emails = [
			[
				'email'    => 'old@example.com',
				'primary'  => false,
				'verified' => true,
			],
			[
				'email'    => 'main@example.com',
				'primary'  => true,
				'verified' => true,
			],
		];

		$this->assertSame( 'main@example.com', GitHub::primary_email( $emails ) );

		$emails[1]['verified'] = false;
		$this->assertSame( '', GitHub::primary_email( $emails ) );
	}

	public function testXNeedsAConfirmedEmail() {
		$this->assertSame(
			'reader@example.com',
			X::from_user(
				[
					'id'              => '42',
					'name'            => 'A Reader',
					'confirmed_email' => 'reader@example.com',
				]
			)['email']
		);
		$this->assertSame( 'nhrrob_social_login_email', X::from_user( [ 'id' => '42' ] )->code );
	}

	public function testLinkedInNeedsEmailVerified() {
		$info = [
			'sub'            => 'li-1',
			'email'          => 'reader@example.com',
			'email_verified' => true,
			'name'           => 'A Reader',
		];

		$this->assertSame( 'li-1', LinkedIn::from_userinfo( $info )['sub'] );
		$this->assertSame( 'nhrrob_social_login_email', LinkedIn::from_userinfo( array_merge( $info, [ 'email_verified' => false ] ) )->code );
		$this->assertSame( 'nhrrob_social_login_token', LinkedIn::from_userinfo( array_merge( $info, [ 'sub' => '' ] ) )->code );
	}
}
