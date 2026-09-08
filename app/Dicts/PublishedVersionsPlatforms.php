<?php

namespace App\Dicts;

class PublishedVersionsPlatforms
{
    public const linux_and_mac = 'linux_and_mac';

    public const windows = 'windows';
    
    public static function getConstants()
    {
        $r = new \ReflectionClass(__CLASS__);
        return $r->getConstants();
    }
}
