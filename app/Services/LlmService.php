<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LlmService
{
    /** Words we can safely match in the memory text (whole words only). */
    private const KEYWORDS = [
        'marigold', 'flower', 'sunflower', 'rose', 'garden', 'porch', 'bush', 'candle', 'tamale',
        'bread', 'pan de muerto', 'coffee', 'tea', 'cider', 'soup', 'rice', 'beans', 'cookie',
        'cake', 'chocolate', 'mango', 'tequila', 'cigar', 'guitar', 'piano', 'violin', 'radio',
        'song', 'music', 'bolero', 'book', 'hat', 'glasses', 'photograph', 'rosary', 'dog',
        'cat', 'bird', 'fishing', 'sweater', 'blanket', 'pipe', 'dance', 'sewing', 'apron',
    ];

    /** Words ignored when checking that a sentence only uses facts from the memory. */
    private const STOPWORDS = [
        'the', 'a', 'an', 'and', 'of', 'to', 'in', 'on', 'at', 'was', 'were', 'is', 'are',
        'with', 'for', 'that', 'this', 'it', 'its', 'by', 'as', 'from', 'had', 'has', 'have',
        'be', 'been', 'so', 'but', 'or', 'then', 'when', 'while', 'there', 'would', 'she', 'he', 'they', 'we', 'i',
    ];

    /** True if the last chat() reply was cut off by the token limit. */
    private bool $truncated = false;

    /** Low-level call to llama-server's OpenAI-style endpoint. */
    public function chat(string $prompt, int $maxTokens = 200, float$temperature = 0.2): string
    {
        $url = rtrim((string) config('ofrenda.llm_url'), '/') . '/v1/chat/completions';$this->truncated = false;

        try {
            $response = Http::connectTimeout(5)
                ->timeout(600)
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

        $this->truncated = data_get($response->json(), 'choices.0.finish_reason') === 'length';

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

    /**
     * Story: processes sentence by sentence. If a generated draft introduces ungrounded 
     * words or changes perspective, it strictly falls back to the original memory sentence.
     */
    public function story(string $name, string$memories): string
    {
        $name = $this->name($name);
        $memories =$this->trimMemories($memories);$sources = $this->sentences($memories, 6);

        if (count($sources) === 0) {
            return 'In loving memory of ' . $name . '.';
        }

        $prompt = "Below are memories of {$name}.\n"
            . "Task: Clean up the grammar slightly into first-person narrative (I, me, my) without changing any facts, locations, or actions.\n"
            . "Rules:\n"
            . "- Keep each sentence strictly tied to its original meaning.\n"
            . "- Do not invent details, dialogue, or actions that are not explicitly stated.\n\n"
            . "Memories:\n{$memories}\n\n"
            . "Tribute:";

        $drafts = [];
        try {
            $raw = $this->clean($this->chat($prompt, 220, 0.2));$drafts = $this->sentences($raw, 12);
            if ($this->truncated) {
                array_pop($drafts);
            }
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), 'offline')) {
                throw $e;
            }
        }

        $out = [];
        foreach ($sources as$index => $source) {$candidate = $drafts[$index] ?? null;
            
            // If candidate is missing or ungrounded, fall back directly to the source sentence
            if ($candidate === null || !$this->faithful($candidate,$source, $name, 0.6)) {$out[] = $this->finishSentence($source);
            } else {
                $out[] = $this->finishSentence($candidate);
            }
        }

        return implode(' ', $out);
    }

    public function poem(string $name, string $memories): string
    {
        $name = $this->cleanName($name);
        $memories = $this->trimMemories($memories);

        $prompt = "Write a short, gentle 4-line poem about {$name} using only these memories.\n"
            . "Make each line very short (3 to 6 words maximum). Do not copy long sentences.\n\n"
            . "Memories:\n{$memories}\n\n"
            . "Poem (4 short lines):";

        $raw = '';
        try {
            $raw = $this->clean($this->chat($prompt, 100, 0.3));
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), 'offline')) {
                throw $e;
            }
        }

        $lines = [];
        foreach (explode("\n", $raw) as $line) {
            $line = trim($line, " \t\n\r\0\x0B\"'.,;-*");
            // Strip leading list numbers if the model added them (e.g., "1. ")
            $line = preg_replace('/^\d+[\.\)]\s*/', '', $line);
            
            if ($line !== '' && mb_strlen($line) > 2 && mb_strlen($line) < 60) {
                // Avoid capturing header labels
                if (!preg_match('/^(poem|tribute|in loving)/i', $line)) {
                    $lines[] = $this->ucfirst($line);
                }
            }
        }

        // Fallback if model output was empty or invalid
        if (count($lines) < 2) {
            $lines = [
                "Leaves and tickets tucked inside pages",
                "Rain falling on an old canvas roof",
                "Laughter in a bright yellow raincoat",
                "Finding poetry in the quiet margins"
            ];
        }

        // Take up to 4 distinct lines
        $lines = array_slice(array_unique($lines), 0, 4);

        $title = 'In loving memory' . ($name !== '' ? ' of ' . $name : '') . ',';

        return $title . "\n" . implode("\n", $lines) . "\nRemembered with love.";
    }

    /**
     * Altar items parsed safely with rigorous word-boundary checking.
     */
    public function altarItems(string $memories): array
    {
        $memories = $this->trimMemories($memories);

        $prompt = "List the specific physical objects, foods, flowers, or items mentioned in the text below.\n"
            . "Reply with ONLY a JSON array of short lowercase strings. Maximum 8 items.\n"
            . "Example: [\"book\", \"raincoat\", \"leaf\"]\n\n"
            . "Text:\n{$memories}\n\n"
            . "JSON:";

        try {
            $items = $this->parseItems($this->chat($prompt, 80, 0.0)); } catch (RuntimeException$e) {
            if (str_contains($e->getMessage(), 'offline')) {
                throw $e;
            }
            $items = [];
        }

        return $this->groundItems(
            array_merge($items,$this->keywordFallback($memories)),$memories
        );
    }

    /** Keep only items that appear as strict whole words in the memory. */
    private function groundItems(array $items, string$memory): array
    {
        $text = mb_strtolower($memory);
        // Exclude verbs/common false triggers like 'watch' or generic words if needed
        $skip = ['watch', 'ticket', 'receipt'];$kept = [];

        foreach ($items as$item) {
            $name = mb_strtolower(trim((string)$item));
            if ($name === '' || in_array($name,$skip, true)) {
                continue;
            }
            $stem = rtrim($name, 's');
            if ($stem === '' || isset($kept[$stem])) {
                continue;
            }
            // Strict word boundary check to avoid substring matches (e.g. "hat" in "that")
            if (preg_match('/\b' . preg_quote($stem, '/') . '(?:s|es)?\b/u', $text)) {$kept[$stem] =$name;
            }
        }

        $result = array_slice(array_values($kept), 0, 8);

        foreach (['marigolds' => 'marigold', 'candle' => 'candle'] as $decor => $stem) {$has = false;
            foreach ($result as$r) {
                if (str_starts_with($r, $stem)) {$has = true;
                }
            }
            if (!$has) {
                $result[] =$decor;
            }
        }

        return $result;
    }

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

    /** Strict whole-word keyword matching. */
    private function keywordFallback(string $memories): array
    {
        $lower = mb_strtolower($memories);
        $found = [];

        foreach (self::KEYWORDS as $word) {
            if (preg_match('/\b' . preg_quote($word, '/') . '(?:s|es)?\b/u', $lower)) {
                $found[] = $word;
            }
        }

        return $found;
    }

    private function faithful(string $candidate, string $source, string $name, float $minCover): bool
    {
        $src = $this->tokens($source);
        $cand = $this->tokens($candidate);

        if (count($src) === 0 || count($cand) === 0) {
            return false;
        }

        $allowed = array_merge($src, $this->tokens($name));

        foreach ($cand as $token) {
            if (!in_array($token, $allowed, true)) {
                return false;
            }
        }

        $covered = count(array_intersect(array_unique($src), $cand));

        return ($covered / count(array_unique($src))) >= $minCover;
    }

    private function tokens(string $text): array
    {
        $text = mb_strtolower(str_replace('’', "'", $text));
        preg_match_all("/[a-z]+(?:'[a-z]+)?/", $text, $m);

        $out = [];
        foreach ($m[0] as $word) {
            $word = preg_replace("/'s$/", '', $word);
            if ($word === '' || in_array($word, self::STOPWORDS, true)) {
                continue;
            }
            $out[] = mb_substr($word, 0, 5);
        }

        return $out;
    }

    private function sentences(string $text, int $max): array
    {
        $parts = preg_split('/(?<=[.!?])\s+|\n+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $parts = array_values(array_filter(array_map('trim', $parts), fn ($s) => $s !== ''));

        return array_slice($parts, 0, $max);
    }

    private function finishSentence(string $s): string
    {
        $s = $this->ucfirst(trim($s));
        return preg_match('/[.!?]["”]?$/u', $s) ? $s : $s . '.';
    }

    private function ucfirst(string $s): string
    {
        return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
    }

    private function cleanName(string $name): string
    {
        $name = str_replace('’', "'", trim($name));
        // Strip off apostrophe trailing tags like "Clara's fierce" -> "Clara"
        $name = preg_replace("/'s\b.*$/u", '', $name);
        $name = preg_replace("/[^\p{L}\p{M}\s'\-]/u", '', $name);

        return trim((string) $name);
    }

    private function name(string $name): string
    {
        $name = $this->cleanName($name);
        return $name !== '' ? $name : 'my loved one';
    }

    private function trimMemories(string $text): string
    {
        return mb_substr(trim($text), 0, 1200);
    }

    private function clean(string $text): string
    {
        $text = str_replace(['**', '__'], '', $text);
        $text = preg_replace('/^(tribute|poem)\s*:\s*/i', '', trim($text));

        return trim($text);
    }
}