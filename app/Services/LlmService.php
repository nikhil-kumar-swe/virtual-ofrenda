<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LlmService
{
    /** Words we can safely match in the memory text if the model's JSON is broken. */
    private const KEYWORDS = [
        'marigold', 'flower', 'rose', 'garden', 'candle', 'tamale', 'bread', 'pan de muerto',
        'coffee', 'tea', 'soup', 'rice', 'beans', 'cookie', 'cake', 'chocolate', 'mango',
        'tequila', 'cigar', 'guitar', 'piano', 'violin', 'radio', 'song', 'music', 'bolero',
        'book', 'hat', 'glasses', 'photograph', 'rosary', 'dog', 'cat', 'bird', 'fishing',
        'sweater', 'blanket', 'pipe', 'dance', 'sewing', 'apron',
    ];

    /** Low-level call to llama-server's OpenAI-style endpoint. */
    public function chat(string $prompt, int $maxTokens = 200, float $temperature = 0.3): string
    {
        $url = rtrim((string) config('ofrenda.llm_url'), '/') . '/v1/chat/completions';

        try {
            $response = Http::connectTimeout(5)
                ->timeout(600) // 270M model at ~6.7 tok/s on a phone is slow
                ->acceptJson()
                ->post($url, [
                    'model'          => config('ofrenda.llm_model'),
                    'messages'       => [['role' => 'user', 'content' => $prompt]],
                    'max_tokens'     => $maxTokens,
                    'temperature'    => $temperature,
                    'top_p'          => 0.9,
                    'repeat_penalty' => 1.1,
                    'stream'         => false,
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('The local model is offline. Start llama-server and try again.', 0, $e);
        }

        if ($response->failed()) {
            throw new RuntimeException('The local model returned HTTP ' . $response->status() . '.');
        }

        $text = trim((string) data_get($response->json(), 'choices.0.message.content', ''));

        if ($text === '') {
            throw new RuntimeException('The model returned an empty reply.');
        }

        return $text;
    }

    /** Simple health check: a plain prompt. */
    public function ping(): string
    {
        return $this->chat('Say hello in one short sentence.', 30, 0.2);
    }

    public function story(string $name, string $memories): string
    {
        $name = $this->name($name);
        $memories = $this->trimMemories($memories);

                $prompt = "Retell the facts below as a short tribute to {$name}.\n"
            . "Rules:\n"
            . "- Write 4 to 5 simple sentences, one for each fact.\n"
            . "- Mention EVERY fact.\n"
            . "- Do NOT describe personality (no 'kind', 'sweet', 'loving') unless the facts say so.\n"
            . "- Do NOT add anything that is not in the facts.\n"
            . "- Use the past tense.\n\n"
            . "Facts:\n{$memories}\n\n"
            . "Tribute:";

        return $this->clean($this->chat($prompt, 200, 0.2));
    }

            public function poem(string $name, string $memories): string
    {
        $memories = $this->trimMemories($memories);

        $sentences = preg_split('/(?<=[.!?])\s+/', $memories, -1, PREG_SPLIT_NO_EMPTY);
        $sentences = array_slice(array_map('trim', $sentences), 0, 4);

        $lines = [];

        foreach ($sentences as $sentence) {
            $prompt = "Rewrite this sentence as one short, gentle line of a poem.\n"
                . "Keep the same words and ideas. Add nothing new.\n\n"
                . "Sentence: {$sentence}\n"
                . "Line:";

            try {
                $line = $this->clean($this->chat($prompt, 35, 0.2));
            } catch (RuntimeException $e) {
                if (str_contains($e->getMessage(), 'offline')) {
                    throw $e;
                }
                $line = '';
            }

            // keep the first line only, drop quotes
            $line = trim(strtok($line, "\n") ?: '', " \t\"'");

            // reject empty, repeated, or ungrounded lines: use the person's own words
            if ($line === '' || in_array($line, $lines, true) || !$this->sharesWords($line, $sentence)) {
                $line = rtrim($sentence, '.!?');
            }

            $lines[] = $line;
        }

        if (count($lines) === 0) {
            return 'In loving memory of ' . $this->name($name) . '.';
        }

                $who = trim($name) !== '' ? ' of ' . trim($name) : '';

        return 'In loving memory' . $who . ",\n"
            . implode("\n", $lines) . "\n"
            . 'remembered with love.';
    }

    /** True if the line reuses at least one meaningful word from the sentence. */
    private function sharesWords(string $line, string $sentence): bool
    {
        $stems = function (string $text): array {
            preg_match_all('/[a-z]{4,}/', mb_strtolower($text), $m);
            return array_map(fn ($w) => rtrim($w, 's'), $m[0]);
        };

        return count(array_intersect($stems($line), $stems($sentence))) > 0;
    }

    /**
     * Altar items as a list of short lowercase strings.
     * Never throws on bad JSON: falls back to keyword matching, then to defaults.
     * 'marigolds' and 'candle' are always added as altar decoration.
     */
    public function altarItems(string $memories): array
    {
        $memories = $this->trimMemories($memories);

        $prompt = "List the objects, foods, flowers, music and places mentioned in the text below.\n"
            . "Reply with ONLY a JSON array of short lowercase strings. Maximum 8 items.\n"
            . "Example: [\"marigolds\", \"tamales\", \"guitar\"]\n\n"
            . "Text:\n{$memories}\n\n"
            . "JSON:";

        try {
            $raw = $this->chat($prompt, 80, 0.0);
            $items = $this->parseItems($raw);
        } catch (RuntimeException $e) {
            // Model offline is handled by the job; a bad reply is not fatal here.
            if (str_contains($e->getMessage(), 'offline')) {
                throw $e;
            }
            $items = [];
        }

        // Keep only items that are actually grounded in what the person said.
        $items = array_values(array_filter($items, fn ($i) => $this->isGrounded($i, $memories)));

                // Add keyword matches the model missed (skip ones already covered)
        foreach ($this->keywordFallback($memories) as $keyword) {
            $covered = false;
            foreach ($items as $item) {
                if (str_contains($item, $keyword)) {
                    $covered = true;
                    break;
                }
            }
            if (!$covered) {
                $items[] = $keyword;
            }
        }

        $items = array_slice($items, 0, 8);

                foreach (['marigold', 'candle'] as $decor) {
            $has = false;
            foreach ($items as $item) {
                if (str_contains($item, $decor)) {
                    $has = true;
                    break;
                }
            }
            if (!$has) {
                $items[] = $decor === 'marigold' ? 'marigolds' : 'candle';
            }
        }

        return $this->groundItems($items, $memoryText);
    }

      /** Keep only items really present in the memory, then add what the model missed. */
    private function groundItems(array $items, string $memory): array
    {
        $text = mb_strtolower($memory);
        $skip = ['watch']; // common verb, rarely meant as an object
        $kept = [];

        foreach ($items as $item) {
            $name = mb_strtolower(trim((string) $item));
            if ($name === '' || in_array($name, $skip, true)) continue;
            $stem = preg_quote(rtrim($name, 's'), '/');
            if (preg_match('/\b' . $stem . '/u', $text)) {
                $kept[] = $name;
            }
        }

        $known = ['sunflower', 'cider', 'garden', 'porch', 'bush', 'flower',
                  'coffee', 'tea', 'bread', 'cake', 'guitar', 'book', 'dog', 'cat'];
        foreach ($known as $k) {
            if (preg_match('/\b' . $k . '(s|es)?\b/u', $text)) {
                $kept[] = $k;
            }
        }

        $kept[] = 'candle';
        $kept[] = 'marigolds';
        return array_slice(array_values(array_unique($kept)), 0, 10);
    }
    /** Pull a list of strings out of whatever the model produced. */
    public function parseItems(string $raw): array
    {
        $raw = preg_replace('/```(?:json)?/i', '', $raw);

        $items = [];

        if (preg_match('/\[.*?\]/s', $raw, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                $items = $decoded;
            }
        }

        if (!$items && preg_match('/\{.*\}/s', $raw, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                $first = reset($decoded);
                $items = is_array($first) ? $first : $decoded;
            }
        }

        // Broken JSON: grab anything in double quotes.
        if (!$items && preg_match_all('/"([^"]{2,40})"/', $raw, $all)) {
            $items = $all[1];
        }

        $clean = [];
        foreach ($items as $item) {
            if (!is_string($item)) {
                continue;
            }
            $item = mb_strtolower(trim($item));
            $item = trim($item, " \t\n\r\0\x0B.,;:-");
            if ($item !== '' && mb_strlen($item) <= 40 && !in_array($item, $clean, true)) {
                $clean[] = $item;
            }
        }

        return $clean;
    }

    private function keywordFallback(string $memories): array
    {
        $lower = mb_strtolower($memories);
        $found = [];

        foreach (self::KEYWORDS as $word) {
            if (str_contains($lower, $word)) {
                $found[] = $word;
            }
        }

        return $found;
    }

    private function isGrounded(string $item, string $memories): bool
    {
        $lower = mb_strtolower($memories);

        foreach (preg_split('/\s+/', $item) as $word) {
            if (mb_strlen($word) < 3) {
                continue;
            }
            // crude stem: "tamales" matches "tamale", "marigolds" matches "marigold"
            $stem = rtrim($word, 's');
            if (str_contains($lower, $stem)) {
                return true;
            }
        }

        return false;
    }

    private function name(string $name): string
    {
        $name = trim($name);
        return $name !== '' ? $name : 'my loved one';
    }

    private function trimMemories(string $text): string
    {
        // Context is 2048 tokens; keep the input well under that.
        return mb_substr(trim($text), 0, 1200);
    }

    private function clean(string $text): string
    {
        $text = str_replace(['**', '__'], '', $text);
        $text = preg_replace('/^(tribute|poem)\s*:\s*/i', '', trim($text));
        return trim($text);
    }
}