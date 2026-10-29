<?php

return [
    'llm_url' => env('LLM_URL', 'http://127.0.0.1:8081'),
    'llm_model' => env('LLM_MODEL', 'gemma-3-270m-it-Q8_0'),
    'whisper_url' => env('WHISPER_URL', 'http://127.0.0.1:8080'),
];