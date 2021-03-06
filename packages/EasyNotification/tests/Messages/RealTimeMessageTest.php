<?php

declare(strict_types=1);

namespace EonX\EasyNotification\Tests\Messages;

use EonX\EasyNotification\Interfaces\MessageInterface;
use EonX\EasyNotification\Messages\RealTimeMessage;
use EonX\EasyNotification\Tests\AbstractTestCase;
use Nette\Utils\Json;

final class RealTimeMessageTest extends AbstractTestCase
{
    /**
     * @var mixed[]
     */
    protected static $body = [
        'message' => 'hey there',
    ];

    /**
     * @var string[]
     */
    protected static $topics = ['nathan', 'pavel'];

    /**
     * @return iterable<mixed>
     *
     * @see testGetters
     */
    public function providerTestGetters(): iterable
    {
        yield 'Constructor' => [
            function (): RealTimeMessage {
                return new RealTimeMessage(static::$body, static::$topics);
            },
            static::$body,
            static::$topics,
        ];

        yield 'Create method' => [
            function (): RealTimeMessage {
                return RealTimeMessage::create(static::$body, static::$topics);
            },
            static::$body,
            static::$topics,
        ];

        yield 'Create method + topics' => [
            function (): RealTimeMessage {
                return RealTimeMessage::create(static::$body)->topics(static::$topics);
            },
            static::$body,
            static::$topics,
        ];

        yield 'Create method + body + topics' => [
            function (): RealTimeMessage {
                $message = RealTimeMessage::create()->topics(static::$topics);
                $message->body(static::$body);

                return $message;
            },
            static::$body,
            static::$topics,
        ];
    }

    /**
     * @param mixed[] $body
     * @param string[] $topics
     *
     * @dataProvider providerTestGetters
     *
     * @throws \Nette\Utils\JsonException
     */
    public function testGetters(callable $getMessage, array $body, array $topics): void
    {
        /** @var \EonX\EasyNotification\Messages\RealTimeMessage $message */
        // Trick for coverage
        $message = $getMessage();

        self::assertEquals(MessageInterface::TYPE_REAL_TIME, $message->getType());
        self::assertEquals(Json::encode($body), $message->getBody());
        self::assertEquals($topics, $message->getTopics());
    }
}
