<?php

namespace Void;

use ArrayAccess;


class ServerSpecs implements ArrayAccess
{
    public function offsetExists(mixed $offset): bool
    {
        return in_array(strtolower($offset), ['cpu', 'cpus', 'ram', 'os', 'web']);
    }

    public function offsetGet(mixed $offset): mixed
    {
        switch($offset) {
            case 'cpu':
            case 'cpus':
                return static::getCPUs();
            case 'ram':
                return static::getRAM();
            case 'os':
                return static::getOS();
            case 'web':
                return static::getWebServerVersions();
            default:
                return false;
        }
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
    }

    public function offsetUnset(mixed $offset): void
    {
    }

    public function __invoke()
    {
        return statis::get();
    }

    public static function get() : array
    {
        return array_merge(
            self::getCPUs(),
            self::getRAM(),
            self::getOS(),
            self::getWebServerVersions(),
        );
    }

    public static function getOS() : array
    {
        preg_match('("([^\(]*))', `cat /etc/*-release | grep PRETTY_NAME`, $out);
        return ['OS'=>trim($out[1])];
    }

    public static function getWebServerVersions() : array
    {
        return [
            'phpVersion'=>PHP_VERSION,
            'apacheVersion'=>$_SERVER['SERVER_SOFTWARE'],
        ];
    }

    public static function getCPUs() : array
    {
        if(!file_exists('/proc/cpuinfo')) {
            return false;
        }
        $content = file_get_contents('/proc/cpuinfo');
        $numCPU =  substr_count($content,"\nprocessor")+1;
        preg_match('(cpu MHz\s+:\s(\d+))', $content, $out);
        // speed is in MHz so we have to multiple by 1048576 to get bytes
        $speed = HumanReadableSizeConvertion::toHumanReadable($out[1]*1048576, 1, 'Hz');
        return [
            'number of CPUs'=>$numCPU,
            'CPU speed'=>$speed,
            'cpus'=>$numCPU.'x'.$speed,
        ];
    }

    public static function getRAM() : array
    {
        $fh = fopen('/proc/meminfo','r');
        $total = 0;
        while ($line = fgets($fh)) {
            $pieces = [];
            if (preg_match('/^MemTotal:\s+(\d+)\skB$/', $line, $pieces)) {
                $total = $pieces[1]*1024;
                break;
            }
        }
        fclose($fh);
        return [
            'total memory'=>HumanReadableSizeConvertion::toHumanReadable($total, 0),
        ];
    }
}
