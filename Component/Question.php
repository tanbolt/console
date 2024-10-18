<?php
namespace Tanbolt\Console\Component;

use Throwable;
use Tanbolt\Console\Ansi;
use Tanbolt\Console\Helper;
use Tanbolt\Console\OutputInterface;
use Tanbolt\Console\Exception\InputException;
use Tanbolt\Console\Exception\RuntimeException;
use Tanbolt\Console\Exception\InvalidArgumentException;

/**
 * Class Question：无状态组件
 * > 需要 input 输入进行交互，若 input->interaction=false, 会跳过交互，直接返回 default 答案
 * @package Tanbolt\Console\Component
 */
class Question extends AbstractStyle
{
    /**
     * 输出一个问题，返回用户输入内容
     * > $question 为 null，则直接切换至用户输入状态。若不满意默认的问题样式，可利用该特性自行输出
     * @param ?string $question 问题
     * @param ?string $default 默认答案
     * @param int $wrap 问题末尾的空白换行数
     * @return ?string
     */
    public function ask(?string $question, string $default = null, int $wrap = 0)
    {
        return $this->getAnswer($question, $default, $wrap);
    }

    /**
     * 输出一个问题，返回输入内容 (输入内容支持换行)
     * > $question 为 null，则直接切换至用户输入状态。若不满意默认的问题样式，可利用该特性自行输出
     * @param ?string $question 问题
     * @param ?string $default 默认答案
     * @param int $wrap 问题末尾的空白换行数
     * @return ?string
     */
    public function askMulti(?string $question, string $default = null, int $wrap = 0)
    {
        return $this->getAnswer($question, $default, $wrap, true);
    }

    /**
     * 输出一个问题，返回输入内容 (输入内容不可见，通常用于密码输入)
     * > $question 为 null，则直接切换至用户输入状态。若不满意默认的问题样式，可利用该特性自行输出
     * @param ?string $question 问题
     * @param ?string $default 默认答案
     * @param int $wrap 问题末尾的空白换行数
     * @return ?string
     */
    public function secret(?string $question, string $default = null, int $wrap = 0)
    {
        return $this->getAnswer($question, $default, $wrap, false, true);
    }

    /**
     * 发送一个是非问题, 用户仅需 Y|N 回答，返回用户的选择
     * > $question 为 null，则直接切换至用户输入状态。若不满意默认的问题样式，可利用该特性自行输出
     * @param ?string $question 问题
     * @param bool $default 默认答案, true=Y, false=N
     * @return bool
     */
    public function confirm(?string $question, bool $default = true)
    {
        $ansi = Ansi::instance($this->output);
        if (strlen($question)) {
            $style = Ansi::getTheme('question');
            $ansi->reset($style);
            $ansi->stdout($question.' [')
                ->reset(Ansi::getTheme('text'))->stdout($default ? 'Y/n' : 'y/N')
                ->reset($style)->stdout(']:');
        }
        if (!$this->isTty()) {
            $ansi->reset()->black()->bright()->wrap()->stdout('[default:'.($default ? 'Y' : 'N').']');
            return $default;
        }
        $answer = $this->getInput();
        if (null !== $answer) {
            if (preg_match('/^y/i', $answer)) {
                return true;
            } elseif (preg_match('/^n/i', $answer)) {
                return false;
            }
        }
        return $default;
    }

    /**
     * 输出一个 $question (为 null 则不输出)，将控制台转为输入模式, 并返回用户输入的内容
     * @param ?string $question
     * @param ?string $default
     * @param int $wrap
     * @param bool $secret
     * @param bool $multi
     * @return string|null
     */
    protected function getAnswer(
        ?string $question, string $default = null, int $wrap = 0,
        bool $multi = false, bool $secret = false
    ) {
        $multi = !$secret && $multi;
        $ansi = Ansi::instance($this->output);
        if (strlen($question)) {
            $style = Ansi::getTheme('question');
            $ansi->reset($style)->stdout($question);
            $extra = [];
            if (null !== $default) {
                $extra[] = 'default:'.$default;
            }
            if ($multi) {
                $extra[] = 'submit:ctrl-'.('\\' === DIRECTORY_SEPARATOR ? 'z' : 'd');
            }
            if ($extra) {
                $ansi->reset(Ansi::getTheme('text'))->wrap($wrap)->stdout('['.join(', ', $extra).']');
            }
            if (!$wrap) {
                $ansi->reset($style)->stdout(':');
            }
        }
        if (!$this->isTty()) {
            $ansi->reset()->black()->bright()->wrap()->stdout('[default:'.(null === $default ? 'NULL' : $default).']');
            return $default;
        }
        return $this->getInput($default, $multi, $secret);
    }

    /**
     * 获取输入
     * @param string|null $default
     * @param bool $multi
     * @param bool $secret
     * @return bool|string|null
     */
    protected function getInput(string $default = null, bool $multi = false, bool $secret = false)
    {
        // 针对 WIN 平台的 unicode 字符, 进行编码设置
        $cp = $oem = null;
        if (function_exists('sapi_windows_cp_set')) {
            $cpp = sapi_windows_cp_get();
            $oem = sapi_windows_cp_get('oem');
            if ($cpp !== $oem) {
                $cp = $cpp;
                sapi_windows_cp_set($oem);
            }
        }
        $stream = $this->input->getStream();
        if ($secret) {
            $answer = static::getHiddenAnswer($stream, $this->output);
            $this->output->stdout(Ansi::EOL);
        } elseif ($multi) {
            $answer = static::getMultilineAnswer($stream);
            $this->output->stdout(Ansi::EOL);
        } else {
            $answer = fgets($stream, 4096);
            if (false === $answer) {
                throw new InputException('Aborted');
            }
            $answer = self::trimEndBreak($answer);
        }
        $hasAnswer = strlen($answer);
        // 还原 WIN 平台编码设置
        if (null !== $cp) {
            sapi_windows_cp_set($cp);
            if ($hasAnswer) {
                $answer = sapi_windows_cp_conv($oem, $cp, $answer);
            }
        }
        return $hasAnswer ? $answer : $default;
    }

    /**
     * 获取密码输入
     * @param resource $inputStream
     * @return bool|string
     */
    protected static function getHiddenAnswer($inputStream, OutputInterface $output)
    {
        // win
        if ('\\' === DIRECTORY_SEPARATOR) {
            $value = Helper::runCommand(sprintf('%s pass', Helper::winTermPath()));
            $output->stdout('');
            if (false !== $pos = strrpos($value, "z")) {
                return substr($value, 0, $pos);
            }
            return static::trimEndBreak($value);
        }

        // stty
        $mode = Helper::runCommand('stty -g', $code);
        if (0 !== $code) {
            return false;
        }
        Helper::runCommand('stty -echo');
        $value = fgets($inputStream, 4096);
        Helper::runCommand('stty '.$mode);
        if (false === $value) {
            throw new InputException('Aborted');
        }
        return static::trimEndBreak($value);
    }

    /**
     * 获取多行输入
     * @param $stream
     * @return string|null
     */
    protected static function getMultilineAnswer($stream)
    {
        $stream = Helper::cloneStream($stream);
        if (!$stream) {
            return null;
        }
        $answer = '';
        while (false !== ($char = fgetc($stream))) {
            $answer .= $char;
        }
        return static::trimEndBreak($answer);
    }

    /**
     * 去除结尾换行符
     * @param string $str
     * @return string
     */
    protected static function trimEndBreak(string $str)
    {
        if ("\r\n" === substr($str, -2)) {
            return substr($str, 0, -2);
        }
        if ("\n" === substr($str, -1)) {
            return substr($str, 0, -1);
        }
        return $str;
    }

    /**
     * 发送一个单选问题, 用户仅需从备选答案中选择，返回用户所选 `[key => val]`
     * > $question 为 null，则直接切换至用户输入状态（若不满意默认的问题样式，可利用该特性自行输出）
     * @param ?string $question 问题
     * @param string[] $options 备选答案数组
     * @param ?string $default 缺省答案键值，如 `0`
     * @return ?array
     * @throws Throwable
     */
    public function radio(?string $question, array $options = [], string $default = null)
    {
        return $this->writeList($question, $options, $default, true);
    }

    /**
     * 发送一个多选问题，用户仅需从备选答案中选择，返回用户所选 `[key => val, ...]`
     * > $question 为 null，则直接切换至用户输入状态（若不满意默认的问题样式，可利用该特性自行输出）
     * @param ?string $question 问题
     * @param string[] $options 备选答案数组
     * @param array|null $default 缺省答案键值，如 `[0,2]`
     * @return ?array
     * @throws Throwable
     */
    public function choice(?string $question, array $options = [], array $default = null)
    {
        return $this->writeList($question, $options, $default);
    }

    /**
     * 输出选项，并返回所选答案
     * @param ?string $question
     * @param array $options
     * @param array|string|int|null $default
     * @param bool $radio
     * @return ?array
     * @throws Throwable
     */
    protected function writeList(?string $question, array $options, $default = null, bool $radio = false)
    {
        if (!count($options)) {
            return null;
        }
        // 先处理 $default 值，判断所设置缺省值是否都在 $options 中
        if (null !== $default) {
            $def = $radio ? [$default] : $default;
            $default = array_intersect_key($options, array_flip($def));
            if (count($default) !== count($def)) {
                throw new InvalidArgumentException('Default value "'.implode(',', $def).'" is invalid');
            }
        }
        // 输出问题
        if (strlen($question)) {
            Ansi::instance($this->output)->reset(Ansi::getTheme('question'))->wrap()->stdout($question);
        }
        // 获取结果
        return $this->getOptionsInput(Ansi::instance($this->output), $options, $radio, $default);
    }

    /**
     * 输出选项，返回输入的答案
     * @param Ansi $ansi
     * @param array $options
     * @param bool $radio
     * @param array|null $default
     * @return ?array
     * @throws Throwable
     */
    protected function getOptionsInput(Ansi $ansi, array $options, bool $radio, ?array $default)
    {
        // 输出选项
        $keys = [];
        $index = 1;
        $keyLen = strlen(count($options));
        $style = ['color' => Ansi::COLOR_GREEN];
        foreach ($options as $key => $option) {
            $keys[$key] = $index;
            $ansi->reset()->stdout(str_repeat(' ', $keyLen - strlen($index)).'[')
                ->reset($style)->stdout($index)
                ->reset()->wrap()->stdout('] '.$option);
            $index++;
        }
        $defaultKeys = $default ? join(',', array_intersect_key($keys, $default)) : null;
        if (!$this->isTty()) {
            $ansi->reset()->black()->bright()->wrap()->stdout('[default:'.($defaultKeys ?: 'NULL').']');
            return $default;
        }
        if ($defaultKeys) {
            $help = ' (Enter to use default:'.$defaultKeys.')';
        } else {
            $help = ' (ex: '.($radio ? '1' : '1,2').')';
        }
        $ansi->reset()->black()->bright()->wrap()->stdout('Input choice number'.$help);
        // 获取结果
        return $this->validateInput(function($answer) use ($options, $radio, $default) {
            if (null === $answer) {
                return $default;
            }
            $choices = [];
            $len = count($options);
            $keys = array_keys($options);
            $select = array_values($options);
            $values = $radio ? [$answer] : explode(',', $answer);
            foreach ($values as $value) {
                $value = trim($value);
                $int = ctype_digit($value) && $value[0] > 0 ? (int) $value - 1 : null;
                if (null === $int || $int < 0 || $int > $len) {
                    throw new InvalidArgumentException('Value "'.$value.'" is invalid, please input again');
                }
                $choices[$keys[$int]] = $select[$int];
            }
            return $choices;
        });
    }

    /**
     * 将控制台转为输入模式，并通过 $verification(?string $input) 回调函数验证输入内容
     * - 若 $verification 回调函数抛出异常，会自动提示用户再次输入，直到用户输入内容通过 $verification 的验证
     * - $attempts 设置验证失败的最大次数,超过次数后不再提示继续输入，而是抛出异常,设置为 null 代表不限次数
     * - $verification 若抛出 Tanbolt\Console\Exception\RuntimeException 异常，将不再提示用户继续输入，而是直接抛出异常信息
     * @param callable $verification 验证回调
     * @param bool $multi 是否允许多行文字
     * @param bool $secret 是否隐藏输入
     * @param ?int $attempts 验证失败的最大次数
     * @return mixed
     * @throws Throwable
     */
    public function validateInput(callable $verification, bool $multi = false, bool $secret = false, int $attempts = null)
    {
        if (!$this->isTty()) {
            return null;
        }
        if (null !== $attempts && $attempts < 1) {
            throw new RuntimeException('Maximum number of attempts must be a positive value.');
        }
        /** @var Throwable $exception */
        $exception = null;
        $this->output->stdout('>');
        $ansi = Ansi::instance($this->output)->red()->wrap();
        while (null === $attempts || $attempts--) {
            if (null !== $exception) {
                $this->output->stdout(Ansi::EOL);
                $ansi->stdout(' '.$exception->getMessage());
                $this->output->stdout('>');
            }
            try {
                return call_user_func($verification, $this->getInput(null, $multi, $secret));
            } catch (InputException | RuntimeException $e) {
                throw $e;
            } catch (Throwable $exception) {
            }
        }
        throw $exception;
    }
}
