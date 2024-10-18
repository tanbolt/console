<?php
namespace Tanbolt\Console;

interface InputInterface
{
    /**
     * 设置输入内容的 resource
     * @param resource $stream
     * @return $this
     */
    public function setStream($stream);

    /**
     * 获取当前设置的输入 resource
     * @param bool $strict 是否必须返回 setStream 所设值
     * @return resource
     */
    public function getStream(bool $strict = false);

    /**
     * 设置是否允许与输入端交互
     * @param bool $interaction
     * @return $this
     */
    public function setInteraction(bool $interaction = true);

    /**
     * 获取当前是否支持交互
     * @return bool
     */
    public function isInteraction();

    /**
     * 设置可接受的参数变量(argument)
     * @param string $name
     * @param string $description
     * @param bool $required
     * @param array|string|null $default
     * @param bool $array
     * @return $this
     */
    public function allowArgument(
        string $name,
        string $description = '',
        bool $required = true,
        $default = null,
        bool $array = false
    );

    /**
     * 设置可接受的选项变量(option)
     * @param string $name
     * @param string $description
     * @param ?bool $requireValue
     * @param array|string|bool $default
     * @param bool $array
     * @return $this
     */
    public function allowOption(
        string $name,
        string $description = '',
        bool $requireValue = null,
        $default = null,
        bool $array = false
    );

    /**
     * 设置是否接受未定义的参数
     * @param bool $allow
     * @return $this
     */
    public function allowUndefined(bool $allow = true);

    /**
     * 设置 Input 原始参数
     * @param array|string $argv
     * @return $this
     */
    public function setTokens($argv);

    /**
     * 获取 Input 原始参数数组
     * @return array
     */
    public function getTokens();

    /**
     * 获取解析过的 Input 原始参数
     * @return array
     */
    public function getPurifiedTokens();

    /**
     * 判断 Input 原始参数是否包含指定的选项变量
     * @param string $name
     * @return bool
     */
    public function hasTokenOption(string $name);

    /**
     * 从 Input 原始参数获取指定的选项变量值
     * @param string $name
     * @param bool $array
     * @return bool|mixed
     */
    public function getTokenOption(string $name, bool $array = false);

    /**
     * 获取指定参数名的最后一个[选项变量]值
     * @param string $keys
     * @return array|false
     */
    public function lastTokenOption(...$keys);

    /**
     * @param array|string|null $argv
     * @return $this
     */
    public function initialize($argv = null);

    /**
     * 根据 allowArgument,allowOption,allowUndefined 设置，对当前对象变量的解析
     * @return $this
     */
    public function parseToken();

    /**
     * 获取当前的命令, 即第一个 argument 变量值
     * @return string
     */
    public function getCommand();

    /**
     * 判断当前变量是否含有指定的 参数变量(argument)
     * @param string $name
     * @return bool
     */
    public function hasArgument(string $name);

    /**
     * 获取指定名称 或 全部的 参数变量(argument) 值
     * @param ?string $name
     * @param array|string|null $default
     * @return array|string|null
     */
    public function getArgument(string $name = null, $default = null);

    /**
     * 强制重置指定名称的 参数变量(argument) 值
     * @param string $name
     * @param array|string $value
     * @return $this
     */
    public function setArgument(string $name, $value);

    /**
     * 判断当前变量是否含有指定的 选项变量(option)
     * @param string $name
     * @return bool
     */
    public function hasOption(string $name);

    /**
     * 获取指定名称 或 全部的 选项变量(option) 值
     * @param ?string $name
     * @param array|bool|null $default
     * @return array|bool|null
     */
    public function getOption(string $name = null, $default = null);

    /**
     * 强制重置指定名称的 选项变量(option) 值
     * @param string $name
     * @param array|string|bool $value
     * @return $this
     */
    public function setOption(string $name, $value);
}
