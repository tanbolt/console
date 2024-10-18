<?php
namespace Tanbolt\Console\Demo;

use Tanbolt\Console\Command;
use Tanbolt\Console\Component\Top;

class DemoTop extends Command
{
    /** @var string */
    protected $name = 'demo:top';

    /** @var string */
    protected $description = 'Top demo';

    /** @var string */
    protected $help = 'This is a simple top demo';

    /** @var string */
    protected $parameter = '
    {--d? : daemonize mode}
    ';

    /** @var array */
    private static $words = [
        'time','year','people','way','day','man','thing','woman','life','child',
        'world','school','state','family','student','group','country','problem',
        'hand','part','place','case','week','company','system','program','question',
        'work','government','number','night','point','home','water','room','mother',
        'area','money','story','fact','month','lot','right','study','book','eye','job',
        'word','business','issue','side','kind','head','house','service','friend',
        'father','power','hour','game','line','end','member','law','car','city',
        'community','name','president','team','minute','idea','kid','body','information',
        'back','parent','face','others','level','office','door','health','person','art',
        'war','history','party','result','change','morning','reason','research','girl',
        'guy','moment','air','teacher','force','education'
    ];

    /**
     * @var array
     */
    private $lists;


    public function handle()
    {
        $this->getListData();

        // basic
        $top = $this->top->instance()->setHeaderHandler(function () {
            return $this->getHeaderData();
        })->setListHandler(function () {
            return $this->getListData();
        });

//        // bind hotKey [u]
//        $top->setHotKey('u', function($key, Top $top) {
//            $this->hotKey($key, $top);
//        }, 'Custom hot key demo');
//
//        // bind hotKey [g]
//        $top->setHotKey('g', function($key, Top $top) {
//            if ($top->getInterface() === 'demoTop') {
//                $answer = $top->getAnswer('Input something:');
//                $top->write($answer.PHP_EOL);
//            }
//        }, 'Get input demo');
//
//        // bind Quit
//        $top->setHotKey('Quit', function($key, Top $top) {
//            if ($top->getInterface() === 'demoTop') {
//                $top->reDraw();
//            }
//        });

        if ($this->getOption('d')) {
            $top->setDaemonize(true);
        }
        $top->showTop();
    }

    /**
     * @return array
     */
    protected function getListData()
    {
        if (!$this->lists) {
            $index = 0;
            $now = time() - 100 * 24 * 3600;
            $this->lists = array_map(function ($w) use ($now, &$index){
                $index++;
                $start = $now + 24 * 3600 * $index;
                $end = $start + 24 * 3600;
                $time = rand($start, $end);
                return [$index, $w, substr($w, 0, 1), substr($w, -1), strrev($w), 0, date('Y-m-d H:i:s', $time)];
            }, self::$words);
        }
        $lists = $this->lists;
        array_unshift($lists, ['id', 'word', 'first', 'end', 'rev', 'rank', 'time']);
        return $lists;
    }

    protected function getHeaderData()
    {
        return 'Demo top up at <info>'.date('H:i:s')."</info>\n".
            'This is a top demo'."\n".
            'You can put main information in here'."\n".
            'Input [h] show help';
    }

    protected function hotKey($key, Top $top)
    {
        $top->clear()->setInterface('demoTop');
        $body = '<info>This is a custom interface</info>' . PHP_EOL . PHP_EOL .
            'Type [q] or [Esc] to continue' . PHP_EOL
        ;
        $top->writeRich($body);
    }
}
