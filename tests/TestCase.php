<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Seed the database once per process alongside migrate:fresh.
     */
    protected bool $seed = true;

    /** @var array{PASSPORT_PRIVATE_KEY: string, PASSPORT_PUBLIC_KEY: string}|null One OAuth signing key pair per test process. */
    private static ?array $passportKeys = null;

    protected function setUp(): void
    {
        $this->usePassportKeys();

        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Sign and verify Passport tokens with a key pair generated for this test process. It is
     * set in the environment before the application boots, so every part of Passport uses it
     * and local key files in storage/ are never read.
     */
    private function usePassportKeys(): void
    {
        if (self::$passportKeys === null) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $private);

            self::$passportKeys = ['PASSPORT_PRIVATE_KEY' => $private, 'PASSPORT_PUBLIC_KEY' => openssl_pkey_get_details($key)['key']];
        }

        foreach (self::$passportKeys as $name => $value) {
            $_ENV[$name] = $_SERVER[$name] = $value;
        }
    }
}
