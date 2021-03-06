<?php

declare(strict_types=1);

namespace EonX\EasyApiToken\Tests\Decoders;

use EonX\EasyApiToken\Decoders\ApiKeyDecoder;
use EonX\EasyApiToken\Interfaces\Tokens\ApiKeyInterface;
use EonX\EasyApiToken\Tests\AbstractTestCase;

final class ApiKeyDecoderTest extends AbstractTestCase
{
    public function testApiKeyNullIfAuthorizationHeaderNotSet(): void
    {
        self::assertNull((new ApiKeyDecoder())->decode($this->createRequest()));
    }

    public function testApiKeyNullIfDoesntStartWithBasic(): void
    {
        self::assertNull((new ApiKeyDecoder())->decode($this->createRequest([
            'HTTP_AUTHORIZATION' => 'SomethingElse',
        ])));
    }

    public function testApiKeyNullIfNotOnlyApiKeyProvided(): void
    {
        $tests = ['', ':', ':password', 'api-key:password'];

        foreach ($tests as $test) {
            self::assertNull((new ApiKeyDecoder())->decode($this->createRequest([
                'HTTP_AUTHORIZATION' => 'Basic ' . \base64_encode($test),
            ])));
        }
    }

    public function testApiKeyReturnEasyApiTokenSuccessfully(): void
    {
        // Value in header => [expectedUsername, expectedPassword]
        $tests = [
            'api-key' => ['api-key'],
            'api-key:' => ['api-key'],
            'api-key: ' => ['api-key'],
            'api-key:     ' => ['api-key'],
        ];

        foreach ($tests as $test => $expected) {
            /** @var \EonX\EasyApiToken\Interfaces\Tokens\ApiKeyInterface $token */
            $token = (new ApiKeyDecoder())->decode($this->createRequest([
                'HTTP_AUTHORIZATION' => \sprintf('Basic %s', \base64_encode($test)),
            ]));

            self::assertInstanceOf(ApiKeyInterface::class, $token);
            self::assertEquals($expected[0], $token->getPayload()['api_key']);
        }
    }
}
