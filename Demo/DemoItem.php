<?php
namespace Tanbolt\Console\Demo;

use Tanbolt\Console\Command;

class DemoItem extends Command
{
    /** @var string */
    protected $name = 'demo:item';

    /** @var array */
    protected $alias = [
        'demo:table',
        'demo:overwrite',
    ];

    /** @var string */
    protected $description = 'Demo of item';

    /** @var array */
    private static $functions = [
        'normal' => 'Normal item value test',
        "key_\n多break" => 'Split an array into chunks',
        'key_normal' => "Values of \nitem 多行 item",
        'array_unique测' => 'Removes duplicate values from an array',
        "in_多\narray" => "Checks if a value \nexists in an array",
        'array_shift' => 'Shift an element off the beginning of array',
        'array_slice' => 'Extract a slice of the array',
        'array_count_values' => 'Counts all the values of an array',
    ];

    public function configure()
    {
        switch ($this->name) {
            case 'demo:overwrite':
                $this->setDescription('Demo of overwrite');
                break;
            case 'demo:table':
                $this->setDescription('Demo of table');
                break;
        }
    }

    public function handle()
    {
        if ('demo:overwrite' === $this->name) {
            $overwrite = $this->overwrite->instance();
            $this->wrap()->info('some php array functions', 1);
            $overwrite->start('<info>%function%</info>: %description%');
            foreach (self::$functions as $function => $description) {
                $overwrite->update(compact('function', 'description'));
                usleep(500000);
            }

        } elseif ('demo:table' === $this->name) {
            $tables = [];
            foreach (self::$functions as $function => $description) {
                $tables[] = [
                    'function' => $function,
                    'description' => $description,
                ];
            }
            $this->table->write($tables, 'some php array functions');

        } else {
            $this->wrap()->info('  some php array functions', 1);
            $this->item->write(self::$functions, 2);
            $this->wrap();

        }
    }
}
