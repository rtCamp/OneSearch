<?php
/**
 * Test the Encryptor class.
 *
 * @package OneSearch\Tests\Unit\inc
 */

declare( strict_types = 1 );

namespace OneSearch\Tests\Unit\inc;

use OneSearch\Encryptor;
use OneSearch\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class - EncryptorTest
 */
#[CoversClass( Encryptor::class )]
class EncryptorTest extends TestCase {
	/**
	 * Test encryption and decryption flow.
	 */
	public function test_encrypt_decrypt_flow(): void {
		$original_value = 'my_super_secret_string';

		$encrypted_value = Encryptor::encrypt( $original_value );
		$this->assertNotEquals( $original_value, $encrypted_value );
		$this->assertIsString( $encrypted_value );

		$decrypted_value = Encryptor::decrypt( $encrypted_value );
		$this->assertEquals( $original_value, $decrypted_value );
	}
}
