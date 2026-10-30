<?php

namespace App\Console\Commands;

use App\Services\LlmService;
use App\Services\WhisperService;
use Illuminate\Console\Command;
use Throwable;

class AiTest extends Command
{
    protected $signature = 'ai:test
        {--only= : Run one part: llm, story, poem, items, or whisper}
        {--audio= : Path to an audio file for the whisper test}';

    protected $description = 'Smoke-test the local LLM and Whisper services';

    private const SAMPLE = 'My grandmother Rosa made tamales every Christmas. '
        . 'She hummed old boleros while she cooked. '
        . 'She always kept marigolds on the kitchen windowsill.';

    public function handle(LlmService $llm, WhisperService $whisper): int
    {
        $only = $this->option('only');
        $failed = false;

        $run = function (string $key, string $label, callable $fn) use ($only, &$failed) {
            if ($only && $only !== $key) {
                return;
            }
            $this->newLine();
            $this->info("== {$label} ==");
            $start = microtime(true);
            try {
                $out = $fn();
                $this->line(is_array($out) ? json_encode($out) : $out);
                $this->comment(sprintf('(%.1fs)', microtime(true) - $start));
            } catch (Throwable $e) {
                $failed = true;
                $this->error($e->getMessage());
            }
        };

        $run('llm', 'LLM plain prompt', fn () => $llm->ping());
        $run('story', 'Story', fn () => $llm->story('Rosa', self::SAMPLE));
        $run('poem', 'Poem', fn () => $llm->poem('Rosa', self::SAMPLE));
        $run('items', 'Altar items', fn () => $llm->altarItems(self::SAMPLE));

        // Parser check with broken JSON, no model needed
        if (!$only || $only === 'items') {
            $this->newLine();
            $this->info('== Parser on broken JSON ==');
            $this->line(json_encode($llm->parseItems('Sure! ["tamales", "marigolds", "guitar"')));
        }

        if (!$only || $only === 'whisper') {
            $audio = $this->option('audio');
            if ($audio) {
                $run('whisper', 'Whisper', fn () => $whisper->transcribe($audio));
            } elseif ($only === 'whisper') {
                $this->error('Pass --audio=path/to/file.wav');
                $failed = true;
            } else {
                $this->newLine();
                $this->comment('Whisper skipped (no --audio given).');
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}