<?php
/**
 * Small JSON Web Token helpers: base64url, reading claims, checking an
 * OpenID Connect ID token's claims, signing with ES256 (Apple's client
 * secret) and verifying RS256 against a JSON Web Key (Google One Tap).
 *
 * @package NHRRob\SocialLogin
 */

namespace NHRRob\SocialLogin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Jwt Class
 *
 * Pure functions, no WordPress calls, so they are unit-tested directly.
 */
class Jwt {

	/**
	 * Seconds of clock difference allowed when checking a token's expiry.
	 */
	const LEEWAY = 60;

	/**
	 * A random value safe for a URL, for a state, a nonce or a code verifier.
	 *
	 * @return string 43 characters.
	 */
	public static function random() {
		return self::encode( random_bytes( 32 ) );
	}

	/**
	 * A PKCE code challenge (S256) for a verifier.
	 *
	 * @param string $verifier Code verifier.
	 * @return string
	 */
	public static function challenge( $verifier ) {
		return self::encode( hash( 'sha256', $verifier, true ) );
	}

	/**
	 * Base64url without padding.
	 *
	 * @param string $data Data.
	 * @return string
	 */
	public static function encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- base64url is the encoding PKCE and JWT use.
	}

	/**
	 * Decode base64url.
	 *
	 * @param string $data Data.
	 * @return string Empty when it is not base64url.
	 */
	public static function decode( $data ) {
		return (string) base64_decode( strtr( (string) $data, '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- base64url is the encoding a JWT uses.
	}

	/**
	 * One part of a JWT, decoded: 0 is the header, 1 the claims.
	 *
	 * @param string $jwt  The token.
	 * @param int    $part Which part.
	 * @return array Empty when it cannot be read.
	 */
	public static function part( $jwt, $part = 1 ) {
		$parts = explode( '.', (string) $jwt );

		if ( 3 !== count( $parts ) ) {
			return [];
		}

		$data = json_decode( self::decode( $parts[ $part ] ), true );

		return is_array( $data ) ? $data : [];
	}

	/**
	 * The claims of a JWT, unverified.
	 *
	 * @param string $jwt The token.
	 * @return array
	 */
	public static function claims( $jwt ) {
		return self::part( $jwt, 1 );
	}

	/**
	 * Whether an ID token's claims are for this sign-in: issuer, audience,
	 * expiry, nonce (when one was sent) and a subject.
	 *
	 * @param array    $claims    Claims.
	 * @param string[] $issuers   Accepted issuers.
	 * @param string   $client_id This site's client ID.
	 * @param string   $nonce     The nonce sent with the sign-in; '' when the provider takes none.
	 * @param int      $now       Current time.
	 * @return bool
	 */
	public static function valid_claims( array $claims, array $issuers, $client_id, $nonce, $now ) {
		$audience = isset( $claims['aud'] ) ? (array) $claims['aud'] : [];

		return isset( $claims['iss'], $claims['sub'], $claims['exp'] )
			&& in_array( $claims['iss'], $issuers, true )
			&& '' !== (string) $client_id && in_array( (string) $client_id, $audience, true )
			&& (int) $claims['exp'] + self::LEEWAY > $now
			&& ( '' === (string) $nonce || ( isset( $claims['nonce'] ) && hash_equals( (string) $nonce, (string) $claims['nonce'] ) ) )
			&& '' !== (string) $claims['sub'];
	}

	/**
	 * Whether a claim is true, as a boolean or the string "true".
	 *
	 * @param array  $claims Claims.
	 * @param string $name   Claim.
	 * @return bool
	 */
	public static function is_true( array $claims, $name ) {
		return isset( $claims[ $name ] ) && ( true === $claims[ $name ] || 'true' === $claims[ $name ] );
	}

	/**
	 * Sign claims with ES256 (an EC P-256 private key in PEM).
	 *
	 * @param array  $header  Header; `alg` is set to ES256.
	 * @param array  $claims  Claims.
	 * @param string $pem     Private key.
	 * @return string The token, or '' when the key cannot sign.
	 */
	public static function sign_es256( array $header, array $claims, $pem ) {
		$key = openssl_pkey_get_private( (string) $pem );

		if ( false === $key ) {
			return '';
		}

		$header['alg'] = 'ES256';
		$input         = self::encode( (string) wp_json_encode( $header ) ) . '.' . self::encode( (string) wp_json_encode( $claims ) );
		$der           = '';

		if ( ! openssl_sign( $input, $der, $key, OPENSSL_ALGO_SHA256 ) ) {
			return '';
		}

		$raw = self::der_to_raw( $der, 32 );

		return '' === $raw ? '' : $input . '.' . self::encode( $raw );
	}

	/**
	 * An ECDSA signature in DER (what OpenSSL makes) as the raw r||s a JWT carries.
	 *
	 * @param string $der    DER signature: SEQUENCE { INTEGER r, INTEGER s }.
	 * @param int    $length Bytes of each of r and s (32 for P-256).
	 * @return string '' when it cannot be read.
	 */
	public static function der_to_raw( $der, $length ) {
		$pos = 0;

		if ( strlen( $der ) < 8 || "\x30" !== $der[0] ) {
			return '';
		}

		// Sequence length: short form, or 0x81 and one byte.
		$pos = ( "\x81" === $der[1] ) ? 3 : 2;
		$out = '';

		for ( $i = 0; $i < 2; $i++ ) {
			if ( ! isset( $der[ $pos + 1 ] ) || "\x02" !== $der[ $pos ] ) {
				return '';
			}

			$len  = ord( $der[ $pos + 1 ] );
			$int  = ltrim( (string) substr( $der, $pos + 2, $len ), "\x00" );
			$pos += 2 + $len;

			if ( strlen( $int ) > $length ) {
				return '';
			}

			$out .= str_pad( $int, $length, "\x00", STR_PAD_LEFT );
		}

		return $out;
	}

	/**
	 * Verify an RS256 token's signature with a set of JSON Web Keys.
	 *
	 * @param string $jwt  The token.
	 * @param array  $keys JWKS `keys`: each with `kid`, `n`, `e`.
	 * @return bool
	 */
	public static function verify_rs256( $jwt, array $keys ) {
		$parts  = explode( '.', (string) $jwt );
		$header = self::part( $jwt, 0 );

		if ( 3 !== count( $parts ) || ! isset( $header['alg'], $header['kid'] ) || 'RS256' !== $header['alg'] ) {
			return false;
		}

		foreach ( $keys as $jwk ) {
			if ( ! is_array( $jwk ) || ! isset( $jwk['kid'], $jwk['n'], $jwk['e'] ) || $jwk['kid'] !== $header['kid'] ) {
				continue;
			}

			$key = openssl_pkey_get_public( self::rsa_pem( self::decode( $jwk['n'] ), self::decode( $jwk['e'] ) ) );

			return false !== $key && 1 === openssl_verify( $parts[0] . '.' . $parts[1], self::decode( $parts[2] ), $key, OPENSSL_ALGO_SHA256 );
		}

		return false;
	}

	/**
	 * A PEM public key from an RSA modulus and exponent.
	 *
	 * @param string $n Modulus, big-endian bytes.
	 * @param string $e Exponent, big-endian bytes.
	 * @return string
	 */
	public static function rsa_pem( $n, $e ) {
		$rsa  = self::der_int( $n ) . self::der_int( $e );
		$rsa  = "\x30" . self::der_length( strlen( $rsa ) ) . $rsa;
		$bits = "\x03" . self::der_length( strlen( $rsa ) + 1 ) . "\x00" . $rsa;
		// AlgorithmIdentifier: rsaEncryption, NULL.
		$algo = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
		$spki = "\x30" . self::der_length( strlen( $algo . $bits ) ) . $algo . $bits;

		return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $spki ), 64, "\n" ) . "-----END PUBLIC KEY-----\n"; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- PEM is base64.
	}

	/**
	 * A DER INTEGER from big-endian bytes.
	 *
	 * @param string $bytes Bytes.
	 * @return string
	 */
	private static function der_int( $bytes ) {
		$bytes = ltrim( $bytes, "\x00" );

		// A leading 1 bit would read as negative.
		if ( '' === $bytes || ord( $bytes[0] ) > 0x7f ) {
			$bytes = "\x00" . $bytes;
		}

		return "\x02" . self::der_length( strlen( $bytes ) ) . $bytes;
	}

	/**
	 * A DER length.
	 *
	 * @param int $length Length.
	 * @return string
	 */
	private static function der_length( $length ) {
		if ( $length < 0x80 ) {
			return chr( $length );
		}

		$bytes = ltrim( pack( 'N', $length ), "\x00" );

		return chr( 0x80 | strlen( $bytes ) ) . $bytes;
	}
}
