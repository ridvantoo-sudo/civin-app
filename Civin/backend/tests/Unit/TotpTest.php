<?php

namespace Tests\Unit;

use App\Support\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    public function test_generated_secret_verifies_the_current_code(): void
    {
        $secret = Totp::generateSecret();
        $key = (new \ReflectionClass(Totp::class))->getMethod('base32Decode');
        $key->setAccessible(true);
        $decoded = $key->invoke(null, $secret);

        $codeAt = (new \ReflectionClass(Totp::class))->getMethod('codeAt');
        $codeAt->setAccessible(true);
        $code = $codeAt->invoke(null, $decoded, (int) floor(time() / 30));

        $this->assertTrue(Totp::verify($secret, $code));
        $this->assertFalse(Totp::verify($secret, '000000') && $code !== '000000');
    }
}
