<?php
namespace Tanbolt\Console;

use Tanbolt\Console\Exception\OutputException;

/**
 * Class Output
 * @package Tanbolt\Console
 */
class Output implements OutputInterface
{
    const NULL_OUTPUT = 'nul';

    /** @var resource|string */
    private $stdoutStream;

    /** @var bool */
    private $stdoutEmpty;

    /** @var bool */
    private $stdoutTty;

    /** @var bool */
    private $stdoutSupportColor;

    /** @var resource|string */
    private $stderrStream;

    /** @var bool */
    private $stderrEmpty;

    /** @var bool */
    private $stderrTty;

    /** @var bool */
    private $stderrSupportColor;

    /** @var null */
    private $colorful = null;

    /** @var bool */
    private $quiet = false;

    /**
     * 创建 Output 对象
     * @param null $stdoutStream 正常内容的输出 stream 资源
     * @param null $stderrStream 异常内容的输出 stream 资源
     */
    public function __construct($stdoutStream = null, $stderrStream = null)
    {
        $this->setStdoutStream($stdoutStream)->setStderrStream($stderrStream);
    }

    /**
     * 校验是否为合法的 stream 对象
     * @param $stream
     * @return resource
     */
    protected function getStreamResource($stream)
    {
        if (self::NULL_OUTPUT !== $stream && (!is_resource($stream) || 'stream' !== get_resource_type($stream))) {
            throw new OutputException('The first argument needs a stream resource.');
        }
        return $stream;
    }

    /**
     * 设置正常内容的输出 stream 资源，支持以下值
     * - resource 对象：将写入到对象中
     * - self::NULL_OUTPUT: 无输出
     * - null: 自动设置为默认的 STDOUT
     * @param resource|string $stream
     * @return $this
     */
    public function setStdoutStream($stream = null)
    {
        if (null === $stream) {
            $stream = Helper::getStdoutStream();
            if (!$stream) {
                throw new OutputException('Unable to create message stream.');
            }
        }
        $this->stdoutStream = $this->getStreamResource($stream);
        $this->stdoutEmpty = true;

        $isResource = self::NULL_OUTPUT !== $stream;
        $this->stdoutTty = $isResource && Helper::isTtyStream($stream);
        $this->stdoutSupportColor = $isResource && Helper::supportColor($stream);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function stdoutStream()
    {
        return $this->stdoutStream;
    }

    /**
     * @inheritDoc
     */
    public function isStdoutEmpty()
    {
        return $this->stdoutEmpty;
    }

    /**
     * @inheritDoc
     */
    public function isStdoutTty()
    {
        return $this->stdoutTty;
    }

    /**
     * @inheritDoc
     */
    public function isStdoutSupportColor()
    {
        return $this->stdoutSupportColor;
    }

    /**
     * 设置异常内容的输出 stream 资源，支持以下值
     * - resource 对象：将写入到对象中
     * - self::NULL_OUTPUT: 无输出
     * - null: 自动设置为默认的 STDERR
     * @param resource|string $stream
     * @return $this
     */
    public function setStderrStream($stream)
    {
        if (null === $stream) {
            $stream = Helper::getStderrStream();
            if (!$stream) {
                throw new OutputException('Unable to create error stream.');
            }
        }
        $this->stderrStream = $this->getStreamResource($stream);
        $this->stderrEmpty = true;

        $isResource = self::NULL_OUTPUT !== $stream;
        $this->stderrTty = $isResource && Helper::isTtyStream($stream);
        $this->stderrSupportColor = $isResource && Helper::supportColor($stream);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function stderrStream()
    {
        return $this->stderrStream;
    }

    /**
     * @inheritDoc
     */
    public function isStderrEmpty()
    {
        return $this->stderrEmpty;
    }

    /**
     * @inheritDoc
     */
    public function isStderrTty()
    {
        return $this->stderrTty;
    }

    /**
     * @inheritDoc
     */
    public function isStderrSupportColor()
    {
        return $this->stderrSupportColor;
    }

    /**
     * @inheritDoc
     */
    public function setColorful(bool $colorful = true)
    {
        $this->colorful = $colorful;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isColorful()
    {
        return $this->colorful;
    }

    /**
     * @inheritDoc
     */
    public function setQuiet(bool $quite = true)
    {
        $this->quiet = $quite;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isQuiet()
    {
        return $this->quiet;
    }

    /**
     * 判断最终输出的正常内容是否可以使用 ANSI 字符
     * > 手动设置了 setColorful 则以此为准，否则根据 isOutputSupportColor 自动判断
     * @return bool
     */
    public function isStdoutDecorated()
    {
        $colorful = $this->isColorful();
        return null === $colorful ? $this->isStdoutSupportColor() : $colorful;
    }

    /**
     * 向正常内容的输出终端写入一条内容
     * @param ?string $message
     * @return $this
     */
    public function stdout(?string $message)
    {
        if (self::NULL_OUTPUT === ($stream = $this->stdoutStream()) || $this->isQuiet()) {
            return $this;
        }
        if (Helper::writeStream($stream, $message) && $this->stdoutEmpty) {
            $this->stdoutEmpty = false;
        }
        return $this;
    }

    /**
     * 判断最终输出的异常内容是否可以使用 ANSI 字符
     * > 手动设置了 setColorful 则以此为准，否则根据 isExceptionSupportColor 自动判断
     * @return bool
     */
    public function isStderrDecorated()
    {
        $colorful = $this->isColorful();
        return null === $colorful ? $this->isStdoutSupportColor() : $colorful;
    }

    /**
     * 向异常内容的输出终端写入一条内容
     * @param ?string $message
     * @return $this
     */
    public function stderr(?string $message)
    {
        if (self::NULL_OUTPUT === ($stream = $this->stderrStream()) || $this->isQuiet()) {
            return $this;
        }
        if (Helper::writeStream($stream, $message) && $this->stderrEmpty) {
            $this->stderrEmpty = false;
        }
        return $this;
    }

    /**
     * 输出一条 普通 信息到 stdout
     * @param string $message
     * @param int $wrap
     * @return $this
     */
    public function line(string $message, int $wrap = 1)
    {
        return $this->writeLine('text', $message, $wrap);
    }

    /**
     * 输出一条 信息类 信息到 stdout
     * @param string $message
     * @param int $wrap
     * @return $this
     */
    public function info(string $message, int $wrap = 0)
    {
        return $this->writeLine('info', $message, $wrap);
    }

    /**
     * 输出一条 注释类 信息到 stdout
     * @param string $message
     * @param int $wrap
     * @return $this
     */
    public function comment(string $message, int $wrap = 0)
    {
        return $this->writeLine('comment', $message, $wrap);
    }

    /**
     * 输出一条 提醒类 信息到 stdout
     * @param string $message
     * @param int $wrap
     * @return $this
     */
    public function notice(string $message, int $wrap = 0)
    {
        return $this->writeLine('notice', $message, $wrap);
    }

    /**
     * 输出一条 警告类 信息到 stdout
     * @param string $message
     * @param int $wrap
     * @return $this
     */
    public function warn(string $message, int $wrap = 0)
    {
        return $this->writeLine('warn', $message, $wrap);
    }

    /**
     * 输出一条 错误类 信息到 stdout
     * @param string $message
     * @param int $wrap
     * @return $this
     */
    public function error(string $message, int $wrap = 0)
    {
        return $this->writeLine('error', $message, $wrap);
    }

    /**
     * 输出一条指定类型的信息到 stdout
     * @param string $type
     * @param string $message
     * @param int $wrap
     * @return $this
     */
    private function writeLine(string $type, string $message, int $wrap = 0)
    {
        $this->ansi($message)->reset(Ansi::getTheme($type))->wrap($wrap)->stdout();
        return $this;
    }

    /**
     * 写入换行符到 stdout
     * @param int $wrap 换行符个数
     * @return $this
     */
    public function wrap(int $wrap = 1)
    {
        if ($wrap) {
            $this->stdout(str_repeat(Ansi::EOL, $wrap));
        }
        return $this;
    }

    /**
     * 创建 Ansi 消息，可进而输出到 stdout 或 stderr
     * @param ?string $message
     * @return Ansi
     */
    public function ansi(?string $message)
    {
        return Ansi::instance($this)->message($message);
    }

    /**
     * close stream
     */
    public function __destruct()
    {
        if ($this->stdoutStream) {
            fclose($this->stdoutStream);
        }
        if ($this->stderrStream) {
            fclose($this->stderrStream);
        }
    }
}
