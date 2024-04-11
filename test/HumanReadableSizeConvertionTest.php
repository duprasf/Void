<?php

namespace VoidTest;

use PHPUnit\Framework\TestCase;
use Void\HumanReadableSizeConvertion;

class HumanReadableSizeConvertionTest extends TestCase
{
    public function testToHumanReadable()
    {
        $this->assertSame('1ko', HumanReadableSizeConvertion::toHumanReadable(1024, 0, 'o'));
        $this->assertSame('1GHz', HumanReadableSizeConvertion::toHumanReadable(1024 * 1024 * 1024, 0, 'Hz'));
        $this->assertSame('1kB', HumanReadableSizeConvertion::toHumanReadable(1024, 0));
        $this->assertSame('1MB', HumanReadableSizeConvertion::toHumanReadable(1024 * 1024, 0));
        $this->assertSame('1GB', HumanReadableSizeConvertion::toHumanReadable(1024 * 1024 * 1024, 0));
        $this->assertSame('2.4kB', HumanReadableSizeConvertion::toHumanReadable(2500, 1));
        $this->assertSame('120.56kB', HumanReadableSizeConvertion::toHumanReadable(123456, 2));
        $this->assertSame('5.25MB', HumanReadableSizeConvertion::toHumanReadable(5505024, 2));
    }

    public function testToBytes()
    {
        $this->assertSame(1024, HumanReadableSizeConvertion::toBytes('1KB'));
        $this->assertSame(5505024, HumanReadableSizeConvertion::toBytes('5.25MB'));
    }
}
