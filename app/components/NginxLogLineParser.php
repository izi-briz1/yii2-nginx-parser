<?php

namespace app\components;

use app\models\ParsedLine;
use DateTime;

class NginxLogLineParser{
    /**
     * @var string
     */
    const PATTERN = '~^(?P<ip>(?:\d+\.?){4})\s-\s-\s\[(?P<datetime>.*)\]\s"(?P<action>GET|PUT|PATCH|HEAD|DELETE|POST)\s(?P<url>.*?)\s(?P<protocol>.*?)"\s(?P<status>[1-5][0-9][0-9])\s(?P<size>\d+)\s"(?P<referrer>.*?)"\s"(?P<userAgent>.*?)"~';

    /**
     * @var string
     */
    const DATETIME_FORMAT = 'd/M/Y:H:i:s O';

    /**
     * @var UserAgentInfoParser
     */
    private UserAgentInfoParser $userAgentInfoParser;

    public function __construct(){
        $this->userAgentInfoParser = new UserAgentInfoParser();
    }

    // 172.18.0.1 - - [31/May/2026:18:18:04 +0000] "GET /assets/49603eb9/bootstrap.css HTTP/1.1" 200 280311 "http://localhost:8080/" "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:102.0) Gecko/20100101 Firefox/102.0"
    // 127.0.0.1 - - [21/Mar/2019:00:20:06 +0300] "GET /favicon/favicon-32.png HTTP/1.1" 200 1306 "/icms/catalog/catalog_edit?id=4" "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36"

    /**
     * @param string $line
     * @return ParsedLine|null
     */
    public function parse(string $line): ?ParsedLine{
        if($line === ''){
            return null;
        }

        $line = trim($line);
        if ($line === '') {
            return null;
        }

        if (!preg_match(self::PATTERN, $line, $m)) {
            return null;
        }

        $m['datetime'] = DateTime::createFromFormat(self::DATETIME_FORMAT, $m['datetime']);

        if($m['datetime'] === false){
            return null;
        }

        $m['datetime'] = $m['datetime']->format('Y-m-d H:i:s');

        $m['status'] = (int)$m['status'];

        if(empty($m['status'])){
            return null;
        }

        $parsedLine = new ParsedLine([
            'ip' => $m['ip'],
            'datetime' => $m['datetime'],
            'url' => $m['url'],
            'status' => $m['status'],
            'userAgent' => $m['userAgent'],
        ]);

        $userAgentInfo = $this->userAgentInfoParser->parse($m['userAgent']);

        $parsedLine->architecture = $userAgentInfo->architecture;
        $parsedLine->os = $userAgentInfo->os;
        $parsedLine->browser = $userAgentInfo->browser;
        $parsedLine->isBot = $userAgentInfo->isBot;

        return $parsedLine;
    }
}
