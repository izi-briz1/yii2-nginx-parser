<?php

declare(strict_types=1);

namespace app\tests\Unit\components;

use app\components\UserAgentInfoParser;
use app\models\UserAgentInfo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserAgentInfoParserTest extends TestCase
{
    public function testParseReturnsUserAgentInfoInstance(): void
    {
        $ua = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36';

        self::assertInstanceOf(UserAgentInfo::class, UserAgentInfoParser::parse($ua));
    }

    public function testParseFillsAllFieldsForChromeOnLinux(): void
    {
        $ua = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36';

        $info = UserAgentInfoParser::parse($ua);

        self::assertSame('x64', $info->architecture);
        self::assertSame('Linux', $info->os);
        self::assertSame('Chrome', $info->browser);
        self::assertFalse($info->isBot);
    }

    public static function architectureProvider(): array
    {
        return [
            'x86_64' => ['Mozilla/5.0 (X11; Linux x86_64)', 'x64'],
            'win64' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'x64'],
            'wow64' => ['Mozilla/5.0 (Windows NT 6.1; WOW64)', 'x64'],
            'amd64' => ['Mozilla/5.0 (X11; FreeBSD amd64)', 'x64'],
            'arm64' => ['Mozilla/5.0 (Macintosh; arm64 Mac OS X)', 'x64'],
            'aarch64' => ['Mozilla/5.0 (Linux; aarch64)', 'x64'],
            'i686' => ['Mozilla/5.0 (X11; Linux i686)', 'x86'],
            'i386' => ['Mozilla/5.0 (Macintosh; Intel i386)', 'x86'],
            'win32' => ['Mozilla/5.0 (Windows NT 6.1; Win32)', 'x86'],
            'unknown' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)', 'unknown'],
        ];
    }

    #[DataProvider('architectureProvider')]
    public function testDetectArchitecture(string $ua, string $expected): void
    {
        self::assertSame($expected, UserAgentInfoParser::parse($ua)->architecture);
    }

    public static function osProvider(): array
    {
        return [
            'Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Windows'],
            'Android' => ['Mozilla/5.0 (Linux; Android 11; Pixel 5)', 'Android'],
            'iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)', 'iOS'],
            'iPad' => ['Mozilla/5.0 (iPad; CPU OS 13_2 like Mac OS X)', 'iOS'],
            'macOS' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', 'macOS'],
            'Linux' => ['Mozilla/5.0 (X11; Linux x86_64)', 'Linux'],
            'unknown' => ['Mozilla/5.0 (compatible; Some unknown platform)', 'unknown'],
        ];
    }

    #[DataProvider('osProvider')]
    public function testDetectOs(string $ua, string $expected): void
    {
        self::assertSame($expected, UserAgentInfoParser::parse($ua)->os);
    }

    /**
     * Android содержит подстроку Linux, но ОС должна определяться как Android,
     * так как проверяется раньше по порядку.
     */
    public function testAndroidTakesPrecedenceOverLinux(): void
    {
        $ua = 'Mozilla/5.0 (Linux; Android 11; SM-G991B) AppleWebKit/537.36';

        self::assertSame('Android', UserAgentInfoParser::parse($ua)->os);
    }

    /**
     * iPhone содержит "Mac OS X", но должна определяться iOS, так как идёт раньше.
     */
    public function testIosTakesPrecedenceOverMacOs(): void
    {
        $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)';

        self::assertSame('iOS', UserAgentInfoParser::parse($ua)->os);
    }

    public static function browserProvider(): array
    {
        return [
            'Edge (Edg/)' => ['Mozilla/5.0 (Windows NT 10.0) Chrome/91.0 Safari/537.36 Edg/91.0.864.59', 'Edge'],
            'Edge (Edge/)' => ['Mozilla/5.0 (Windows NT 10.0) Edge/18.18363', 'Edge'],
            'Opera (OPR/)' => ['Mozilla/5.0 (Windows NT 10.0) Chrome/77.0 Safari/537.36 OPR/64.0.3417.92', 'Opera'],
            'Opera (legacy)' => ['Opera/9.80 (Windows NT 6.0) Presto/2.12.388 Version/12.14', 'Opera'],
            'Yandex Browser' => ['Mozilla/5.0 (Windows NT 10.0) Chrome/89.0 YaBrowser/21.3.0 Safari/537.36', 'Yandex Browser'],
            'Chrome' => ['Mozilla/5.0 (X11; Linux x86_64) Chrome/73.0.3683.75 Safari/537.36', 'Chrome'],
            'Firefox' => ['Mozilla/5.0 (X11; Linux x86_64; rv:88.0) Gecko/20100101 Firefox/88.0', 'Firefox'],
            'Safari' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Version/14.0.3 Safari/605.1.15', 'Safari'],
            'IE (MSIE)' => ['Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1)', 'Internet Explorer'],
            'IE (Trident)' => ['Mozilla/5.0 (Windows NT 6.1; Trident/7.0; rv:11.0) like Gecko', 'Internet Explorer'],
            'unknown' => ['Mozilla/5.0 (Unknown custom client)', 'unknown'],
        ];
    }

    #[DataProvider('browserProvider')]
    public function testDetectBrowser(string $ua, string $expected): void
    {
        self::assertSame($expected, UserAgentInfoParser::parse($ua)->browser);
    }

    /**
     * Edge построен на Chromium и содержит "Chrome/", но должен определяться как Edge.
     */
    public function testEdgeTakesPrecedenceOverChrome(): void
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36 Edg/91.0.864.59';

        self::assertSame('Edge', UserAgentInfoParser::parse($ua)->browser);
    }

    /**
     * Yandex Browser содержит "Chrome/", но должен определяться как Yandex Browser.
     */
    public function testYandexBrowserTakesPrecedenceOverChrome(): void
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/89.0.4389.90 YaBrowser/21.3.0.740 Safari/537.36';

        self::assertSame('Yandex Browser', UserAgentInfoParser::parse($ua)->browser);
    }

    /**
     * У Chrome тоже есть токен "Safari", но без "Version/" это не Safari.
     */
    public function testChromeWithSafariTokenIsNotDetectedAsSafari(): void
    {
        $ua = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36';

        self::assertSame('Chrome', UserAgentInfoParser::parse($ua)->browser);
    }

    public static function botProvider(): array
    {
        return [
            'Googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', true],
            'YandexBot' => ['Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)', true],
            'Applebot' => ['Mozilla/5.0 (compatible; Applebot/0.1; +http://www.apple.com/go/applebot)', true],
            'bingbot' => ['Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)', true],
            'Baiduspider' => ['Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html)', true],
            'DuckDuckBot' => ['DuckDuckBot/1.0; (+http://duckduckgo.com/duckduckbot.html)', true],
            'Slurp' => ['Mozilla/5.0 (compatible; Yahoo! Slurp; http://help.yahoo.com/help/us/ysearch/slurp)', true],
            'Facebook' => ['facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', true],
            'generic bot' => ['SomeRandomBot/1.0', true],
            'generic crawler' => ['MyCrawler/2.0', true],
            'generic spider' => ['CoolSpider/1.0', true],
            'regular human Chrome' => ['Mozilla/5.0 (X11; Linux x86_64) Chrome/73.0.3683.75 Safari/537.36', false],
            'regular human Firefox' => ['Mozilla/5.0 (Windows NT 10.0; rv:88.0) Gecko/20100101 Firefox/88.0', false],
        ];
    }

    #[DataProvider('botProvider')]
    public function testDetectIsBot(string $ua, bool $expected): void
    {
        self::assertSame($expected, UserAgentInfoParser::parse($ua)->isBot);
    }

    public function testBotDetectionIsCaseInsensitive(): void
    {
        self::assertTrue(UserAgentInfoParser::parse('GOOGLEBOT/2.1')->isBot);
        self::assertTrue(UserAgentInfoParser::parse('googlebot/2.1')->isBot);
    }

    public function testEmptyUserAgent(): void
    {
        $info = UserAgentInfoParser::parse('');

        self::assertSame('unknown', $info->architecture);
        self::assertSame('unknown', $info->os);
        self::assertSame('unknown', $info->browser);
        self::assertFalse($info->isBot);
    }
}
