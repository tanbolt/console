<?php
namespace Tanbolt\Console;

interface OutputInterface
{
    /**
     * 设置正常内容的输出 stream 资源
     * @param mixed $stream
     * @return $this
     */
    public function setStdoutStream($stream);

    /**
     * 获取正常内容输出的 stream 资源对象
     * @return resource
     */
    public function stdoutStream();

    /**
     * 判断当前正常内容输出是否为空
     * @return bool
     */
    public function isStdoutEmpty();

    /**
     * 判断正常内容的输出流是否为 tty 终端
     * @return bool
     */
    public function isStdoutTty();

    /**
     * 判断正常内容的输出终端是否支持 ANSI 字符（字体可以有格式、颜色）
     * @return bool
     */
    public function isStdoutSupportColor();

    /**
     * 设置异常内容的输出 stream 资源
     * @param mixed $stream
     * @return $this
     */
    public function setStderrStream($stream);

    /**
     * 获取异常内容输出的 stream 资源对象
     * @return resource
     */
    public function stderrStream();

    /**
     * 判断当前异常内容输出是否为空
     * @return bool
     */
    public function isStderrEmpty();

    /**
     * 判断异常内容的输出流是否为 tty 终端
     * @return bool
     */
    public function isStderrTty();

    /**
     * 判断异常内容的输出终端是否支持 ANSI 字符（字体可以有格式、颜色）
     * @return bool
     */
    public function isStderrSupportColor();

    /**
     * 强制输出是否使用 ANSI 字符（字体可以有格式、颜色）
     * > 默认情况下根据 `SupportColor` 来决定是否使用 ANSI 字符
     * @param bool $colorful
     * @return $this
     */
    public function setColorful(bool $colorful = true);

    /**
     * 判断当前输出是否使用 ANSI 字符（字体可以有格式、颜色）
     * @return ?bool
     */
    public function isColorful();

    /**
     * 设置输出为 quiet 模式, 不输出任何内容
     * @param bool $quite
     * @return $this
     */
    public function setQuiet(bool $quite = true);

    /**
     * 获取当前输出是否为 quiet 模式
     * @return bool
     */
    public function isQuiet();

    /**
     * 判断最终输出的正常内容是否可以使用 ANSI 字符
     * @return bool
     */
    public function isStdoutDecorated();

    /**
     * 向正常内容的输出终端写入一条内容
     * @param ?string $message
     * @return $this
     */
    public function stdout(?string $message);

    /**
     * 判断最终输出的异常内容是否可以使用 ANSI 字符
     * @return bool
     */
    public function isStderrDecorated();

    /**
     * 向异常内容的输出终端写入一条内容
     * @param ?string $message
     * @return $this
     */
    public function stderr(?string $message);
}
