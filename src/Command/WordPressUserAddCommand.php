<?php

namespace Nucleus\Command;

use Nucleus\Service\GlobalConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class WordPressUserAddCommand extends Command
{
    use WordPressUserTrait;

    protected static $defaultName = 'user:add';

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
        $globalDefaults = (new GlobalConfig())->getDeveloperDefaults();

        $username = $io->ask(
            'Username',
            $globalDefaults['username'] ?? $envLoader->get('WORDPRESS_USER') ?: null
        );
        if ($username === null || $username === '') {
            $io->error('Username is required.');
            return $this->failure();
        }

        $email = $io->ask(
            'Email',
            $globalDefaults['email_address'] ?? $envLoader->get('WORDPRESS_USER_EMAIL') ?: null
        );
        if ($email === null || $email === '') {
            $io->error('Email is required.');
            return $this->failure();
        }

        $firstName = $io->ask('First name', $globalDefaults['first_name'] ?? null);
        $lastName = $io->ask('Last name', $globalDefaults['last_name'] ?? null);
        $jobTitle = $io->ask('Job title', $globalDefaults['job_title'] ?? null);
        $team = $io->ask('Team', $globalDefaults['team'] ?? null);

        $defaultPassword = $envLoader->get('WORDPRESS_PASSWORD');
        $password = $defaultPassword !== null && $defaultPassword !== ''
            ? $defaultPassword
            : $io->askHidden('Password');

        if ($password === null || $password === '') {
            $io->error('Password is required.');
            return $this->failure();
        }

        try {
            $rolesJson = $runner->runSuccessful(['role', 'list', '--fields=role', '--format=json']);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return $this->failure();
        }

        $roleEntries = json_decode(trim($rolesJson), true);
        if (!is_array($roleEntries) || empty($roleEntries)) {
            $io->error('Could not load roles from WP-CLI.');
            return $this->failure();
        }

        $roles = [];
        foreach ($roleEntries as $entry) {
            if (!empty($entry['role'])) {
                $roles[] = $entry['role'];
            }
        }

        if (empty($roles)) {
            $io->error('No roles found on the selected environment.');
            return $this->failure();
        }

        $defaultRole = in_array('administrator', $roles, true) ? 'administrator' : $roles[0];
        $role = $io->choice('Role', $roles, $defaultRole);

        $createArgs = [
            'user',
            'create',
            $username,
            $email,
            "--role={$role}",
            "--user_pass={$password}",
            '--porcelain',
        ];

        if ($firstName !== null && $firstName !== '') {
            $createArgs[] = "--first_name={$firstName}";
        }
        if ($lastName !== null && $lastName !== '') {
            $createArgs[] = "--last_name={$lastName}";
        }
        if ($firstName !== null && $firstName !== '' && $lastName !== null && $lastName !== '') {
            $createArgs[] = "--display_name={$firstName} {$lastName}";
        }

        $io->text("Creating user in {$context['environment']}...");

        try {
            $userId = trim($runner->runSuccessful($createArgs));
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return $this->failure();
        }

        if ($userId === '' || !ctype_digit($userId)) {
            $io->error('User may have been created, but WP-CLI did not return a user ID.');
            return $this->failure();
        }

        try {
            if ($jobTitle !== null && $jobTitle !== '') {
                $runner->runSuccessful(['user', 'meta', 'update', $userId, 'job_title', $jobTitle]);
            }
            if ($team !== null && $team !== '') {
                $runner->runSuccessful(['user', 'meta', 'update', $userId, 'team', $team]);
            }
        } catch (\RuntimeException $e) {
            $io->warning('User was created, but some profile meta could not be saved: ' . $e->getMessage());
        }

        $io->success("User '{$username}' (ID {$userId}) created successfully in {$context['environment']}.");
        return $this->success();
    }
}
