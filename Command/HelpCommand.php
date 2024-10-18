<?php
namespace Tanbolt\Console\Command;

use Tanbolt\Console\Command;

class HelpCommand extends Command
{
    /** @var string  */
    protected $name = 'help';

    /** @var string  */
    protected $description = 'Displays help for a command';

    /** @var string  */
    protected $parameter = '{command_name=help : Command name}';

    /** @var string */
    protected $help = null;

    /** @var bool  */
    protected $hidden = false;

    /** @var bool  */
    protected $disable = false;

    /** @var Command */
    private $command;

    /**
     * @return void
     */
    public function handle()
    {
        $this->command = $this->console->findCommand($this->input->getArgument('command_name'));
        $this->writeDescription()->writeUsage()->writeArguments()->writeOptions()->writeHelp();
    }

    /**
     * @return $this
     */
    protected function writeDescription()
    {
        if ($description = $this->command->getDescription()) {
            $this->info('Description:', 1)->line('  '.$description, 2);
        }
        return $this;
    }

    /**
     * usage: command [options] [--] <argument_required>  [<argument>]...
     * @return $this
     */
    protected function writeUsage()
    {
        $usage = $this->command->getName();
        if (count($this->command->optionAllowed())) {
            $usage .= ' [options]';
        }
        $arguments = $this->command->argumentAllowed();
        foreach ($arguments as $argument) {
            $argv = '<'.$argument['name'].'>';
            if (!$argument['required']) {
                $argv = '['.$argv.']';
            }
            if ($argument['array']) {
                $argv .= '...';
            }
            $usage .= ' [--] '.$argv;
        }
        $this->info('Usage:', 1)->line('  '.$usage);
        return $this;
    }

    /**
     * @return $this
     */
    protected function writeArguments()
    {
        $items = [];
        $arguments = $this->command->argumentAllowed();
        foreach ($arguments as $argument) {
            $items[$argument['name']] = $this->getRichText($argument);
        }
        if (count($items)) {
            $this->wrap()->info('Arguments:', 1);
            $this->item->write($items, 2);
            $this->wrap();
        }
        return $this;
    }

    /**
     * @return $this
     */
    protected function writeOptions()
    {
        $items = [];
        $options = $this->command->optionAllowed();
        foreach ($options as $option) {
            $name = '--'.$option['name'];
            if (true === $option['requireValue']) {
                $name .= '='.strtoupper($option['name']);
            } elseif (null === $option['requireValue']) {
                $name .= '[='.strtoupper($option['name']).']';
            }
            $items[$name] = $this->getRichText($option);
        }
        if (count($items)) {
            $this->wrap()->info('Options:', 1);
            $this->item->write($items, 2);
            $this->wrap();
        }
        return $this;
    }

    /**
     * @param array $option
     * @return string
     */
    private function getRichText(array $option)
    {
        $text = $option['description'];
        if ($option['default'] !== null) {
            $default = is_array($option['default']) ? '['.implode(',', $option['default']).']' : $option['default'];
            $text .= ('' === $text ? '' : ' ') . '<comment>[default:'.$default.']</comment>';
        }
        if ($option['array']) {
            $text .= ('' === $text ? '' : ' ') . '<comment>(multiple values allowed)</comment>';
        }
        return $text;
    }

    /**
     * @return $this
     */
    protected function writeHelp()
    {
        if ($help = $this->command->getHelp()) {
            $help = '  '.str_replace("\n", "\n  ", str_replace("\r\n", "\n", $help));
            $this->wrap()->info('Help:', 1)->line($help, 1);
        }
        return $this;
    }
}
