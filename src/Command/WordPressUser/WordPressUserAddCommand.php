<?php

namespace Nucleus\Command\WordPressUser;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class WordPressUserAddCommand extends Command
{
    use WordPressUserTrait;

    protected static $defaultName = 'wordpress:user add';

    protected function configure(): void
    {
        $this
            ->setDescription('Add a WordPress user')
            ->setHelp('Creates a new WordPress user in the selected environment.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Add WordPress User');

        $context = $this->initializeWordPressUserContext($io);
        if ($context === null) {
            return $this->failure();
        }

        /** @var \Nucleus\Service\WpCliRunner $runner */
        $runner = $context['runner'];
        /** @var \Nucleus\Service\EnvFileLoader $envLoader */
        $envLoader = $context['envLoader'];

        $username = $io->ask('Username', $envLoader->get('WORDPRESS_USER') ?: null);
        if ($username === null || $username === '') {
            $io->error('Username is required.');
            return $this->failure();
        }

        $email = $io->ask('Email', $envLoader->get('WORDPRESS_USER_EMAIL') ?: null);
        if ($email === null || $email === '') {
            $io->error('Email is required.');
            return $this->failure();
        }

        $defaultPassword = $envLoader->get('WORDPRESS_PASSWORD');
        $password = $defaultPassword !== null && $defaultPassword !== ''
            ? $defaultPassword
            : $io->askHidden('Password');

        if ($password === null || $password === '') {
            $io->error('Password is required.');
            return $this->failure();
        }

        $role = $io->choice('Role', ['administrator', 'editor', 'author', 'contributor', 'subscriber'], 'administrator');

        $io->text("Creating user in {$context['environment']}...");

        try {
            $runner->runSuccessful(
                ['create', $username, $email, "--role={$role}", "--user_pass={$password}"],
                $output
            );
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return $this->failure();
        }

        $io->success("User '{$username}' created successfully in {$context['environment']}.");
        return $this->success();
    }
}
