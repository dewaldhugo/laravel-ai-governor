<?php

use AiGovernor\Models\PromptVersion;
use Illuminate\Support\Facades\File;

describe('ai:prompts:sync command', function () {

    beforeEach(function () {
        $this->promptPath = storage_path('test-prompts');
        config(['ai-governor.prompt_path' => $this->promptPath]);

        File::ensureDirectoryExists($this->promptPath);
    });

    afterEach(function () {
        File::deleteDirectory($this->promptPath);
    });

    it('warns when no prompt definitions exist', function () {
        $this->artisan('ai:prompts:sync')
             ->expectsOutputToContain('No prompt definitions found')
             ->assertExitCode(0);
    });

    it('syncs a valid prompt definition to the database', function () {
        File::put($this->promptPath . '/2026_01_01_000000_test_prompt.php', <<<'PHP'
<?php
use AiGovernor\Prompts\PromptDefinition;

return new class extends PromptDefinition {
    public string $name        = 'test-prompt';
    public int    $version     = 1;
    public string $model       = 'gpt-4o-mini';
    public float  $temperature = 0.5;
    public int    $maxTokens   = 500;

    public function system(): string { return 'You are helpful.'; }
    public function user(): string   { return 'Say hello to {{name}}.'; }
};
PHP);

        $this->artisan('ai:prompts:sync')->assertExitCode(0);

        expect(PromptVersion::count())->toBe(1);
        expect(PromptVersion::first()->name)->toBe('test-prompt');
    });

    it('skips files that do not return a PromptDefinition instance', function () {
        File::put($this->promptPath . '/bad_file.php', '<?php return "not a definition";');

        $this->artisan('ai:prompts:sync')
             ->expectsOutputToContain('SKIP')
             ->assertExitCode(1);

        expect(PromptVersion::count())->toBe(0);
    });

    it('does not write to the database on dry-run', function () {
        File::put($this->promptPath . '/2026_01_01_000000_dry_test.php', <<<'PHP'
<?php
use AiGovernor\Prompts\PromptDefinition;

return new class extends PromptDefinition {
    public string $name        = 'dry-test';
    public int    $version     = 1;
    public string $model       = 'gpt-4o-mini';
    public float  $temperature = 0.7;
    public int    $maxTokens   = 100;

    public function system(): string { return 'System.'; }
    public function user(): string   { return 'User.'; }
};
PHP);

        $this->artisan('ai:prompts:sync --dry-run')->assertExitCode(0);

        expect(PromptVersion::count())->toBe(0);
    });

});
