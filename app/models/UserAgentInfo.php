<?php

namespace app\models;

class UserAgentInfo
{
    /**
     * @var string
     */
    public readonly string $architecture;

    /**
     * @var string
     */
    public readonly string $os;

    /**
     * @var string
     */
    public readonly string $browser;

    /**
     * @var bool
     */
    public readonly bool $isBot;

    /**
     * @param string $architecture
     * @param string $os
     * @param string $browser
     * @param bool $isBot
     */
    public function __construct(string $architecture, string $os, string $browser, bool $isBot)
    {
        $this->architecture = $architecture;
        $this->os = $os;
        $this->browser = $browser;
        $this->isBot = $isBot;
    }
}
