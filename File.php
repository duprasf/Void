<?php

namespace Void;

class File
{
    public static function humanReadableSize($bytes, $decimals = 2, array $specificSufix = array())
    {
        return HumanReadableSizeConvertion::toHumanReadable($bytes, $decimals, $specificSufix);
    }

    public static function humanReadableToBytes($size)
    {
        return HumanReadableSizeConvertion::toBytes($size);
    }

    public static function humanFilesize($filename, $decimals = 2)
    {
        return static::humanReadableSize(filesize($filename), $decimals);
    }

    public static function importSVG($file)
    {
        return preg_replace('((?:<?\?xml |<!DOCTYPE )[^>]+>)', '', file_get_contents($file));
        //<?xml version="1.0" encoding="utf-8"? >
        //<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
    }

    protected static $mimes;
    public static function mimeToExtension($mimeType)
    {
        if(!self::$mimes) {
            self::$mimes = include(__DIR__.'/mimeToExtension.php');
        }
        return self::$mimes[$mimeType] ?? false;
    }
}
