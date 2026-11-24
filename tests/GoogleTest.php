<?php
namespace NHRRob\SocialLogin\Tests;

use NHRRob\SocialLogin\Jwt;
use NHRRob\SocialLogin\Providers\Google;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * An ID token signs someone in only when Google issued it for this site,
 * for this sign-in, recently, and for a verified email address.
 */
class GoogleTest extends TestCase {

	const CLIENT = 'client-123.apps.googleusercontent.com';
	const NONCE  = 'nonce-abc';
	const NOW    = 1800000000;

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

	private function claims( array $changes = [] ) {
		return array_merge(
			[
				'iss'            => 'https://accounts.google.com',
				'aud'            => self::CLIENT,
				'sub'            => '1098765',
				'exp'            => self::NOW + 3600,
				'nonce'          => self::NONCE,
				'email'          => 'reader@example.com',
				'email_verified' => true,
				'name'           => 'A Reader',
				'given_name'     => 'A',
				'picture'        => 'https://lh3.googleusercontent.com/a/photo=s96-c',
			],
			$changes
		);
	}

	private function check( array $claims ) {
		return Google::check_claims( $claims, self::CLIENT, self::NONCE, self::NOW );
	}

	public function testAGoodTokenGivesTheIdentity() {
		$this->assertSame(
			[
				'sub'        => '1098765',
				'email'      => 'reader@example.com',
				'name'       => 'A Reader',
				'given_name' => 'A',
				'picture'    => 'https://lh3.googleusercontent.com/a/photo=s96-c',
			],
			$this->check( $this->claims() )
		);
	}

	public function testBothIssuerSpellingsAreAccepted() {
		$this->assertIsArray( $this->check( $this->claims( [ 'iss' => 'accounts.google.com' ] ) ) );
	}

	public function testVerifiedMayBeTheStringTrue() {
		$this->assertIsArray( $this->check( $this->claims( [ 'email_verified' => 'true' ] ) ) );
	}

	public function testAnAudienceListWithThisClientIsAccepted() {
		$this->assertIsArray( $this->check( $this->claims( [ 'aud' => [ 'other', self::CLIENT ] ] ) ) );
	}

	/**
	 * @dataProvider refused
	 */
	public function testABadTokenIsRefused( $changes, $code ) {
		$error = $this->check( $this->claims( $changes ) );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( $code, $error->code );
	}

	public function refused() {
		return [
			'another issuer'         => [ [ 'iss' => 'https://evil.example.com' ], 'nhrrob_social_login_token' ],
			'another site'           => [ [ 'aud' => 'someone-else.apps.googleusercontent.com' ], 'nhrrob_social_login_token' ],
			'expired'                => [ [ 'exp' => self::NOW - 3600 ], 'nhrrob_social_login_token' ],
			'another sign-in'        => [ [ 'nonce' => 'nonce-xyz' ], 'nhrrob_social_login_token' ],
			'no nonce'               => [ [ 'nonce' => '' ], 'nhrrob_social_login_token' ],
			'no subject'             => [ [ 'sub' => '' ], 'nhrrob_social_login_token' ],
			'unverified email'       => [ [ 'email_verified' => false ], 'nhrrob_social_login_email' ],
			'verified as text false' => [ [ 'email_verified' => 'false' ], 'nhrrob_social_login_email' ],
			'no email'               => [ [ 'email' => '' ], 'nhrrob_social_login_email' ],
		];
	}

	public function testAMissingClaimIsRefused() {
		$claims = $this->claims();
		unset( $claims['nonce'] );

		$this->assertInstanceOf( \WP_Error::class, $this->check( $claims ) );
	}

	public function testWithoutAClientIdNothingIsAccepted() {
		$this->assertInstanceOf( \WP_Error::class, Google::check_claims( $this->claims( [ 'aud' => '' ] ), '', self::NONCE, self::NOW ) );
	}

	public function testAPictureThatIsNotHttpsIsDropped() {
		$this->assertSame( '', $this->check( $this->claims( [ 'picture' => 'javascript:alert(1)' ] ) )['picture'] );
	}

	public function testTheClaimsAreReadFromTheToken() {
		$payload = rtrim( strtr( base64_encode( json_encode( [ 'sub' => '42' ] ) ), '+/', '-_' ), '=' );

		$this->assertSame( [ 'sub' => '42' ], Jwt::claims( 'header.' . $payload . '.signature' ) );
		$this->assertSame( [], Jwt::claims( 'not-a-token' ) );
	}

	public function testThePkceChallengeIsS256() {
		// The example in RFC 7636, appendix B.
		$this->assertSame( 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', Jwt::challenge( 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk' ) );
	}

	public function testRandomValuesAreLongAndUrlSafe() {
		$value = Jwt::random();

		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9_-]{43}$/', $value );
		$this->assertNotSame( $value, Jwt::random() );
	}
}
