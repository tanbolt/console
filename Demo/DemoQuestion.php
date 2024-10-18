<?php
namespace Tanbolt\Console\Demo;

use Tanbolt\Console\Command;

class DemoQuestion extends Command
{
    protected $name = 'demo:question';
    protected $description = 'Demo of question';

    public function handle()
    {
        $name = $this->question->ask('Name', 'foo');
        $pass = $this->question->secret('Password');

        $gender = $hobbies = null;
        if ($more = $this->question->confirm('do you want type more')) {
            $gender = $this->question->radio('Gender', ['male', 'female', 'other'], 0);
            $hobbies = $this->question->choice('Hobbies', [
                'football',
                'movie',
                'music',
                'computer game',
            ], [1,3]);
        }

        $this->wrap(2);
        $this->comment('Your answer:', 1);
        $this->line('name: '.$name);
        $this->line('password: '.$pass);
        if ($more) {
            $this->line('gender: ['.key($gender).' => '.current($gender).']');
            $hobby = '';
            foreach ($hobbies as $key=>$value) {
                $hobby .= ('' === $hobby ? '' : ', ') . ''.$key.' => '.$value;
            }
            $this->line('Hobbies: ['.$hobby.']');
        }
    }
}
