<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhisperService
{
    /**
     * Send an audio file (webm, wav, mp3...) to whisper-server and return the text.
     * whisper-server must be started with --convert so ffmpeg handles browser formats.
     */
    public function transcribe(string $path): string
    {
        if (!is_file($path)) {
            throw new RuntimeException('Audio file not found.');
        }

        $url = rtrim((string) config('ofrenda.whisper_url'), '/') . '/inference';

        try {
            $response = Http::connectTimeout(5)
                ->timeout(300)
                ->attach('file', file_get_contents($path), basename($path))
                ->post($url, [
                    'response_format' => 'json',
                    'temperature'     => '0.0',
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('The local speech model is offline. Start whisper-server or type your memory instead.', 0, $e);
        }

        if ($response->failed()) {
            throw new RuntimeException('Speech model returned HTTP ' . $response->status() . '.');
        }

        $text = (string) data_get($response->json(), 'text', '');

        // Remove markers like [BLANK_AUDIO] or (music), and tidy whitespace.
        $text = preg_replace('/\[[^\]]*\]|\([^)]*\)/', ' ', $text);
        $text = trim(preg_replace('/\s+/', ' ', $text));

        if ($text === '') {
            throw new RuntimeException('No speech was detected. Please try again or type your memory.');
        }

        return $text;
    }
}