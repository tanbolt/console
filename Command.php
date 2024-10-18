<?php
namespace Tanbolt\Console;

use Exception;
use Tanbolt\Console\Exception\RuntimeException;
use Tanbolt\Console\Exception\InvalidArgumentException;

/**
 * Class Command
 * @package Tanbolt\Console
 * @property-read Component\Top $top
 * @property-read Component\Menu $menu
 * @property-read Component\Item $item
 * @property-read Component\Table $table
 * @property-read Component\RichText $richText
 * @property-read Component\Question $question
 * @property-read Component\Progress $progress
 * @property-read Component\Terminal $terminal
 * @property-read Component\Overwrite $overwrite
 */
abstract class Command
{
    /**
     * 命令名称
     * @var string
     */
    protected $name = null;

    /**
     * 命令别名
     * 可以对功能近似的命令通过别名功能用一个文件来实现
     * @var array
     */
    protected $alias = null;

    /**
     * 命令描述
     * @var string
     */
    protected $description = null;

    /**
     * 命令可接受参数
     * ```
     * 格式为 {name: description}, 支持以下类型：
     *
     * {name*: required argument} 必须设置的参数变量
     * {name : optional argument} 可选的参数变量
     * {name=value : optional argument with default value} 可选且有默认值的参数变量，必须在必选变量之后
     * {[name] : optional argument} 可选且允许数组的参数变量,只能放在参数变量的最后一个
     *
     * {--name? : option must be bool value (could not set value)}  不允许设置值的选项变量
     * {--name* : option must set value} 必须设置值的选项变量
     * {--name : optional option} 设置或不设置值都可以的选项变量, 若未设置值, 获取结果为 null
     * {--name=value : optional option with default value} 设置或不设置值都可以的选项变量, 若未设置值, 获取结果为默认值 value
     * {--[name] : option allow multiple values} 接受多个 设置或不设置值都可以的选项变量
     * {--[name*] : option allow multiple values} 接受多个 必须设置值的选项变量
     * ```
     * @var string
     */
    protected $parameter = null;

    /**
     * 命令帮助信息
     * @var string
     */
    protected $help = null;

    /**
     * 是否在隐藏命令
     * @var bool
     */
    protected $hidden = false;

    /**
     * 是否禁用命令
     * @var bool
     */
    protected $disable = false;

    /**
     * 是否接受未定义变量
     * @var bool
     */
    protected $allowUndefined = false;

    /**
     * 控制台对象
     * @var Console
     */
    protected $console;

    /**
     * InputInterface 对象
     * @var InputInterface
     */
    protected $input;

    /**
     * OutputInterface 对象
     * @var OutputInterface
     */
    protected $output;

    /**
     * 解析后的 argument 定义
     * @var array
     */
    private $arguments = [];

    /**
     * 解析后的 option 定义
     * @var array
     */
    private $options = [];

    /**
     * 自定义的格式化输出组件
     * @var array
     */
    private $componentDef = [];

    /**
     * 已实例化的格式化输出组件对象
     * @var array
     */
    private $components = [];

    /**
     * 创建 Command 对象
     * @param ?Console $console
     */
    public function __construct(Console $console = null)
    {
        if ($console) {
            $this->setConsole($console);
        }
    }

    /**
     * 使用当前对象的 Console 创建一个新的命令行组件
     * @return $this
     */
    public function newInstance()
    {
        return new static($this->console);
    }

    /**
     * 设置命令行组件所用的控制台对象
     * @param Console $console
     * @return $this
     */
    public function setConsole(Console $console)
    {
        $this->console = $console;
        return $this;
    }

    /**
     * 重置 Command 对象，会触发 configure() 函数
     * @param ?string $name
     * @return $this
     */
    public function reset(string $name = null)
    {
        if ($name) {
            $this->name = $name;
        }
        $this->setParameter($this->parameter)->configure();
        return $this;
    }

    /**
     * 一般情况下, 创建一个命令行组件只需 extend Command，然后通过指定属性即可完成命令行组件配置。
     * 若有其他的特殊需求，比如命令行组件的属性是动态获取的，就可以通过实现 configure 函数来对命令行组件进行配置
     */
    public function configure()
    {
        // 可使用下面的函数动态设置命令配置，如
        // $this->setName('name')->setDescription('des')->setHelp('help');
    }

    /**
     * 设置命令行组件名称
     * @param ?string $name
     * @return $this
     */
    public function setName(?string $name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * 获取命令行组件名称
     * @return ?string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * 设置命令行组件别名，仅接受数组参数，可同时设置多个
     * @param ?array $alias
     * @return $this
     */
    public function setAlias(?array $alias)
    {
        $this->alias = $alias;
        return $this;
    }

    /**
     * 获取命令行组件别名
     * @return ?array
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * 设置命令行组件描述
     * @param ?string $description
     * @return $this
     */
    public function setDescription(?string $description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * 获取命令行组件描述
     * @return ?string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * 设置命令行组件接受的变量集合，格式参加 $parameter
     * @param ?string $parameter
     * @return $this
     * @see $parameter
     */
    public function setParameter(?string $parameter)
    {
        $this->parameter = $parameter;
        $this->arguments = $this->options = [];
        return $this->parseParameter($parameter);
    }

    /**
     * 追加命令行组件接受的变量集合，格式参加 $parameter
     * @param string $parameter
     * @return $this
     * @see $parameter
     */
    public function addParameter(string $parameter)
    {
        $this->parameter .= "\n".$parameter;
        return $this->parseParameter($parameter);
    }

    /**
     * 获取命令行组件接受的变量集合
     * @return string|null
     */
    public function getParameter()
    {
        return $this->parameter;
    }

    /**
     * 设置命令行组件的帮助信息
     * @param ?string $help
     * @return $this
     */
    public function setHelp(?string $help)
    {
        $this->help = $help;
        return $this;
    }

    /**
     * 获取命令行组件的帮助信息
     * @return ?string
     */
    public function getHelp()
    {
        return $this->help;
    }

    /**
     * 设置在使用控制台 list 命令时是否隐藏该命令行组件
     * @param bool $hidden
     * @return $this
     */
    public function setHidden(bool $hidden = true)
    {
        $this->hidden = $hidden;
        return $this;
    }

    /**
     * 判断当前命令行组件是否为隐藏组件
     * @return bool
     */
    public function isHidden()
    {
        return $this->hidden;
    }

    /**
     * 设置命令行组件是否禁用
     * @param bool $disable
     * @return $this
     */
    public function setDisable(bool $disable = true)
    {
        $this->disable = $disable;
        return $this;
    }

    /**
     * 判断当前命令行组件是否已禁用
     * @return bool
     */
    public function isDisable()
    {
        return $this->disable;
    }

    /**
     * 新增一个可接受的参数变量
     * @param string $name
     * @param string $description
     * @param bool $required
     * @param array|string|null $default
     * @param bool $array
     * @return $this
     */
    public function allowArgument(
        string $name,
        string $description = '',
        bool $required = true,
        $default = null,
        bool $array = false
    ) {
        $this->arguments[$name] = compact('name', 'description', 'required', 'default', 'array');
        $this->parameter .= static::compactParameter(false, $name, $description, $required, $default, $array);
        return $this;
    }

    /**
     * 获取当前所有可接受的参数变量
     * @return array
     */
    public function argumentAllowed()
    {
        return $this->arguments;
    }

    /**
     * 新增一个可接受的选项变量
     * @param string $name
     * @param string $description
     * @param ?bool $requireValue
     * @param array|string|bool|null $default
     * @param bool $array
     * @return $this
     */
    public function allowOption(
        string $name,
        string $description = '',
        bool $requireValue = null,
        $default = null,
        bool $array = false
    ) {
        $this->options[$name] = compact('name', 'description', 'requireValue', 'default', 'array');
        $this->parameter .= static::compactParameter(true, $name, $description, $requireValue, $default, $array);
        return $this;
    }

    /**
     * 获取当前所有可接受的选项变量
     * @return array
     */
    public function optionAllowed()
    {
        return $this->options;
    }

    /**
     * 设置命令行组件是否接受未设置变量
     * @param bool $allow
     * @return $this
     */
    public function allowUndefined(bool $allow = true)
    {
        $this->allowUndefined = $allow;
        return $this;
    }

    /**
     * 判断当前命令行组件是否接受未设置变量
     * @return bool
     */
    public function isAllowUndefined()
    {
        return $this->allowUndefined;
    }

    /**
     * 载入终端的 input output
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return $this
     */
    public function boot(InputInterface $input, OutputInterface $output)
    {
        if ($this->isDisable()) {
            throw new RuntimeException('Command "'.$this->getName().'" is disable.');
        }
        $this->input = $input;
        $this->output = $output;
        foreach ($this->argumentAllowed() as $item) {
            $this->input->allowArgument(
                $item['name'],
                $item['description'],
                $item['required'],
                $item['default'],
                $item['array']
            );
        }
        foreach ($this->optionAllowed() as $item) {
            $this->input->allowOption(
                $item['name'],
                $item['description'],
                $item['requireValue'],
                $item['default'],
                $item['array']
            );
        }
        $this->input->allowUndefined($this->isAllowUndefined())->parseToken();
        return $this;
    }

    /**
     * 执行 Command，该方法在 boot 成功之后调用
     * @return int|void
     */
    abstract public function handle();

    /**
     * 命令行组件启动后，获取当前的命令名称，该命令名称应该是 setName() 设置的名称 或 setAlias() 设置名称的其中一个
     * @return string
     */
    public function getCommand()
    {
        return $this->input->getCommand();
    }

    /**
     * 命令行组件启动后，判断是否存在指定的参数变量
     * @param string $name
     * @return bool
     */
    public function hasArgument(string $name)
    {
        return $this->input->hasArgument($name);
    }

    /**
     * 命令行组件启动后，获取指定的参数变量值
     * @param string|null $name
     * @param null $default
     * @return array|string|null
     */
    public function getArgument(string $name = null, $default = null)
    {
        return $this->input->getArgument($name, $default);
    }

    /**
     * 命令行组件启动后，判断是否存在指定的选项变量
     * @param string $name
     * @return bool
     */
    public function hasOption(string $name)
    {
        return $this->input->hasOption($name);
    }

    /**
     * 命令行组件启动后，获取指定的选项变量值
     * @param $name
     * @param null $default
     * @return array|bool|null
     */
    public function getOption($name = null, $default = null)
    {
        return $this->input->getOption($name, $default);
    }

    /**
     * 输出指定的消息到 stdout
     * @param string $message
     * @return $this
     */
    public function write(string $message)
    {
        $this->output->stdout($message);
        return $this;
    }

    /**
     * 输出一条 普通 信息到 stdout
     * @param string $message
     * @param int $wrap
     * @return $this
     */
    public function line(string $message, int $wrap = 1)
    {
        return $this->writeLine('text', $message, $wrap);
    }

    /**
     * 输出一条 信息类 信息到 stdout
     * @param string $message
     * @param int $wrap
     * @return $this
     */
    public function info(string $message, int $wrap = 0)
    {
        return $this->writeLine('info', $message, $wrap);
    }

    /**
     * 输出一条 注释类 信息到 stdout
     * @param string $message
     * @param int $wrap
     * @return $this
     */
    public function comment(string $message, int $wrap = 0)
    {
        return $this->writeLine('comment', $message, $wrap);
    }

    /**
     * 输出一条 提醒类 信息到 stdout
     * @param string $message
     * @param int $wrap
     * @return $this
     */
    public function notice(string $message, int $wrap = 0)
    {
        return $this->writeLine('notice', $message, $wrap);
    }

    /**
     * 输出一条 警告类 信息到 stdout
     * @param string $message
     * @param int $wrap
     * @return $this
     */
    public function warn(string $message, int $wrap = 0)
    {
        return $this->writeLine('warn', $message, $wrap);
    }

    /**
     * 输出一条 错误类 信息到 stdout
     * @param string $message
     * @param int $wrap
     * @return $this
     */
    public function error(string $message, int $wrap = 0)
    {
        return $this->writeLine('error', $message, $wrap);
    }

    /**
     * 输出一条指定类型的信息到 stdout
     * @param string $type
     * @param string $message
     * @param int $wrap
     * @return $this
     */
    private function writeLine(string $type, string $message, int $wrap = 0)
    {
        $this->ansi($message)->reset(Ansi::getTheme($type))->wrap($wrap)->stdout();
        return $this;
    }

    /**
     * 写入换行符到 stdout
     * @param int $wrap 换行符个数
     * @return $this
     */
    public function wrap(int $wrap = 1)
    {
        if ($wrap) {
            $this->output->stdout(str_repeat(Ansi::EOL, $wrap));
        }
        return $this;
    }

    /**
     * 创建 Ansi 消息，可进而输出到 stdout 或 stderr
     * @param ?string $message
     * @return Ansi
     */
    public function ansi(?string $message)
    {
        return Ansi::instance($this->output)->message($message);
    }

    /**
     * 新增输出组件
     * @param string $name 名称
     * @param string $class class 名
     * @return $this
     */
    public function addComponent(string $name, string $class)
    {
        $name = lcfirst($name);
        $this->componentDef[$name] = $class;
        return $this;
    }

    /**
     * 获取自定义输出组件的 class 名（不包括内置的）
     * @param ?string $name
     * @return array|null
     */
    public function getComponent(string $name = null)
    {
        if ($name) {
            $name = lcfirst($name);
            return array_key_exists($name, $this->componentDef) ? $this->componentDef[$name] : null;
        }
        return $this->componentDef;
    }

    /**
     * 获取格式输出组件的对象实例
     * @param string $name
     * @return mixed
     */
    public function component(string $name)
    {
        if (!isset($this->components[$name])) {
            $this->components[$name] = $this->makeComponent($name);
        }
        return $this->components[$name];
    }

    /**
     * 创建输出组件
     * @param $name
     * @return array|mixed
     */
    private function makeComponent($name)
    {
        if (!($component = $this->getComponent($name)) && !($component = $this->getSysComponent($name))) {
            throw new RuntimeException('Component "'.$name.'" not defined.');
        }
        return new $component($this->input, $this->output, $this);
    }

    /**
     * 是否存在 $name 内置组件
     * @param string $name
     * @return string|null
     */
    private function getSysComponent(string $name)
    {
        $component = __NAMESPACE__ . '\\Component\\' . ucfirst($name);
        return class_exists($component) ? $component : null;
    }

    /**
     * @param $name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->components[$name]) || array_key_exists($name, $this->componentDef) ||
            $this->getSysComponent($name);
    }

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        try {
            return $this->component($name);
        } catch (Exception $e) {
            throw new InvalidArgumentException('Undefined property: '.__CLASS__.'::$'.$name);
        }
    }

    /**
     * @param ?string $parameter
     * @return $this
     */
    private function parseParameter(?string $parameter)
    {
        if (!$parameter || !preg_match_all('#{\s*(.*?)\s*}#', $parameter, $matches)) {
            return $this;
        }
        foreach ($matches[1] as $match) {
            $name = preg_split('#\s+:\s+#', $match, 2);
            $description = count($name) > 1 ? trim($name[1]) : null;
            $name = trim($name[0]);
            $isOption = substr($name, 0, 2) === '--';
            $array = ($isOption ? substr($name, 2, 1) : substr($name, 0, 1)) === '[' && substr($name, -1, 1) === ']';
            if ($isOption) {
                $name = $array ? substr($name, 3, -1) : substr($name, 2);
            } else {
                $name = $array ? substr($name, 1, -1) : $name;
            }
            if (strpos($name, '=')) {
                $required = $isOption ? null : false;
                $name = explode('=', $name);
                $default = !strlen(trim($name[1])) ? null : $name[1];
                $name = trim($name[0]);
                if ($array && $default) {
                    $default = array_map(function($item) {
                        return str_replace('\|', '|', $item);
                    }, preg_split('#(?<!\\\\)\|#', $default));
                }
            } else {
                $default = null;
                $lastBit = substr($name, -1, 1);
                if ('*' === $lastBit) {
                    $name = substr($name, 0, -1);
                    $required = true;
                } elseif ($isOption && '?' === $lastBit) {
                    $name = substr($name, 0, -1);
                    $required = false;
                } else {
                    $required = $isOption ? null : false;
                }
            }
            if ($isOption) {
                $requireValue = $required;
                $this->options[$name] = compact('name', 'description', 'requireValue', 'default', 'array');
            } else {
                $this->arguments[$name] = compact('name', 'description', 'required', 'default', 'array');
            }
        }
        return $this;
    }

    /**
     * @param $options
     * @param $name
     * @param $description
     * @param $required
     * @param null $default
     * @param bool $array
     * @return string
     */
    private static function compactParameter($options, $name, $description, $required, $default = null, bool $array = false)
    {
        if ($default !== null && $array) {
            $default = join('|', array_map(function($item) {
                return str_replace('|', '\|', $item);
            }, is_array($default) ? $default : [$default]));
        }
        if ($default !== null) {
            $name = $name.'='.$default;
        } elseif ($required) {
            $name .= '*';
        } elseif (false === $required && $options) {
            $name .= '?';
        }
        if ($array) {
            $name = '['.$name.']';
        }
        if ($options) {
            $name = '--'.$name;
        }
        if ($description) {
            return sprintf("\n{%s : %s}", $name, $description);
        }
        return "\n$name";
    }
}
