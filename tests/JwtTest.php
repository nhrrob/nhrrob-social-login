<?php
namespace NHRRob\SocialLogin\Tests;

use NHRRob\SocialLogin\Jwt;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * Apple's client secret is signed with ES256 in the raw form a JWT takes,
 * and One Tap's token is checked against Google's RSA keys.
 */
class JwtTest extends TestCase {

	protected function setUp(): void {
		WP_Mock::setUp();
		WP_Mock::userFunction( 'wp_json_encode' )->andReturnUsing(
			function ( $data ) {
				return json_encode( $data );
			}
		);
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	public function testAnEs256TokenVerifiesWithThePublicKey() {
		$key = openssl_pkey_new(
			[
				'private_key_type' => OPENSSL_KEYTYPE_EC,
				'curve_name'       => 'prime256v1',
			]
		);
		openssl_pkey_export( $key, $pem );

		$token = Jwt::sign_es256( [ 'kid' => 'K1' ], [ 'sub' => 'services.id' ], $pem );
		$parts = explode( '.', $token );

		$this->assertCount( 3, $parts );
		$this->assertSame( [ 'kid' => 'K1', 'alg' => 'ES256' ], Jwt::part( $token, 0 ) );

		$raw = Jwt::decode( $parts[2] );
		$this->assertSame( 64, strlen( $raw ) );

		// Back to DER, which openssl_verify() reads.
		$int = function ( $bytes ) {
			$bytes = ltrim( $bytes, "\x00" );
			if ( ord( $bytes[0] ) > 0x7f ) {
				$bytes = "\x00" . $bytes;
			}
			return "\x02" . chr( strlen( $bytes ) ) . $bytes;
		};
		$seq = $int( substr( $raw, 0, 32 ) ) . $int( substr( $raw, 32 ) );
		$der = "\x30" . chr( strlen( $seq ) ) . $seq;

		$this->assertSame( 1, openssl_verify( $parts[0] . '.' . $parts[1], $der, openssl_pkey_get_details( $key )['key'], OPENSSL_ALGO_SHA256 ) );
	}

	public function testABadKeySignsNothing() {
		$this->assertSame( '', Jwt::sign_es256( [], [], 'not a key' ) );
	}

	public function testAnRs256TokenIsCheckedWithTheMatchingKey() {
		$key = openssl_pkey_new(
			[
				'private_key_type' => OPENSSL_KEYTYPE_RSA,
				'private_key_bits' => 2048,
			]
		);
		$rsa = openssl_pkey_get_details( $key )['rsa'];
		$jwk = [
			'kid' => 'G1',
			'n'   => Jwt::encode( $rsa['n'] ),
			'e'   => Jwt::encode( $rsa['e'] ),
		];

		$input = Jwt::encode( json_encode( [ 'alg' => 'RS256', 'kid' => 'G1' ] ) ) . '.' . Jwt::encode( json_encode( [ 'sub' => '1' ] ) );
		openssl_sign( $input, $signature, $key, OPENSSL_ALGO_SHA256 );
		$token = $input . '.' . Jwt::encode( $signature );

		$this->assertTrue( Jwt::verify_rs256( $token, [ $jwk ] ) );
		$this->assertFalse( Jwt::verify_rs256( $token, [ array_merge( $jwk, [ 'kid' => 'other' ] ) ] ) );
		$this->assertFalse( Jwt::verify_rs256( $input . '.' . Jwt::encode( 'forged' ), [ $jwk ] ) );

		$none = Jwt::encode( json_encode( [ 'alg' => 'none', 'kid' => 'G1' ] ) ) . '.' . Jwt::encode( json_encode( [ 'sub' => '1' ] ) ) . '.';
		$this->assertFalse( Jwt::verify_rs256( $none, [ $jwk ] ) );
	}

	public function testDerSignaturesBecomeRaw() {
		// r has a leading zero byte (a high first bit), s is short.
		$r   = "\x00" . str_repeat( "\x81", 32 );
		$s   = str_repeat( "\x01", 31 );
		$seq = "\x02" . chr( strlen( $r ) ) . $r . "\x02" . chr( strlen( $s ) ) . $s;
		$raw = Jwt::der_to_raw( "\x30" . chr( strlen( $seq ) ) . $seq, 32 );

		$this->assertSame( str_repeat( "\x81", 32 ) . "\x00" . $s, $raw );
		$this->assertSame( '', Jwt::der_to_raw( 'junk', 32 ) );
	}
}
