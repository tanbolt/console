<?php
namespace Tanbolt\Console\Demo;

use Tanbolt\Console\Command;

class DemoRichText extends Command
{
    public function configure()
    {
        $this->setName('demo:text')->setDescription('Demo of rich text');
    }

    public function handle()
    {
        $txt = <<<'EOF'

<comment><b>Tanbolt Process</b></comment> 是一个用于处理 <info>PHP CLI</info> 的库，
可以很方便的获取输入<notice>参数</notice>，输出<warn>结果</warn>。并且内置了一些组件用于简化终端交互。

<i>Tanbolt console</i> is a library for dealing with <question>php cli</question>, 
And some built-in <error>components</error> are used to simplify <text background="black" color="white">terminal</text> interaction.

EOF;

        $this->richText->write($txt);
    }
}
