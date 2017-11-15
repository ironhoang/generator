<?php
namespace LaravelRocket\Generator\Console\Commands;

use LaravelRocket\Generator\Generators\AlterMigrationGenerator;
use Symfony\Component\Console\Input\InputArgument;

class AlterMigrationGeneratorCommand extends GeneratorCommand
{
    protected $name        = 'rocket:make:migration:alter';

    protected $description = 'Create a migration for alter table';

    protected $generator   = AlterMigrationGenerator::class;

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the table'],
            ['action', InputArgument::REQUIRED, 'Alter action'],
        ];
    }

    protected function getAdditionalData()
    {
        return [
            'action' => $this->argument('action'),
        ];
    }
}
