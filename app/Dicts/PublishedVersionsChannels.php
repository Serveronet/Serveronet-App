<?php

namespace App\Dicts;

class PublishedVersionsChannels
{
    public const prod = 'prod';

    public const dev = 'dev';

    public static function getConstants()
    {
        $r = new \ReflectionClass(__CLASS__);

        return $r->getConstants();
    }
}
