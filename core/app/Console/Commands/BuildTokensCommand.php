<?php

namespace App\Console\Commands;

use App\Support\DesignTokens;
use Illuminate\Console\Command;

class BuildTokensCommand extends Command
{
    protected $signature = 'tokens:build';
    protected $description = 'Compile centralized design tokens from config/tokens.php to core/resources/css/tokens.css';

    public function handle(): int
    {
        $target = resource_path('css/tokens.css');
        DesignTokens::writeCssFile($target);
        $this->info("Successfully compiled tokens to {$target}");
        return 0;
    }
}
