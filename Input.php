<?php
namespace Tanbolt\Console;

use Exception;
use Tanbolt\Console\Exception\InputException;

/**
 * Class Input
 * @package Tanbolt\Console
 */
class Input implements InputInterface
{
    /** @var resource */
    private $stream;

    /** @var bool */
    private $interaction = null;

    /** @var array */
    private $argumentDef = [];

    /** @var bool */
    private $hasArrayArgument = false;

    /** @var bool */
    private $hasOptionalArgument = false;

    /** @var int */
    private $requiredArgumentCount = 0;

    /** @var array */
    private $optionDef = [];

    /** @var bool */
    private $allowUndefined = false;

    /** @var ?string */
    private $inputArgv = null;

    /** @var array */
    private $tokens = [];

    /** @var bool */
    private $parsed = false;

    /** @var array */
    private $purifiedTokens = [];

    /** @var null */
    private $command = null;

    /** @var array */
    private $arguments = [];

    /** @var array */
    private $combinedArguments = [];

    /** @var array */
    private $options = [];

    /** @var array */
    private $combinedOptions = [];

    /**
     * Input constructor.
     * @param null $argv
     */
    public function __construct($argv = null)
    {
        $this->initialize($argv);
    }

    /**
     * @inheritDoc
     */
    public function setStream($stream)
    {
        if (!is_resource($stream)) {
            throw new InputException('The stream must be a valid resource.');
        }
        $this->stream = $stream;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getStream(bool $strict = false)
    {
        if ($this->stream || $strict) {
            return $this->stream;
        }
        return defined('STDIN') ? STDIN : fopen('php://stdin', 'rb+');
    }

    /**
     * @inheritDoc
     */
    public function setInteraction(bool $interaction = true)
    {
        $this->interaction = $interaction;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isInteraction()
    {
        if (null === $this->interaction) {
            return stream_isatty($this->getStream());
        }
        return $this->interaction;
    }

    /**
     * 设置可接受的 参数变量(argument)，argument 值为字符，但最后一个(且只能是最后一个) argument 可以为数组，
     * 当所设置的参数变量(argument) 全部都匹配到了值之后，剩下的所有 argument 值将组合为一个数组作为最后一个 argument 的值
     * @param string $name 名称
     * @param string $description 用途说明
     * @param bool $required 是否必须项
     * @param array|string|null $default 缺省值
     * @param bool $array 是否接受数组
     * @return $this
     */
    public function allowArgument(
        string $name,
        string $description = '',
        bool $required = true,
        $default = null,
        bool $array = false
    ) {
        if (empty($name)) {
            throw new InputException('Argument name cloud not empty.');
        }
        if (isset($this->argumentDef[$name])) {
            throw new InputException('An argument named "'.$name.'" already exists.');
        }
        if ($this->hasArrayArgument) {
            throw new InputException('Cannot add an argument "'.$name.'" after an array argument.');
        }
        if ($required && $this->hasOptionalArgument) {
            throw new InputException('Cannot add a required argument "'.$name.'" after an optional one.');
        }
        if ($required && null !== $default) {
            throw new InputException('Cannot set a default value for a required argument "'.$name.'".');
        }
        if ($array && !$this->hasArrayArgument) {
            $this->hasArrayArgument = true;
        }
        if ($required) {
            ++$this->requiredArgumentCount;
        } elseif (!$this->hasArrayArgument) {
            $this->hasOptionalArgument = true;
        }
        if (null !== $default && $array && !is_array($default)) {
            $default = [$default];
        }
        $this->parsed = false;
        $this->argumentDef[$name] = compact('name', 'description', 'required', 'default', 'array');
        return $this;
    }

    /**
     * 获取必须要有 参数变量(argument) 数量
     * @return int
     */
    public function requiredArgumentCount()
    {
        return $this->requiredArgumentCount;
    }

    /**
     * 获取当前已设置的所有 参数变量(argument)
     * @return array
     */
    public function argumentDefined()
    {
        return $this->argumentDef;
    }

    /**
     * 清空当前已设置的 参数变量(argument)
     * @return $this
     */
    public function clearArgumentDefined()
    {
        $this->argumentDef = [];
        $this->requiredArgumentCount = 0;
        $this->hasArrayArgument = $this->hasOptionalArgument = false;
        return $this;
    }

    /**
     * 设置可接受的 选项变量(option)
     * @param string $name 名称
     * @param string $description 用途说明
     * @param ?bool $requireValue false: 选项变量值类型必须为布尔值(ex: --foo)；
     *                            true:必须设置值(ex: --foo=bar)；
     *                            null:都可以(--foo=bar, --foo), 变量字符串未指定值, 获取到的选项变量值为 $default
     * @param array|string|bool|null $default 缺省值
     * @param bool $array 是否接受数组
     * @return $this
     */
    public function allowOption(
        string $name,
        string $description = '',
        bool $requireValue = null,
        $default = null,
        bool $array = false
    ) {
        if (empty($name)) {
            throw new InputException('Argument name cloud not empty.');
        }
        if (isset($this->optionDef[$name])) {
            throw new InputException('An option named "'.$name.'" already exists.');
        }
        // 数组选项，必须 可设置值
        if ($array) {
            $requireValue = !$requireValue ? null : $requireValue;
        }
        // 若设置了缺省值
        if (null !== $default) {
            if (false === $requireValue) {
                // 布尔值选项，不能有缺省值
                $default = null;
            } elseif ($array && !is_array($default)) {
                $default = [$default];
            }
        }
        $this->parsed = false;
        $this->options[$name] = $array ? [] : (false === $requireValue ? false : null);
        $this->optionDef[$name] = compact('name', 'description', 'requireValue', 'default', 'array');
        return $this;
    }

    /**
     * 获取当前已设置的所有可接受 选项变量(option)
     * @return array
     */
    public function optionDefined()
    {
        return $this->optionDef;
    }

    /**
     * 清空当前已设置的可接受 选项变量(option)
     * @return $this
     */
    public function clearOptionDefined()
    {
        $this->optionDef = [];
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function allowUndefined(bool $allow = true)
    {
        $this->allowUndefined = $allow;
        return $this;
    }

    /**
     * 判断当期是否接受未定义的参数
     * @return bool
     */
    public function isAllowUndefined()
    {
        return $this->allowUndefined;
    }

    /**
     * 设置 Input 原始参数，可使用 [字符串] 或 [数组]，若值为 null，则使用 $_SERVER['argv']。如：
     *
     * ```
     * 字符串形式: `cmd foo bar --biz --biz=foo --biz=bar`
     * 数组形式:
     *      [
     *          'cmd',
     *          'foo',
     *          'bar',
     *          '--biz'
     *          '--biz=foo',
     *          '--biz=bar'
     *      ]
     * ```
     *
     * 设置后会通过内部函数 purifyToken 解析为数组:
     * ```
     *      [
     *          'cmd',
     *          'foo',
     *          'bar',
     *          ['biz', 1]
     *          ['biz', 'foo'],
     *          ['biz', 'bar']
     *      ]
     * ```
     *
     * 整理后的数组包含以下几种类型
     * - 命令(command)：第一项字符串被认为是当前要执行的命令
     * - 参数变量(argument)：字符串类型的项
     * - 选项变量(option)：数组类型的项（由 `--` 开头的命令解析而来）
     * @param array|string $argv
     * @return $this
     */
    public function setTokens($argv)
    {
        if (is_string($argv)) {
            $this->inputArgv = $argv;
            $argv = Helper::strToArgv($argv);
        } else {
            $this->inputArgv = null;
        }
        if (!is_array($argv)) {
            throw new InputException('argv must be array or string.');
        }
        $this->command = null;
        $this->parsed = false;
        $this->tokens = $argv;
        return $this->purifyToken();
    }

    /**
     * 解析 Input 原始参数
     * @return $this
     */
    protected function purifyToken()
    {
        $parseOptions = true;
        $tokens = $this->tokens;
        $this->purifiedTokens = [];
        while (null !== $token = array_shift($tokens)) {
            if (!is_string($token)) {
                throw new InputException('argv must be array or string.');
            }
            if ($parseOptions && '--' == $token) {
                // 出现 `--` 字符之后的所有参数都认为是 argument 而不是 option
                // 如 cmd foo --a=a -- --b=b , --a=a 会解析为 option; --b=b 解析为 argument
                $parseOptions = false;
            } elseif ($parseOptions && 0 === strpos($token, '--')) {
                $name = substr($token, 2);
                if (false !== $pos = strpos($name, '=')) {
                    $value = substr($name, $pos + 1);
                    $value = strlen($value) ? $value : '';
                    $name = substr($name, 0, $pos);
                } else {
                    $value = true;
                }
                $this->purifiedTokens[] = [$name, $value];
            } else {
                $this->purifiedTokens[] = $token;
            }
        }
        return $this;
    }

    /**
     * 获取 Input 原始参数数组，即 setTokens 设置的数组，或由 setTokens 所设置的字符串解析得到的数组
     * @return array
     * @see setTokens
     */
    public function getTokens()
    {
        return $this->tokens;
    }

    /**
     * @inheritDoc
     * @see setTokens
     */
    public function getPurifiedTokens()
    {
        return $this->purifiedTokens;
    }

    /**
     * 判断 Input 原始参数是否包含指定的 [选项变量]
     * - ex: --$name 或 --$name=foo
     * @param string $name
     * @return bool
     */
    public function hasTokenOption(string $name)
    {
        foreach ($this->getPurifiedTokens() as $token) {
            if (is_array($token) && $token[0] === $name) {
                return true;
            }
        }
        return false;
    }

    /**
     * 从 Input 原始参数, 获取指定名称的 [选项变量] 值
     * - ex: --$name[=foo] --$name[=bar]
     * - 若 $array 为 true, 返回 (array) [foo, bar]，否则仅返回最后一个值 (string) bar
     * - 若不存在，返回 false
     * @param string $name
     * @param bool $array 是否返回数组类型
     * @return array|string|false
     */
    public function getTokenOption(string $name, bool $array = false)
    {
        $options = [];
        foreach ($this->getPurifiedTokens() as $token) {
            if (is_array($token) && $token[0] === $name) {
                $options[] = $token[1];
            }
        }
        if (count($options)) {
            return $array ? $options : end($options);
        }
        return false;
    }

    /**
     * 获取指定参数名的最后一个 [选项变量] 值，返回 [key => value]
     * - ex: --foo=bar --bar=biz --biz
     * - lastTokenOption() --> return: [biz => 1]
     * - lastTokenOption('foo', 'bar') == lastTokenOption('bar', 'foo') -> return: ['bar' => biz]
     * @param array|string $keys
     * @return array|false
     */
    public function lastTokenOption(...$keys)
    {
        $noKey = !count($keys);
        foreach (array_reverse($this->getPurifiedTokens()) as $token) {
            if (!is_array($token)) {
                continue;
            }
            if ($noKey || in_array($token[0], $keys)) {
                return [$token[0] => $token[1]];
            }
        }
        return false;
    }

    /**
     * 重置对象的 ArgumentDefined, OptionDefined, allowUndefined 参数，并重置 Input 参数
     * @param array|string|null $argv
     * @return Input
     * @see setTokens
     */
    public function initialize($argv = null)
    {
        if (null === $argv) {
            $argv = $_SERVER['argv'];
            array_shift($argv);
        }
        $this->stream = null;
        return $this->clearArgumentDefined()->clearOptionDefined()->allowUndefined(false)->setTokens($argv);
    }

    /**
     * 根据 allowArgument,allowOption,allowUndefined 设置，通过对 Input 参数的解析，
     * 设置 参数变量(argument) 和 选项变量(option) 对应的匹配值。
     * @return $this
     * @throws Exception
     */
    public function parseToken()
    {
        if ($this->parsed) {
            return $this;
        }
        $this->command = null;
        $this->arguments = $this->options = [];
        foreach ($this->getPurifiedTokens() as $token) {
            if (is_array($token)) {
                $this->initOptions($token[0], $token[1]);
            } else {
                $this->initArguments($token);
            }
        }
        // miss argument
        $missingArguments = array_filter($this->argumentDefined(), function($arr) {
            return $arr['required'] && !array_key_exists($arr['name'], $this->arguments);
        });
        if (count($missingArguments) > 0) {
            throw new InputException(sprintf(
                'Not enough arguments (missing: "%s").',
                implode(', ', array_keys($missingArguments))
            ));
        }
        $this->parsed = true;
        return $this->initTokens();
    }

    /**
     * 对 参数变量(argument) 设置对应的匹配值
     * @param string $token
     * @return $this
     */
    protected function initArguments(string $token)
    {
        if (!$this->command) {
            $this->command = $token;
            return $this;
        }
        $count = count($this->arguments);
        $argumentDef = $this->argumentDefined();
        $defined = array_slice($argumentDef, $count, 1);
        if ($name = key($defined)) {
            $defined = current($defined);
            $this->arguments[$name] = $defined['array'] ? [$token] : $token;
            return $this;
        }
        $defined = $count ? array_slice($argumentDef, $count - 1, 1) : null;
        if ($defined && ($name = key($defined)) && ($defined = current($defined)) && $defined['array']) {
            $this->arguments[$name][] = $token;
            return $this;
        }
        if ($this->isAllowUndefined()) {
            return $this;
        }
        if (count($argumentDef)) {
            throw new InputException(sprintf(
                'Too many arguments, expected arguments "%s".',
                implode('" "', array_keys($argumentDef))
            ));
        }
        throw new InputException(sprintf('No arguments expected, got "%s".', $token));
    }

    /**
     * 对 选项变量(option) 设置对应的匹配值
     * @param string $name
     * @param $value
     * @return $this
     */
    protected function initOptions(string $name, $value)
    {
        $supportArr = null;
        $optionDef = $this->optionDefined();
        // 校验
        if (array_key_exists($name, $optionDef)) {
            $option = $optionDef[$name];
            $requireValue = $option['requireValue'];
            if ($requireValue && (true === $value || !strlen($value))) {
                throw new InputException(sprintf('The "--%s" option requires a value.', $name));
            }
            if (false === $requireValue && true !== $value) {
                throw new InputException(sprintf('The "--%s" option does not accept a value.', $name));
            }
            $supportArr = $option['array'];
        } elseif (!$this->isAllowUndefined()) {
            throw new InputException(sprintf('The "--%s" option does not exist.', $name));
        }
        // 设置
        if (array_key_exists($name, $this->options)) {
            if (is_array($this->options[$name])) {
                // 已是数组, 说明已通过判断, 直接追加即可
                $this->options[$name][] = $value;
            } elseif (false === $supportArr || (true === $value && true === $this->options[$name])) {
                // 不支持数组 || 当前、上一个都为 true --> 无需保持为数组,保持为当前 $value 即可
                $this->options[$name] = $value;
            } else {
                $this->options[$name] = [$this->options[$name], $value];
            }
        } else {
            $this->options[$name] = true === $supportArr ? [$value] : $value;
        }
        return $this;
    }

    /**
     * 将定义过但没有出现在 Input 参数的 argument,option 整理到最终结果，
     * 为了 hasArgument, hasOption 可以正确判断 Input 参数是否包含，
     * 这里另外定一个变量存储包含了 未出现在 Input 参数中的 所有参数
     * @return $this
     */
    protected function initTokens()
    {
        // arguments
        $this->combinedArguments = [];
        $argumentDef = $this->argumentDefined();
        foreach ($argumentDef as $name => $setting) {
            if (array_key_exists($name, $this->arguments)) {
                $this->combinedArguments[$name] = $this->arguments[$name];
            } else {
                $this->combinedArguments[$name] = $setting['default'];
            }
        }
        // options
        $options = $this->options;
        $this->combinedOptions = [];
        $optionsDef = $this->optionDefined();
        foreach ($optionsDef as $name => $setting) {
            if (array_key_exists($name, $options)) {
                $this->combinedOptions[$name] = $options[$name];
                unset($options[$name]);
            } else {
                $this->combinedOptions[$name] = $setting['default'];
            }
        }
        if ($options && $this->isAllowUndefined()) {
            $this->combinedOptions = array_merge($this->combinedOptions, $options);
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getCommand()
    {
        if ($this->command) {
            return $this->command;
        }
        foreach ($this->tokens as $token) {
            if ($token && '-' === $token[0]) {
                continue;
            }
            return $token;
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    public function hasArgument(string $name)
    {
        return array_key_exists($name, $this->arguments);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function getArgument(string $name = null, $default = null)
    {
        $all = $this->parseToken()->combinedArguments;
        return $name ? (array_key_exists($name, $all) && ($v = $all[$name]) !== null ? $v : $default) : $all;
    }

    /**
     * @inheritDoc
     */
    public function setArgument(string $name, $value)
    {
        $this->combinedArguments[$name] = $value;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function hasOption(string $name)
    {
        return array_key_exists($name, $this->options);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function getOption(string $name = null, $default = null)
    {
        $all = $this->parseToken()->combinedOptions;
        return $name ? (array_key_exists($name, $all) && ($v = $all[$name]) !== null ? $v : $default) : $all;
    }

    /**
     * @inheritDoc
     */
    public function setOption(string $name, $value)
    {
        $this->combinedOptions[$name] = $value;
        return $this;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        if (null === $this->inputArgv) {
            return Helper::argvToStr($this->tokens);
        }
        return $this->inputArgv;
    }
}
