<?php
namespace Tanbolt\Console\Component;

use Tanbolt\Console\Ansi;
use Tanbolt\Console\Helper;
use Tanbolt\Console\Exception\RuntimeException;

/**
 * Class Overwrite: 有状态组件
 * @package Tanbolt\Console\Component
 */
class Overwrite extends AbstractStyle
{
    /** @var bool */
    private $adaptive = false;

    /** @var string */
    private $format = null;

    /** @var array */
    private $formatContainer = null;

    /** @var string */
    private $lastMessage = null;

    /** @var array */
    private $data = [];

    /**
     * 是否自适应窗口尺寸，默认以启动命令时的窗口尺寸为准。
     * @param bool $adaptive
     * @return $this
     */
    public function setAdaptive(bool $adaptive = true)
    {
        $this->adaptive = $adaptive;
        return $this;
    }

    /**
     * 设置初始化富文本格式、变量数据
     * @param string $format 支持富文本，如 `<info>%foo%</info>`
     * @param ?array $data 如 [foo => foo], 也可设置为 null, 待 update() 时才会输出
     * @return $this
     * @see update
     */
    public function start(string $format, array $data = null)
    {
        if ($format !== $this->format) {
            $this->format = $format;
            // 保证最后一个字符为换行符，万一输出不支持 ascii 字符，起码具有可读性
            $container = Helper::getMessageContainer($format);
            if ($len = count($container)) {
                $lastKey = $len - 1;
                if ("\n" !== substr($container[$lastKey], -1)) {
                    $container[$lastKey] .= Ansi::EOL;
                }
            }
            $this->formatContainer = $container;
        }
        $this->data = [];
        $this->lastMessage = null;
        if ($data) {
            $this->update($data);
        }
        return $this;
    }

    /**
     * 更新复写富文本中的变量值
     * - 可以批量化设置, update(['foo' => foo, 'bar' => bar])
     * - 也可以单独设置一个 update('foo', foo)
     * - 还可以使用回调函数 update(callable)，callable 返回数组即可
     * - 未发生变化的 key 值可以不更新，会自动使用上次的值
     * @param string|array|callable $data
     * @param null $value
     * @return string
     * @see finish
     */
    public function update($data, $value = null)
    {
        if (!$this->formatContainer) {
            throw new RuntimeException('Overwrite message is empty');
        }
        $callback = null;
        if (is_array($data)) {
            $this->data = array_merge($this->data, $data);
        } elseif (is_string($data)) {
            $this->data[$data] = $value;
        } elseif (is_callable($data)) {
            $callback = $data;
        } else {
            throw new RuntimeException(
                'Argument 1 must be of the type array or string or callable, '.gettype($data).' given'
            );
        }
        $message = Helper::sprintfMessageContainer($this->formatContainer, function($key) use ($callback) {
            return $this->getDataValue($key, $callback);
        });
        $this->clear()->lastMessage = $message;
        $this->richText->write($message);
        return $message;
    }

    /**
     * @param string $key
     * @param ?callable $callback
     * @return bool
     */
    private function getDataValue(string $key, callable $callback = null)
    {
        if ($callback) {
            $value = $callback($key);
            if (null === $value || false === $value) {
                return false;
            }
            return $this->data[$key] = $value;
        }
        return array_key_exists($key, $this->data) ? $this->data[$key] : false;
    }

    /**
     * 清除最后一次输出的内容
     * @return $this
     */
    public function clear()
    {
        if ($this->lastMessage) {
            $this->terminal->revert(Helper::sectionLines(
                Helper::getPureText($this->lastMessage), $this->adaptive
            ));
            $this->lastMessage = null;
        }
        return $this;
    }
}
