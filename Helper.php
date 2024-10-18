<?php
namespace Tanbolt\Console;

use Tanbolt\Console\Exception\InputException;
use Tanbolt\Console\Exception\OutputException;
use Tanbolt\Console\Exception\InvalidArgumentException;

class Helper
{
    /**
     * 窗口尺寸换成
     * @var array
     */
    private static $terminalSize;

    /**
     * utf-8 首字节 mask
     * @var array
     */
    private static $utfMask = [
        "\xC0" => 2, "\xD0" => 2, "\xE0" => 3, "\xF0" => 4
    ];

    /**
     * 全角，占宽为2的字符正则
     * 在 php 中可参考以下两个链接，但不够直观，其内部进行换算
     * https://git.io/JEDQr | https://git.io/JEDQ6
     * 在 jdk 源码中就比较直观了，还注视了每一段宽字符表示的范围
     * https://git.io/JEDQQ
     * @var string
     */
    private static $widePattern= '/[' .
        '\x{1100}-\x{115F}\x{2329}\x{232A}\x{2E80}-\x{303E}\x{3040}-\x{A4CF}\x{AC00}-\x{D7A3}\x{F900}-\x{FAFF}' .
        '\x{FE10}-\x{FE19}\x{FE30}-\x{FE6F}\x{FF00}-\x{FF60}\x{FFE0}-\x{FFE6}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}' .
    ']/u';

    /**
     * 执行一个命令，返回命令输出，code 为退出码。
     *
     * *注意*：该命令不会处理 STDERR，如果不希望在终端显示 STDERR，
     * 需在执行命令后添加 `2>&1`，将 STDERR 重定向到 STDOUT
     *
     * 可通过 $force 指定运行函数，支持 `1:exec 2:proc_open 3:system 4:passthru 5:shell_exec 6:popen`
     * 通常情况下无需指定（该参数意在单元测试之用），会自动按顺序尝试。另外需注意，在 WIN 系统下，使用 `5,6` 项函数运行时，
     * 若运行失败，返回的 $code 总是为 `1`
     * @param string $command
     * @param null $code
     * @param ?int $force 指定运行函数
     * @return null|string
     */
    public static function runCommand(string $command, &$code = null, int $force = null)
    {
        if (static::checkRunFunction(1, $force, 'exec')) {
            exec($command, $output, $code);
            return join(Ansi::EOL, $output);
        }
        if (static::checkRunFunction(2, $force, ['proc_open', 'proc_close'])) {
            return static::runCommandByProc($command, $code);
        }
        if (static::checkRunFunction(3, $force, 'system')) {
            ob_start();
            system($command, $code);
            return ob_get_clean();
        }
        if (static::checkRunFunction(4, $force, 'passthru')) {
            ob_start();
            passthru($command, $code);
            return ob_get_clean();
        }
        $next = static::checkRunFunction(5, $force, 'shell_exec') ? 5 : (
            static::checkRunFunction(6, $force, ['popen', 'pclose']) ? 6 : 0
        );
        if (!$next) {
            $code = 1;
            return null;
        }
        if ('\\' === DIRECTORY_SEPARATOR) {
            $command .= ' && echo -0 || echo -1';
        } else {
            $command .= ' && echo -$? || echo -$?';
        }
        if (5 === $next) {
            $output = shell_exec($command);
        } else {
            $output = '';
            $handle = popen($command, 'r');
            if ($handle) {
                while (!feof($handle)) {
                    $output .= fread($handle, 8192);
                }
                pclose($handle);
            }
        }
        $output = explode('-', rtrim($output));
        $code = array_pop($output);
        $code = is_numeric($code) ? (int) $code : null;
        return join('-', $output);
    }

    /**
     * 使用 proc_open 函数运行命令
     * @param string $command
     * @param null $code
     * @param bool $throwStderr
     * @return false|string|null
     */
    protected static function runCommandByProc(string $command, &$code = null, bool $throwStderr = false)
    {
        $descriptors = [1 => ["pipe", "w"]];
        if ($throwStderr) {
            $descriptors[2] = ["pipe", "w"];
        }
        $process = proc_open($command, $descriptors, $pipes, null, null, [
            'suppress_errors' => true,
            'bypass_shell' => true
        ]);
        if (!is_resource($process)) {
            $code = 1;
            return null;
        }
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        if ($throwStderr) {
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
        }
        $code = proc_close($process);
        if ($throwStderr && 0 !== $code) {
            throw new InputException($stderr);
        }
        return $stdout;
    }

    /**
     * 校验 runCommand 使用的函数
     * @param int $sort
     * @param ?int $force
     * @param array|string $func
     * @return bool
     */
    protected static function checkRunFunction(int $sort, ?int $force, $func)
    {
        if ($sort === $force) {
            return true;
        }
        if (null !== $force) {
            return false;
        }
        if (!is_array($func)) {
            return function_exists($func);
        }
        foreach ($func as $call) {
            if (!function_exists($call)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 仅包含 字母/数字/下划线/横线
     * @param string $arg
     * @return bool
     */
    public static function isRegularStr(string $arg)
    {
        return (bool) preg_match('/^[\w-]+$/', $arg);
    }

    /**
     * 将字符串转为 argv 数组
     * @param string $str
     * @return array|null
     */
    public static function strToArgv(string $str)
    {
        $dash = '\\' === DIRECTORY_SEPARATOR ? '' : '\\';
        $command = sprintf('%s -r "echo serialize('.$dash.'$_SERVER[\'argv\']);" -- %s', PHP_BINARY, $str);
        if (function_exists('proc_open') && function_exists('proc_close')) {
            $stdout = static::runCommandByProc($command, $code, true);
        } else {
            $stdout = static::runCommand($command, $code);
        }
        $stdout = $stdout ? unserialize($stdout) : null;
        if (is_array($stdout)) {
            array_shift($stdout);
        }
        return $stdout;
    }

    /**
     * 将 argv 数组转为字符串，可通过 $cmd 参数设置最终返回结果的风格
     * - true: 使用 cmd 风格，特殊字符使用双引号包裹（cmd 不支持单引号）
     * - false: 使用 unix 风格，特殊字符使用单引号包裹
     * - null: 根据当前系统自动选择对应风格
     * @param array $args
     * @param ?bool $cmd
     * @return string
     */
    public static function argvToStr(array $args, bool $cmd = null)
    {
        $cmd = null === $cmd ? '\\' === DIRECTORY_SEPARATOR : $cmd;
        $args = array_map(function ($token) use ($cmd) {
            if (preg_match('/^([^=]+)=(.+)/', $token, $match)) {
                // option: 名称正常，仅根据需要对 value 加引号即可; 否则整体加引号处理
                if (static::isRegularStr($match[1])) {
                    $v = static::isRegularStr($match[2]) ? $match[2] : static::escapeToken($match[2], $cmd);
                    return $match[1].'='.$v;
                }
            } elseif (static::isRegularStr($token)) {
                // argument: 无需处理
                return $token;
            }
            // 对 $token 整体处理
            return static::escapeToken($token, $cmd);
        }, array_filter($args));
        return implode(' ', $args);
    }

    /**
     * 处理 arg 参数
     * @param string $token
     * @param bool $cmd
     * @return string
     */
    protected static function escapeToken(string $token, bool $cmd = false)
    {
        // Unix 直接使用 PHP 内置函数, 参数会使用单引号包裹，并转义参数内的单引号字符
        if (!$cmd) {
            return escapeshellarg($token);
        }
        // Win 下的 PHP 内置函数会使用双引号包裹，并将 [", %, !] 替换为空格 ” “，
        // https://stackoverflow.com/a/58519038
        // https://github.com/php/php-src/blob/aca6aefd850d7f25b3730a3990826a25ebf02e37/ext/standard/exec.c#L388
        // 这样做主要是为了防止参数注入，因为这三个字符可以截断命令或调用系统变量
        // 但考虑到 console 模块的使用环境很少也不应该设置未经安全处理的参数，所以这里保留这三个字符
        // (虽然这三个字符在实际使用中出现的几率不是很大)
        $token = str_replace(['"', '%', '!'], ['"""', '"%"', '"!"'], $token);
        if (substr($token, -1) === "\\")  {
            for ($k = 0, $n = strlen($token) - 1; $n >= 0 && substr($token, $n, 1) === "\\"; $k++, $n--);
            if ($k % 2) {
                $token .= "\\";
            }
        }
        return '"'.$token.'"';
    }

    /**
     * 获取默认的 STDOUT 资源对象
     * @return resource
     */
    public static function getStdoutStream()
    {
        $stream = !static::isRunningOS400() ? @fopen('php://stdout', 'wb') : null;
        if (!$stream) {
            $stream = fopen('php://output', 'wb');
        }
        return $stream;
    }

    /**
     * 获取默认的 STDERR 资源对象
     * @return resource
     */
    public static function getStderrStream()
    {
        $stream = !static::isRunningOS400() ? @fopen('php://stderr', 'wb') : null;
        if (!$stream) {
            $stream = fopen('php://output', 'wb');
        }
        return $stream;
    }

    /**
     * Checks if current executing environment is IBM iSeries (OS400), which
     * doesn't properly convert character-encodings between ASCII to EBCDIC.
     * Licensed under the MIT/X11 License (http://opensource.org/licenses/MIT)
     * (c) Fabien Potencier <fabien@symfony.com>
     * @see https://github.com/symfony/console/blob/master/Output/ConsoleOutput.php#L121
     * @return bool
     */
    protected static function isRunningOS400()
    {
        return false !== stripos(PHP_OS, 'OS400') || false !== stripos(getenv('OSTYPE'), 'OS400') ||
            (function_exists('php_uname') && false !== stripos(php_uname('s'), 'OS400'));
    }

    /**
     * 判断 stream 对象是否为文件系统
     * @param resource $stream
     * @return bool
     */
    public static function isFileStream($stream)
    {
        if (is_resource($stream) && 'stream' === get_resource_type($stream)) {
            $stat = fstat($stream);
            return ($stat['mode'] & 0170000) === 0100000;
        }
        return false;
    }

    /**
     * 判断 stream 是否为 tty 终端
     * @param resource $stream
     * @return bool
     */
    public static function isTtyStream($stream)
    {
        return is_resource($stream) && 'stream' === get_resource_type($stream) && stream_isatty($stream);
    }

    /**
     * 复制一个 stream 对象
     * @param resource $stream
     * @return resource|null
     */
    public static function cloneStream($stream)
    {
        if (!is_resource($stream)) {
            return null;
        }
        $meta = stream_get_meta_data($stream);
        $uri = $meta['uri'] ?? null;
        if (null === $uri) {
            return null;
        }
        $mode = $meta['mode'] ?? 'rb';
        $seekable = $meta['seekable'] ?? false;
        $cloneStream = fopen($uri, $mode);
        if (true === $seekable && !in_array($mode, ['r', 'rb', 'rt'])) {
            $offset = ftell($stream);
            rewind($stream);
            stream_copy_to_stream($stream, $cloneStream);
            fseek($stream, $offset);
            fseek($cloneStream, $offset);
        }
        return $cloneStream;
    }

    /**
     * 写入内容到 $stream 中
     * @param resource $stream
     * @param string $message
     * @return bool
     */
    public static function writeStream($stream, string $message)
    {
        if (strlen($message)) {
            if (false === @fwrite($stream, $message)) {
                throw new OutputException('Unable to write output.');
            }
            fflush($stream);
            return true;
        }
        return false;
    }

    /**
     * 判断资源对象是否支持修饰
     * @param mixed $stream
     * @return bool
     */
    public static function supportColor($stream)
    {
        // https://no-color.org/
        if (false !== getenv('NO_COLOR')) {
            return false;
        }
        // https://babun.github.io/
        // https://hyper.is/
        if (false !== getenv('BABUN_HOME') || 'Hyper' === getenv('TERM_PROGRAM')) {
            return true;
        }
        if ('\\' === DIRECTORY_SEPARATOR) {
            return ($stream && function_exists('sapi_windows_vt100_support') && @sapi_windows_vt100_support($stream))
                || false !== getenv('ANSICON')
                || 'ON' === getenv('ConEmuANSI')
                || stripos(getenv('TERM'), 'xterm') === 0;
        }
        return static::isTtyStream($stream);
    }

    /**
     * windows term.exe 路径
     * @return string
     */
    public static function winTermPath()
    {
        $exe = realpath(__DIR__.'/Bin/term.exe');
        // handle code running from a phar
        if ('phar:' === substr(__FILE__, 0, 5)) {
            $tmpExe = sys_get_temp_dir().DIRECTORY_SEPARATOR.'term.exe';
            copy($exe, $tmpExe);
            $exe = $tmpExe;
        }
        return $exe;
    }

    /**
     * 获取终端的尺寸
     * > This file is part of the Symfony package.
     * (c) Fabien Potencier <fabien@symfony.com>
     * @see https://github.com/symfony/console/blob/master/Terminal.php
     * @param int $direction  0 => [width=> int, height => int], 1 => width, 2 => height
     * @param bool $fresh 是否获取实时数据, 否则将使用上一次获取的缓存数据
     * @return int|array
     */
    public static function terminalSize(int $direction = 0, bool $fresh = false)
    {
        if ($fresh || !self::$terminalSize) {
            $width = $height = null;
            if ('\\' === DIRECTORY_SEPARATOR) {
                if (preg_match('/^(\d+)x(\d+)(?: \((\d+)x(\d+)\))?$/', trim(getenv('ANSICON')), $matches)) {
                    // extract [w, H] from "wxh (WxH)"
                    // or [w, h] from "wxh"
                    $width = (int) $matches[1];
                    $height = isset($matches[4]) ? (int) $matches[4] : (int) $matches[2];
                } elseif ($termSize = trim(static::runCommand(sprintf('%s 2>&1', static::winTermPath())))) {
                    if (false !== strpos($termSize, "\n")) {
                        $termSize = explode("\n", $termSize);
                        $width = (int) $termSize[0];
                        $height = (int) $termSize[1];
                    }
                }
            } elseif ($sttyString = static::runCommand('stty -a 2>&1 | grep columns')) {
                if (preg_match('/rows.(\d+);.columns.(\d+);/i', $sttyString, $matches)) {
                    // extract [w, h] from "rows h; columns w;"
                    $width = (int) $matches[2];
                    $height = (int) $matches[1];
                } elseif (preg_match('/;.(\d+).rows;.(\d+).columns/i', $sttyString, $matches)) {
                    // extract [w, h] from "; h rows; w columns"
                    $width = (int) $matches[2];
                    $height = (int) $matches[1];
                }
            }
            if (!$width) {
                $width = 80;
            }
            if (!$height) {
                $height = 50;
            }
            self::$terminalSize = compact('width', 'height');
        }
        return $direction > 1 ? self::$terminalSize['height'] : (
            $direction > 0 ? self::$terminalSize['width'] : self::$terminalSize
        );
    }

    /**
     * 获取富文本的纯文本, 即去除了所有标签之后的字符串
     * @param string $text
     * @return string
     */
    public static function getPureText(string $text)
    {
        return '' === $text ? '' : preg_replace('/<[^>]*>/', '', $text);
    }

    /**
     * 将 $str 中的 crlf(\r\n) 转为 lf(\n) [可选：最后再将 \n 转为 Ansi::EOF]
     * @param string|null $str
     * @param bool $eof 换行符最终是否转为 Ansi::EOF
     * @return string
     */
    public static function crlfToLf(?string $str, bool $eof = false)
    {
        if (null === $str) {
            return null;
        }
        $str = str_replace("\r\n", "\n", $str);
        if (!$eof || "\n" === Ansi::EOL) {
            return $str;
        }
        return str_replace("\n", Ansi::EOL, $str);
    }

    /**
     * 获取字符个数，多字节字符可以被正确统计
     * @param string $str
     * @return int
     */
    public static function strCount(string $str)
    {
        if ('' === $str) {
            return 0;
        }
        static $iconvLenFunc = 0;
        if (0 === $iconvLenFunc) {
            $iconvLenFunc = function_exists('mb_strlen') ? 3 : (
                function_exists('iconv_strlen') ? 2 : (function_exists('utf8_decode') ? 1 : 0)
            );
        }
        if (3 === $iconvLenFunc) {
            return mb_strlen($str);
        }
        if (2 === $iconvLenFunc) {
            return iconv_strlen($str);
        }
        if (1 === $iconvLenFunc) {
            return strlen(utf8_decode($str));
        }
        $i = 0;
        $j = 0;
        $len = strlen($str);
        while ($i < $len) {
            $u = $str[$i] & "\xF0";
            $i += self::$utfMask[$u] ?? 1;
            ++$j;
        }
        return $j;
    }

    /**
     * 将字符串转为数组，每一个字符为一项，多字节字符会被正确分割
     * @param string $str
     * @return string[]
     */
    public static function strSplit(string $str)
    {
        static $hasMbSplit = null;
        if (null === $hasMbSplit) {
            $hasMbSplit = function_exists('mb_str_split');
        }
        if ($hasMbSplit) {
            $chars = mb_str_split($str, 1, 'UTF-8');
        } else {
            $chars = preg_split('/(.)/us', $str, null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        }
        return $chars;
    }

    /**
     * 获取单行字符串宽度
     * > unicode 多字节字符中，比如 ⭑ (U+2B51) ⭐ (U+2B50)，二者是相邻字符；
     * 但后者在大部分系统中有对应的 emoji 表情符号，所以在输出时，前者为字符串（宽度1），后者为图形（宽度2）。
     * > 但该函数并未对 emoji 字符进行修正，因为 emoji 在不同系统支持度不一定相同，此类字符统一当作宽度 1，
     * 所以在输出内容时，如果与窗口尺寸有关，最好不使用 emoji 符号
     * @param string $str
     * @return int
     */
    public static function strWidth(string $str)
    {
        if ('' === $str) {
            return 0;
        }
        static $hasMbString = null;
        if (null === $hasMbString) {
            $hasMbString = function_exists('mb_strwidth');
        }
        // "\t" (Tab) 制表符号输出宽度为 8
        $str = str_replace("\t", '        ', $str);
        if ($hasMbString) {
            return mb_strwidth($str, 'UTF-8');
        }
        $str = preg_replace(self::$widePattern, '', $str, -1, $wide);
        return ($wide << 1) + static::strCount($str);
    }

    /**
     * 获取多行字符串宽度
     * @param string $str
     * @return int
     */
    public static function sectionWidth(string $str)
    {
        return max(array_map('static::strWidth', explode("\n", static::crlfToLf($str))));
    }

    /**
     * 获取 str 在终端输出后最终的占用行数
     * - 占用行数不能简单的统计换行符，在终端窗口尺寸较小时，较长的单行内容会自动换行显示为多行
     * - str 参数必须时经过处理的，换行符为 `Ansi::EOF`，可通过 `crlfToLf(str, true)` 函数进行处理，
     *   该函数不会自动处理，因为参数传入前已经过处理的话，这里再次处理就有点多余了。
     * - 计算占用行数与窗口宽度有关，$fresh 可以强制每次都获取最新的窗口宽度以防窗口尺寸发生变化，否则使用上次获取的缓存。
     * @param string|null $str
     * @param bool $fresh
     * @return int
     */
    public static function sectionLines(?string $str, bool $fresh = false)
    {
        if (null === $str) {
            return 0;
        }
        $lines = 0;
        $str = explode(Ansi::EOL, $str);
        $terminalWidth = Helper::terminalSize(1, $fresh);
        foreach ($str as $val) {
            $lines += ceil(static::strWidth($val) / $terminalWidth) ?: 1;
        }
        return $lines;
    }

    /**
     * 获取优化过的字符段落, 会根据设置将超长字符串自动换行
     * @param string $str 字符串
     * @param int $maxWidth 最大宽度
     * @param ?string $filled 每一行都使用该字符 填充至 $maxWidth 宽度
     * @param string $position 若开启填充，可设置文字位置，支持 left/center/right
     * @return string
     */
    public static function getChapter(string $str, int $maxWidth = 80, string $filled = null, string $position = 'left')
    {
        return join(Ansi::EOL, static::getChapterArr($str, $maxWidth, $filled, $position));
    }

    /**
     * 获取优化过的字符段落, 会根据设置将超长字符串自动换行, 返回数组
     * @param string $str 字符串
     * @param int $maxWidth 最大宽度
     * @param ?string $filled 每一行都使用该字符 填充至 $maxWidth 宽度
     * @param string $position 若开启填充，可设置文字位置，支持 left/center/right
     * @return array
     */
    public static function getChapterArr(string $str, int $maxWidth = 80, string $filled = null, string $position = 'left')
    {
        if ($filled && static::strWidth($filled) > 1) {
            throw new InvalidArgumentException('filled str width not 1');
        }
        $sections = explode("\n", static::crlfToLf($str));
        if ($maxWidth < 1) {
            return $sections;
        }
        $chapters = [];
        foreach ($sections as $section) {
            // 保留空行
            if ('' === $section) {
                $chapters[] = $filled ? str_repeat($filled, $maxWidth) : '';
                continue;
            }
            // 非空行
            $length = 0;
            $string = null;
            $chars = static::strSplit($section);
            foreach ($chars as $char) {
                $len = static::strWidth($char);
                $lenTry = $length + $len;
                if ($lenTry > $maxWidth) {
                    if ($string !== null) {
                        $chapters[] = static::fillStr($string, $length, $maxWidth, $position, $filled);
                    }
                    $length = $len;
                    $string = $char;
                } else {
                    $length = $lenTry;
                    $string = null === $string ? $char : $string . $char;
                }
            }
            if ($string) {
                $chapters[] = static::fillStr($string, $length, $maxWidth, $position, $filled);
            }
        }
        return $chapters;
    }

    /**
     * 填充字符到指定长度
     * @param string $str
     * @param int $strLen
     * @param int $width
     * @param string $position
     * @param string|null $filled
     * @return string
     */
    private static function fillStr(string $str, int $strLen, int $width, string $position = 'left', string $filled = null)
    {
        if (!$filled || $strLen >= $width) {
            return $str;
        }
        $fillLen = $width - $strLen;
        if ('center' === $position) {
            $left = floor($fillLen / 2);
            $right = $fillLen - $left;
            return str_repeat($filled, $left).$str.str_repeat($filled, $right);
        }
        $fill = str_repeat($filled, $fillLen);
        return ('right' === $position ? $fill : '').$str.('left' === $position ? $fill : '');
    }

    /**
     * 解析富文本字符串，返回解析后的数组，普通字符串为 string，变量为 array
     * ```
     * <tag> str_a %var% str_b </tag>
     * $arr = [
     *      '<tag> str_a ',
     *      [
     *          'var'
     *          ''
     *          ''
     *      ],
     *      'str_b </tag>'
     * ]
     *
     * <tag> str_a %prev{var}next% str_b </tag>
     * $arr = [
     *      '<tag> str_a ',
     *      [
     *          'var'
     *          'prev'
     *          'next'
     *      ],
     *      'str_b </tag>'
     * ]
     * ```
     *
     * 提供变量值，通过 sprintfMessageContainer 可获得最终的组合字符串
     * ```
     * 若 var 的值不存在，如 sprintfMessageContainer($arr)
     * 返回
     * <tag> str_a str_b </tag>
     * <tag> str_a str_b </tag>
     *
     * 若 var 的值存在，如 sprintfMessageContainer($arr, ['var' => '_foo_'])
     * 返回
     * <tag> str_a str_b </tag>
     * <tag> str_a prev_foo_next str_b </tag>
     * ```
     *
     * 总结：
     * 可以在富文本中使用 %var% 或 %{var}% 表示变量， %var% 较为容易理解, 即变量值,
     * %{var}% 可以在花括号 {} 两边提供静态字符串, 这些字符串只有在 var 值存在的情况下才会被返回
     *
     * @param string $message
     * @return array
     */
    public static function getMessageContainer(string $message)
    {
        // 格式化换行符, 提取处理变量字符
        $splitPos = 0;
        $container = [];
        $message = static::crlfToLf($message, true);
        preg_match_all('#%([^%]*)%#i', $message, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as $key => $match) {
            $pos = $match[1];
            if ($pos - $splitPos) {
                $container[] = substr($message, $splitPos, $pos - $splitPos);
            }
            $tag = $matches[1][$key][0];
            if (preg_match('#^([a-z\-_]+)$#i', $tag)) {
                $tags = [$tag, '', ''];
            } elseif (preg_match('#{([a-z\-_]+)(?::([^}]+))?}#i', $tag, $keys, PREG_OFFSET_CAPTURE)) {
                $pre = $keys[0][1] ? substr($tag, 0, $keys[0][1]) : '';
                $nxt = substr($tag, $keys[0][1] + strlen($keys[0][0])) ?: '';
                $tags = [$keys[1][0], $pre, $nxt];
            } else {
                $tags = $tag;
            }
            $container[] = $tags;
            $splitPos = $pos + strlen($match[0]);
        }
        $container[] = substr($message, $splitPos);
        return $container;
    }

    /**
     * 通过已解析的富文本数组，和变量值，返回最终字符串 （参见 getMessageContainer 注释）
     * @param array $container 富文本数组
     * @param callable|array $data 变量值数组 或 提供变量值的回调函数
     * @return string
     * @see getMessageContainer
     */
    public static function sprintfMessageContainer(array $container, $data = [])
    {
        if (is_callable($data)) {
            $callback = $data;
        } else {
            $callback = null;
            $data = (array) $data;
        }
        $messages = [];
        foreach ($container as $item) {
            if (null === ($key = is_array($item) ? $item[0] : null)) {
                $messages[] = $item;
                continue;
            }
            if ($callback) {
                $value = call_user_func($callback, $key);
            } else {
                $value = array_key_exists($key, $data) ? $data[$key] : false;
            }
            if (null !== $value && false !== $value) {
                if ($item[1]) {
                    $messages[] = $item[1];
                }
                $messages[] = $value;
                if ($item[2]) {
                    $messages[] = $item[2];
                }
            }
        }
        return implode('', $messages);
    }

    /**
     * 通过富文本 和 变量值，获取最终字符串
     * @param string $message
     * @param callable|array $data
     * @return string
     */
    public static function sprintfMessage(string $message, $data = [])
    {
        return static::sprintfMessageContainer(static::getMessageContainer($message), $data);
    }

    /**
     * 通过秒数格式化时长
     * @param int $secs
     * @return string
     */
    public static function formatTime(int $secs)
    {
        if ($secs > 3600) {
            return (floor($secs / 360) / 10) . ' hour';
        } elseif ($secs > 60) {
            return floor($secs / 60) . ' min';
        }
        return $secs . ' sec';
    }

    /**
     * 格式化内存使用大小, 若不指定, 则获取当前使用的内存大小
     * @param ?float $memory
     * @return string
     */
    public static function formatMemory(float $memory = null)
    {
        if (null === $memory) {
            $memory = memory_get_usage(true);
        }
        if ($memory >= 1073741824) {
            return sprintf('%.1fG', $memory / 1073741824);
        } elseif ($memory >= 1048576) {
            return sprintf('%.1fM', $memory / 1048576);
        } elseif ($memory >= 1024) {
            return sprintf('%dK', $memory / 1024);
        }
        return sprintf('%dB', $memory);
    }
}
