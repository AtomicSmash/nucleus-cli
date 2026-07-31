<?php

namespace Nucleus\Command;

use Nucleus\Service\GlobalConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ConfigSetupCommand extends Command
{
    protected static $defaultName = 'config:setup';

    protected function configure(): void
    {
        $this
            ->setDescription('Create or update the global Nucleus config file')
            ->setHelp('Interactively sets MailerSend settings and developer defaults used by other commands.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $configService = new GlobalConfig();
        $path = $configService->getPath();
        $config = $configService->load();

        $io->title('Nucleus Global Config');
        $io->text($configService->exists()
            ? "Updating existing config at {$path}"
            : "Creating new config at {$path}"
        );

        $io->section('MailerSend');
        if (!isset($config['mailersend']) || !is_array($config['mailersend'])) {
            $config['mailersend'] = [];
        }
        $config['mailersend']['api_key'] = $io->ask(
            'MailerSend API key',
            $config['mailersend']['api_key'] ?? null
        );
        $config['mailersend']['template_id'] = $io->ask(
            'MailerSend template ID',
            $config['mailersend']['template_id'] ?? 'v69oxl5d0ez4785k'
        );

        $io->section('Developer defaults (used by user:add and maintenance emails)');
        if (!isset($config['developer_defaults']) || !is_array($config['developer_defaults'])) {
            $config['developer_defaults'] = [];
        }

        $prompts = [
            'username' => 'Username',
            'email_address' => 'Email address',
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'job_title' => 'Job title',
            'team' => 'Team',
        ];

        foreach ($prompts as $key => $label) {
            $current = $config['developer_defaults'][$key] ?? null;
            $value = $io->ask($label, is_string($current) && $current !== '' ? $current : null);
            if ($value === null || $value === '') {
                unset($config['developer_defaults'][$key]);
            } else {
                $config['developer_defaults'][$key] = $value;
            }
        }

        if (empty($config['developer_defaults'])) {
            unset($config['developer_defaults']);
        }

        $configService->save($config);

        $io->success("Config saved to {$path}");
        $io->text('Run `nucleus config:show` to review the saved values.');

        return Command::SUCCESS;
    }
}
