<?php

declare(strict_types=1);

namespace app\tests\Unit\components;

use app\components\NginxLogLineParser;
use app\models\ParsedLine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NginxLogLineParserTest extends TestCase
{
    private NginxLogLineParser $parser;

    protected function setUp(): void
    {
        $this->parser = new NginxLogLineParser();
    }

    /**
     * Эталонная строка должна разбираться во все поля.
     */
    public function testParseValidLineFillsAllFields(): void
    {
        $line = '192.168.0.1 - - [21/Mar/2019:00:20:06 +0300] "GET /favicon/favicon-32.png HTTP/1.1" 200 1306 "https://habr.com/ru/feed/" "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36"';

        $result = $this->parser->parse($line);

        self::assertInstanceOf(ParsedLine::class, $result);
        self::assertSame('192.168.0.1', $result->ip);
        self::assertSame('2019-03-21 00:20:06', $result->datetime);
        self::assertSame('/favicon/favicon-32.png', $result->url);
        self::assertSame(200, $result->status);
        self::assertSame(
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36',
            $result->userAgent,
        );
    }

    /**
     * Поля User-Agent должны заполняться через UserAgentInfoParser.
     */
    public function testParseFillsUserAgentDerivedFields(): void
    {
        $line = '192.168.0.1 - - [21/Mar/2019:00:20:06 +0300] "GET / HTTP/1.1" 200 1306 "-" "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36"';

        $result = $this->parser->parse($line);

        self::assertNotNull($result);
        self::assertSame('x64', $result->architecture);
        self::assertSame('Linux', $result->os);
        self::assertSame('Chrome', $result->browser);
        self::assertFalse($result->isBot);
    }

    public function testParseDetectsBotUserAgent(): void
    {
        $line = '66.249.66.1 - - [21/Mar/2019:00:20:06 +0300] "GET /robots.txt HTTP/1.1" 200 100 "-" "Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)"';

        $result = $this->parser->parse($line);

        self::assertNotNull($result);
        self::assertTrue($result->isBot);
    }

    /**
     * Лишние пробелы и перевод строки по краям должны обрезаться.
     */
    public function testParseTrimsSurroundingWhitespace(): void
    {
        $line = "  192.168.0.1 - - [21/Mar/2019:00:20:06 +0300] \"GET /index HTTP/1.1\" 200 500 \"-\" \"curl/7.68.0\"  \n";

        $result = $this->parser->parse($line);

        self::assertNotNull($result);
        self::assertSame('192.168.0.1', $result->ip);
        self::assertSame('/index', $result->url);
    }

    public static function httpMethodProvider(): array
    {
        return [
            'GET' => ['GET'],
            'POST' => ['POST'],
            'PUT' => ['PUT'],
            'PATCH' => ['PATCH'],
            'HEAD' => ['HEAD'],
            'DELETE' => ['DELETE'],
        ];
    }

    #[DataProvider('httpMethodProvider')]
    public function testParseSupportsHttpMethods(string $method): void
    {
        $line = sprintf(
            '10.0.0.5 - - [21/Mar/2019:00:20:06 +0300] "%1$s /api/resource HTTP/1.1" 200 10 "-" "curl/7.68.0"',
            $method,
        );

        $result = $this->parser->parse($line);

        self::assertNotNull($result, "Метод {$method} должен поддерживаться");
        self::assertSame('/api/resource', $result->url);
    }

    public static function statusProvider(): array
    {
        return [
            '200 OK' => ['200', 200],
            '301 redirect' => ['301', 301],
            '404 not found' => ['404', 404],
            '500 server error' => ['500', 500],
        ];
    }

    #[DataProvider('statusProvider')]
    public function testParseExtractsStatusAsInt(string $rawStatus, int $expected): void
    {
        $line = sprintf(
            '10.0.0.5 - - [21/Mar/2019:00:20:06 +0300] "GET / HTTP/1.1" %s 10 "-" "curl/7.68.0"',
            $rawStatus,
        );

        $result = $this->parser->parse($line);

        self::assertNotNull($result);
        self::assertSame($expected, $result->status);
    }

    public static function invalidLineProvider(): array
    {
        return [
            'empty string' => [''],
            'whitespace only' => ['   '],
            'newline only' => ["\n"],
            'garbage' => ['just some random text'],
            'missing user agent' => [
                '192.168.0.1 - - [21/Mar/2019:00:20:06 +0300] "GET / HTTP/1.1" 200 1306 "-"',
            ],
            'unsupported method' => [
                '192.168.0.1 - - [21/Mar/2019:00:20:06 +0300] "OPTIONS / HTTP/1.1" 200 1306 "-" "curl/7.68.0"',
            ],
            'status out of range (600)' => [
                '192.168.0.1 - - [21/Mar/2019:00:20:06 +0300] "GET / HTTP/1.1" 600 1306 "-" "curl/7.68.0"',
            ],
            'status out of range (099)' => [
                '192.168.0.1 - - [21/Mar/2019:00:20:06 +0300] "GET / HTTP/1.1" 099 1306 "-" "curl/7.68.0"',
            ],
            'non-numeric size' => [
                '192.168.0.1 - - [21/Mar/2019:00:20:06 +0300] "GET / HTTP/1.1" 200 - "-" "curl/7.68.0"',
            ],
        ];
    }

    #[DataProvider('invalidLineProvider')]
    public function testParseReturnsNullForInvalidLine(string $line): void
    {
        self::assertNull($this->parser->parse($line));
    }

    /**
     * Дата/время должны конвертироваться из nginx-формата в "Y-m-d H:i:s".
     */
    public function testParseConvertsDatetimeFormat(): void
    {
        $line = '192.168.0.1 - - [31/May/2026:18:18:04 +0000] "GET / HTTP/1.1" 200 280311 "-" "curl/7.68.0"';

        $result = $this->parser->parse($line);

        self::assertNotNull($result);
        self::assertSame('2026-05-31 18:18:04', $result->datetime);
    }

    /**
     * URL с query-строкой должен сохраняться полностью.
     */
    public function testParsePreservesUrlWithQueryString(): void
    {
        $line = '192.168.0.1 - - [21/Mar/2019:00:20:06 +0300] "GET /ru/companies/otpbank/news/1042016/?id=4&mode=full HTTP/1.1" 200 1306 "-" "curl/7.68.0"';

        $result = $this->parser->parse($line);

        self::assertNotNull($result);
        self::assertSame('/ru/companies/otpbank/news/1042016/?id=4&mode=full', $result->url);
    }
}
