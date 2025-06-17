<?php

namespace Nucleus;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class Plugin implements PluginInterface
{
    public function activate(Composer $composer, IOInterface $io): void
    {
        
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        
    }
} 
