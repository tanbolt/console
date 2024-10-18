<?php
namespace Tanbolt\Console\Component;

use Tanbolt\Console\Ansi;
use Tanbolt\Console\Helper;
use Tanbolt\Console\Exception\RuntimeException;

/**
 * Class Progress
 * ```
 * 缺省情况下，输出的进度条样式为
 * ===================>------------------
 *
 * 但可以自定义输出样式，如
 *
 * $progress = new Progress();
 * $progress->setDoneChar(Ansi::instance()->bgGreen()->getStyle(), ' ')
 *     ->setProgressChar(Ansi::instance()->bgGreen()->getStyle(), ' ')
 *     ->setEmptyChar(Ansi::instance()->bgBlack()->bgBright()->getStyle(), ' ');
 *
 * $progress->start();
 * $percent = 0;
 * while ($percent < 100) {
 *    $progress->update($percent);
 *    $percent++;
 *    usleep(10000);
 * }
 * $progress->finish();
 *
 * 最后输出样式为
 * ██████████████░░░░░░░░░░░░░░░░░
 *
 * ```
 * @package Tanbolt\Console\Component
 */
class Progress extends AbstractStyle
{
    const STYLE_MINI = 'mini';
    const STYLE_DEBUG = 'debug';
    const STYLE_NORMAL = 'normal';
    const STYLE_VERBOSE = 'verbose';

    /** @var string */
    private $doneChar = '=';

    /** @var int */
    private $doneWidth = 1;

    /** @var array */
    private $doneCharStyle;

    /** @var string  */
    private $emptyChar = '-';

    /** @var int */
    private $emptyWidth = 1;

    /** @var array */
    private $emptyCharStyle;

    /** @var string  */
    private $progressChar = '>';

    /** @var int */
    private $progressWidth = 1;

    /** @var array */
    private $progressCharStyle;

    /** @var bool */
    private $progressAdaptive = false;

    /** @var string */
    private $title;

    /** @var int */
    private $total = 100;

    /** @var int */
    private $current = 0;

    /** @var int */
    private $runTime;

    /** @var string */
    private $progressFormat;

    /** @var array */
    private $progressParsed;

    /** @var bool */
    private $progressHasBar;

    /** @var string */
    private $lastOutputMessage;

    /** @var array */
    private static $formatter = [
        self::STYLE_MINI => "%{title}\n%Progress: %current%/%total% (%percent%)\n",
        self::STYLE_NORMAL => "%{title}\n%%current%/%total% [%bar%] %percent%\n",
        self::STYLE_VERBOSE => "%{title}\n%%current%/%total% [%bar%] %percent% %estimated%\n",
        self::STYLE_DEBUG => "%{title}\n%%current%/%total% [%bar%] %percent% %estimated% %memory%\n",
    ];

    /**
     * 设置进度条中 已完成 部分的字符串和颜色 [=====>----] 中的 =
     * @param ?array $style
     * @param ?string $char
     * @return $this
     */
    public function setDoneChar(array $style = null, string $char = null)
    {
        if ($style) {
            $this->doneCharStyle = $style;
        }
        if ($char) {
            $this->doneChar = $char;
            $this->doneWidth = Helper::strWidth($char);
        }
        return $this;
    }

    /**
     * 设置进度条中 未完成 部分的字符串和颜色 [=====>----] 中的 -
     * @param ?array $style
     * @param ?string $char
     * @return $this
     */
    public function setEmptyChar(array $style = null, string $char = null)
    {
        if ($style) {
            $this->emptyCharStyle = $style;
        }
        if ($char) {
            $this->emptyChar = $char;
            $this->emptyWidth = Helper::strWidth($char);
        }
        return $this;
    }

    /**
     * 设置进度条中 当前进度 部分的字符串和颜色 [=====>----] 中的 >
     * @param ?array $style
     * @param ?string $char
     * @return $this
     */
    public function setProgressChar(array $style = null, string $char = null)
    {
        if ($style) {
            $this->progressCharStyle = $style;
        }
        if ($char) {
            $this->progressChar = $char;
            $this->progressWidth = Helper::strWidth($char);
        }
        return $this;
    }

    /**
     * 是否自适应窗口尺寸，默认以启动命令时的窗口尺寸为准
     * @param bool $adaptive
     * @return $this
     */
    public function setProgressAdaptive(bool $adaptive = true)
    {
        $this->progressAdaptive = $adaptive;
        return $this;
    }

    /**
     * 设置进度条标题，可以在 start 之后, 根据进度重置标题
     * @param ?string $title
     * @return $this
     */
    public function setTitle(?string $title)
    {
        if ($title !== $this->title) {
            $this->title = $title;
            if ($this->runTime) {
                $this->updateProgress($this->current);
            }
        }
        return $this;
    }

    /**
     * 设置任务总工作量大小，默认为 100
     * @param int $total
     * @return $this
     */
    public function setTotal(int $total)
    {
        if ($total < 1) {
            throw new RuntimeException('Total number must above zero');
        }
        $this->total = $total;
        return $this;
    }

    /**
     * 开始执行任务, 初始化进度条
     * - 支持设置的样式: mini|normal|verbose|debug
     * - 若不指定，则根据 Input debug 等级自动设置
     * @param ?string $formatType 进度条样式
     * @return string
     */
    public function start(string $formatType = null)
    {
        if (null === $formatType) {
            if ($this->input->hasOption('debug')) {
                $debug = $this->input->getOption('debug');
                if ($debug > 2) {
                    $formatType = self::STYLE_DEBUG;
                } elseif ($debug > 1) {
                    $formatType = self::STYLE_VERBOSE;
                } else {
                    $formatType = self::STYLE_NORMAL;
                }
            } else {
                $formatType = self::STYLE_NORMAL;
            }
        } elseif (!array_key_exists($formatType, self::$formatter)) {
            throw new RuntimeException('Progress format type "' . $formatType . '" not exist');
        }
        return $this->startWith(self::$formatter[$formatType]);
    }

    /**
     * 以自定义的进度条样式（支持富文本）开始执行任务, 支持富文本，显示进度条，如：
     * ```
     * `<comment>%{title}\n%</comment> %current% / %total% [%bar%] %percent%`
     *
     * 支持的变量有
     * title: 标题
     * current: 当前完成任务量
     * total: 总任务量
     * percent: 已完成任务的百分比
     * bar: 进度条
     * estimated: 已耗费时长
     * memory: 内存消耗大小
     * ```
     * @param string $formatString
     * @return string
     */
    public function startWith(string $formatString)
    {
        if ($formatString !== $this->progressFormat) {
            // 保证最后一个字符为换行符，万一输出不支持 ascii 字符，起码具有可读性
            $formatString = Helper::crlfToLf($formatString);
            if ("\n" !== substr($formatString, -1)) {
                $formatString .= "\n";
            }
            if ("\n" !== Ansi::EOL) {
                $formatString = str_replace("\n", Ansi::EOL, $formatString);
            }
            $this->progressFormat = $formatString;
            $parsed = Helper::getMessageContainer($this->progressFormat);
            // make sure only one %bar% tag
            $parsedClean = [];
            $this->progressHasBar = false;
            foreach ($parsed as $parse) {
                if (is_array($parse) && 'bar' === $parse[0]) {
                    if ($this->progressHasBar) {
                        continue;
                    }
                    $this->progressHasBar = true;
                }
                $parsedClean[] = $parse;
            }
            $this->progressParsed = $parsedClean;
        }
        $this->runTime = $this->lastOutputMessage = null;
        return $this->update();
    }

    /**
     * 更新当前工作进度（相对于 setTotal 设置的 total 总量，默认为 100）
     * @param int $current
     * @return string
     * @see setTotal
     */
    public function update(int $current = 0)
    {
        if (empty($this->progressFormat)) {
            $this->start();
            return $this->update($current);
        }
        return $this->updateProgress($current);
    }

    /**
     * 任务完成，进度条显示 100%
     * @param bool $reset 是否重置进度条自定义设置
     * @param bool $clear 是否清除最后一次输出
     * @return string
     */
    public function finish(bool $reset = true, bool $clear = false)
    {
        $message = $this->updateProgress($this->total);
        $this->current = 0;
        $this->runTime = null;
        $this->progressFormat = $this->progressParsed = $this->progressHasBar =null;
        if ($reset) {
            $this->total = 100;
            $this->title = null;
            $this->doneChar = '=';
            $this->emptyChar = '-';
            $this->progressChar = '>';
            $this->doneWidth = $this->emptyWidth = $this->progressWidth = 1;
            $this->doneCharStyle = $this->emptyCharStyle = $this->progressCharStyle = null;
        }
        $this->resetLastOutput($clear);
        return $message;
    }

    /**
     * 更新进度条
     * @param int $current
     * @return string
     */
    private function updateProgress(int $current = 0)
    {
        $this->current = $current;
        $message = Helper::sprintfMessageContainer($this->progressParsed, function($key) {
            return $this->getOverwriteData($key);
        });
        if ($this->progressHasBar && preg_match('/.*%bar%.*/', $message, $match)) {
            // 自适应的情况下需要每次都实时获取窗口宽度
            $terminalWidth = Helper::terminalSize(1, $this->progressAdaptive);

            // %bar% 所在行文字转为纯文字 -> 获取该行宽度
            $width = Helper::strWidth(preg_replace('/<[^>]*>|^[^>]*>|<[^>]*$/', '', $match[0]));

            // 替换 %bar% 为进度条
            $message = str_replace('%bar%', $this->getBar($terminalWidth - $width + 3), $message);
        }
        if (!$this->runTime) {
            $this->runTime = time();
        }
        $this->resetLastOutput(true)->lastOutputMessage = $message;
        $this->richText->write($message);
        return $message;
    }

    /**
     * 清除最后输出
     * @param bool $clear
     * @return $this
     */
    private function resetLastOutput(bool $clear = false)
    {
        if ($this->lastOutputMessage) {
            if ($clear) {
                $this->terminal->revert(Helper::sectionLines(
                    Helper::getPureText($this->lastOutputMessage), $this->progressAdaptive
                ));
            }
            $this->lastOutputMessage = null;
        }
        return $this;
    }

    /**
     * @param string $key
     * @return string
     */
    private function getOverwriteData(string $key)
    {
        switch ($key) {
            case 'bar':
                return '%bar%';
            case 'total':
                return $this->total;
            case 'current':
                return $this->current;
            case 'left':
                return $this->total - $this->current;
            case 'percent':
                return floor(100 * $this->current / $this->total) . '%';
            case 'estimated':
                if (!$this->runTime) {
                    return null;
                }
                return Helper::formatTime(time() - $this->runTime);
            case 'remaining':
                if (!$this->runTime || !$this->current) {
                    return null;
                }
                if ($this->current >= $this->total) {
                    return Helper::formatTime(0);
                }
                return Helper::formatTime(
                    round( (time() - $this->runTime) * ($this->total - $this->current) / $this->current )
                );
            case 'memory':
                return Helper::formatMemory();
            case 'title':
                return empty($this->title) ? false : $this->title;
        }
        return null;
    }

    /**
     * @param int $width
     * @return string
     */
    private function getBar(int $width)
    {
        // 最长不超过 100
        $width = min($width, 100);
        if ($this->current >= $this->total) {
            return $this->getDoneChar($width);
        }
        if ($width < $this->progressWidth) {
            return '';
        }
        if ($width < $this->progressWidth + $this->emptyWidth) {
            return $this->getProgressChar();
        }
        $empty = floor($width * (1 - $this->current / $this->total));
        $done = $width - $empty - $this->progressWidth;
        return $this->getDoneChar($done) . $this->getProgressChar() . $this->getEmptyChar($empty);
    }

    /**
     * @return string
     */
    private function getProgressChar()
    {
        return $this->progressCharStyle ? sprintf(
            '<text%s>%s</text>', self::inlineStyle($this->progressCharStyle), $this->progressChar
        ) : $this->progressChar;
    }

    /**
     * @param int $width
     * @return string
     */
    private function getDoneChar(int $width)
    {
        if ($width < $this->doneWidth) {
            return '';
        }
        $char = str_repeat($this->doneChar, floor($width / $this->doneWidth));
        return $this->doneCharStyle ? sprintf(
            '<text%s>%s</text>', self::inlineStyle($this->doneCharStyle), $char
        ) : $char;
    }

    /**
     * @param int $width
     * @return string
     */
    private function getEmptyChar(int $width)
    {
        if ($width < $this->emptyWidth) {
            return '';
        }
        $char = str_repeat($this->emptyChar, floor($width / $this->emptyWidth));
        return $this->emptyCharStyle ? sprintf(
            '<text%s>%s</text>', self::inlineStyle($this->emptyCharStyle), $char
        ) : $char;
    }

    /**
     * @param array $style
     * @return string
     */
    private static function inlineStyle(array $style)
    {
        $inline = [];
        foreach ($style as $key => $val) {
            if (true === $val) {
                $inline[] = $key;
            } else {
                $inline[] = $key.'="'.$val.'"';
            }
        }
        if (count($inline)) {
            return ' '.join(' ', $inline);
        }
        return '';
    }
}
