<?php
namespace Tanbolt\Console\Demo;

use Tanbolt\Console\Ansi;
use Tanbolt\Console\Command;
use Tanbolt\Console\Component\Progress;

class DemoProgress extends Command
{
    protected $name = 'demo:progress';
    protected $description = 'Demo of progress';

    public function handle()
    {
        $styles = [
            Progress::STYLE_MINI,
            Progress::STYLE_NORMAL,
            Progress::STYLE_VERBOSE,
            Progress::STYLE_DEBUG,
            'ui_bar',
            'unicode_bar',
            'custom'
        ];
        $style = $this->menu->radio('Choose progress theme', $styles, 1);
        $style = reset($style);
        $this->menu->clear(true, true);
        $progress = $this->progress->instance();

        if ('custom' === $style) {
            $progress->setDoneChar(Ansi::instance()->bgGreen()->getStyle(), ' ')
                ->setProgressChar(Ansi::instance()->bgGreen()->getStyle(), ' ')
                ->setEmptyChar(Ansi::instance()->bgBlack()->bgBright()->getStyle(), ' ')
                ->startWith('%<comment>{title}:</comment>% %bar%');
        } elseif ('ui_bar' === $style) {
            $progress->setDoneChar(Ansi::instance()->bgGreen()->getStyle(), ' ')
                ->setProgressChar(Ansi::instance()->bgGreen()->getStyle(), ' ')
                ->setEmptyChar(Ansi::instance()->bgBlack()->bgBright()->getStyle(), ' ')
                ->start();
        } elseif ('unicode_bar' === $style) {
            $progress->setDoneChar(null, '完')
                ->setProgressChar(null, '>')
                ->setEmptyChar(null, '未')
                ->start();
        } else {
            $progress->start($style);
        }

        usleep(500000);
        $progress->update(10);
        usleep(500000);
        $progress->update(20);
        usleep(500000);
        $progress->update(30);
        usleep(500000);

        $progress->setTitle('continue.....');
        $progress->update(40);
        usleep(500000);
        $progress->update(50);
        usleep(500000);
        $progress->update(60);
        usleep(500000);

        $progress->setTitle('go next task');
        $progress->update(70);
        usleep(500000);
        $progress->update(80);
        usleep(500000);
        $progress->update(90);
        usleep(500000);
        $progress->update(100);

        $progress->setTitle('finish')->finish();
    }
}
