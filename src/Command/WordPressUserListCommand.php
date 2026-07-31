<?php

namespace Nucleus\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class WordPressUserListCommand extends Command
{
    use WordPressUserTrait;

    protected static $defaultName = 'user:list';

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
            $json = $runner->runSuccessful([
                'user',
                'list',
                '--fields=ID,user_login,user_email,roles',
                '--format=json',
            ]);
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

        $io->table(
            ['ID', 'Login', 'Email', 'Roles'],
            array_map(static function (array $user): array {
                $roles = $user['roles'] ?? '';
                if (is_array($roles)) {
                    $roles = implode(', ', $roles);
                }

                return [
                    $user['ID'] ?? '',
                    $user['user_login'] ?? '',
                    $user['user_email'] ?? '',
                    $roles,
                ];
            }, $users)
        );

        return $this->success();
    }
}
