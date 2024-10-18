<?php
namespace Tanbolt\Console\Command;

use Tanbolt\Console\Command;

class ListCommand extends Command
{
    /** @var string  */
    protected $name = 'list';

    /** @var string  */
    protected $description = 'List commands';

    /** @var string  */
    protected $parameter = '{keyword= : Command contains word}';

    /**
     * @return void
     */
    public function handle()
    {
        $this->writeUsage()->writeOptions()->writeCommands($this->input->getArgument('keyword'));
    }

    /**
     * usage: command [options] [--] <argument_required>  [<argument>]...
     * @return $this
     */
    protected function writeUsage()
    {
        return $this->info('Usage:', 1)->line('  command [options] [arguments]');
    }

    /**
     * @return $this
     */
    protected function writeOptions()
    {
        $items = [];
        $options = $this->optionAllowed();
        foreach ($options as $option) {
            $name = '--'.$option['name'];
            if (true === $option['requireValue']) {
                $name .= '='.strtoupper($option['name']);
            } elseif (null === $option['requireValue']) {
                $name .= '[='.strtoupper($option['name']).']';
            }
            $items[$name] = static::getRichText($option);
        }
        if (count($items)) {
            $this->wrap()->info('Options:', 1);
            $this->item->write($items, 2);
            $this->wrap();
        }
        return $this;
    }

    /**
     * @param $keyword
     * @return $this
     */
    protected function writeCommands($keyword = null)
    {
        $items = [];
        foreach ($this->console->allCommand() as $command) {
            if ($command->isHidden()) {
                continue;
            }
            $name = $command->getName();
            if ($keyword && strpos($name, $keyword) === false) {
                continue;
            }
            $items[$name] = $command->getDescription();
        }
        $title = $keyword ? 'Available commands contains keyword "'.$keyword.'"' : 'Available commands';
        $this->wrap()->info($title.':', 1);
        if (count($items)) {
            $lists = [];
            if (isset($items['list'])) {
                $lists['list'] = $items['list'];
                unset($items['list']);
            }
            if (isset($items['help'])) {
                $lists['help'] = $items['help'];
                unset($items['help']);
            }
            ksort($items);
            $lists = array_merge($lists, $items);
            $this->item->write($lists, 2);
        } else {
            $this->write('  There is no result');
        }
        return $this->wrap();
    }

    /**
     * @param array $option
     * @return string
     */
    private static function getRichText(array $option)
    {
        $text = $option['description'];
        if ($option['default']) {
            $default = is_array($option['default']) ? '['.implode(',', $option['default']).']' : $option['default'];
            $text .= ('' === $text ? '' : ' ') . '<comment>[default:'.$default.']</comment>';
        }
        if ($option['array']) {
            $text .= ('' === $text ? '' : ' ') . '<comment>(multiple values allowed)</comment>';
        }
        return $text;
    }
}
