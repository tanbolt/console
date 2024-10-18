<?php
namespace Tanbolt\Console\Component;

use Tanbolt\Console\Ansi;
use Tanbolt\Console\Helper;
use Tanbolt\Console\Keyboard;
use Tanbolt\Console\Exception\InvalidArgumentException;

/**
 * Class Menu: 有状态组件
 * @package Tanbolt\Console\Component
 */
class Menu extends AbstractStyle
{
    private $radioMarker = ' ○ ';
    private $radioCheckedMarker = ' ● ';
    private $checkboxMarker = '[ ] ';
    private $checkboxSelectedMarker = '[✔] ';
    private $menuStyle = [];
    private $menuCheckedStyle = [
        'background' => Ansi::COLOR_BLACK,
        'color' => Ansi::COLOR_WHITE
    ];
    private $disableHelp;
    private $lastTitleMessage;
    private $menuFullwidth;
    private $fullwidthCache;
    private $lastMenuLines;
    private $lastMenuMessage;

    /**
     * 设置 radio 单选标记
     * @param string $marker
     * @param string $checkedMaker
     * @return $this
     */
    public function setRadioMarker(string $marker, string $checkedMaker)
    {
        $this->radioMarker = $marker;
        $this->radioCheckedMarker = $checkedMaker;
        return $this;
    }

    /**
     * 设置 checkbox 多选标记
     * @param string $marker
     * @param string $checkedMaker
     * @return $this
     */
    public function setCheckboxMarker(string $marker, string $checkedMaker)
    {
        $this->checkboxMarker = $marker;
        $this->checkboxSelectedMarker = $checkedMaker;
        return $this;
    }

    /**
     * 设置单（多）选菜单样式
     * @param array $style
     * @param array $checkedStyle
     * @return $this
     */
    public function setMenuStyle(array $style, array $checkedStyle)
    {
        $this->menuStyle = $style;
        $this->menuCheckedStyle = $checkedStyle;
        return $this;
    }

    /**
     * 设置选项是否为全宽显示模式
     * > 该模式仅能适应当前窗口宽度，若在执行过程中窗口尺寸发生变化，无法自适应调整
     * @param bool $fullwidth
     * @return $this
     */
    public function setMenuFullwidth(bool $fullwidth = true)
    {
        $this->menuFullwidth = $fullwidth;
        return $this;
    }

    /**
     * 禁用/启用 帮助信息的输出
     * @param bool $disable
     * @return $this
     */
    public function disableHelpInfo(bool $disable = true)
    {
        $this->disableHelp = $disable;
        return $this;
    }

    /**
     * 发送一个单选问题, 用户仅需从备选答案中选择，返回用户所选 `[key => val]`
     * > $question 为 null，则不输出问题（若不满意默认的问题样式，可利用该特性自行输出）
     * @param ?string $question 问题
     * @param string[] $options 待选答案数组
     * @param ?string $default 缺省答案键值，如 `0`
     * @return ?array
     */
    public function radio(?string $question, array $options = [], string $default = null)
    {
        if (null === $default || !key_exists($default, $options)) {
            $default = key($options);
        }
        return $this->writeMenu($question, $options, [$default], true);
    }

    /**
     * 发送一个多选问题，用户仅需从备选答案中选择，返回用户所选 `[key => val, ...]`
     * > $question 为 null，则不输出问题（若不满意默认的问题样式，可利用该特性自行输出）
     * @param ?string $question 问题
     * @param string[] $options 待选答案数组
     * @param array|null $default 缺省答案键值，如 `[0,2]`
     * @return ?array
     */
    public function choice(?string $question, array $options = [], array $default = null)
    {
        return $this->writeMenu($question, $options, null === $default ? [] : $default, false);
    }

    /**
     * 清除最后一次输出
     * @param bool $includeTitle 是否清除标题
     * @param bool $adaptive 是否自适应窗口尺寸变化（默认使用上次获取到的窗口尺寸缓存）
     * @return $this
     */
    public function clear(bool $includeTitle = true, bool $adaptive = false)
    {
        $message = '';
        if ($includeTitle && $this->lastTitleMessage) {
            $message .= $this->lastTitleMessage;
            $this->lastTitleMessage = null;
        }
        if ($this->lastMenuMessage) {
            $message .= $this->lastMenuMessage;
            $this->lastMenuMessage = null;
        }
        $this->terminal->revert(Helper::sectionLines(Helper::getPureText($message), $adaptive));
        return $this;
    }

    /**
     * 输出问题/帮助信息/选项列表
     * @param string|null $question
     * @param array $options
     * @param array $selected
     * @param bool $radio
     * @return array
     */
    protected function writeMenu(?string $question, array $options, array $selected, bool $radio)
    {
        if (!count($options)) {
            throw new InvalidArgumentException('Options is empty');
        }
        // 输出问题
        $message = '';
        if (null !== $question) {
            if ("\n" !== substr($question, -1)) {
                $question .= Ansi::EOL;
            }
            $this->richText->write($question);
            $message .= Helper::getPureText($question);
        }
        $ansi = Ansi::instance($this->output);
        if (!$this->disableHelp) {
            $help = [
                'switch' => '↑ ↓'
            ];
            if (!$radio) {
                $help['  check/uncheck'] = 'Space';
            }
            $help['  submit'] = 'Enter';
            foreach ($help as $info => $key) {
                $ansi->reset()->black()->bright()->stdout($info.':')
                    ->reset()->green()->stdout(' '.$key.' ');
                $message .= $info.': '.$key.' ';
            }
            $ansi->reset()->black()->bright()->stdout(
                $split = Ansi::EOL.str_repeat('-', Helper::terminalSize(1)).Ansi::EOL
            );
            $message .= $split;
        }
        $this->lastTitleMessage = $message;
        return $this->writeMenuSelect($ansi, $options, $selected, $radio);
    }

    /**
     * 监听键盘，输出列表
     * @param Ansi $ansi
     * @param array $options
     * @param array $selected
     * @param bool $radio
     * @return array
     */
    protected function writeMenuSelect(Ansi $ansi, array $options, array $selected, bool $radio)
    {
        // 全宽显示模式: 缓存标记字符宽度、屏幕宽度
        $this->lastMenuLines = null;
        if ($this->menuFullwidth) {
            if ($radio) {
                $marker = Helper::strWidth($this->radioMarker);
                $checked = Helper::strWidth($this->radioCheckedMarker);
            } else {
                $marker = Helper::strWidth($this->checkboxMarker);
                $checked = Helper::strWidth($this->checkboxSelectedMarker);
            }
            $max = Helper::terminalSize(1) - 1;
            $this->fullwidthCache = compact('marker', 'checked', 'max');
        }
        // 选项的 键数组 键序号
        $optIndex = array_keys($options);
        $optKeys = array_flip($optIndex);

        // 校验所设选中值，将值转为键值，并设置 $index 为选中选项的序号
        $index = null;
        $selKeys = [];
        foreach ($selected as $v) {
            $key = $optKeys[$v] ?? null;
            if (null === $key) {
                continue;
            }
            if (null === $index) {
                $index = $key;
            }
            $selKeys[$v] = true;
        }
        if (null === $index) {
            $index = 0;
        }
        if ($this->isTty()) {
            // 监听键盘，输出选项
            $keyboard = new Keyboard();
            $keyboard->onStart(function () use ($ansi, $options, $radio, $selKeys, $index) {
                $this->writeMenuList($ansi, $options, $selKeys, $index, $radio);
            })->onHotkey([Keyboard::UP, '8'], function () use($ansi, $options, $radio, $optIndex, &$selKeys, &$index) {
                // 向上 ↑ 切换选项
                if (--$index < 0) {
                    $index = count($optIndex) - 1;
                }
                if ($radio) {
                    $v = $optIndex[$index];
                    $selKeys = [$v => true];
                }
                $this->writeMenuList($ansi, $options, $selKeys, $index, $radio);
            })->onHotkey([Keyboard::DOWN, '2'], function () use ($ansi, $options, $radio, $optIndex, &$selKeys, &$index) {
                // 向下 ↓ 切换选项
                if (++$index >= count($optIndex)) {
                    $index = 0;
                }
                if ($radio) {
                    $v = $optIndex[$index];
                    $selKeys = [$v => true];
                }
                $this->writeMenuList($ansi, $options, $selKeys, $index, $radio);
            })->onHotkey(Keyboard::SPACE, function () use ($ansi, $options, $radio, $optIndex, &$selKeys, &$index) {
                if ($radio) {
                    return;
                }
                // 空格 选中/取消 多选选项
                $key = $optIndex[$index];
                if (isset($selKeys[$key])) {
                    unset($selKeys[$key]);
                } else {
                    $selKeys[$key] = true;
                }
                $this->writeMenuList($ansi, $options, $selKeys, $index, $radio);
            })->onHotkey(Keyboard::ENTER, function () use ($keyboard) {
                // 回车提交
                $keyboard->stop();
            })->listen($this->output);
        } else {
            $this->writeMenuList($ansi, $options, $selKeys, $index, $radio);
        }
        // 返回结果
        return array_intersect_key($options, $selKeys);
    }

    /**
     * 输出选项列表
     * @param Ansi $ansi
     * @param array $options
     * @param array $selectedKeys
     * @param int $checkIndex
     * @param bool $radio
     */
    protected function writeMenuList(Ansi $ansi, array $options, array $selectedKeys, int $checkIndex, bool $radio)
    {
        // 清除上次输出的选项列表
        if (null !== $this->lastMenuLines) {
            $this->terminal->revert($this->lastMenuLines);
        }
        // 输出本次选项列表
        $lines = 0;
        $index = 0;
        $message = '';
        foreach ($options as $key => $value) {
            $checked = isset($selectedKeys[$key]);
            $marker = ($radio
                ? ($checked ? $this->radioCheckedMarker : $this->radioMarker)
                : ($checked ? $this->checkboxSelectedMarker : $this->checkboxMarker)
            );
            if ($this->menuFullwidth) {
                //全宽模式
                $firstLine = true;
                $markerWidth = $this->fullwidthCache[$checked ? 'checked' : 'marker'];
                $chapters = array_map(function ($line) use ($marker, $markerWidth, &$firstLine){
                    if ($firstLine) {
                        $prefix = $marker;
                        $firstLine = false;
                    } else {
                        $prefix = str_repeat(' ', $markerWidth);
                    }
                    return $prefix.$line.' ';
                }, Helper::getChapterArr(
                    $value,
                    $this->fullwidthCache['max'] - $markerWidth,
                    ' '
                ));
                $lines += count($chapters);
                $item = join(Ansi::EOL, $chapters);
            } else {
                // 普通模式，在选项最后加一个空格
                $item = $marker.$value.' ';
            }
            $message .= $item.Ansi::EOL;
            $ansi->reset($index === $checkIndex ? $this->menuCheckedStyle : $this->menuStyle)->wrap()->stdout($item);
            $index++;
        }
        // 缓存输出文字/行数
        $this->lastMenuMessage = $message;
        $this->lastMenuLines = $this->menuFullwidth ? $lines + 1 : Helper::sectionLines($message);
    }
}
