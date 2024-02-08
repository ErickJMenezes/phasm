<?php

namespace ErickJMenezes\Phasm\Command;

use ErickJMenezes\Phasm\Compiler\WebAssemblyCompiler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'phasm',
    description: 'Phasm Compiler',
)]
class PhasmCommand extends Command
{
    protected function configure()
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'The input file to compile.')
            ->addArgument('output', InputArgument::REQUIRED, 'Output file path.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($input->getArgument('file'))) {
            $output->writeln("The file <{$input->getArgument('file')}> does not exists.");
            return Command::INVALID;
        }

        $code = file_get_contents($input->getArgument('file'));

        $compiler = new WebAssemblyCompiler();

        $watCode = $compiler->compileRoot($code);

        file_put_contents($input->getArgument('output'), $watCode);

        return Command::SUCCESS;
    }
}
