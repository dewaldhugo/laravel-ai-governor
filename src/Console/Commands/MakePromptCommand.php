<?php

namespace AiGovernor\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakePromptCommand extends Command
{
    protected $signature   = 'make:prompt {name : The class name of the prompt definition}';
    protected $description = 'Create a new versioned prompt definition file in app/Prompts.';

    public function handle(): int
    {
        $name      = $this->argument('name');
        $className = Str::studly($name);
        $slug      = Str::snake($name);
        $timestamp = now()->format('Y_m_d_His');
        $filename  = "{$timestamp}_{$slug}.php";
        $path      = config('ai-governor.prompt_path', app_path('Prompts'));

        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $stub = file_get_contents($this->stubPath());

        $stub = str_replace(
            ['{{ slug }}', '{{ class }}'],
            [$slug, $className],
            $stub
        );

        $destination = $path . DIRECTORY_SEPARATOR . $filename;

        if (file_exists($destination)) {
            $this->error("Prompt definition already exists: {$destination}");
            return self::FAILURE;
        }

        file_put_contents($destination, $stub);

        $this->info("Prompt definition created: {$destination}");
        $this->line('Remember to increment the <comment>version</comment> number on each change.');

        return self::SUCCESS;
    }

    private function stubPath(): string
    {
        $published = base_path('stubs/ai-governor/prompt.stub');

        return file_exists($published)
            ? $published
            : __DIR__ . '/../stubs/prompt.stub';
    }
}
