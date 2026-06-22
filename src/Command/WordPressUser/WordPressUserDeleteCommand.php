<?php

namespace Nucleus\Command\WordPressUser;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class WordPressUserDeleteCommand extends Command
{
    use WordPressUserTrait;

    protected static $defaultName = 'wordpress:user delete';

    protected function configure(): void
    {
        $this
            ->setDescription('Delete a WordPress user')
            ->setHelp('Deletes a WordPress user from the selected environment.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Delete WordPress User');

        $context = $this->initializeWordPressUserContext($io);
        if ($context === null) {
            return $this->failure();
        }

        /** @var \Nucleus\Service\WpCliRunner $runner */
        $runner = $context['runner'];

        $io->text("Fetching users from {$context['environment']}...");

        try {
            $json = $runner->runSuccessful(['list', '--format=json']);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return $this->failure();
        }

        $users = json_decode(trim($json), true);
        if (!is_array($users)) {
            $io->error('Could not parse user list from WP-CLI.');
            return $this->failure();
        }

        if (empty($users)) {
            $io->warning('No users found.');
            return $this->success();
        }

        $choices = [];
        foreach ($users as $user) {
            $id = $user['ID'] ?? '';
            $login = $user['user_login'] ?? '';
            $email = $user['user_email'] ?? '';
            $choices["{$id}: {$login} ({$email})"] = (string) $id;
        }

        $selectedLabel = $io->choice('Select a user to delete', array_keys($choices));
        $userId = $choices[$selectedLabel];

        if ($userId === '1') {
            $io->warning('You are about to delete the primary administrator account (ID 1).');
            if (!$io->confirm('Are you absolutely sure you want to delete user ID 1?', false)) {
                $io->text('Deletion cancelled.');
                return $this->success();
            }
        }

        if (!$io->confirm("Delete user {$selectedLabel}?", false)) {
            $io->text('Deletion cancelled.');
            return $this->success();
        }

        try {
            $runner->runSuccessful(['delete', $userId, '--yes'], $output);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return $this->failure();
        }

        $io->success("User deleted successfully from {$context['environment']}.");
        return $this->success();
    }
}
