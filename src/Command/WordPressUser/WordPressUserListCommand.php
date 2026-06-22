<?php

namespace Nucleus\Command\WordPressUser;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class WordPressUserListCommand extends Command
{
    use WordPressUserTrait;

    protected static $defaultName = 'wordpress:user list';

    protected function configure(): void
    {
        $this
            ->setDescription('List WordPress users')
            ->setHelp('Lists WordPress users in the selected environment.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('List WordPress Users');

        $context = $this->initializeWordPressUserContext($io);
        if ($context === null) {
            return $this->failure();
        }

        /** @var \Nucleus\Service\WpCliRunner $runner */
        $runner = $context['runner'];

        $io->text("Listing users in {$context['environment']}...");

        try {
            $runner->runSuccessful(
                ['list', '--fields=ID,user_login,user_email,roles', '--format=table'],
                $output
            );
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return $this->failure();
        }

        return $this->success();
    }
}
