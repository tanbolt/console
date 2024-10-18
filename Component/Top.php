<?php
namespace Tanbolt\Console\Component;

use Tanbolt\Console\Ansi;
use Tanbolt\Console\Input;
use Tanbolt\Console\Output;
use Tanbolt\Console\Helper;
use Tanbolt\Console\Command;
use Tanbolt\Console\Keyboard;

/**
 * Class Top:
 * 控制台输出类似 top 命令的效果
 * ```
 * [↑] [↓] - Scroll up / Scroll down
 * [←] [→] - Scroll left / Scroll right
 * [<] [PageUp] - Turn to previous page
 * [>] [PageDown] - Turn to next page
 * [m] [Home] - Scroll to the top
 * [e] [End] - Scroll top the bottom
 *
 * [0,1,2,3,4,5,6,7,8,9] - Set the sorted column
 * [s] - Input the sorted column
 *
 * [t] - Toggle header display
 * [d] - Set update interval
 * [h] - Show help
 * [esc] [q] - Quit
 * ```
 *
 * @package Tanbolt\Console\Component
 */
class Top extends AbstractStyle
{
    const INTERFACE_TOP = 'top';
    const INTERFACE_HELP = 'help';

    /**
     * 用于键盘监听对的象
     * @var Keyboard
     */
    protected $keyboard;

    /**
     * 当前正在显示界面类型
     * @var string
     */
    protected $interface;

    /**
     * 命令名称 用于 Help 显示
     * @var string
     */
    protected $commandName;

    /**
     * 获取头部的回调
     * @var callable|string
     */
    protected $headerHandler;

    /**
     * 隐藏数据头部
     * @var bool
     */
    protected $hideHeader = false;

    /**
     * 获取列表的回调
     * @var callable
     */
    protected $listHandler;

    /**
     * 列表头部 总结性行数
     * @var int
     */
    protected $topSummary = 0;

    /**
     * 列表尾部 总结性行数
     * @var int
     */
    protected $bottomSummary = 0;

    /**
     * 数据 起始行
     * @var int
     */
    protected $startRow = 0;

    /**
     * 数据 起始列
     * @var int
     */
    protected $startColumn = 0;

    /**
     * 数据 排序列序号
     * @var int
     */
    protected $sortColumn;

    /**
     * 是否正序排列
     * @var bool
     */
    protected $sortAsc = false;

    /**
     * 最后一次获取的 Top 的数据
     * @var array
     */
    protected $lastData;

    /**
     * 最后一次输出的 Top 列数
     * @var int
     */
    protected $screenColumn = 0;

    /**
     * 最后一次输出的 Top 行数
     * @var int
     */
    protected $screenRow = 0;

    /**
     * 输出前回调
     * @var callable
     */
    protected $beforeHandler;

    /**
     * 输出后回调
     * @var callable
     */
    protected $afterHandler;

    /**
     * 是否守护进程
     * @var bool
     */
    protected $daemonize = true;

    /**
     * 守护进程模式下 绑定的热键
     * @var array
     */
    protected $hotKeys = [];

    /**
     * 守护进程退出时，是否保留输出
     * @var bool
     */
    protected $keepOnStop = false;

    /**
     * 暂停刷新
     * @var bool
     */
    protected $stopRefresh = false;

    /**
     * @var string
     */
    protected $drawMessage = '';

    /**
     * 当前屏幕输出行数
     * @var string
     */
    protected $drawLines = 0;

    /**
     * Top constructor.
     * @param Input $input
     * @param Output $output
     * @param Command|null $command
     */
    public function __construct(Input $input, Output $output, Command $command = null)
    {
        parent::__construct($input, $output, $command);
        $this->keyboard = new Keyboard();
        $this->setCommandName(null)->setDefaultHotKey();
    }

    /**
     * 重置所有设置为初始化设置
     * @return $this
     */
    public function reset()
    {
        $this->interface = $this->commandName = $this->headerHandler = $this->listHandler =
        $this->sortColumn = $this->lastData = $this->beforeHandler = $this->afterHandler = null;

        $this->topSummary = $this->bottomSummary = $this->startRow = $this->startColumn =
        $this->screenColumn = $this->screenRow = 0;

        $this->daemonize = true;
        $this->hideHeader = $this->sortAsc = $this->keepOnStop = $this->stopRefresh = false;

        $this->hotKeys = [];
        $this->drawMessage = '';
        $this->keyboard->clearHotkey();
        $this->setDefaultHotKey();
        return $this;
    }

    /**
     * 设置当前界面类型
     * > 内置了 TOP, HELP 两种, 相关热键回调只在界面类型匹配时才会执行。
     * 此处开放了该接口，若有其他输出需求，可自行设置界面类型用于后续判断。
     * @param string $interface
     * @return $this
     */
    public function setInterface(string $interface)
    {
        $this->interface = $interface;
        return $this;
    }

    /**
     * 获取当前界面类型，从而确定是否继续执行
     * @return string
     */
    public function getInterface()
    {
        return $this->interface;
    }

    /**
     * 设置命令名称
     * @param ?string $name
     * @return $this
     */
    public function setCommandName(?string $name)
    {
        if (empty($name)) {
            $this->commandName = $this->command ? $this->command->getName() : 'Top';
        } else {
            $this->commandName = $name;
        }
        return $this;
    }

    /**
     * 设置头部信息：直接设置 string, 或设置一个回调函数，通过 callback() 回调函数应返回字符串
     * @param callable|string|null $handler
     * @return $this
     */
    public function setHeaderHandler($handler)
    {
        $this->headerHandler = $handler;
        return $this;
    }

    /**
     * 设置头部为 隐藏/显示状态
     * @param bool $hide
     * @return $this
     */
    public function setHeaderHidden(bool $hide = true)
    {
        $this->hideHeader = $hide;
        return $this;
    }

    /**
     * 当前头部是否为隐藏状态
     * @return bool
     */
    public function isHeaderHidden()
    {
        return $this->hideHeader;
    }

    /**
     * 设置获取列表数据的回调函数，回调函数返回数组
     * ```
     * // 第一列为标题栏显示, 后面的为 top 数据
     * [
     *     ['foo', 'bar'],
     *     [1, 2],
     *     [2, 3]
     * ]
     * ```
     * @param ?callable $handler
     * @return $this
     */
    public function setListHandler(?callable $handler)
    {
        $this->listHandler = $handler;
        return $this;
    }

    /**
     * 设置 总结性质 的行数, 列表排序时, 会排除总结性 row 之后进行排序
     * @param int $line 行数
     * @param bool $top 是否在顶部（默认为底部）
     * @return $this
     */
    public function setSummaryLine(int $line, bool $top = false)
    {
        if ($top) {
            $this->topSummary = $line;
        } else {
            $this->bottomSummary = $line;
        }
        return $this;
    }

    /**
     * 设置输出列表的 起始行
     * @param int $row
     * @return $this
     */
    public function setStartRow(int $row)
    {
        $this->startRow = max(0, $row);
        return $this;
    }

    /**
     * 获取输出列表的 起始行
     * @return int
     */
    public function getStartRow()
    {
        return $this->startRow;
    }

    /**
     * 设置输出列表的 起始列
     * @param int $column
     * @return $this
     */
    public function setStartColumn(int $column)
    {
        $this->startColumn = max(0, $column);
        return $this;
    }

    /**
     * 获取输出列表的 起始列
     * @return int
     */
    public function getStartColumn()
    {
        return $this->startColumn;
    }

    /**
     * 设置排序的 列序号 / 是否升序排序
     * @param ?int $column
     * @param bool $asc
     * @return $this
     */
    public function setSortColumn(?int $column, bool $asc = false)
    {
        $this->sortColumn = $column;
        $this->sortAsc = $asc;
        return $this;
    }

    /**
     * 获取当前排序的 列序号
     * @return int
     */
    public function getSortColumn()
    {
        return $this->sortColumn;
    }

    /**
     * 当前是否按照升序排序
     * @return bool
     */
    public function isSortAsc()
    {
        return $this->sortAsc;
    }

    /**
     * 最后一次获取的 Top 数据总列数
     * @return int
     */
    public function getTotalColumn()
    {
        return $this->lastData ? count($this->lastData[0][0]) : 0;
    }

    /**
     * 最后一次 Top 输出的列数
     * > 比如屏幕宽度无法输出所有数据，输出列数会小于数据总列数
     * @return int
     */
    public function getScreenColumn()
    {
        return $this->screenColumn;
    }

    /**
     * 最后一次获取的 Top 数据总行数
     * @return int
     */
    public function getTotalRow()
    {
        return $this->lastData ? count($this->lastData[0]) - 1 : 0;
    }

    /**
     * 最后一次 Top 输出的行数
     * > 比如屏幕高度无法输出所有数据，输出行数会小于数据总行数
     * @return int
     */
    public function getScreenRow()
    {
        return $this->screenRow;
    }

    /**
     * 设置输出前回调 `callback(string $header, array $list)`
     * @param callable $handler
     * @return $this
     */
    public function beforeTerminate(callable $handler)
    {
        $this->beforeHandler = $handler;
        return $this;
    }

    /**
     * 设置输出后回调 `callback(string $header, array $list)`
     * @param callable $handler
     * @return $this
     */
    public function afterTerminate(callable $handler)
    {
        $this->afterHandler = $handler;
        return $this;
    }

    /**
     * 设置为守护进程，周期性获取数据并刷新输出；
     * > 对于不支持的情况，比如输出流为文件，就无法设置为守护进程，返回 false
     * @param bool $daemonize
     * @return bool
     */
    public function setDaemonize(bool $daemonize = true)
    {
        if ($daemonize && !$this->isTty()) {
            return false;
        }
        $this->daemonize = $daemonize;
        return true;
    }

    /**
     * 设置守护进程模式下的刷新间隔时间 (单位：秒， 支持小数点后三位)
     * @param float $freshTime
     * @return $this
     */
    public function setRefreshInterval(float $freshTime)
    {
        $this->keyboard->setHeartbeatInterval($freshTime);
        return $this;
    }

    /**
     * 获取守护进程模式下的刷新频率
     * @return float
     */
    public function getRefreshInterval()
    {
        return $this->keyboard->getHeartbeatInterval();
    }

    /**
     * 启用/禁用自动刷新
     * @param bool $disable
     * @return $this
     */
    public function disableRefresh(bool $disable = true)
    {
        $this->stopRefresh = $disable;
        return $this;
    }

    /**
     * 守护进程模式下，退出时是否保留输出（默认不保留）
     * @param bool $keep
     * @return $this
     */
    public function keepOnStop(bool $keep = true)
    {
        $this->keepOnStop = $keep;
        return $this;
    }

    /**
     * 当前是否在守护进程退出时保留输出
     * @return bool
     */
    public function isKeepOnStop()
    {
        return $this->keepOnStop;
    }

    /**
     * 守护进程模式：绑定按键回调, 可同时绑定多个 key
     * > 绑定多个 key 可使用数组 或 逗号分隔的字符串, 区别在于显示帮助时的格式, 如：
     * - 绑定  ['a', 'b']  帮助信息显示为 [a][b] - description
     * - 绑定  'a,b'  帮助信息显示为 [a,b] - description
     * - 若不设置 key(key=null)，认为是显示帮助时的分隔空格
     * - 默认已绑定了一批快捷键，但仍可对这些键绑定回调，不会覆盖已有监听，可绑定其他键，可在启动后添加绑定
     * @param string|array|null $key
     * @param ?callable $callback
     * @param ?string $description
     * @return $this
     */
    public function setHotKey($key = null, callable $callback = null, string $description = null)
    {
        if (!$key) {
            $this->hotKeys[] = ['key' => false];
            return $this;
        }
        $hKey = null;
        if (!is_array($key)) {
            if (false === strpos($key, ',')) {
                $key = [$key];
            } else {
                $hKey = '['.$key.']';
                $key = explode(',', $key);
            }
        }
        if (null === $hKey) {
            $hKey = '['.join('] [', $key).']';
        }
        $this->hotKeys[] = [
            'key' => $hKey,
            'desc' => $description
        ];
        $this->keyboard->onHotkey($key, $callback);
        return $this;
    }

    /**
     * 守护进程模式: 默认按键回调
     * @return $this
     */
    protected function setDefaultHotKey()
    {
        // 方向键
        $this->setHotKey([Keyboard::UP, Keyboard::DOWN], function ($key) {
            if (self::INTERFACE_TOP === $this->interface) {
                Keyboard::UP === $key ? $this->startRow-- : $this->startRow++;
                $this->drawTop();
            }
        }, 'Scroll up / Scroll down');
        $this->setHotKey([Keyboard::LEFT, Keyboard::RIGHT], function ($key) {
            if (self::INTERFACE_TOP === $this->interface) {
                Keyboard::LEFT === $key ? $this->startColumn-- : $this->startColumn++;
                $this->drawTop();
            }
        }, 'Scroll left / Scroll right');

        // 翻页键
        $this->setHotKey(['<', Keyboard::PAGEUP], function () {
            if (self::INTERFACE_TOP === $this->interface) {
                $this->setStartRow($this->getStartRow() - $this->getScreenRow())->drawTop();
            }
        }, 'Turn to previous page');
        $this->setHotKey(['>', Keyboard::PAGEDOWN], function () {
            if (self::INTERFACE_TOP === $this->interface) {
                $this->setStartRow($this->getStartRow() + $this->getScreenRow())->drawTop();
            }
        }, 'Turn to next page');

        // Home End 键
        $this->setHotKey(['m', Keyboard::HOME], function () {
            if (self::INTERFACE_TOP === $this->interface) {
                $this->setStartRow(0)->drawTop();
            }
        }, 'Scroll to the top');
        $this->setHotKey(['e', Keyboard::END], function () {
            if (self::INTERFACE_TOP === $this->interface) {
                $this->setStartRow($this->getTotalRow())->drawTop();
            }
        }, 'Scroll to the bottom');
        $this->setHotKey();

        // 排序
        $this->setHotKey('0,1,2,3,4,5,6,7,8,9', function ($key) {
            if (self::INTERFACE_TOP !== $this->interface) {
                return;
            }
            $key = intval($key);
            if ($this->sortColumn === $key) {
                $this->sortAsc = !$this->sortAsc;
            } else {
                $this->sortColumn = $key;
            }
            $this->drawTop();
        }, 'Set the sorted column');
        $this->setHotKey('s', function () {
            if (self::INTERFACE_TOP !== $this->interface) {
                return;
            }
            $this->getAnswer('Input the sorted column number', function ($sortColumn) {
                $sortColumn = is_numeric($sortColumn) ? intval($sortColumn) : null;
                if (null !== $sortColumn) {
                    if ($this->sortColumn === $sortColumn) {
                        $this->sortAsc = !$this->sortAsc;
                    } else {
                        $this->sortColumn = $sortColumn;
                    }
                }
                $this->drawTop();
            });
        }, 'Input the sorted column');
        $this->setHotKey();

        // 头部切换 / 设置刷新频率 / 显示帮助 / 隐藏帮助 / 退出命令
        $this->setHotKey('t', function () {
            if (self::INTERFACE_TOP === $this->interface) {
                $this->hideHeader = !$this->hideHeader;
                $this->drawTop();
            }
        }, 'Toggle header display');
        $this->setHotKey('d', function () {
            if (self::INTERFACE_TOP !== $this->interface) {
                return;
            }
            $this->getAnswer('Change delay from '.$this->getRefreshInterval().' to', function ($freshTime) {
                if (is_numeric($freshTime)) {
                    $this->setRefreshInterval($freshTime);
                }
                $this->drawTop();
            });
        }, 'Set update interval');
        $this->setHotKey('h', function () {
            if (self::INTERFACE_TOP === $this->interface) {
                $this->showHelp();
            } elseif (self::INTERFACE_HELP === $this->interface) {
                $this->drawTop();
            }
        }, 'Show help');
        $this->setHotKey(['q', Keyboard::ESC], function () {
            if (self::INTERFACE_TOP === $this->interface) {
                $this->keyboard->stop();
            } elseif (self::INTERFACE_HELP === $this->interface) {
                $this->drawTop();
            }
        }, 'Quit');
        $this->setHotKey();
        return $this;
    }

    /**
     * 显示 Top 界面
     * @return $this
     */
    public function showTop()
    {
        return $this->drawTop()->runDaemonize();
    }

    /**
     * 整理/输出 Top 数据
     * @param bool $update
     * @return $this
     */
    protected function drawTop(bool $update = false)
    {
        // 获取输出数据
        if (!$this->lastData || $update) {
            // 获取头部
            $header = '';
            if ($this->headerHandler) {
                $header = is_callable($this->headerHandler)
                    ? call_user_func($this->headerHandler)
                    : (string) $this->headerHandler;
            }
            if (!strlen($header)) {
                $header = null;
            } elseif (substr($header, -1) !== "\n") {
                $header .= Ansi::EOL;
            }
            // 获取列表，为预防 key 值不是基本的自增数字, 需预处理一下
            $lists = $this->listHandler ? call_user_func($this->listHandler) : [];
            $lists = is_array($lists) ? array_values(array_map('array_values', $lists)) : [];
            $this->lastData = [$lists, $header];
        } else {
            list($lists, $header) = $this->lastData;
        }
        // 输出前回调
        if ($this->beforeHandler) {
            call_user_func($this->beforeHandler, $lists, $header);
        }
        // 输出界面
        $this->drawTopList($lists, $header);
        // 输出后回调
        if ($this->afterHandler) {
            call_user_func($this->afterHandler, $lists, $header);
        }
        return $this->setInterface(self::INTERFACE_TOP);
    }

    /**
     * 输出 Top 数据
     * @param array $lists
     * @param ?string $header
     * @return array
     */
    protected function drawTopList(array $lists, ?string $header)
    {
        $this->clear();
        $isTty = $this->isTty();

        // 若是 tty, 先计算列表的可用高度
        $width = $listHeight = 0;
        if ($isTty) {
            $headerHeight = $this->hideHeader || null === $header
                ? 0 : Helper::sectionLines(Helper::getPureText($header)) - 1;
            list($width, $height) = array_values(Helper::terminalSize(0, true));
            $listHeight = $height - $headerHeight - 1; #列表高度 = 总高 - 头部高 - 标题栏
        }

        // 标题栏
        $tHeader = array_shift($lists);
        $totalColumn = count($tHeader);
        if ($this->startColumn > $totalColumn - 1) {
            $this->startColumn = $totalColumn - 1;
        }

        // 排序数据
        $sortColumn = null === $this->sortColumn ? null : $this->sortColumn;
        if (null !== $sortColumn && $sortColumn >= $totalColumn) {
            $sortColumn = null;
        }
        if (null !== $sortColumn) {
            $topArr = $bottomArr = [];
            if ($this->topSummary && $this->bottomSummary) {
                $topArr = array_slice($lists, 0, $this->topSummary);
                $bottomArr = array_slice($lists, -1 * $this->bottomSummary);
                $lists = array_values(array_slice($lists, $this->topSummary, -1 * $this->bottomSummary));
            } elseif ($this->topSummary) {
                $topArr = array_slice($lists, 0, $this->topSummary);
                $lists = array_values(array_slice($lists, $this->topSummary));
            } elseif ($this->bottomSummary) {
                $bottomArr = array_slice($lists, -1 * $this->bottomSummary);
                $lists = array_values(array_slice($lists, 0, -1 * $this->bottomSummary));
            }
            $sortArr = [];
            foreach ($lists as $key => $list) {
                $sortArr[$key] = $list[$sortColumn];
            }
            array_multisort($lists, $this->sortAsc ? SORT_ASC : SORT_DESC, $sortArr);
            $lists = array_merge($topArr, $lists, $bottomArr);
        }

        // 输出行处理：根据终端高度截取可显示行
        $totalRow = count($lists);
        if ($listHeight && $totalRow > $listHeight) {
            if ($this->startRow < 0) {
                $this->startRow = 0;
            }
            if ($totalRow - $this->startRow < $listHeight) {
                $this->startRow = $totalRow - $listHeight;
            }
            $lists = array_slice($lists, $this->startRow, $listHeight);
        }

        // 输出列处理：计算每列输出的宽度
        $cellMax = []; // list 每列最大宽度
        $cellWidth = []; // list 数据所有 cell 的宽度
        foreach ($lists as $key => $list) {
            $currentCellWidth = [];
            foreach ($list as $k => $item) {
                // 记录每个 cell 的宽度, 省的后面再计算
                $itemWidth = Helper::strWidth($item);
                $currentCellWidth[$k] = $itemWidth;
                // 每一列最宽字符数, 预留1个字符的间距
                $itemWidth += 1;
                if (!isset($cellMax[$k])) {
                    $cellMax[$k] = $itemWidth;
                } elseif ($itemWidth > $cellMax[$k]) {
                    $cellMax[$k] = $itemWidth;
                }
            }
            $cellWidth[$key] = $currentCellWidth;
        }

        // 标题栏 cell width 可能大于列表, 也计算一下
        $headerWidth = [];
        foreach ($tHeader as $k => $item) {
            if ($k === $sortColumn) {
                $item .= $this->sortAsc ? '↑' : '↓';
                $tHeader[$k] = $item;
            }
            $itemWidth = Helper::strWidth($item);
            $headerWidth[$k] = $itemWidth;
            // 每一列最宽字符数, 预留1个字符的间距
            $itemWidth += 1;
            if ($itemWidth > $cellMax[$k]) {
                $cellMax[$k] = $itemWidth;
            }
        }

        // 计算可输出的 最大列 的 key
        $pad = 6;
        $rightWidth = 0;
        $maxKey = $totalColumn - 1;
        if ($isTty) {
            if ($this->startColumn < 0) {
                $this->startColumn = 0;
            }
            // 所有字段所需的总宽度
            $totalWidth = array_sum($cellMax);
            // 如果可显示所有, 就忽略 startColumn 设置
            if ($totalWidth < $width) {
                $this->startColumn = 0;
            } else {
                $totalWidth = 0;
                foreach ($cellMax as $k => $item) {
                    if ($k < $this->startColumn) {
                        continue;
                    }
                    $totalWidth += $item;
                    if ($totalWidth > $width) {
                        $maxKey = $k - 1;
                        $totalWidth -= $item;
                        break;
                    }
                }
            }
            // 如果还有很多宽度可用, 可用适当加大列间距
            $showRow = $maxKey - $this->startColumn + 1;
            if ($showRow) {
                $pad = floor(($width - $totalWidth) / $showRow);
                $pad = max(0, min(6, $pad));
                $rightWidth = $width - ($totalWidth + $pad * $showRow);
            }
        }

        // 输出头部
        $ansi = Ansi::instance($this->output);
        if (!$this->hideHeader && null !== $header) {
            $this->richText->write($header);
            $this->drawMessage .= Helper::getPureText($header);
        }

        // 输出标题栏
        $subject = '';
        $screenColumn = 0;
        foreach ($tHeader as $k => $item) {
            if ($k < $this->startColumn || $k > $maxKey) {
                continue;
            }
            $screenColumn++;
            $subject .= $item . str_repeat(' ', max(0, $cellMax[$k] - $headerWidth[$k] + $pad));
        }
        if ($rightWidth) {
            $subject .= str_repeat(' ', max(0, $rightWidth));
        }
        $this->screenColumn = $screenColumn;
        $this->stdout($subject.Ansi::EOL, $ansi->reset()->bgWhite()->black());

        // 输出列表
        $ansi->reset();
        $lastKey = count($lists) - 1;
        foreach ($lists as $key => $list) {
            $line = '';
            foreach ($list as $k => $item) {
                if ($k < $this->startColumn || $k > $maxKey) {
                    continue;
                }
                $line .= $item . str_repeat(' ', max(0, $cellMax[$k] - $cellWidth[$key][$k] + $pad));
            }
            if ($key < $lastKey) {
                $line .= Ansi::EOL;
            }
            $this->stdout($line);
            // 这里有很搞笑了, 不加这个, 可能输出会导致输出不完整, 终端响应跟不上, 直接丢弃了
            usleep(1);
        }
        $this->screenRow = count($lists);
        return $lists;
    }

    /**
     * 显示 Helper 界面
     * @return $this
     */
    public function showHelp()
    {
        // key 可能 strlen=2 但 占宽为1, 先计算一下
        $keyMaxLen = 0;
        $hotKeyList = [];
        foreach ($this->hotKeys as $hotKey) {
            if ($hotKey['key']) {
                $width = Helper::strWidth($hotKey['key']);
                $hotKey['pad'] = strlen($hotKey['key']) - $width;
                if ($width > $keyMaxLen) {
                    $keyMaxLen = $width;
                }
            }
            $hotKeyList[] = $hotKey;
        }
        $keyMaxLen++;

        // 输出
        $this->clear();
        $ansi = Ansi::instance($this->output);
        $this->stdout(Ansi::EOL.'Help for '.$this->commandName.' Command'.Ansi::EOL.Ansi::EOL, $ansi->green());
        foreach ($hotKeyList as $hotKey) {
            if ($hotKey['key']) {
                $max = $keyMaxLen + $hotKey['pad'];
                $this->stdout(sprintf("%-{$max}s", $hotKey['key']), $ansi->reset()->yellow())
                    ->stdout($hotKey['desc'].Ansi::EOL);
            } else {
                $this->stdout(Ansi::EOL);
            }
        }
        $this->stdout(Ansi::EOL.'Type [q] or [Esc] to continue...'.Ansi::EOL);
        return $this->setInterface(self::INTERFACE_HELP)->runDaemonize();
    }

    /**
     * 显示指定的 RichText 界面
     * @param string $message
     * @return $this
     */
    public function showRich(string $message)
    {
        $this->drawMessage = Helper::getPureText($message);
        $this->richText->write($message);
        return $this->runDaemonize();
    }

    /**
     * 运行守护进程模式
     * @return $this
     */
    protected function runDaemonize()
    {
        if (!$this->daemonize || $this->keyboard->isRunning()) {
            return $this;
        }
        $this->keyboard->onHeartbeat(function () {
            if (!$this->stopRefresh && self::INTERFACE_TOP === $this->interface) {
                $this->drawTop(true);
            }
        })->onStop(function () {
            if ($this->keepOnStop) {
                $this->output->stdout(Ansi::EOL);
            } else {
                $this->clear();
            }
        })->listen($this->output);
        return $this;
    }







    /**
     * 输出内容 (使用该方法输出的内容可使用 clear() 清除)
     * @param string $message
     * @param Ansi|null $ansi
     * @return $this
     */
    public function stdout(string $message, Ansi $ansi = null)
    {
        $this->drawMessage .= $message;
        if ($ansi) {
            $ansi->stdout($message);
        } else {
            $this->output->stdout($message);
        }
        return $this;
    }

    /**
     * 设置当前输出的总行数
     * > 若输出内置内容（如 showTop,showHelp），或通过 showRich 输出自定义文本，无需手动设置，会自动获取。
     * 若通过其他方式自行输出内容，可手动设置输出行数，该行数用于 clear() 清屏
     * @param int $lines
     * @return $this
     */
    public function setLines(int $lines)
    {
        $this->drawLines = $lines;
        return $this;
    }

    /**
     * 获取当前输出的总行数 (clear 方法即是删除该行数输出)
     * @return int
     */
    public function getLines()
    {
        return $this->drawLines;
    }

    /**
     * 清空当前输出
     * @return $this
     */
    public function clear()
    {
        if (!$this->drawMessage) {
            return $this;
        }
        $lines = Helper::sectionLines($this->drawMessage, true);
        $this->terminal->revert($lines);
        $this->drawMessage = '';
        return $this;
    }





    /**
     * 在当前界面获取一段输入数据
     * @param string $question
     * @param callable $callback
     * @return $this
     */
    public function getAnswer(string $question, callable $callback)
    {
        $height = Helper::terminalSize(2, true);
        $revertLine = min(3, 3 - ($height - $this->drawLines));
        if ($revertLine >  0) {
            $this->drawLines = $height;
            $this->terminal->revert($revertLine);
        } else {
            $this->drawLines += 3;
        }
        $this->output->stdout(Ansi::EOL);
        $this->pause(function () use ($question) {
            return $this->question->ask($question);
        }, $callback);
        return $this;
    }

    /**
     * 暂停监听，详情可参考 Keyboard::pause
     * @param callable $onStop
     * @param callable|null $onStart
     * @return $this
     * @see Keyboard::pause
     */
    public function pause(callable $onStop, callable $onStart = null)
    {
        $this->keyboard->pause($onStop, $onStart);
        return $this;
    }

    /**
     * 退出命令
     * @return $this
     */
    public function stop()
    {
        $this->keyboard->stop();
        return $this;
    }
}
