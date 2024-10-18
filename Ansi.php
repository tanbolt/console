<?php
namespace Tanbolt\Console;

use BadMethodCallException;
use Tanbolt\Console\Exception\OutputException;

/**
 * Class Ansi
 * @package Tanbolt\Console
 * @method $this bold(bool $enable = true) 是否加粗文字
 * @method $this dim(bool $enable = true) 是否使用暗淡模式
 * @method $this italic(bool $enable = true) 是否斜体文字（未广泛支持）
 * @method $this underline(bool $enable = true) 是否给文字添加下划线（未广泛支持）
 * @method $this blinking(bool $enable = true) 是否文字闪烁（未广泛支持）
 * @method $this strikethrough(bool $enable = true) 是否添加删除线（未广泛支持）
 *
 * @method $this black() 设置文字为：黑色
 * @method $this red() 设置文字为：红色
 * @method $this green() 设置文字为：绿色
 * @method $this yellow() 设置文字为：黄色
 * @method $this blue() 设置文字为：蓝色
 * @method $this magenta() 设置文字为：品红色
 * @method $this cyan() 设置文字为：青色
 * @method $this white() 设置文字为：白色
 *
 * @method $this bgBlack() 设置背景为：黑色
 * @method $this bgRed() 设置背景为：红色
 * @method $this bgGreen() 设置背景为：绿色
 * @method $this bgYellow() 设置背景为：黄色
 * @method $this bgBlue() 设置背景为：蓝色
 * @method $this bgMagenta() 设置背景为：品红色
 * @method $this bgCyan() 设置背景为：青色
 * @method $this bgWhite() 设置背景为：白色
 */
class Ansi
{
    // 定义换行符
    // 默认不使用 PHP_EOL，统一使用  LF (\n) 模式,
    // 目前几乎所有系统都支持, 这样可以保证在切换运行系统时，程序的兼容性
    const EOL = "\n";
    // const EOL = PHP_EOL;

    const COLOR_BLACK = 'black';
    const COLOR_RED = 'red';
    const COLOR_GREEN = 'green';
    const COLOR_YELLOW = 'yellow';
    const COLOR_BLUE = 'blue';
    const COLOR_MAGENTA = 'magenta';
    const COLOR_CYAN = 'cyan';
    const COLOR_WHITE = 'white';

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var ?string
     */
    private $message;

    /**
     * @var array
     */
    private $style = [];

    /**
     * ansi 文字颜色
     * colorName => [color, background, brightColor, brightBackground]
     * 恢复 color/background 的代码为 [39, 49]
     * @var array
     */
    private static $colors = [
        self::COLOR_BLACK => [30, 40, 90, 100],
        self::COLOR_RED => [31, 41, 91, 101],
        self::COLOR_GREEN => [32, 42, 92, 102],
        self::COLOR_YELLOW => [33, 43, 93, 103],
        self::COLOR_BLUE => [34, 44, 94, 104],
        self::COLOR_MAGENTA => [35, 45, 95, 105],
        self::COLOR_CYAN => [36, 46, 96, 106],
        self::COLOR_WHITE => [37, 47, 97, 107],
    ];

    /**
     * ansi 文字格式
     * @see https://zh.wikipedia.org/wiki/ANSI%E8%BD%AC%E4%B9%89%E5%BA%8F%E5%88%97
     * @var int[][]
     */
    private static $modifier = [
        'bold' => [1, 22],
        'dim' => [2, 22],
        'italic' => [3, 23],
        'underline' => [4, 24],
        'blinking' => [5, 25],
        'strikethrough' => [9, 29],
    ];

    /**
     * 缺省主题
     * @var array
     */
    private static $theme = [
        'text' => [],
        'b' => [
            'bold' => true,
        ],
        'i' => [
            'italic' => true,
        ],
        'info' => [
            'color' => self::COLOR_YELLOW
        ],
        'comment' => [
            'color' => self::COLOR_GREEN
        ],
        'notice' => [
            'color' => self::COLOR_RED
        ],
        'warn' => [
            'color' => self::COLOR_WHITE,
            'background' => self::COLOR_YELLOW
        ],
        'error' => [
            'color' => self::COLOR_WHITE,
            'background' => self::COLOR_RED
        ],
        'question' => [
            'color' => Ansi::COLOR_CYAN,
        ],
    ];

    /**
     * 获取所有支持的颜色值
     * @return string[]
     */
    public static function colors()
    {
        return array_keys(static::$colors);
    }

    /**
     * 获取所有支持的格式
     * @return string[]
     */
    public static function modifier()
    {
        return array_keys(static::$modifier);
    }

    /**
     * 设置当前主题
     * @param array $theme
     */
    public static function setTheme(array $theme)
    {
        static::$theme = $theme;
    }

    /**
     * 获取当前的主题
     * @param ?string $type
     * @return array
     */
    public static function getTheme(string $type = null)
    {
        return null === $type ? static::$theme : (static::$theme[$type] ?? null);
    }

    /**
     * 创建一个 Ansi 实例对象
     * @param OutputInterface|null $output
     * @return static
     */
    public static function instance(OutputInterface $output = null)
    {
        return new static($output);
    }

    /**
     * Ansi constructor.
     * @param OutputInterface|null $output
     */
    public function __construct(OutputInterface $output = null)
    {
        $this->bindOutput($output);
    }

    /**
     * 绑定 Output 对象
     * @param OutputInterface|null $output
     * @return $this
     */
    public function bindOutput(OutputInterface $output = null)
    {
        $this->output = $output;
        return $this;
    }

    /**
     * 设置消息内容
     * @param ?string $message
     * @return $this
     */
    public function message(?string $message)
    {
        $this->message = $message;
        return $this;
    }

    /**
     * 清空并重置所有格式，支持以下属性
     * ```
     * $style = [
     *     // 布尔值，支持：bold, dim, italic, underline,
     *     // blinking, strikethrough, bright, bgBright
     *    'bold' => true,
     *    ....
     *
     *    // 颜色值, 支持 Ansi::COLOR_*
     *    'color' => Ansi::COLOR_RED,
     *    'background' => Ansi::COLOR_BLUE,
     *
     *    // 在结尾追加的换行符数目
     *    'wrap' => 1,
     * ]
     * ```
     * @param ?array $style
     * @return $this
     */
    public function reset(?array $style = [])
    {
        $this->style = $style ?: [];
        return $this;
    }

    /**
     * 设置文字颜色
     * @param ?string $color
     * @return $this
     */
    public function color(?string $color)
    {
        $this->style['color'] = $color;
        return $this;
    }

    /**
     * 文字颜色是否使用 Bright 模式
     * @param bool $bright
     * @return $this
     */
    public function bright(bool $bright = true)
    {
        $this->style['bright'] = $bright;
        return $this;
    }

    /**
     * 设置文字背景颜色
     * @param ?string $color
     * @return $this
     */
    public function background(?string $color)
    {
        $this->style['background'] = $color;
        return $this;
    }

    /**
     * 文字背景颜色是否使用 Bright 模式
     * @param bool $bgBright
     * @return $this
     */
    public function bgBright(bool $bgBright = true)
    {
        $this->style['bgBright'] = $bgBright;
        return $this;
    }

    /**
     * 设置消息后追加的换行数
     * @param int $line
     * @return $this
     */
    public function wrap(int $line = 1)
    {
        $this->style['wrap'] = $line;
        return $this;
    }

    /**
     * 获取当前 Ansi 的 style 数组
     * @return array
     */
    public function getStyle()
    {
        return $this->style;
    }

    /**
     * 获取 $message 经过 ANSI 修饰后的字符串
     * @param ?string $message 参数为 null 则使用 message() 方法设置的内容
     * @param bool $decorate 参数为 false, 则不应用格式，仅在消息后追加所设置的换行符
     * @return string
     */
    public function getDecorated(?string $message = null, bool $decorate = true)
    {
        $message = null === $message ? $this->message : $message;
        if (!strlen($message)) {
            return '';
        }
        $wrap = 0;
        $set = $unset = [];
        $styles = $this->style;
        if (isset($styles['wrap'])) {
            $wrap = (int) $styles['wrap'];
            unset($styles['wrap']);
        }
        if ($decorate && count($styles)) {
            foreach (static::$modifier as $name => $item) {
                if (isset($styles[$name]) && $styles[$name]) {
                    $set[] = $item[0];
                    $unset[] = $item[1];
                }
            }
            if (isset($styles['color']) && isset(static::$colors[$styles['color']])) {
                $bright = $styles['bright'] ?? false;
                $item = static::$colors[$styles['color']];
                $set[] = $bright ? $item[2] : $item[0];
                $unset[] = 39;
            }
            if (isset($styles['background']) && isset(static::$colors[$styles['background']])) {
                $bright = $styles['bgBright'] ?? ($styles['bgbright'] ?? false);
                $item = static::$colors[$styles['background']];
                $set[] = $bright ? $item[3] : $item[1];
                $unset[] = 49;
            }
        }
        return (count($set) ? sprintf("\x1b[%sm%s\x1b[%sm",
            implode(';', $set), $message, implode(';', $unset)
        ) : $message).($wrap > 0 ? str_repeat(self::EOL, $wrap) : '');
    }

    /**
     * 将已设置的消息输出到 output stdout，会自动根据 stdout 是否支持 ansi 进行输出
     * @param ?string $message 参数为 null 则使用 message() 方法设置的内容
     * @return $this
     */
    public function stdout(?string $message = null)
    {
        if (!$this->output) {
            throw new OutputException('Output is not defined');
        }
        $this->output->stdout($this->getDecorated($message, $this->output->isStdoutDecorated()));
        return $this;
    }

    /**
     * 将已设置的消息输出到 output stderr，会自动根据 stderr 是否支持 ansi 进行输出
     * @param ?string $message 参数为 null 则使用 message() 方法设置的内容
     * @return $this
     */
    public function stderr(?string $message = null)
    {
        if (!$this->output) {
            throw new OutputException('Output is not defined');
        }
        $this->output->stderr($this->getDecorated($message, $this->output->isStderrDecorated()));
        return $this;
    }

    /**
     * 将已设置的消息输出到指定的 stream resource, 参数 $decorate 支持以下设置
     * - null: 自动判断 stream 是否支持 ansi 字符用以决定是否输出有格式的文字
     * - true: 强制输出有格式的文字
     * - false: 强制输出无格式的文字
     * @param resource $stream
     * @param ?string $message
     * @param ?bool $decorate
     * @return $this
     */
    public function writeTo($stream, ?string $message = null, bool $decorate = null)
    {
        if (!is_resource($stream) || 'stream' !== get_resource_type($stream)) {
            throw new OutputException('The first argument needs a stream resource.');
        }
        Helper::writeStream(
            $stream,
            $this->getDecorated($message, null === $decorate ? Helper::supportColor($stream) : $decorate)
        );
        return $this;
    }

    /**
     * 文字格式/颜色 快捷方法
     * @param string $name
     * @param array $arguments
     * @return $this
     */
    public function __call(string $name, array $arguments)
    {
        if (isset(static::$modifier[$name])) {
            $this->style[$name] = !count($arguments) || $arguments[0];
        } elseif (isset(static::$colors[$name])) {
            $this->style['color'] = $name;
        } elseif (0 === strpos($name, 'bg') && isset(static::$colors[$name = strtolower(substr($name, 2))])) {
            $this->style['background'] = $name;
        } else {
            throw new BadMethodCallException("Method $name does not exist.");
        }
        return $this;
    }
}
