<?php

namespace Void;

use ArrayAccess;


class ServerSpecs implements ArrayAccess
{
    public const CPU=1;
    public const RAM=2;
    public const OS=4;
    public const WEB=8;

    public function offsetExists(mixed $offset): bool
    {
        return in_array(strtolower($offset), ['cpu', 'cpus', 'ram', 'os', 'web']);
    }

    public function offsetGet(mixed $offset): mixed
    {
        switch($offset) {
            case 'cpu':
            case 'cpus':
            case self::CPU:
                return static::getCPUs();
            case 'ram':
            case self::RAM:
                return static::getRAM();
            case 'os':
            case self::OS:
                return static::getOS();
            case 'web':
            case self::WEB:
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

    public function __invoke(array $params=[])
    {
        return statis::get($params);
    }

    public static function get(int|array $params=[]) : array
    {
        if((!is_array($params) || count($params) == 0) || $params==0 || $params==-1) {
            return array_merge(
                self::getCPUs(),
                self::getRAM(),
                self::getOS(),
                self::getWebServerVersions(),
            );
        }
        if(is_array($params)) {
            $info=[];
            foreach($params as $p) {
                switch($p) {
                    case 'cpu':
                    case 'cpus':
                    case self::CPU:
                        $info+=static::getCPUs();
                        break;
                    case 'ram':
                    case self::RAM:
                        $info+=static::getRAM();
                        break;
                    case 'os':
                    case self::OS:
                        $info+=static::getOS();
                        break;
                    case 'web':
                    case self::WEB:
                        $info+=static::getWebServerVersions();
                        break;
                }
            }
            return $info;
        }

        $info=[];
        if($params & self::CPU) {
            $info+=static::getCPUs();
        }
        if($params & self::RAM) {
            $info+=static::getRAM();
        }
        if($params & self::OS) {
            $info+=static::getOS();
        }
        if($params & self::WEB) {
            $info+=static::getWebServerVersions();
        }
        return $info;
    }

    public static function getOS() : array
    {
        preg_match('(NAME="([^"]*)")', shell_exec('cat /etc/*-release | grep NAME'), $out);

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
