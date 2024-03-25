<?php
namespace Void;

class File {
    static public function humanReadableSize($bytes, $decimals=2, array $specificSufix=array())
    {
        // found at http://highscalability.com/blog/2012/9/11/how-big-is-a-petabyte-exabyte-zettabyte-or-a-yottabyte.html
        $sz = 'BKMGTPEZYXSD';
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . (isset($specificSufix[$factor]) ? ' '.$specificSufix[$factor] : (isset($sz[$factor]) ? $sz[$factor] : 'too large'));
    }

    static public function humanReadableToBytes($size)
    {
        $indexes = array('B','K','M','G','T','P');
        preg_match('(^(\d+)([BKMGTP])?$)', $size, $out);
        if(isset($out[1])) {
            $factor = isset($out[2]) ? array_search($out[2], $indexes) : 0;
        }
        $value = $out[1] * pow(1024, $factor);
        return $value;
    }

    static public function humanFilesize($filename, $decimals=2)
    {
        return static::humanReadableSize(filesize($filename), $decimals);
    }

    static public function importSVG($file)
    {
        return preg_replace('((?:<?\?xml |<!DOCTYPE )[^>]+>)', '', file_get_contents($file));
        //<?xml version="1.0" encoding="utf-8"? >
        //<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
    }

    static protected $mimes;
    static public function mimeToExtension($mimeType)
    {
        if(!self::$mimes) {
            self::$mimes=include(__DIR__.'/mimeToExtension.php');
        }
        return self::$mimes[$mimeType] ?? false;
    }
}
