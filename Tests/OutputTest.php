<?php

use Tanbolt\Console\Output;
use PHPUnit\Framework\TestCase;

class OutputTest extends TestCase
{
    public function testSetStdoutStream()
    {
        $output = new Output();
        $stream = fopen('php://memory', 'wb');
        static::assertSame($output, $output->setStdoutStream($stream));
        static::assertSame($stream, $output->stdoutStream());



    }
}

