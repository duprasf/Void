<?php

namespace Void;

class HumanReadableSizeConvertion
{
    public static function toHumanReadable($bytes, $decimals = 2, array|string $specificSufix = 'B')
    {
        $sz = str_split('BkMGTPEZY');
        $factor = floor((strlen($bytes) - 1) / 3)*1;
        $size = sprintf("%.{$decimals}f", $bytes / pow(1024, $factor));

        if(!isset($specificSufix[$factor]) && !isset($sz[$factor])) {
            return false;
        }
        if(is_array($specificSufix)) {
            if(isset($specificSufix[$factor])){
                return $size. ' '.$specificSufix[$factor];
            }
            return $size;
        }

        if(isset($sz[$factor])) {
            return $size.$sz[$factor].$specificSufix;
        }
        return $size;
    }

    public static function toBytes($size)
    {
        $indexes = str_split('BKMGTPEZY');
        preg_match('(^([\d\.,]+)(([BKMGTPEZY])([ob]))?)i', $size, $out);
        if(isset($out[1])) {
            $factor = isset($out[3]) ? array_search(strtoupper($out[3]), $indexes) : 0;
        }
        $value = $out[1] * pow(1024, $factor);
        return intval($value);
    }
}
