<?php
namespace Tanbolt\Console\Component;

use Tanbolt\Console\Command;
use Tanbolt\Console\InputInterface;
use Tanbolt\Console\OutputInterface;
use Tanbolt\Console\Exception\InvalidArgumentException;

/**
 * Class AbstractStyle
 * @package Tanbolt\Console\Component
 * @property-read Top $top
 * @property-read Item $item
 * @property-read Menu $menu
 * @property-read Table $table
 * @property-read RichText $richText
 * @property-read Question $question
 * @property-read Progress $progress
 * @property-read Terminal $terminal
 * @property-read Overwrite $overwrite
 */
abstract class AbstractStyle
{
    /** @var InputInterface */
    protected $input;

    /** @var OutputInterface  */
    protected $output;

    /** @var ?Command  */
    protected $command;

    /** @var bool */
    private $isTty;

    /** @var array */
    private $components = [];

    /** @var array */
    private static $supportComponent = [
        'item', 'overwrite', 'progress', 'question',
        'richText', 'table', 'terminal', 'top'
    ];

    /**
     * AbstractStyle constructor.
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param ?Command $command
     */
    public function __construct(InputInterface $input, OutputInterface $output, Command $command = null)
    {
        $this->input = $input;
        $this->output = $output;
        $this->command = $command;
    }

    /**
     * 使用当前 input/output 创建一个新对象
     * > 输出组件可能是有状态的，内部会存储一些状态值，在大部分情况下，执行完一个命令后会结束进程，组件被释放。
     * 但有些时候，可能同一个组件在一次运行中会多次使用，互相之间会因为内部状态导致冲突，此时通过该方法创建新的对象就十分有必要了。
     * @return $this
     */
    public function instance()
    {
        return new static($this->input, $this->output, $this->command);
    }

    /**
     * 判断 input/output 是否都为 tty 终端
     * @return bool
     */
    protected function isTty()
    {
        if (null === $this->isTty) {
            $this->isTty = $this->input->isInteraction() && $this->output->isStdoutTty();
        }
        return $this->isTty;
    }

    /**
     * @param $name
     * @return bool
     */
    public function __isset($name)
    {
        return $this->command ? isset($this->command->{$name}) : in_array($name, self::$supportComponent);
    }

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        if ($this->command) {
            return $this->command->{$name};
        }
        if (!in_array($name, self::$supportComponent)) {
            throw new InvalidArgumentException('Undefined property: '.__CLASS__.'::$'.$name);
        }
        if (!isset($this->components[$name])) {
            $class = __NAMESPACE__ . '\\' . ucfirst($name);
            $this->components[$name] =  new $class($this->input, $this->output);
        }
        return $this->components[$name];
    }
}
