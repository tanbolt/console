<?php
namespace Tanbolt\Console;

use Exception;
use Throwable;
use ErrorException;
use ReflectionClass;
use ReflectionException;
use Tanbolt\Console\Exception\InputException;
use Tanbolt\Console\Exception\RuntimeException;

/**
 * Class Console: 控制台命令中心
 *
 * -----------------------------------------------------------------------------------------------
 * 1、通过 Input,Output 处理命令，匿名函数中的异常和警告会得到合适的处理
 * ```
 * $console->runClosure(function(InputInterface $input, OutputInterface $output) {
 *      // do something
 *      // Input 用来获取输入参数, Output 用来输出内容
 * }, InputInterface $input, OutputInterface $output)
 * ```
 *
 * -----------------------------------------------------------------------------------------------
 * 2、实现一个 Command 类并运行（在 Command 内部可以获取输入，设置输出，运行中异常和警告将以原生 PHP 的方式抛出）
 * ```
 * $console->runCommand(Command $command, InputInterface $input, OutputInterface $output)
 * ```
 *
 * -----------------------------------------------------------------------------------------------
 * 3、由 Console 中已添加的 Command 自动适配并运行命令，运行中异常和警告将以原生 PHP 的方式抛出
 * ```
 * $console->runConsole(InputInterface $input, OutputInterface $output)
 * ```
 *
 * -----------------------------------------------------------------------------------------------
 * 4、由 Console 中已添加的 Command 自动适配并运行命令，并合适的处理异常和警告
 * ```
 * $console->getResult(InputInterface $input, OutputInterface $output)
 *
 * // 等价于
 * $console->runClosure(function(InputInterface $input, OutputInterface $output) {
 *    $console->runConsole($input, $output)
 * }, InputInterface $input, OutputInterface $output)
 * ```
 *
 * -----------------------------------------------------------------------------------------------
 * 5、运行指定 Command 命令，合适的处理异常和警告
 * ```
 * $console->runClosure(function(InputInterface $input, OutputInterface $output) {
 *    $console->runCommand(Command $command, $input, $output)
 * }, InputInterface $input, OutputInterface $output)
 * ```
 *
 * @package Tanbolt\Console
 */
class Console
{
    const DEBUG = 1;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $version;

    /**
     * @var string
     */
    private $stdoutSubject;

    /**
     * @var string
     */
    private $stderrSubject;

    /**
     * @var Command[]
     */
    private $commandsInstance = [];

    /**
     * @var Command[]
     */
    private $commandsBridge = [];

    /**
     * @var bool
     */
    private $catchException = true;

    /**
     * @var bool
     */
    private $catchStrict = false;

    /**
     * @var bool
     */
    private $hasException = false;

    /**
     * @var array
     */
    private $warnings = [];

    /**
     * @var callable|null
     */
    private $exceptionHandler;

    /**
     * @var callable|null
     */
    private $warningHandler;

    /**
     * @var callable
     */
    private $inputHandler;

    /**
     * @var callable
     */
    private $outputHandler;

    /**
     * 获取 InputInterface 对象的 debug 等级
     * @param InputInterface|null $input 不设置则从 argv 获取 --debug 参数
     * @return int
     */
    public static function getDebugLevel(InputInterface $input = null)
    {
        if ($input) {
            if ($input->hasTokenOption('debug')) {
                $debug = $input->getTokenOption('debug');
                return true === $debug ? self::DEBUG : (int) $debug;
            }
            return self::DEBUG;
        }
        $argv = $_SERVER['argv'];
        array_shift($argv);
        foreach ($argv as $item) {
            if (strpos($item, '--debug') === 0) {
                if (strlen($item) === 7) {
                    return self::DEBUG;
                }
                if (substr($item, 7, 1) === '=') {
                    $debug = substr($item, 8);
                    $debug = is_numeric($debug) && strlen($debug) === 1 ? $debug : null;
                    if ($debug !== null) {
                        return (int) $debug;
                    }
                }
            }
        }
        return self::DEBUG;
    }

    /**
     * 创建 Console 对象
     * @param ?string $name
     * @param ?string $version
     */
    public function __construct(string $name = null, string $version = null)
    {
        $this->setName($name)->setVersion($version)->addDefaultCommand();
    }

    /**
     * 设置默认支持的命令，可继承当前对象，通过覆盖该方法进行重置或扩展
     * @return $this
     */
    protected function addDefaultCommand()
    {
        return $this->addCommand([
            __NAMESPACE__ . '\\Command\\HelpCommand',
            __NAMESPACE__ . '\\Command\\ListCommand'
        ]);
    }

    /**
     * 设置控制台名称 (用于信息显示)
     * @param ?string $name
     * @return $this
     */
    public function setName(?string $name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * 获取控制台名称
     * @return ?string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * 设置控制台版本 (用于信息显示)
     * @param ?string $version
     * @return $this
     */
    public function setVersion(?string $version)
    {
        $this->version = $version;
        return $this;
    }

    /**
     * 获取控制台版本
     * @return ?string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * 获取控制台标题 （由控制台名称和版本组成）
     * @return string
     */
    public function getTitle()
    {
        if ($this->name || $this->version) {
            if ($this->name) {
                return $this->name . ($this->version ? ' '.$this->version : '');
            }
            return 'Unknown Application '.$this->version;
        }
        return 'Console Tool';
    }

    /**
     * 设置创建 InputInterface 对象的方法，默认自动创建 Input 对象
     * @param ?callable $handler
     * @return $this
     */
    public function resolveInput(?callable $handler)
    {
        $this->inputHandler = $handler;
        return $this;
    }

    /**
     * 设置创建 OutputInterface 对象的方法，默认自动创建 Output 对象
     * @param ?callable $handler
     * @return $this
     */
    public function resolveOutput(?callable $handler)
    {
        $this->outputHandler = $handler;
        return $this;
    }

    /**
     * 同时创建 InputInterface, OutputInterface 对象
     * @param InputInterface|null $input
     * @param OutputInterface|null $output
     */
    protected function resolveStd(InputInterface &$input = null, OutputInterface &$output = null)
    {
        if (!$input) {
            $input = $this->inputHandler ? call_user_func($this->inputHandler) : new Input();
        }
        if (!$output) {
            $output = $this->outputHandler ? call_user_func($this->outputHandler) : new Output();
        }
    }

    /**
     * 设置是否记录程序异常
     * @param bool $boolean
     * @return $this
     */
    public function catchException(bool $boolean = true)
    {
        $this->catchException = $boolean;
        return $this;
    }

    /**
     * 当前是否记录程序异常
     * @return bool
     */
    public function isCatchException()
    {
        return $this->catchException;
    }

    /**
     * 设置需要记录的异常是否为严格模式 (即 warning 等一般性异常是否需要需要记录)
     * @param bool $strict
     * @return $this
     */
    public function catchStrict(bool $strict = true)
    {
        $this->catchStrict = $strict;
        return $this;
    }

    /**
     * 当前是否记录一般异常
     * @return bool
     */
    public function isCatchStrict()
    {
        return $this->catchStrict;
    }

    /**
     * 设置发生异常的回调函数
     * @param ?callable $handler
     * @return $this
     */
    public function onException(?callable $handler)
    {
        $this->exceptionHandler = $handler;
        return $this;
    }

    /**
     * 设置发生警告的回调函数
     * @param ?callable $handler
     * @return $this
     */
    public function onWarning(?callable $handler)
    {
        $this->warningHandler = $handler;
        return $this;
    }

    /**
     * 通过 OutputInterface 对象输出可读性更高的致命异常
     * @param OutputInterface $output
     * @param Throwable $exception
     * @param bool $showTrace
     * @return $this
     */
    public function outputException(OutputInterface $output, Throwable $exception, bool $showTrace = false)
    {
        $output->stderr(Ansi::EOL);
        if ($this->stderrSubject) {
            $output->stderr($this->stderrSubject.Ansi::EOL);
        }
        $ansi = Ansi::instance($output);
        $ansi->wrap()->bold()->red()->stderr(get_class($exception) . ':')
            ->reset()->wrap()->stderr('  '.$exception->getMessage())
            ->reset()->wrap()->green()->stderr('  '.$exception->getFile().':'.$exception->getLine());
        if ($showTrace) {
            $traces = $exception->getTrace();
            array_unshift($traces, [
                'function' => '',
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'args' => [],
            ]);
            $ansi->reset()->wrap()->bold()->red()->stderr(Ansi::EOL.'Stack trace:');
            foreach ($traces as $key => $trace) {
                // file
                $file = null;
                if (isset($trace['file'])) {
                    $file = $trace['file'];
                    if (isset($trace['line'])) {
                        $file .= ':'.$trace['line'];
                    }
                }
                // class
                $class = $trace['class'] ?? '';
                $class .= $trace['type'] ?? '';
                $class .= $trace['function'] ?? '';
                $ansi->reset()->yellow()->stderr('  #'.$key)
                    ->reset()->wrap($file ? 0 : 1)->stderr(' '.$class . '() '.($file ? 'at ' : ''));
                if ($file) {
                    $ansi->reset()->wrap()->green()->stderr($file);
                }
            }
        }
        $output->stderr(Ansi::EOL);
        $this->hasException = true;
        return $this;
    }

    /**
     * 通过 OutputInterface 对象输出可读性更高的警告信息
     * @param OutputInterface $output
     * @param Throwable[] $exceptions
     * @return $this
     */
    public function outputWarning(OutputInterface $output, array $exceptions = [])
    {
        $warnings = [];
        foreach ($exceptions as $exception) {
            if ($exception instanceof Exception || $exception instanceof Throwable) {
                $warnings[] = $exception;
            }
        }
        if (!count($warnings)) {
            return $this;
        }
        $ansi = Ansi::instance($output);
        $ansi->wrap()->bold()->red()->stderr(($this->hasException ? '' : Ansi::EOL) . 'PHP Notice:');
        /** @var Exception $warning */
        $key = 1;
        foreach ($warnings as $warning) {
            $message = $warning->getMessage();
            $file = $warning->getFile();
            if (empty($message) && empty($file)) {
                continue;
            }
            $ansi->reset()->yellow()->stderr('  #'.$key);
            if ($message) {
                $output->stderr(' '.($file ? $message.' at ' : $message));
            }
            if ($file) {
                if ($line = $warning->getLine()) {
                    $file .= ':'.$line;
                }
                $ansi->reset()->green()->stderr($file);
            }
            $output->stderr(Ansi::EOL);
            $key++;
        }
        $output->stderr(Ansi::EOL);
        return $this;
    }

    /**
     * 通过 Closure,InputInterface,OutputInterface 作为参数，返回 Closure 回调函数的返回值。
     * - 参数为已创建 Input,Output 对象, 或自己实现的 InputInterface,OutputInterface 类
     * - 若不指定这两个参数，会自动创建 Input,Output 作为对象
     *
     * 回调函数的实现应该为
     * ```
     * $callback(InputInterface $input, OutputInterface $output) {
     *   return int;
     * }
     * ```
     * 处理完毕后应返回退出控制台的 code 值，在处理期间若发送异常，并不会以原生 php 的方式直接抛出。
     * 会根据 catchException,catchStrict 设置，获取符合等级的异常或警告;
     * 并将其发送给 onException,onWarning 设置的异常处理函数，
     * 若未设置异常处理函数，默认使用 outputException,outputWarning 进行处理
     *
     * @param callable $callback 回调函数
     * @param InputInterface|null $input
     * @param OutputInterface|null $output
     * @return int
     * @throws Throwable
     */
    public function runClosure(callable $callback, InputInterface $input = null, OutputInterface $output = null)
    {
        $this->resolveStd($input, $output);
        $warnings = $this->warnings;
        $this->warnings = [];
        $errorHandler = $this->handleError($input);
        $e = null;
        try {
            $code = call_user_func($callback, $input, $output);
        } catch (Throwable $e) {
            $code = $this->handleException($input, $output, $e);
        }
        if (!$e) {
            $this->outputWarning(
                $output,
                $this->warningHandler ? call_user_func($this->warningHandler, $this->warnings, $this) : $this->warnings
            );
        }
        $code = min(255, (int) $code);
        if ($errorHandler) {
            set_error_handler($errorHandler);
        }
        $this->warnings = $warnings;
        return $code;
    }

    /**
     * handle runClosure() 过程中的错误
     * @param InputInterface|null $input
     * @return ?callable
     * @throws ErrorException
     */
    protected function handleError(InputInterface $input = null)
    {
        if (!$this->catchException || !static::getDebugLevel($input)) {
            return null;
        }
        return set_error_handler(function($code, $message, $file = '', $line = 0) {
            if (error_reporting() & $code) {
                $e = new ErrorException($message, 0, $code, $file, $line);
                if ($this->catchStrict) {
                    throw $e;
                } else {
                    $this->warnings[] = $e;
                }
            }
        });
    }

    /**
     * handle $this->runClosure() 过程中的异常
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param $e
     * @return int
     */
    protected function handleException(InputInterface $input, OutputInterface $output, $e)
    {
        if (!$this->isCatchException()) {
            throw $e;
        }
        $ws = $this->warningHandler ? call_user_func($this->warningHandler, $this->warnings, $this) : $this->warnings;
        $e = $this->exceptionHandler ? call_user_func($this->exceptionHandler, $e, $this) : $e;
        $this->outputException($output, $e, static::getDebugLevel($input) === 2)->outputWarning($output, $ws);
        if ($e instanceof Exception || $e instanceof Throwable) {
            return (is_numeric($code = $e->getCode()) && ($code = (int) $code) !== 0) ? $code : 255;
        }
        return 255;
    }

    /**
     * 添加控制台可用命令
     * ```
     * addCommand(Command|CommandClassName) //通过 Command 对象 或 类名添加一个 Command
     * ```
     *
     * 同时添加多个 Command
     * ```
     * addCommand([
     *      Command,  //通过 Command 对象 添加命令
     *      CommandString,  //通过 Command 类名 添加命令
     *      namespace => dir,  //通过相同的 namespace 前缀 和 类所在文件夹添加一组命令
     * ])
     * ```
     * @param array|string $command
     * @return $this
     */
    public function addCommand($command)
    {
        $this->commandsBridge = [];
        if (!is_array($command)) {
            $command = [$command];
        }
        foreach ($command as $key => $val) {
            if (!is_int($key) && is_string($val)) {
                $this->addCommandByGroup($key, $val);
            } else {
                $this->addCommandByName($val);
            }
        }
        foreach ($this->commandsBridge as $command) {
            if ($name = $command->getName()) {
                $this->commandsInstance[$name] = $command;
            }
            $alias = $command->getAlias();
            if (!$alias) {
                continue;
            }
            $names = array_merge([$name], $alias);
            foreach ($alias as $alia) {
                if(($key = array_search($alia, $names)) !== false) {
                    $currentAlias = $names;
                    unset($currentAlias[$key]);
                    $this->commandsInstance[$alia] = $this->configureCommand($command->newInstance(), $alia);
                }
            }
        }
        $this->commandsBridge = [];
        ksort($this->commandsInstance);
        return $this;
    }

    /**
     * 添加测试命令到控制台的可用命令
     * @return $this
     */
    public function addTestCommand()
    {
        return $this->addCommand([__NAMESPACE__.'\\Demo' => __DIR__.'/Demo']);
    }

    /**
     * 添加单个控制台命令
     * @param $name
     * @return Console
     */
    protected function addCommandByName($name)
    {
        if ($name instanceof Command) {
            $this->commandsBridge[] = $this->configureCommand($name);
        } else {
            $this->pushCommand((string) $name);
        }
        return $this;
    }

    /**
     * 添加一组控制台命令
     * @param string $namespace
     * @param string $dir
     * @return $this
     */
    protected function addCommandByGroup(string $namespace, string $dir)
    {
        $names = [];
        if (function_exists('glob')) {
            foreach (glob(realpath($dir) . DIRECTORY_SEPARATOR . '*.[pP][hH][pP]') as $file) {
                $names[] = substr(basename($file), 0, -4);
            }
        } elseif ($handle = opendir($dir)) {
            while (false !== ($file = readdir($handle))) {
                if (strtolower(substr($file, -4)) === '.php') {
                    $names[] = substr($file, 0, -4);
                }
            }
            closedir($handle);
        }
        foreach ($names as $name) {
            $this->pushCommand($namespace . '\\' . $name);
            try {
                $this->pushCommand($namespace . '\\' . $name);
            } catch (Throwable $e) {
                // do nothing
            }
        }
        return $this;
    }

    /**
     * 将一个控制台命令, 处理并放到当前可用命令的集合中
     * @param Command|string $command
     * @return $this
     */
    protected function pushCommand($command)
    {
        try {
            $reflection = new ReflectionClass($command);
        } catch (ReflectionException $e) {
            throw new RuntimeException('Command "'.$command.'" not found');
        }
        if (!$reflection->isInstantiable()) {
            throw new RuntimeException('Command "'.$command.'" is not instantiable');
        }
        if (!$reflection->isSubclassOf(__NAMESPACE__.'\\Command')) {
            throw new RuntimeException('Command "'.$command.'" is not context of "'.__NAMESPACE__.'\\Command'.'"');
        }
        $this->commandsBridge[] = $this->configureCommand(new $command());
        return $this;
    }

    /**
     * 配置控制台命令的基本可用项
     * @param Command $command
     * @param ?string $name
     * @return Command
     */
    protected function configureCommand(Command $command, string $name = null)
    {
        return $command->setConsole($this)->reset($name)
            ->allowOption('version', 'Display this application version', false)
            ->allowOption('help', 'Display this help message', false)
            ->allowOption('subject', 'Display command subject', false)
            ->allowOption('no-subject', 'Do not display command subject', false)
            ->allowOption('ansi', 'Force ANSI output (default output)', false)
            ->allowOption('no-ansi', 'Disable ANSI output (colorful output)', false)
            ->allowOption('no-ask', 'Do not ask any interactive question', false)
            ->allowOption('quiet', 'Do not ask or output any message', false)
            ->allowOption('debug', 'Set verbosity of debug (0,1,2)', null, self::DEBUG)
        ;
    }

    /**
     * 获取所有可用的控制台命令
     * @return Command[]
     */
    public function allCommand()
    {
        return $this->commandsInstance;
    }

    /**
     * 通过关键字查找匹配控制台命令
     * @param string $name
     * @return Command
     */
    public function findCommand(string $name)
    {
        $commands = $this->allCommand();
        $error = 'Command "'.$name.'" is not defined';
        if (array_key_exists($name, $commands)) {
            return $commands[$name];
        }
        $suggest = $this->suggestCommand($name);
        if ($count = count($suggest)) {
            if ($count > 1) {
                $error .= "\n\nDid you mean one of these?\n    ";
            } else {
                $error .= "\n\nDid you mean this?\n    ";
            }
            $error .= implode("\n    ", $suggest);
        }
        throw new InputException($error);
    }

    /**
     * 获取 $name 相关的推荐命令
     * @param string $name
     * @return array
     */
    protected function suggestCommand(string $name)
    {
        $finds = [];
        if (strpos($name, ':')) {
            $finds = explode(':', $name, 2);
        } else {
            $finds[] = $name;
        }
        $suggest = [];
        $commands = array_keys($this->allCommand());
        foreach ($finds as $find) {
            if (!($find = trim($find))) {
                continue;
            }
            foreach ($commands as $command) {
                if (strpos($command, $find) !== false) {
                    $suggest[] = $command;
                }
            }
        }
        return $suggest;
    }

    /**
     * 在 $output 中追加换行符
     * @param OutputInterface $output
     * @return $this
     */
    protected function eolCommand(OutputInterface $output)
    {
        if (!$output->isStdoutEmpty()) {
            $output->stdout(Ansi::EOL);
        }
        return $this;
    }

    /**
     * 通过指定的 $command 运行命令
     * - 可以使用已创建 Input,Output对象, 或自己实现的 InputInterface, OutputInterface 对象
     * - 同时 InputInterface, OutputInterface 参数也可省略，会自动创建 Input,Output对象
     * @param Command $command
     * @param ?InputInterface $input
     * @param ?OutputInterface $output
     * @return int
     * @throws Throwable
     */
    public function runCommand(Command $command, InputInterface $input = null, OutputInterface $output = null)
    {
        $this->resolveStd($input, $output);
        if ($command->boot($input, $output) && $this->stdoutSubject) {
            $output->stdout($this->stdoutSubject.Ansi::EOL);
        }
        try {
            $result = $command->handle();
            $this->eolCommand($output);
            return $result;
        } catch (Throwable $e) {
            $this->eolCommand($output);
            throw $e;
        }
    }

    /**
     * 通过已添加的 Command 自动匹配合适的控制台命令并运行
     * @param ?InputInterface $input
     * @param ?OutputInterface $output
     * @return int
     * @throws Throwable
     */
    public function runConsole(InputInterface $input = null, OutputInterface $output = null)
    {
        $this->resolveStd($input, $output);

        // interaction
        if ($input->hasTokenOption('quiet')) {
            $input->setInteraction(false);
            $output->setQuiet();
        } elseif ($input->hasTokenOption('no-ask')) {
            $input->setInteraction(false);
        }

        // ansi
        if ($key = $input->lastTokenOption('ansi', 'no-ansi')) {
            $output->setColorful('ansi' === key($key));
        }

        // set subject
        $stdoutSubject = $stderrSubject = false;
        if ($subject = $input->lastTokenOption('subject', 'no-subject')) {
            $subject = key($subject);
        }
        if ('subject' === $subject) {
            $stdoutSubject = $stderrSubject = true;
        } elseif (!$subject) {
            if (!$output->isStdoutTty()) {
                $stdoutSubject = true;
            }
            if (!$output->isStderrTty()) {
                $stderrSubject = true;
            }
        }
        if ($stdoutSubject || $stderrSubject) {
            $subject = sprintf('[%s] Command: %s', date('Y-m-d H:i:s'), (string) $input);
            if ($stdoutSubject) {
                $this->stdoutSubject = $subject;
            }
            if ($stderrSubject) {
                $this->stderrSubject = $subject;
            }
        }

        // version
        if ($input->hasTokenOption('version')) {
            Ansi::instance($output)->wrap()->green()->stdout($this->getTitle());
            return 0;
        }

        /** @var Command $command */
        if (!($name = $input->getCommand())) {
            $command = $this->commandsInstance['list'];
        } else {
            $command = $this->findCommand($name);
            if ($input->hasTokenOption('help')) {
                $input->initialize(['help', $command->getName()]);
                $command = $this->commandsInstance['help'];
            }
        }
        $code = $this->runCommand($command, $input, $output);
        if (!is_numeric($code)) {
            $code = 0;
        }
        return $code;
    }

    /**
     * 该函数功能为 exit($this->getResult($input, $output)),
     * 运行完成后直接使用 getResult 获取到的 code 值退出
     * @param ?InputInterface $input
     * @param ?OutputInterface $output
     * @throws Throwable
     */
    public function run(InputInterface $input = null, OutputInterface $output = null)
    {
        exit($this->getResult($input, $output));
    }

    /**
     * 使用 $this->runConsole 作为匿名函数运行 $this->runClosure, 返回 $this->runInput 的退出 code
     * 基本等同于 $this->runConsole, 但获得了异常处理的功能
     * @param ?InputInterface $input
     * @param ?OutputInterface $output
     * @return int
     * @throws Throwable
     */
    public function getResult(InputInterface $input = null, OutputInterface $output = null)
    {
        return $this->runClosure(function(InputInterface $input, OutputInterface $output) {
            $this->runConsole($input, $output);
        }, $input, $output);
    }
}
