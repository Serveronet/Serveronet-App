<?php

namespace App\Dicts;

class PublishedVersionsSources
{
    public const central_server = 'central_server';

    public const dev_server = 'dev_server';

    public const github = 'github';

    public static function getConstants()
    {
        $r = new \ReflectionClass(__CLASS__);
        return $r->getConstants();
    }
}
