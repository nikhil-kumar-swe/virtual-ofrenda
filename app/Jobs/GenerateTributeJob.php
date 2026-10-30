<?php

namespace App\Jobs;

use App\Models\Memory;
use App\Models\Tribute;
use App\Services\LlmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class GenerateTributeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900; // a phone-sized model is slow
    public int $tries = 1;     // never retry: the status row tells the story

    public function __construct(public int $tributeId)
    {
    }

    public function handle(LlmService $llm): void
    {
        $tribute = Tribute::find($this->tributeId);
        if (!$tribute) {
            return;
        }

        $memory = Memory::find($tribute->memory_id);
        if (!$memory) {
            $tribute->status = 'failed';
            $tribute->error = 'That memory could not be found.';
            $tribute->save();
            return;
        }

        $tribute->status = 'processing';
        $tribute->error = null;
        $tribute->save();

        try {
            $name = (string) $memory->name;
            $text = (string) $memory->text;

            $tribute->story = $llm->story($name, $text);
            $tribute->save();

            $tribute->poem = $llm->poem($name, $text);
            $tribute->save();

            $items = $llm->altarItems($text);
            $tribute->items = $tribute->hasCast('items') ? $items : json_encode($items);
            $tribute->status = 'done';
            $tribute->save();
        } catch (Throwable $e) {
            report($e);
            $tribute->status = 'failed';
            $tribute->error = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Something went wrong while writing the tribute.';
            $tribute->save();
        }
    }
}