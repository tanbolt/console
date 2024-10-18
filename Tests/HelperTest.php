<?php

use Tanbolt\Console\Helper;
use PHPUnit\Framework\TestCase;

class HelperTest extends TestCase
{
    const WIN = '\\' === DIRECTORY_SEPARATOR;

    public function testRunCommandSuccess()
    {
        $out = "a\nb";
        $str = sprintf('%s -r "echo \"a\nb\"; exit();" 2>&1', PHP_BINARY);

        if (function_exists('exec')) {
            static::assertEquals($out, Helper::runCommand($str, $code, 1));
            static::assertEquals(0, $code);
        }
        if (function_exists('proc_open') && function_exists('proc_close')) {
            static::assertEquals($out, Helper::runCommand($str, $code, 2));
            static::assertEquals(0, $code);
        }
        if (function_exists('system')) {
            static::assertEquals($out, Helper::runCommand($str, $code, 3));
            static::assertEquals(0, $code);
        }
        if (function_exists('passthru')) {
            static::assertEquals($out, Helper::runCommand($str, $code, 4));
            static::assertEquals(0, $code);
        }
        if (function_exists('shell_exec')) {
            static::assertEquals($out, Helper::runCommand($str, $code, 5));
            static::assertEquals(0, $code);
        }
        if (function_exists('popen') && function_exists('pclose')) {
            static::assertEquals($out, Helper::runCommand($str, $code, 6));
            static::assertEquals(0, $code);
        }
    }

    public function testRunCommandFailed()
    {
        $out = "a\nb";
        $str = sprintf('%s -r "echo \"a\nb\"; exit(23);" 2>&1', PHP_BINARY);

        if (function_exists('exec')) {
            static::assertEquals($out, Helper::runCommand($str, $code, 1));
            static::assertEquals(23, $code);
        }
        if (function_exists('proc_open') && function_exists('proc_close')) {
            static::assertEquals($out, Helper::runCommand($str, $code, 2));
            static::assertEquals(23, $code);
        }
        if (function_exists('system')) {
            static::assertEquals($out, Helper::runCommand($str, $code, 3));
            static::assertEquals(23, $code);
        }
        if (function_exists('passthru')) {
            static::assertEquals($out, Helper::runCommand($str, $code, 4));
            static::assertEquals(23, $code);
        }
        if (function_exists('shell_exec')) {
            static::assertEquals($out, Helper::runCommand($str, $code, 5));
            if (static::WIN) {
                static::assertTrue($code > 0);
            } else {
                static::assertEquals(23, $code);
            }
        }
        if (function_exists('popen') && function_exists('pclose')) {
            static::assertEquals($out, Helper::runCommand($str, $code, 6));
            if (static::WIN) {
                static::assertTrue($code > 0);
            } else {
                static::assertEquals(23, $code);
            }
        }
    }

    public function testIsRegularStr()
    {
        static::assertFalse(Helper::isRegularStr('@zz'));
        static::assertTrue(Helper::isRegularStr('1z_v-'));
    }

    /**
     * @dataProvider getStrToArgvData
     * @param $str
     * @param $argv
     * @param null $unixOnly
     */
    public function testStrToArgv($str, $argv, $unixOnly = null)
    {
        if (!$unixOnly || '\\' !== DIRECTORY_SEPARATOR) {
            static::assertEquals($argv, Helper::strToArgv($str));
        } else {
            static::assertTrue(true);
        }
    }

    public function getStrToArgvData()
    {
        return [
            /**
             * 通用， unix win 通用型
             * 参数可使用双引号包裹，在包裹内可使用空格，整个包裹会当作一个整体字符处理
             */
            ['', []],
            ['foo', ['foo']],
            ['  foo  bar  ', ['foo', 'bar']],
            ['  foo " " bar  ', ['foo', ' ', 'bar']],
            ['foo a"b c"d bar', ['foo', 'ab cd', 'bar']],
            ["foo \"a\rb\nc\td\" bar", ['foo', "a\rb\nc\td", 'bar']],
            ['-a', ['-a']],
            ['-long', ['-long']],
            ['--foo-bar', ['--foo-bar']],
            ['--"foo bar"', ['--foo bar']],
            ['--s"foo bar"e', ['--sfoo bare']],
            ['--foo-bar=foo', ['--foo-bar=foo']],
            ['--foo"-b ar"="foo bar"', ['--foo-b ar=foo bar']],
            [
                'cmd foo  --foo  --b"i z"  " "  "bar"z  --baz="b az" '
                ."\"a\rb\nc\td\""
                .' --"que"=que --lst="lst"'
                .' --q"ue"=' . '"f\tg\nh\"j"',
                [
                    'cmd', 'foo', '--foo', '--bi z', ' ', 'barz', '--baz=b az',
                    "a\rb\nc\td",
                    '--que=que', '--lst=lst',
                    '--que=f\tg\nh"j'
                ]
            ],

            // WinCmd 参数不支持单引号包裹, 且不支持连续引号, 以下仅在 Unix 下测试
             ["\'quoted\'", ['\'quoted\''], 1],
             ['foo "baz" bar \'biz\'', ['foo', 'baz', 'bar', 'biz'], 1],
             ['-a"foo bar""foo bar"', ['-afoo barfoo bar'], 1],
             ['-a\'foo bar\'', ['-afoo bar'], 1],
             ['-a\'foo bar\'\'foo bar\'', ['-afoo barfoo bar'], 1],
             ['-a\'foo bar\'"foo bar"', ['-afoo barfoo bar'], 1],
             ['--foo-bar="foo bar""another"', ['--foo-bar=foo baranother'], 1],
             ['--foo-bar=\'foo bar\'', ['--foo-bar=foo bar'], 1],
             ["--foo-bar='foo bar''another'", ['--foo-bar=foo baranother'], 1],
             ["--foo-bar='foo bar'\"another\"", ['--foo-bar=foo baranother'], 1],
        ];
    }

    /**
     * @dataProvider getArgvToStrData
     * @param $argv
     * @param $str
     */
    public function testArgvToStr($argv, $str)
    {
        static::assertEquals($str, Helper::argvToStr($argv));
        static::assertEquals($argv, Helper::strToArgv($str));
    }

    public function getArgvToStrData()
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            // Win 特殊符合使用双引号包裹
            return [
                [['foo'], 'foo'],
                [['f"o \' o'], '"f"""o \' o"'],
                [['f"%DD% !VAR!'], '"f""""%"DD"%" "!"VAR"!""'],
                [['foo\\'], '"foo\\\\"'],
                [['-foo'], '-foo'],
                [['-"fo!o'], '"-"""fo"!"o"'],
                [['-foo=foo'], '-foo=foo'],
                [['-foo=f o"o'], '-foo="f o"""o"'],
                [
                    ['foo', ' ', 'ba"r', '-foo', '-b%ar%', '-foo=foo', '-f o!o=foo', '-foo=f o\'o', '-fo o=ba " r'],
                    'foo " " "ba"""r" -foo "-b"%"ar"%"" -foo=foo "-f o"!"o=foo" -foo="f o\'o" "-fo o=ba """ r"'
                ],
            ];
        }
        // Unix 特殊符合使用单引号包裹
        return [
            [['foo'], 'foo'],
            [['f"o \' o'], "'f\"o '\'' o'"],
            [['f"%DD% !VAR!'], "'f\"%DD% !VAR!'"],
            [['foo\\'], "'foo\'"],
            [['-foo'], '-foo'],
            [['-"fo!o'], "'-\"fo!o'"],
            [['-foo=foo'], '-foo=foo'],
            [['-foo=f o"o'], "-foo='f o\"o'"],
            [
                ['foo', ' ', 'ba"r', '-foo', '-b%ar%', '-foo=foo', '-f o!o=foo', '-foo=f o\'o', '-fo o=ba " r'],
                "foo ' ' 'ba\"r' -foo '-b%ar%' -foo=foo '-f o!o=foo' -foo='f o'\''o' '-fo o=ba \" r'"
            ],
        ];
    }

    public function testStdStream()
    {
        static::assertTrue(is_resource(Helper::getStdoutStream()));
        static::assertTrue(is_resource(Helper::getStderrStream()));
    }

    public function testIsFileStream()
    {
        $fp = fopen('php://stdout', 'r');
        static::assertFalse(Helper::isFileStream($fp));
        fclose($fp);

        $fp = fopen(__DIR__.'/phpunit.xml', 'r');
        static::assertTrue(Helper::isFileStream($fp));
        fclose($fp);
    }

    public function testCloneStream()
    {
        static::assertNull(Helper::cloneStream('s'));
        $stream = fopen('php://memory', 'rb+');
        fwrite($stream, '12');
        $clone = Helper::cloneStream($stream);
        static::assertEquals('', fread($clone, 2));
        static::assertEquals('', fread($stream, 2));

        fwrite($stream, '34');
        rewind($stream);
        static::assertEquals('1234', fread($stream, 4));

        rewind($clone);
        static::assertEquals('12', fread($clone, 4));
        fwrite($clone, '34');
        rewind($clone);
        static::assertEquals('1234', fread($clone, 4));
    }

    public function testWriteStream()
    {
        $stream = fopen('php://memory', 'rb+');
        fwrite($stream, '12');
        Helper::writeStream($stream, '34');
        rewind($stream);
        static::assertEquals('1234', fread($stream, 4));
    }

    public function testStrCount()
    {
        static::assertEquals(3, Helper::strCount('foo'));
        static::assertEquals(7, Helper::strCount('foo bar'));
        static::assertEquals(2, Helper::strCount('中国'));
        static::assertEquals(3, Helper::strCount('中 国'));
        static::assertEquals(5, Helper::strCount('中国 cn'));
        static::assertEquals(9, Helper::strCount("foo\n中国 cn"));
        static::assertEquals(11, Helper::strCount("foo\r\nbar\n中国"));
    }

    public function testStrSplit()
    {
        static::assertEquals(['f', 'o', 'o'], Helper::strSplit('foo'));
        static::assertEquals(['f', 'o', ' ', 'o'], Helper::strSplit('fo o'));
        static::assertEquals(['中','国'], Helper::strSplit('中国'));
        static::assertEquals(['中',' ','国',' ','c','n'], Helper::strSplit('中 国 cn'));
        static::assertEquals(
            ['f', 'o', ' ', 'o', "\n", '中', "\r", '国',' ','c','n'],
            Helper::strSplit("fo o\n中\r国 cn")
        );
    }

    public function testStrWidth()
    {
        static::assertEquals(3, Helper::strWidth('foo'));
        static::assertEquals(7, Helper::strWidth('foo bar'));
        static::assertEquals(4, Helper::strWidth('中国'));
        static::assertEquals(5, Helper::strWidth('中 国'));
        static::assertEquals(7, Helper::strWidth('中国 cn'));

        static::assertEquals(7, Helper::sectionWidth('中国 cn'));
        static::assertEquals(7, Helper::sectionWidth("foo\n中国 cn"));
        static::assertEquals(4, Helper::sectionWidth("foo\nbar\n中国"));
    }

    public function testGetChapter()
    {
        static::assertEquals("foo", Helper::getChapter('foo', 3));
        static::assertEquals("foo", Helper::getChapter('foo', 6));
        static::assertEquals("foo---", Helper::getChapter('foo', 6, '-'));
        static::assertEquals("---foo", Helper::getChapter('foo', 6, '-', 'right'));
        static::assertEquals("-foo--", Helper::getChapter('foo', 6, '-', 'center'));
        static::assertEquals("--foo--", Helper::getChapter('foo', 7, '-', 'center'));

        static::assertEquals("foo\nbar", Helper::getChapter('foobar', 3));
        static::assertEquals("foob\nar", Helper::getChapter('foobar', 4));
        static::assertEquals("foob\nar--", Helper::getChapter('foobar', 4, '-'));
        static::assertEquals("foob\n--ar", Helper::getChapter('foobar', 4, '-', 'right'));
        static::assertEquals("foob\n-ar-", Helper::getChapter('foobar', 4, '-', 'center'));

        static::assertEquals("中\n国b\nar", Helper::getChapter('中国bar', 3));
        static::assertEquals("中-\n国b\nar-", Helper::getChapter('中国bar', 3, '-'));
    }

    public function testGetMessageContainer()
    {
        static::assertEquals([
            'foo ',
            ['var', '', ''],
            ' bar'
        ], Helper::getMessageContainer('foo %var% bar'));

        static::assertEquals([
            'foo ',
            ['var', 'p', 'n'],
            ' bar'
        ], Helper::getMessageContainer('foo %p{var}n% bar'));

        static::assertEquals([
            'foo ',
            ['var', 'p', 'n{cc}m'],
            ' bar'
        ], Helper::getMessageContainer('foo %p{var}n{cc}m% bar'));

        static::assertEquals([
            'foo ',
            ['var', 'p', 'n'],
            " bar\nque ",
            ['cc', '', ''],
            ' biz'
        ], Helper::getMessageContainer("foo %p{var}n% bar\nque %cc% biz"));
    }

    /**
     * @dataProvider getSprintfMessageData
     * @param $message
     * @param $data
     * @param $parsed
     */
    public function testSprintfMessage($message, $data, $parsed)
    {
        static::assertEquals($parsed, Helper::sprintfMessage($message, $data));
        static::assertEquals($parsed, Helper::sprintfMessageContainer(Helper::getMessageContainer($message), $data));
    }

    public function getSprintfMessageData()
    {
        $message = "foo %p{foo}n% bar\na %bar% b\nx %p{biz}%y\nm %{baz}n%n\nw %{bar}%e";
        return [
            [$message, [], "foo  bar\na  b\nx y\nm n\nw e"],
            [$message, ['foo' => 'foo'], "foo pfoon bar\na  b\nx y\nm n\nw e"],
            [
                $message,
                ['foo' => 'foo', 'bar' => 'bar'],
                "foo pfoon bar\na bar b\nx y\nm n\nw bare"
            ],
            [
                $message,
                ['foo' => 'foo', 'bar' => 'bar', 'biz' => 'biz'],
                "foo pfoon bar\na bar b\nx pbizy\nm n\nw bare"
            ],
            [
                $message,
                ['foo' => 'foo', 'bar' => 'bar', 'biz' => 'biz', 'baz' => 'baz'],
                "foo pfoon bar\na bar b\nx pbizy\nm baznn\nw bare"
            ]
        ];
    }

    /**
     * @dataProvider getFormatTimeData
     * @param $secs
     * @param $time
     */
    public function testFormatTime($secs, $time)
    {
        static::assertEquals($time, Helper::formatTime($secs));
    }

    public function getFormatTimeData()
    {
        return [
            [-10, '-10 sec'],
            [0, '0 sec'],
            [10, '10 sec'],
            [70, '1 min'],
            [300, '5 min'],
            [700, '11 min'],
            [1000, '16 min'],
            [5000, '1.3 hour']
        ];
    }

    /**
     * @dataProvider getFormatMemoryData
     * @param $memory
     * @param $str
     */
    public function testFormatMemory($memory, $str)
    {
        static::assertEquals($str, Helper::formatMemory($memory));
    }

    public function getFormatMemoryData()
    {
        return [
            [-10, '-10B'],
            [0, '0B'],
            [320, '320B'],
            [1024, '1K'],
            [1025, '1K'],
            [1048576, '1.0M'],
            [6098578, '5.8M'],
            [1073741824, '1.0G'],
            [1873741826, '1.7G']
        ];
    }
}

