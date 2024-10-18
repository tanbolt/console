<?php
namespace Tanbolt\Console\Demo;

use Tanbolt\Console\Command;

class DemoBasic extends Command
{
    /** @var string */
    protected $name = 'demo:basic';

    /** @var string */
    protected $description = 'Basic demo';

    /** @var string */
    protected $parameter = '
    {name* : required argument}
    {middleName : optional argument}
    {lastName=foo : optional argument with default value}
    {[aliasName] : optional argument}
    {--female? : option must be bool value (could not set value)}
    {--age* : option must set value}
    {--weight : optional option}
    {--job=phper : optional option with default value}
    {--[hobby*] : option allow multiple values}
    ';

    /** @var string */
    protected $help = 'This is a simple command demo';

    /** @var bool */
    protected $hidden = false;

    /** @var bool */
    protected $disable = false;

    /** @var bool */
    protected $allowUndefined = false;


    public function configure()
    {
        // 除了通过变量方式配置，也可以通过函数式进行配置
        // $this->setConfigureDemo();
    }

    protected function setConfigureDemo()
    {
        // set name/description/help/hidden/disable/allowUndefined
        $this->setName('demo:basic')
            ->setDescription('Basic demo')
            ->setHelp('This is a simple command demo')
            ->setHidden(false)
            ->setDisable(false)
            ->allowUndefined(false);

        // set parameter
        $this->allowArgument('name', 'Demo required argument', true)
            ->allowArgument('middleName', 'Demo optional argument', false)
            ->allowArgument('lastName', 'Demo optional argument with default value', false, 'foo')
            ->allowArgument('aliasName', 'optional argument', false, null, true)
            ->allowOption('female', 'Demo option must be bool value (could not set value)', false)
            ->allowOption('age', 'Demo option must set value', true)
            ->allowOption('weight', 'Demo optional option')
            ->allowOption('job', 'Demo optional option with default value', null, 'phper')
            ->allowOption('hobby', 'Demo option allow multiple values', false, null, true);
    }

    public function handle()
    {
        // name
        $name = $this->getArgument('name');
        $this->comment('name:')->line($name, 2);

        // middle name
        $middleName = $this->getArgument('middleName');
        $this->comment('middleName:');
        strlen($middleName) ? $this->line($middleName,2) : $this->info('empty', 2);

        // last name
        $lastName = $this->getArgument('lastName');
        $this->comment('lastName:');
        strlen($lastName) ? $this->line($lastName, 2) : $this->info('empty', 2);

        // alis name
        $aliasName = $this->getArgument('aliasName', []);
        $this->comment('aliasName:');
        count($aliasName) ? $this->line('['.join(',', $aliasName).']', 2) : $this->info('empty', 2);

        // female
        $female = $this->getOption('female') ? 'Y' : 'N';
        $this->comment('female:')->line($female,2);

        // age
        $age = $this->getOption('age');
        $this->comment('age:');
        if (strlen($age)) {
            $this->line($age);
            if (!is_numeric($age)) {
                $this->error('age must be number', 2);
            } else {
                $this->wrap();
            }
        } else {
            $this->info('empty', 1);
            $this->warn('you\'d better to input age', 2);
        }

        // weight
        $weight = $this->getOption('weight');
        $this->comment('weight:');
        strlen($weight) ? $this->line($weight, 2) : $this->info('empty', 2);

        // job
        $job = $this->getOption('job');
        $this->comment('job:');
        strlen($job) ? $this->line($job, 2) : $this->info('empty', 2);

        // hobby
        $hobby = $this->getOption('hobby');
        $this->comment('hobby:');
        !empty($hobby) ? $this->line('['.join(',', $hobby).']') : $this->info('empty', 1);
    }
}
