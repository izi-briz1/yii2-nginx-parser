<?php

namespace app\components;

use app\models\UserAgentInfo;

class UserAgentInfoParser{
    public static function parse(string $userAgent): UserAgentInfo{
        return new UserAgentInfo(
            self::detectArchitecture($userAgent),
            self::detectOs($userAgent),
            self::detectBrowser($userAgent),
            self::detectIsBot($userAgent),
        );
    }

    private static function detectArchitecture(string $userAgent): string
    {
        static $architectures = [
            "x64" => ['x86_64', 'win64', 'wow64', 'x64', 'amd64', 'aarch64', 'arm64', 'ia64'],
            "x86" => ['i686', 'i586', 'i386', 'win32', 'x86'],
        ];

        foreach($architectures as $architecture => $tags){
            if(preg_match('~\b('. join('|', $tags) .')\b~i', $userAgent, $_)){
                return $architecture;
            }
        }

        return "unknown";
    }

    private static function detectOs(string $userAgent): string
    {
        static $oss = [
            "Windows" => 'Windows NT',
            "Android" => 'Android',
            "iOS" => ['iPhone', 'iPad'],
            "macOS" => ['Mac OS X', 'Macintosh'],
            "Linux" => 'Linux',
        ];

        foreach($oss as $os => $tags){
            if(preg_match('~\b('. join('|', (array)$tags) .')\b~i', $userAgent, $_)){
                return $os;
            }
        }

        return "unknown";
    }

    private static function detectBrowser(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Edg/'),
            str_contains($ua, 'Edge/') => "Edge",
            str_contains($ua, 'OPR/'),
            str_contains($ua, 'Opera') => "Opera",
            str_contains($ua, 'YaBrowser') => "Yandex Browser",
            str_contains($ua, 'Chrome/') => "Chrome",
            str_contains($ua, 'Firefox/') => "Firefox",
            str_contains($ua, 'Version/') && str_contains($ua, 'Safari') => "Safari",
            str_contains($ua, 'MSIE'),
            str_contains($ua, 'Trident/') => "Internet Explorer",
            default => "unknown",
        };
    }

    private static function detectIsBot(string $ua): bool
    {
        $known = [
            'Googlebot',
            'YandexBot',
            'Applebot',
            'bingbot',
            'Baiduspider',
            'DuckDuckBot',
            'Slurp',
            'facebookexternalhit',
            'bot',
            'crawler',
            'spider'
        ];

        return (bool)preg_match('~('. join('|', $known).')~i', $ua);
    }
}
