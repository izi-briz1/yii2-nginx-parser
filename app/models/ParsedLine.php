<?php

namespace app\models;

use yii\base\BaseObject;

class ParsedLine extends BaseObject{
    public string $ip;
    public string $datetime;
    public string $url;
    public int $status;
    public string $userAgent;
    public string $architecture;
    public string $os;
    public string $browser;
    public bool $isBot;
}
