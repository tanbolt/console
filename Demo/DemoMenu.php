<?php
namespace Tanbolt\Console\Demo;

use Tanbolt\Console\Ansi;
use Tanbolt\Console\Command;
use Tanbolt\Console\Component\Progress;

class DemoMenu extends Command
{
    protected $name = 'demo:menu';
    protected $description = 'Demo of menu';

    private static $menuList = [
        'demo of item',
        '中文选项',
        'long item:One should give up anger; one should abandon pride; one should overcome all fetters.'.
        ' I’ll never befall him who clings not to mind and body and is passionless.',
        'foo' => 'string key',
        'bar' => '得道者多助（A just cause enjoys abundant support）失道者寡助（while an unjust one finds little support）'
    ];

    public function handle()
    {
        $this->launch();
    }

    protected function launch()
    {
        // 输出选择 要演示 的选项
        $menu = $this->menu->instance();
        $next = $menu->setMenuFullwidth(false)->radio('<info>Choose demo of menu</info>', [
            'radio',
            'full radio',
            'choice',
            'full choice'
        ], 0);
        $menu->clear();

        // 根据选择输出选项
        $key = key($next);
        $radio = $key < 2;
        $full = $key % 2;

        $title = <<<'EOF'

可以使用<b>富文本</b>输出<i>标题</i>，这样<comment>灵活性更高</comment>。
You can use <info>rich text</info> to output the title, which is more <error>flexible</error>


EOF;
        $menu->setMenuFullwidth($full);
        if ($radio) {
            $rs = $menu->radio($title, self::$menuList);
        } else {
            $rs = $menu->choice($title, self::$menuList);
        }
        $menu->clear();

        // 输出结果 -> 后续可进行操作
        $this->richText->write('<comment>The result of your choice:</comment>'.Ansi::EOL, true);
        $this->item->write($rs, 2, null, true);
        $rs = $menu->disableHelpInfo()->setMenuFullwidth(false)->radio(Ansi::EOL.'<comment>Next:</comment>', [
            'Back',
            'Exit'
        ]);
        $key = key($rs);
        if (!$key) {
            $menu->clear();
            $this->item->clear();
            $this->richText->clear();
            $this->launch();
        }
    }

}
