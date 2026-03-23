<?php

namespace AiGovernor\Console\Commands;

use AiGovernor\Models\PromptVersion;
use AiGovernor\Prompts\PromptDefinition;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncPromptsCommand extends Command
{
    protected $signature = 'ai:prompts:sync
                            {--dry-run : Preview changes without writing to the database}';

    protected $description = 'Sync prompt definitions from the filesystem into the database.';

    public function handle(): int
    {
        $path  = config('ai-governor.prompt_path', app_path('Prompts'));
        $files = glob($path . DIRECTORY_SEPARATOR . '*.php');

        if ($files === false || empty($files)) {
            $this->warn("No prompt definitions found in [{$path}].");
            return self::SUCCESS;
        }

        $environment = app()->environment();
        $dryRun      = (bool) $this->option('dry-run');
        $synced      = 0;
        $skipped     = 0;

        if ($dryRun) {
            $this->line('<comment>Dry run — no changes will be written.</comment>');
        }

        foreach ($files as $file) {
            try {
                $definition = require $file;

                if (! $definition instanceof PromptDefinition) {
                    $this->error("  SKIP  {$file} — does not return a PromptDefinition instance.");
                    $skipped++;
                    continue;
                }

                // Validate required properties with a clear error before any DB work.
                $definition->validate();

                // Checksum covers all fields that affect model behaviour so that
                // drift (including max_tokens changes) is detectable at a glance.
                $checksum = hash(
                    'sha256',
                    $definition->system() .
                    $definition->user() .
                    (string) $definition->temperature .
                    $definition->model .
                    (string) $definition->maxTokens
                );

                if (! $dryRun) {
                    PromptVersion::updateOrCreate(
                        [
                            'name'        => $definition->name,
                            'version'     => $definition->version,
                            'environment' => $environment,
                        ],
                        [
                            'model'         => $definition->model,
                            'system_prompt' => $definition->system(),
                            'user_template' => $definition->user(),
                            'temperature'   => $definition->temperature,
                            'max_tokens'    => $definition->maxTokens,
                            'checksum'      => $checksum,
                        ]
                    );
                }

                $this->info(
                    sprintf(
                        '  OK    %s v%d [%s] — checksum: %s',
                        $definition->name,
                        $definition->version,
                        $environment,
                        substr($checksum, 0, 12) . '...'
                    )
                );

                $synced++;

            } catch (\Throwable $e) {
                $this->error("  FAIL  {$file} — {$e->getMessage()}");

                Log::error('AiGovernor: prompt sync failure', [
                    'file'        => $file,
                    'environment' => $environment,
                    'error'       => $e->getMessage(),
                ]);

                $skipped++;
            }
        }

        $this->newLine();
        $this->line(
            sprintf(
                '<info>Sync complete.</info> Synced: <comment>%d</comment> | Skipped: <comment>%d</comment>',
                $synced,
                $skipped
            )
        );

        return $skipped > 0 ? self::FAILURE : self::SUCCESS;
    }
}
