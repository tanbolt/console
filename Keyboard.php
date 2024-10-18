<?php
namespace Tanbolt\Console;

use Throwable;
use Tanbolt\Console\Exception\RuntimeException;
use Tanbolt\Console\Exception\InvalidArgumentException;

class Keyboard
{
    const F1 = 'F1';
    const F2 = 'F2';
    const F3 = 'F3';
    const F4 = 'F4';
    const F5 = 'F5';
    const F6 = 'F6';
    const F7 = 'F7';
    const F8 = 'F8';
    const F9 = 'F9';
    const F10 = 'F10';

    /**
     * 不建议使用
     * F11 通常为终端全屏快捷键, 容易冲突, 另外 F11, F12 在某些终端
     * 比如 phpstorm terminal, 不晓得是不是 bug, 会在 F11 后自动添加 ESC 键代码, F12 后自动添加 BACKSPACE 键代码
     */
    const F11 = 'F11';
    const F12 = 'F12';

    // 常用快捷键
    const ESC = 'Esc';
    const BACKSPACE = 'BackSpace';
    const TAB = 'Tab';
    const ENTER = 'Enter';
    const SPACE = 'Space';

    const UP = '↑';
    const DOWN = '↓';
    const RIGHT = '→';
    const LEFT = '←';

    // 以下键不建议使用，若希望使用，应同时绑定备选键
    // 因为并不是所有键盘都有以下键，需要通过组合键模拟，不方便
    const INSERT = 'Insert';
    const DELETE = 'Delete';
    const HOME = 'Home';
    const END = 'End';
    const PAGEUP = 'PageUp';
    const PAGEDOWN = 'PageDown';

    /**
     * 功能键对应的 str 在不同终端下可能是不一样的，目前仅在 mac 的 terminal 测试了下。
     * 所以这里不是很严谨，可参考下面的链接，当前使用的是 xterm
     * 猜测：是不是要先通过 `getenv('TERM')` 获取类型，再通过 `infocmp -1 $type` 获取映射
     * @var string[]
     * @see https://github.com/gdamore/tcell/tree/ca8fb5bcc94b37b52eb8508d3ed8753e4762e83f/terminfo
     */
    private static $specialCodes = [
        'OP' => self::F1,
        'OQ' => self::F2,
        'OR' => self::F3,
        'OS' => self::F4,
        '[15~' => self::F5,
        '[17~' => self::F6,
        '[18~' => self::F7,
        '[19~' => self::F8,
        '[20~' => self::F9,
        '[21~' => self::F10,
        '[23~' => self::F11,
        '[24~' => self::F12,

        '[A' => self::UP,
        '[B' => self::DOWN,
        '[C' => self::RIGHT,
        '[D' => self::LEFT,

        '[2~' => self::INSERT,
        '[3~' => self::DELETE,
        '[H' => self::HOME,
        '[F' => self::END,
        '[5~' => self::PAGEUP,
        '[6~' => self::PAGEDOWN,
    ];

    /**
     * $specialCodes 键值交换后的数组
     * @var array
     */
    private static $specialChars;

    /**
     * @var ?callable
     */
    private $startListener;

    /**
     * 心跳监听
     * @var ?callable
     */
    private $heartbeatListener;

    /**
     * 心跳频率
     * @var ?float
     */
    private $heartbeatInterval = 3;

    /**
     * 最后一次调用心跳的时间
     * @var float
     */
    private $lastHeartbeatTime = 0;

    /**
     * 热键监听
     * @var null|callable[][]
     */
    private $hotkeyListener = [];

    /**
     * 输入值监听
     * @var ?callable
     */
    private $inputListener;

    /**
     * 输入值监听是否包括热键
     * @var bool
     */
    private $inputHotkey = false;

    /**
     * 输入值监听是否包括不可见字符
     * @var bool
     */
    private $inputInvisible = false;

    /**
     * 退出监听时的回调函数
     * @var ?callable
     */
    private $stopListener;

    /**
     * 是否已触发启动回调
     * @var bool
     */
    private $startTriggered = false;

    /**
     * 是否 ctrl+c 退出的
     * @var bool
     */
    private $isSigint = false;

    /**
     * 是否启动成功，正在监听
     * @var bool
     */
    private $listening = false;

    /**
     * 暂停监听 -> 停止后回调
     * @var callable
     */
    protected $pauseOnStop;

    /**
     * 暂停监听 -> 重启后回调
     * @var callable
     */
    protected $pauseOnStart;

    /**
     * 暂停监听 -> 停止时执行回调函数的返回值
     * @var mixed
     */
    protected $pauseResult;

    /**
     * 设置开始监听后的回调函数，仅可绑定一个回调
     * - `$listener($this)`
     * @param callable|null $listener
     * @return $this
     */
    public function onStart(?callable $listener)
    {
        $this->startListener = $listener;
        return $this;
    }

    /**
     * 设置心跳回调，即在无输入的情况下，周期性回调，仅可绑定一个回调
     * - `$listener($this)`
     * @param callable|null $listener
     * @return $this
     */
    public function onHeartbeat(?callable $listener)
    {
        $this->heartbeatListener = $listener;
        return $this;
    }

    /**
     * 设置心跳回调间隔时间，单位秒，最大支持3位小数（如果小于等于0，则不执行心跳回调）
     * @param float $interval
     * @return $this
     */
    public function setHeartbeatInterval(float $interval)
    {
        $this->heartbeatInterval = sprintf('%.3f', $interval);
        return $this;
    }

    /**
     * 获取当前设置的心跳回调间隔时间
     * @return float
     */
    public function getHeartbeatInterval()
    {
        return $this->heartbeatInterval;
    }

    /**
     * 确定 heartbeatListener/heartbeatInterval 已设置, 周期性触发
     * @return $this
     */
    protected function triggerHeartbeatInterval()
    {
        $now = microtime(true);
        if ($now - $this->lastHeartbeatTime >= $this->heartbeatInterval) {
            $this->lastHeartbeatTime = $now;
            call_user_func($this->heartbeatListener, $this);
        }
        return $this;
    }

    /**
     * 设置热键监听函数
     * - 回调函数为 $listener(KEY, char, $this) - KEY 为按键，char 为可见字符
     * - $key 可以是普通单个键，此时回调参数 char=KEY
     * - $key 也可为内置的特殊键，如 Keyboard::ESC，回调的 char 为映射的可见字符
     * - $key 支持使用数组，即多个热键触发同一个监听
     * - 同一个 $key 可绑定多个回调，将按照绑定顺序逐个触发
     * @param string[]|string $key
     * @param callable $listener
     * @return $this
     */
    public function onHotkey($key, callable $listener)
    {
        if (!is_array($key)) {
            $key = [$key];
        }
        foreach ($key as $k) {
            $k = (string) $k;
            if (!isset($this->hotkeyListener[$k])) {
                $this->hotkeyListener[$k] = [];
            } elseif (in_array($listener, $this->hotkeyListener[$k])) {
                // 同一个回调在同一个热键上仅绑定一次
                continue;
            }
            $this->hotkeyListener[$k][] = $listener;
        }
        return $this;
    }

    /**
     * 获取指定 key 或 所有热键回调绑定
     * @param string|null $key
     * @return callable[]|null
     */
    public function getHotkey(?string $key = null)
    {
        if (null === $key) {
            return $this->hotkeyListener;
        }
        return $this->hotkeyListener[$key] ?? null;
    }

    /**
     * 移除热键监听函数
     * @param string[]|string $key
     * @param callable $listener
     * @return $this
     */
    public function offHotkey($key, callable $listener)
    {
        if (!is_array($key)) {
            $key = [$key];
        }
        foreach ($key as $k) {
            if (!isset($this->hotkeyListener[$k])) {
                continue;
            }
            $callables = $this->hotkeyListener[$k];
            if (false === $key = array_search($listener, $callables)) {
                continue;
            }
            unset($callables[$key]);
            $this->hotkeyListener[$k] = array_values($callables);
        }
        return $this;
    }

    /**
     * 清空所有绑定的热键回调
     * @return $this
     */
    public function clearHotkey()
    {
        $this->hotkeyListener = [];
        return $this;
    }

    /**
     * 键盘输入监听
     * - `$listener($char, $key, $this)`
     * - 回调参数中 $char 为可见字符串，$key 为特殊键或与 $char 相同
     * - 仅可绑定一个回调
     * @param callable|null $listener
     * @param bool $withHotkey 是否包括热键
     * @param bool $withInvisible 是否包括不可见字符
     * @return $this
     */
    public function onInput(?callable $listener, bool $withHotkey = false, bool $withInvisible = false)
    {
        $this->inputListener = $listener;
        $this->inputHotkey = $withHotkey;
        $this->inputInvisible = $withInvisible;
        return $this;
    }

    /**
     * 触发输入监听
     * @param string $key
     * @param string $char
     * @return $this
     */
    protected function triggerInput(string $key, string $char)
    {
        $listeners = $this->getHotkey($key);
        if ($listeners) {
            foreach ($listeners as $listener) {
                call_user_func($listener, $key, $char, $this);
            }
            // input 监听不需要热键, 直接返回
            if (!$this->inputHotkey) {
                return $this;
            }
        }
        // 非热键 或 热键输入也要监听, 触发之
        if ($this->inputListener) {
            call_user_func($this->inputListener, $char, $key, $this);
        }
        return $this;
    }

    /**
     * 设置监听停止后回调函数，可能有以下几种情况
     * - 回调为 `$listener($e, $this)`
     * - 用户使用 ctrl+c 终止: $e = true
     * - 手动调用 stop() 终止: $e = null
     * - 运行过程中发生异常导致终止: $e = Exception
     * - 仅可绑定一个回调
     * @param callable|null $listener
     * @return $this
     */
    public function onStop(?callable $listener)
    {
        $this->stopListener = $listener;
        return $this;
    }

    /**
     * 开始监听，若指定 $stdout，会判断 stdout 是否支持隐藏鼠标并自动进行隐藏操作
     * @param OutputInterface|resource|null $stdout
     * @return $this
     * @throws Throwable
     */
    public function listen($stdout = null)
    {
        if ($this->listening) {
            throw new RuntimeException('Keyboard already run');
        }
        if ('cli' !== php_sapi_name()) {
            throw new InvalidArgumentException('Keyboard must run in cli');
        }
        $hideCursor = false;
        if ($stdout instanceof OutputInterface) {
            $hideCursor = $stdout->isStdoutSupportColor();
            $stdout = $stdout->stdoutStream();
        } elseif (is_resource($stdout)) {
            $hideCursor = Helper::supportColor($stdout);
        }
        return $this->listenStart($stdout, $hideCursor);
    }

    /**
     * 启动监听 - 同时判断是否为 pause() 触发并进行处理
     * @param resource $stdout
     * @param bool $hideCursor
     * @return $this
     * @throws Throwable
     */
    protected function listenStart($stdout, bool $hideCursor = true)
    {
        $exception = null;
        $this->isSigint = false;
        $this->listening = true;
        $this->startTriggered = false;
        try {
            $showCursor = $hideCursor && fwrite($stdout, "\x1b[?25l");
            if ('\\' === DIRECTORY_SEPARATOR) {
                $this->listenWin();
            } else {
                $this->listenUnix();
            }
        } catch (Throwable $exception) {
            throw $exception;
        } finally {
            $this->listening = false;
            if ($showCursor) {
                fwrite($stdout, "\x1b[?25h");
            }
            // pause() 导致的停止，不触发 stopListener
            if (!$this->pauseOnStop && $this->stopListener) {
                $stop = $this->isSigint ? true : $exception;
                call_user_func($this->stopListener, $stop, $this);
            }
            // ctrl+c 停止的, 退出
            if ($this->isSigint) {
                exit(2);
            }
        }
        // 由 pause() 触发导致的停止监听 -> 停止后执行 pauseOnStop 回调 -> 重启监听
        if ($this->pauseOnStop) {
            $this->pauseResult = call_user_func($this->pauseOnStop, $this);
            $this->pauseOnStop = null;
            return $this->listenStart($stdout, $hideCursor);
        }
        return $this;
    }

    /**
     * 启动成功后的回调
     * @return $this
     */
    protected function triggerStartEvent()
    {
        $this->startTriggered = true;
        // 由 pause() 触发的重启 -> 执行 pauseOnStart 回调; 否则执行 startListener 回调
        if ($this->pauseOnStart) {
            call_user_func($this->pauseOnStart, $this->pauseResult, $this);
            $this->pauseOnStart = $this->pauseResult = null;
        } elseif ($this->startListener) {
            call_user_func($this->startListener, $this);
        }
        return $this;
    }

    /**
     * Win 平台输入流监听
     * @return $this
     */
    protected function listenWin()
    {
        if (!self::$specialChars) {
            self::$specialChars = array_flip(self::$specialCodes) + [
                // 224 开头的功能键的第2位 Byte 值
                // https://docs.microsoft.com/en-us/previous-versions/visualstudio/visual-studio-6.0/aa299374(v=vs.60)
                133 => self::F11,
                134 => self::F12,
                72 => self::UP,
                80 => self::DOWN,
                75 => self::LEFT,
                77 => self::RIGHT,
                82 => self::INSERT,
                83 => self::DELETE,
                71 => self::HOME,
                79 => self::END,
                73 => self::PAGEUP,
                81 => self::PAGEDOWN
            ];
        }
        // 可能会有多字节, 记录一下 codepage 以便后续转换
        $cpp = sapi_windows_cp_get();
        $oem = sapi_windows_cp_get('oem');

        // 创建临时文件, 用于接收输出流, 运行命令
        $file = tempnam(sys_get_temp_dir(), 'ks');
        $command = sprintf('%s key > "%s"', Helper::winTermPath(), $file);
        $descriptors = [
            ['pipe', 'r'],
            ['file', 'NUL', 'w'],
            ['file', 'NUL', 'w'],
        ];
        $options = [
            'suppress_errors' => true,
            'bypass_shell' => true
        ];
        $process = proc_open($command, $descriptors, $pipes, null, $options);
        if (!$process) {
            throw new RuntimeException('Start listen failed');
        }
        try {
            // 读取输出
            $bytes = 0;
            $input = '';
            $handle = fopen($file, 'rb');
            $heartbeat = $this->heartbeatListener && $this->heartbeatInterval > 0;
            while (1) {
                if ($listening = $this->listening) {
                    $char = stream_get_contents($handle, -1, $bytes);
                    if ($len = strlen($char)) {
                        $input .= $char;
                        $bytes += $len;
                        // 有可能输入值为多字节字符, 这里需要略微停顿一下, 否则读取值为被切割的 ascii
                        usleep(100);
                    } elseif ('' !== $input) {
                        $listening = $this->listenWinKey($input, $cpp, $oem);
                        $input = '';
                    } elseif (!$this->startTriggered) {
                        $this->triggerStartEvent();
                    } elseif ($heartbeat) {
                        $this->triggerHeartbeatInterval();
                    }
                }
                if (!$listening) {
                    $status = proc_get_status($process);
                    if ($status['running']) {
                        proc_terminate($process);
                    }
                    break;
                }
            }
        } finally {
            // 删除临时文件
            $k = 0;
            fclose($handle);
            while ($k++ < 10) {
                if (unlink($file)) {
                    break;
                }
                usleep(100);
            }
        }
        return $this;
    }

    /**
     * Win 输入键处理
     * @param string $input
     * @param int $cp
     * @param int $oem
     * @return bool
     */
    protected function listenWinKey(string $input, int $cp, int $oem)
    {
        $i = 0;
        $str = '';
        $len = strlen($input);
        while ($i < $len) {
            $chr = $input[$i];
            $ord = ord($chr);
            $key = $char = null;
            switch ($ord) {
                // ctrl + c
                case 3:
                    $this->isSigint = true;
                    $this->listenWinKeyStr($str, $cp, $oem);
                    return false;

                // backspace=8 Tab=9 enter=13 esc=27 space=32
                case 8:
                    $key = self::BACKSPACE;
                    $char = '';
                    break;
                case 9:
                    $key = self::TAB;
                    $char = "\t";
                    break;
                case 13:
                    $key = self::ENTER;
                    $char = "\n";
                    break;
                case 27:
                    $key = self::ESC;
                    $char = '^[';
                    break;
                case 32:
                    $key = self::SPACE;
                    $char = ' ';
                    break;

                // $ord=0 len=2
                //   59~68 -> F1~F10
                // $ord=224 len=2
                //   F11=133 F12=134 up=72 down=80 left=75 right=77
                //   insert=82 delete=83 home=71 end=79 pgup=73 pgdn=81
                case 0:
                case 224:
                    if (++$i >= $len) {
                        if ($this->inputInvisible) {
                            $str .= $chr;
                        }
                        break;
                    }
                    $chr2 = $input[$i];
                    $ord2 = ord($chr2);
                    if (0 === $ord && $ord2 > 58 && $ord2 < 69) {
                        $key = constant('self::' . 'F' . ($ord2 - 58));
                        $char = '^[' . self::$specialChars[$key];
                    } elseif (224 === $ord && null !== ($key = self::$specialChars[$ord2] ?? null)) {
                        $char = '^[' . self::$specialChars[$key];
                    } elseif ($this->inputInvisible) {
                        $str .= $chr.$chr2;
                    }
                    break;

                // 普通字符
                default:
                    $str .= $chr;
                    break;
            }
            // 先触发特殊键之前累积的字符, 再特殊键触发
            if (null !== $key) {
                if ($this->listenWinKeyStr($str, $cp, $oem)) {
                    $str = '';
                }
                $this->triggerInput($key, $char);
            }
            $i++;
        }
        // 整个输入 或 剩余部分 无特殊键, 也要触发
        $this->listenWinKeyStr($str, $cp, $oem);
        return true;
    }

    /**
     * Win 输入字符处理
     * @param string $str
     * @param int $cp
     * @param int $oem
     * @return bool
     */
    protected function listenWinKeyStr(string $str, int $cp, int $oem)
    {
        if ('' === $str) {
            return false;
        }
        if ($cp !== $oem) {
            $str = sapi_windows_cp_conv($oem, $cp, $str);
        }
        $this->triggerInput($str, $str);
        return true;
    }

    /**
     * Unix 平台输入流监听
     * @return $this
     */
    protected function listenUnix()
    {
        // 确认是否支持 stty 并记录当前模式
        $mode = Helper::runCommand('stty -g', $code);
        if (0 !== $code) {
            throw new RuntimeException('stdin not support stty');
        }
        // 设置: 关闭中断/退出/挂起字符、无需回车即读取输入(-icanon)、关闭输入显示(-echo)
        Helper::runCommand('stty -isig -icanon -echo');

        // 设置 $stream 为非阻塞
        $stream = STDIN;
        $meta = stream_get_meta_data($stream);
        $blocked = $meta['blocked'] ?? true;
        if ($blocked) {
            stream_set_blocking($stream, false);
        }
        try {
            // 读取输入
            $input = '';
            $heartbeat = $this->heartbeatListener && $this->heartbeatInterval > 0;
            while (!feof($stream)) {
                if (!$this->listening) {
                    break;
                }
                $chr = fread($stream, 8192);
                if ('' !== $chr) {
                    $key = $this->formatUnixKey($chr);
                    if ($key) {
                        // 这里自行获取 ctrl+c 退出信号进行处理，如果使用系统级的退出
                        // 还需要使用 pcntl_signal 监听，以便在退出时恢复 stty 设置
                        // 考虑到该扩展默认是关闭的，PHP不一定安装，所以这里采用这种方式
                        if ('Exit' === $key) {
                            $this->isSigint = true;
                            break;
                        }
                        $this->triggerInput($key, $chr);
                    } else {
                        $input .= $chr;
                    }
                } elseif ('' !== $input) {
                    $this->triggerInput($input, $input);
                    $input = '';
                } elseif (!$this->startTriggered) {
                    $this->triggerStartEvent();
                } elseif ($heartbeat) {
                    $this->triggerHeartbeatInterval();
                }
                // 这里必须 sleep 一下，不然 CPU 飙升
                usleep(100);
            }
        } finally {
            // 恢复 $stream 阻塞, stty 模式
            if ($blocked) {
                stream_set_blocking($stream, true);
            }
            Helper::runCommand('stty '.$mode);
        }
        return $this;
    }

    /**
     * Unix 平台特殊键处理
     * @param string $char
     * @return string|null
     */
    protected function formatUnixKey(string &$char)
    {
        $len = strlen($char);
        $ord = ord($char[0]);
        if (1 === $len) {
            switch ($ord) {
                case 3:
                    return 'Exit';
                case 32:
                    return self::SPACE;
                case 127:
                    $char = '';
                    return self::BACKSPACE;
                case 27:
                    $char = '^[';
                    return self::ESC;
                case 9:
                    return self::TAB;
                case 10:
                    return self::ENTER;
            }
        } elseif (27 === $ord) {
            $code = substr($char, 1);
            $key = static::$specialCodes[$code] ?? null;
            if (null !== $key) {
                $char = '^['.$code;
                return $key;
            }
            if (!$this->inputInvisible) {
                $char = '';
            }
        }
        return null;
    }

    /**
     * 当前是否正在监听中
     * @return bool
     */
    public function isRunning()
    {
        return $this->listening;
    }

    /**
     * 停止监听 -> 执行 $onStop 回调 -> 重启监听 -> 执行 $onStart 回调
     * > 键盘监听是通过监听 stdin 输入实现的，如果某些操作需要读取 stdin,
     * 比如 `Question`,`Item` 的方法，就可以通过该方法暂停键盘监听，读取 stdin 获取输入后重启监听
     * - `$onStop($this)` - 监听停止后的回调，此时可执行需要 stdin 的操作
     * - `$onStart($mixed, $this)` - 重启监听后的回调，$mixed 为 $onStop() 的返回值
     * - pause() 导致的 停止->重启 不会触发 onStart/onStop 回调
     * @param callable $onStop 键盘监听停止后的回调，可在此回调读取 stdin, 执行操作, 返回结果
     * @param ?callable $onStart 重启键盘监听后的回调，参数为 $onStop 的返回值, 可根据返回值进行后续操作
     * @return $this
     */
    public function pause(callable $onStop, callable $onStart = null)
    {
        if (!$this->listening) {
            throw new RuntimeException('Keyboard not running');
        }
        $this->pauseOnStop = $onStop;
        $this->pauseOnStart = $onStart;
        return $this->stop();
    }

    /**
     * 停止监听
     * > 注意：stop() 为非阻塞的，执行该方法后会立即返回，但此时并未真正结束，会处理结束工作，并触发 onStop 回调后真正结束
     * @return $this
     */
    public function stop()
    {
        $this->listening = false;
        return $this;
    }
}
