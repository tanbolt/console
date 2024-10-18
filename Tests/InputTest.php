<?php

use Tanbolt\Console\Input;
use Tanbolt\Console\Exception\InputException;
use PHPUnit\Framework\TestCase;

class InputTest extends TestCase
{
    public function testSetStream()
    {
        $input = new Input();

        $stream = $input->getStream();
        static::assertTrue(is_resource($stream));
        static::assertNull($input->getStream(true));

        $stream = fopen('php://memory', 'rb+');
        static::assertSame($input, $input->setStream($stream));
        static::assertSame($stream, $input->getStream());
        static::assertSame($stream, $input->getStream(true));

        try {
            $input->setStream('str');
            static::fail('It should throw exception when stream is not resource');
        } catch (InputException $e) {
            static::assertTrue(true);
        }
    }

    public function testInteraction()
    {
        $input = new Input();
        static::assertSame($input, $input->setInteraction(true));
        static::assertTrue($input->isInteraction());
        static::assertSame($input, $input->setInteraction(false));
        static::assertFalse($input->isInteraction());
    }

    public function testAllowArgument()
    {
        $input = new Input();

        // set
        try {
            $input->allowArgument('');
            static::fail('It should throw exception when argument name empty');
        } catch (InputException $e) {
            static::assertTrue(true);
        }
        static::assertSame($input, $input->allowArgument('foo'));
        try {
            $input->allowArgument('foo');
            static::fail('It should throw exception when argument already exists');
        } catch (InputException $e) {
            static::assertTrue(true);
        }
        static::assertSame($input, $input->allowArgument('bar', 'bar_des'));
        try {
            $input->allowArgument('que', 'que_des', true, 'yes');
            static::fail('It should throw exception when set a default value for a required argument');
        } catch (InputException $e) {
            static::assertTrue(true);
        }
        static::assertSame($input, $input->allowArgument('biz', 'biz_des', false, 'biz_def'));
        try {
            $input->allowArgument('que', 'que_des', true);
            static::fail('It should throw exception when add a required argument after an optional one');
        } catch (InputException $e) {
            static::assertTrue(true);
        }
        static::assertSame($input, $input->allowArgument('que', 'que_des', false, 'que_def', true));
        try {
            $input->allowArgument('lst');
            static::fail('It should throw exception when add an argument after an array argument');
        } catch (InputException $e) {
            static::assertTrue(true);
        }

        // get
        static::assertEquals(2, $input->requiredArgumentCount());
        $arguments = $input->argumentDefined();
        static::assertEquals([
            'foo' => [
                'name' => 'foo',
                'description' => '',
                'required' => true,
                'default' => null,
                'array' => false
            ],
            'bar' => [
                'name' => 'bar',
                'description' => 'bar_des',
                'required' => true,
                'default' => null,
                'array' => false
            ],
            'biz' => [
                'name' => 'biz',
                'description' => 'biz_des',
                'required' => false,
                'default' => 'biz_def',
                'array' => false
            ],
            'que' => [
                'name' => 'que',
                'description' => 'que_des',
                'required' => false,
                'default' => ['que_def'],
                'array' => true
            ]
        ], $arguments);

        static::assertSame($input, $input->clearArgumentDefined());
        static::assertEquals([], $input->argumentDefined());
    }

    public function testAllowOption()
    {
        $input = new Input();

        // set
        try {
            $input->allowOption('');
            static::fail('It should throw exception when option name empty');
        } catch (InputException $e) {
            static::assertTrue(true);
        }

        static::assertSame($input, $input->allowOption('foo'));
        try {
            $input->allowOption('foo');
            static::fail('It should throw exception when argument already exists');
        } catch (InputException $e) {
            static::assertTrue(true);
        }
        static::assertSame($input, $input->allowOption('bar', 'bar_des'));

        static::assertSame($input, $input->allowOption('biz', 'biz_des', true, 'biz_def'));
        static::assertSame($input, $input->allowOption('baz', 'baz_des', null, 'baz_def'));
        static::assertSame($input, $input->allowOption('que', 'que_des', false, 'que_def'));
        static::assertSame($input, $input->allowOption('nic', 'nic_des', false, null, true));
        static::assertSame($input, $input->allowOption('qux', 'qux_des', false, 'qux_def', true));

        // get
        $options = $input->optionDefined();
        static::assertEquals([
            'foo' => [
                'name' => 'foo',
                'description' => '',
                'requireValue' => null,
                'default' => null,
                'array' => false
            ],
            'bar' => [
                'name' => 'bar',
                'description' => 'bar_des',
                'requireValue' => null,
                'default' => null,
                'array' => false
            ],
            'biz' => [
                'name' => 'biz',
                'description' => 'biz_des',
                'requireValue' => true,
                'default' => 'biz_def',
                'array' => false
            ],
            'baz' => [
                'name' => 'baz',
                'description' => 'baz_des',
                'requireValue' => null,
                'default' => 'baz_def',
                'array' => false
            ],
            // 布尔选项，default 重置为 null
            'que' => [
                'name' => 'que',
                'description' => 'que_des',
                'requireValue' => false,
                'default' => null,
                'array' => false
            ],
            // 数组选项
            'nic' => [
                'name' => 'nic',
                'description' => 'nic_des',
                'requireValue' => null,
                'default' => null,
                'array' => true
            ],
            // 数组选项，必须可接受设置值
            'qux' => [
                'name' => 'qux',
                'description' => 'qux_des',
                'requireValue' => null,
                'default' => ['qux_def'],
                'array' => true
            ]
        ], $options);

        static::assertSame($input, $input->clearOptionDefined());
        static::assertEquals([], $input->optionDefined());
    }

    public function testAllowUndefined()
    {
        $input = new Input();
        static::assertSame($input, $input->allowUndefined());
        static::assertTrue($input->isAllowUndefined());

        static::assertSame($input, $input->allowUndefined(false));
        static::assertFalse($input->isAllowUndefined());
    }

    /**
     * @dataProvider getTokenData
     * @param $token
     */
    public function testSetTokens($token)
    {
        $input = new Input();
        static::assertSame($input, $input->setTokens($token));
        static::checkBasicToken($input);
    }

    /**
     * @dataProvider getTokenData
     * @param $token
     */
    public function testInitialize($token)
    {
        $input = new Input();
        $input->allowArgument('foo')->allowOption('bar')->initialize($token);

        static::assertEquals([], $input->argumentDefined());
        static::assertEquals([], $input->optionDefined());
        static::checkBasicToken($input);
    }

    public function getTokenData()
    {
        return [
            'strToken' => [static::getBasicTokens(true)],
            'arrToken' => [static::getBasicTokens()],
        ];
    }

    protected static function getBasicTokens($str = false)
    {
        if ($str) {
            return 'cmd foo  --biz  " "  bar  --baz="b az" '
                .'"a\tb\nc\"d\'e"'
                .' --que=que --lst=\'lst\''
                .' --que=' . '\'f\tg\nh"j\'';
        }
        return [
            'cmd', 'foo', '--biz', ' ', 'bar', '--baz=b az', 'a\tb\nc"d\'e',
            '--que=que', '--lst=lst', '--que=f\tg\nh"j'
        ];
    }

    protected static function checkBasicToken(Input $input)
    {
        $basicTokens = static::getBasicTokens();
        static::assertEquals($basicTokens, $input->getTokens());
        static::assertEquals([
            'cmd',
            'foo',
            ['biz', 1],
            ' ',
            'bar',
            ['baz', 'b az'],
            'a\tb\nc"d\'e',
            ['que', 'que'],
            ['lst', 'lst'],
            ['que', 'f\tg\nh"j']
        ], $input->getPurifiedTokens());

        static::assertTrue($input->hasTokenOption('baz'));
        static::assertTrue($input->hasTokenOption('que'));
        static::assertFalse($input->hasTokenOption('cmd'));
        static::assertFalse($input->hasTokenOption('foo'));
        static::assertFalse($input->hasTokenOption('none'));

        static::assertEquals('b az', $input->getTokenOption('baz'));
        static::assertEquals(['b az'], $input->getTokenOption('baz', true));
        static::assertEquals('f\tg\nh"j', $input->getTokenOption('que'));
        static::assertEquals(['que', 'f\tg\nh"j'], $input->getTokenOption('que', true));

        static::assertEquals(['que' => 'f\tg\nh"j'], $input->lastTokenOption());
        static::assertEquals(['que' => 'f\tg\nh"j'], $input->lastTokenOption('que', 'lst', 'none'));
        static::assertEquals(['lst' => 'lst'], $input->lastTokenOption('baz', 'lst', 'none'));
    }

    public function testParseTokenWithAllowUndef()
    {
        // 1. argument
        $input = new Input();
        $input->allowUndefined(false)->setTokens(static::getBasicTokens());
        $input->allowArgument('foo')
            ->allowOption('biz')->allowOption('baz')->allowOption('que')->allowOption('lst');

        // 参数过多
        try {
            $input->parseToken();
            static::fail('It should throw exception when too many arguments');
        } catch (InputException $e) {
            static::assertTrue(true);
        }
        // 允许 未定义参数
        $input->allowUndefined(true);
        static::assertSame($input, $input->parseToken());

        // 刚好
        $input->allowUndefined(false)->allowArgument('bar')->allowArgument('biz')->allowArgument('que');
        static::assertSame($input, $input->parseToken());

        // 参数不够，但最后一个为非必需
        $input->allowArgument('baz', '', false);
        static::assertSame($input, $input->parseToken());

        // 参数不够
        $input->clearArgumentDefined()
            ->allowArgument('foo')->allowArgument('bar')->allowArgument('biz');
        try {
            $input->parseToken();
            static::fail('It should throw exception when not enough arguments');
        } catch (InputException $e) {
            static::assertTrue(true);
        }

        // 2. option
        $input = new Input();
        $input->allowUndefined(false)->setTokens(static::getBasicTokens());
        $input->allowArgument('foo')->allowArgument('bar')->allowArgument('biz')->allowArgument('que')
            ->allowOption('biz')->allowOption('baz')->allowOption('que');

        // 参数过多
        try {
            $input->parseToken();
            static::fail('It should throw exception when option undefined');
        } catch (InputException $e) {
            static::assertTrue(true);
        }

        // 允许 未定义参数
        $input->allowUndefined(true);
        static::assertSame($input, $input->parseToken());

        // 刚好
        $input->allowUndefined(false)->allowOption('lst');
        static::assertSame($input, $input->parseToken());

        // 参数必须指定值
        $input->allowUndefined()->clearOptionDefined()->allowOption('biz', '', true);
        try {
            $input->parseToken();
            static::fail('It should throw exception when option requires a value');
        } catch (InputException $e) {
            static::assertTrue(true);
        }

        // 参数必须为布尔值
        $input->allowUndefined()->clearOptionDefined()->allowOption('baz', '', false);
        try {
            $input->parseToken();
            static::fail('It should throw exception when option does not accept a value');
        } catch (InputException $e) {
            static::assertTrue(true);
        }
    }

    public function testArgumentDef()
    {
        $input = new Input();
        $input->allowUndefined()->setTokens('cmd foo bar');
        $input->allowArgument('a')->allowArgument('b')->allowArgument('c', '', false, 'c')->parseToken();

        static::assertEquals('cmd', $input->getCommand());
        static::assertTrue($input->hasArgument('a'));
        static::assertTrue($input->hasArgument('b'));
        static::assertFalse($input->hasArgument('c'));
        static::assertFalse($input->hasArgument('none'));

        static::assertEquals([
            'a' => 'foo',
            'b' => 'bar',
            'c' => 'c'
        ], $input->getArgument());
        static::assertEquals('foo', $input->getArgument('a'));
        static::assertEquals('bar', $input->getArgument('b'));
        static::assertEquals('c', $input->getArgument('c'));
        static::assertNull($input->getArgument('none'));
        static::assertEquals('none', $input->getArgument('none', 'none'));

        static::assertSame($input, $input->setArgument('a', 'a'));
        static::assertSame($input, $input->setArgument('none', 'yes'));
        static::assertEquals([
            'a' => 'a',
            'b' => 'bar',
            'c' => 'c',
            'none' => 'yes'
        ], $input->getArgument());
        static::assertEquals('a', $input->getArgument('a'));
        static::assertEquals('bar', $input->getArgument('b'));
        static::assertEquals('yes', $input->getArgument('none'));

        // reset
        $input->allowUndefined(false)->setTokens('cmd a b')->parseToken();
        static::assertEquals([
            'a' => 'a',
            'b' => 'b',
            'c' => 'c',
        ], $input->getArgument());
    }

    public function testOptionDef()
    {
        $input = new Input();
        $input->allowUndefined()->setTokens('cmd --foo --bar=bar --bar=bar2 --baz=baz');
        $input->allowOption('foo')->allowOption('bar', '', null, null, true)->allowOption('biz')->parseToken();

        static::assertEquals('cmd', $input->getCommand());
        static::assertTrue($input->hasOption('foo'));
        static::assertTrue($input->hasOption('bar'));
        static::assertTrue($input->hasOption('baz'));
        static::assertFalse($input->hasOption('biz'));
        static::assertFalse($input->hasOption('none'));

        static::assertEquals([
            'foo' => true,
            'bar' => ['bar', 'bar2'],
            'biz' => null,
            'baz' => 'baz'
        ], $input->getOption());
        static::assertTrue($input->getOption('foo'));
        static::assertEquals(['bar', 'bar2'], $input->getOption('bar'));
        static::assertEquals('baz', $input->getOption('baz'));
        static::assertNull($input->getOption('biz'));
        static::assertNull($input->getOption('none'));
        static::assertEquals('none', $input->getOption('none', 'none'));

        static::assertSame($input, $input->setOption('foo', false));
        static::assertSame($input, $input->setOption('none', 'yes'));
        static::assertEquals([
            'foo' => false,
            'bar' => ['bar', 'bar2'],
            'biz' => null,
            'baz' => 'baz',
            'none' => 'yes'
        ], $input->getOption());
        static::assertFalse($input->getOption('foo'));
        static::assertEquals('yes', $input->getOption('none'));

        // reset
        $input->allowUndefined(false)->setTokens('cmd --foo --bar=bar --bar=bar2')->parseToken();
        static::assertEquals([
            'foo' => true,
            'bar' => ['bar', 'bar2'],
            'biz' => null
        ], $input->getOption());
    }
}

