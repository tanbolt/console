<?php
namespace Tanbolt\Console\Component;

use Tanbolt\Console\Ansi;
use Tanbolt\Console\Helper;

/**
 * Class Item：若不使用 clear(), 为无状态组件
 * @package Tanbolt\Console\Component
 */
class Item extends AbstractStyle
{
    /**
     * @var string
     */
    private $lastMessage;

    /**
     * 通过数组输出一个列表
     * ```
     * 输出列表，如 ['a' => 'des_a', 'b' => 'des_b']
     * 输出
     *
     * a  des_a
     * b  des_b
     *
     * 键名：可通过 $keyStyle 设置样式
     * 键值：支持 richText 富文本
     * ```
     * @param array $items 数据数组
     * @param int $preWrap 前置空格
     * @param ?array $keyStyle 键名样式
     * @param bool $canClear 输出后是否可使用 clear() 方法清除
     * @return $this
     */
    public function write(array $items, int $preWrap = 0, array $keyStyle = null, bool $canClear = false)
    {
        if (!$items) {
            return $this;
        }
        // 计算 key 宽度
        $max = 0;
        $keyCells = [];
        $itemKeys = array_keys($items);
        foreach ($itemKeys as $key) {
            $cell = [];
            $keys = explode("\n", Helper::crlfToLf($key));
            foreach ($keys as $str) {
                // key 有可能 strlen=2 但 占宽为1, 若要对齐, 需计算 pad 值
                $width = Helper::strWidth($str);
                $pad = strlen($str) - $width;
                if ($width > $max) {
                    $max = $width;
                }
                $cell[] = [$str, $pad];
            }
            $keyCells[$key] = $cell;
        }
        $max += 1;
        // 输出
        $lastMessage = '';
        $richText = $this->richText;
        $pre = str_repeat(' ', $preWrap);
        $emptyKey = str_repeat(' ', $max);
        $ansi = Ansi::instance($this->output);
        $decorated = $this->output->isStdoutDecorated();
        $keyAnsi = Ansi::instance($this->output)->reset(null === $keyStyle ? ['color' => Ansi::COLOR_GREEN] : $keyStyle);
        foreach ($items as $key => $value) {
            $keys = $keyCells[$key];
            $values = static::parseItemValue($richText, $ansi, $decorated, $value);
            // key 行数较多, 先输出多出的行
            $keyMoreLen = count($keys) - ($valueLen = count($values));
            while ($keyMoreLen > 0) {
                $cell = array_shift($keys);
                $this->output->stdout($pre);
                $keyAnsi->stdout($out = $cell[0].Ansi::EOL);
                $lastMessage .= $pre.$out;
                $keyMoreLen--;
            }
            // 肯定是 value 的行数较多 或 等于 key 行数
            $index = 0;
            $keyLen = count($keys);
            while ($index < $valueLen) {
                // 输出 key
                $this->output->stdout($pre);
                if ($index < $keyLen) {
                    $cell = $keys[$index];
                    $maxLen = $max + $cell[1];
                    $keyAnsi->stdout($out = sprintf("%-{$maxLen}s", $cell[0]));
                } else {
                    $this->output->stdout($out = $emptyKey);
                }
                $lastMessage .= $pre.$out;

                // 输出 value
                $val = $values[$index];
                $this->output->stdout($val[1].Ansi::EOL);
                $lastMessage .= $val[0].Ansi::EOL;
                $index++;
            }
        }
        if ($canClear) {
            $this->lastMessage = $lastMessage;
        }
        return $this;
    }

    /**
     * 将 item 转为数组，每一行作为数组的一项
     * @param RichText $richText
     * @param Ansi $ansi
     * @param bool $decorated
     * @param string $item
     * @return array
     */
    protected static function parseItemValue(RichText $richText, Ansi $ansi, bool $decorated, string $item)
    {
        $values = [];
        $pure = $rich = '';
        $richText->getRichText($item, function ($msg, $style) use ($ansi, $decorated, &$values, &$pure, &$rich) {
            $messages = explode(Ansi::EOL, $msg);

            // 仅一项, 追加即可
            $line = array_shift($messages);
            $pure .= $line;
            $rich .= $line ? $ansi->reset($style)->getDecorated($line, $decorated) : '';
            if (!count($messages)) {
                return;
            }

            // 还有项数，添加当前累计 msg 为一行到 $values
            if ($pure) {
                $values[] = [$pure, $rich];
                $pure = $rich = '';
            }
            // 提取最后一项
            $line = array_pop($messages);

            // 还有项，那就是一项一行
            foreach ($messages as $message) {
                if ($message) {
                    $values[] = [
                        $message,
                        $ansi->reset($style)->getDecorated($message, $decorated)
                    ];
                }
            }
            // 将提取的最后一行设置为 累计 msg
            $pure = $line;
            $rich = $line ? $ansi->reset($style)->getDecorated($line, $decorated) : '';
        });
        if ($pure) {
            $values[] = [$pure, $rich];
        }
        return $values;
    }

    /**
     * 清除最后一次输出的富文本
     * @param bool $adaptive 是否自适应窗口尺寸变化（默认使用上次获取到的窗口尺寸缓存）
     * @return $this
     */
    public function clear(bool $adaptive = false)
    {
        if ($this->lastMessage) {
            $this->terminal->revert(Helper::sectionLines(Helper::getPureText($this->lastMessage), $adaptive));
            $this->lastMessage = null;
        }
        return $this;
    }
}
