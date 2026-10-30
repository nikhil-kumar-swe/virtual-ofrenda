<?php

namespace App\Http\Controllers;

use App\Models\Memory;
use App\Services\WhisperService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MemoryController extends Controller
{
    /** Save typed (or already-transcribed) text. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'   => ['nullable', 'string', 'max:80'],
            'text'   => ['required', 'string', 'min:10', 'max:3000'],
            'source' => ['nullable', 'in:typed,voice'],
        ], [
            'text.required' => 'Please share a memory first.',
            'text.min'      => 'Please write a little more, a full sentence or two.',
            'text.max'      => 'That is a bit long. Please keep it under 3000 characters.',
        ]);

        $memory = new Memory();
        $memory->name = trim($data['name'] ?? '');
        $memory->text = trim($data['text']);
        $memory->source = $data['source'] ?? 'typed';
        $memory->save();

        return response()->json(['id' => $memory->id], 201);
    }

    /** Receive a recording, transcribe it locally, return the text. Audio is deleted right after. */
    public function audio(Request $request, WhisperService $whisper): JsonResponse
    {
        $request->validate([
            'audio' => ['required', 'file', 'max:10240'],
        ], [
            'audio.required' => 'No recording was received.',
        ]);

        $file = $request->file('audio');
        $ext = preg_replace('/[^a-z0-9]/i', '', $file->getClientOriginalExtension()) ?: 'webm';
        $relative = $file->storeAs('audio', Str::uuid() . '.' . $ext);

        try {
            $text = $whisper->transcribe(Storage::path($relative));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        } finally {
            Storage::delete($relative);
        }

        return response()->json(['text' => $text]);
    }
}