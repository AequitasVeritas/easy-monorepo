<?php
declare(strict_types=1);

namespace StepTheFkUp\ApiToken\Tests\Decoders;

use StepTheFkUp\ApiToken\Decoders\JwtTokenDecoder;
use StepTheFkUp\ApiToken\Exceptions\InvalidApiTokenFromRequestException;
use StepTheFkUp\ApiToken\Tests\AbstractFirebaseJwtTokenTestCase;
use StepTheFkUp\ApiToken\Tokens\JwtApiToken;

final class FirebaseJwtTokenDecoderTest extends AbstractFirebaseJwtTokenTestCase
{
    /**
     * JwtTokenDecoder should decode token successfully for each algorithms.
     *
     * @return void
     *
     * @throws \StepTheFkUp\ApiToken\Exceptions\InvalidApiTokenFromRequestException
     */
    public function testJwtTokenDecodeSuccessfully(): void
    {
        foreach (static::$algos as $algo) {
            $key = static::$key;

            if ($this->isAlgoRs($algo)) {
                $key = $this->getOpenSslPublicKey();
            }

            $jwtApiTokenFactory = $this->createJwtApiTokenFactory($this->createFirebaseJwtDriver(
                null,
                $key,
                null,
                [$algo]
            ));

            /** @var \StepTheFkUp\ApiToken\Interfaces\Tokens\JwtApiTokenInterface $token */
            $token = (new JwtTokenDecoder($jwtApiTokenFactory))->decode($this->createServerRequest([
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->createToken($algo)
            ]));

            $payload = $token->getPayload();

            self::assertInstanceOf(JwtApiToken::class, $token);

            foreach (static::$tokenPayload as $key => $value) {
                self::assertArrayHasKey($key, $payload);
                self::assertEquals($value, $payload[$key]);
            }
        }
    }

    /**
     * JwtTokenDecoder should return null if Authorization header not set.
     *
     * @return void
     *
     * @throws \StepTheFkUp\ApiToken\Exceptions\InvalidApiTokenFromRequestException
     */
    public function testJwtTokenNullIfAuthorizationHeaderNotSet(): void
    {
        $decoder = new JwtTokenDecoder($this->createJwtApiTokenFactory($this->createFirebaseJwtDriver()));

        self::assertNull($decoder->decode($this->createServerRequest()));
    }

    /**
     * JwtTokenDecoder should return null if Authorization header doesn't start with "Bearer ".
     *
     * @return void
     *
     * @throws \StepTheFkUp\ApiToken\Exceptions\InvalidApiTokenFromRequestException
     */
    public function testJwtTokenNullIfDoesntStartWithBearer(): void
    {
        $decoder = new JwtTokenDecoder($this->createJwtApiTokenFactory($this->createFirebaseJwtDriver()));

        self::assertNull($decoder->decode($this->createServerRequest(['HTTP_AUTHORIZATION' => 'SomethingElse'])));
    }

    /**
     * JwtTokenDecoder should throw an exception if unable to decode token because token is invalid.
     *
     * @return void
     *
     * @throws \StepTheFkUp\ApiToken\Exceptions\InvalidApiTokenFromRequestException
     */
    public function testJwtTokenThrowExceptionIfUnableToDecodeToken(): void
    {
        $this->expectException(InvalidApiTokenFromRequestException::class);

        $jwtApiTokenFactory = $this->createJwtApiTokenFactory($this->createFirebaseJwtDriver(
            null,
            'different-key',
            null,
            ['HS256'],
            2
        ));

        (new JwtTokenDecoder($jwtApiTokenFactory))->decode($this->createServerRequest([
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->createToken()
        ]));
    }
}