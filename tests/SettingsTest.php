<?php
namespace NHRRob\SocialLogin\Tests;

use NHRRob\SocialLogin\Settings;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * Settings keep what was saved before there were other providers, and
 * clean what is typed in.
 */
class SettingsTest extends TestCase {

	protected function setUp(): void {
		WP_Mock::setUp();
		WP_Mock::userFunction( '__' )->andReturnArg( 0 );
		WP_Mock::passthruFunction( 'sanitize_text_field' );
		WP_Mock::passthruFunction( 'sanitize_textarea_field' );
		WP_Mock::passthruFunction( 'sanitize_key' );
		WP_Mock::passthruFunction( 'esc_url_raw' );
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	public function testGoogleStaysOnFromBeforeOtherProviders() {
		$clean = Settings::sanitize(
			[
				'google_client_id'     => ' id ',
				'google_client_secret' => 'secret',
				'create_accounts'      => true,
			]
		);

		$this->assertTrue( $clean['google_enabled'] );
		$this->assertFalse( $clean['github_enabled'] );
		$this->assertSame( 'id', $clean['google_client_id'] );
		$this->assertSame( 'secret', $clean['google_client_secret'] );
		$this->assertTrue( $clean['create_accounts'] );
		$this->assertSame( 'subscriber', $clean['new_user_role'] );
		$this->assertTrue( $clean['use_avatar'] );
		$this->assertSame( 'off', $clean['one_tap'] );
	}

	public function testAnUnknownOneTapValueIsOff() {
		$this->assertSame( 'off', Settings::sanitize( [ 'one_tap' => 'everywhere' ] )['one_tap'] );
		$this->assertSame( 'site', Settings::sanitize( [ 'one_tap' => 'site' ] )['one_tap'] );
	}

	public function testDomainsAreCleaned() {
		$this->assertSame( [ 'example.com', 'team.example.org' ], Settings::domains( "@Example.com, team.example.org\nnot a domain\nexample.com" ) );
		$this->assertSame( [], Settings::domains( '' ) );
	}

	public function testTheApplePrivateKeyKeepsItsLines() {
		$key = "-----BEGIN PRIVATE KEY-----\nABC\n-----END PRIVATE KEY-----";

		$this->assertSame( $key, Settings::sanitize( [ 'apple_private_key' => $key ] )['apple_private_key'] );
	}

	public function testConstantsAreNamedAfterProviderAndField() {
		$this->assertSame( 'NHRROB_SOCIAL_LOGIN_GITHUB_CLIENT_SECRET', Settings::constant( 'github', 'client_secret' ) );
	}
}
