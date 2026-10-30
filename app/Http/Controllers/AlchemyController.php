<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateTributeJob;
use App\Models\Memory;
use App\Models\Tribute;
use Illuminate\Http\JsonResponse;

class AlchemyController extends Controller
{
    /** Create a tribute row and queue the generation. */
    public function start(Memory $memory): JsonResponse
    {
        $tribute = new Tribute();
        $tribute->memory_id = $memory->id;
        $tribute->status = 'pending';
        $tribute->save();

        GenerateTributeJob::dispatch($tribute->id);

        return response()->json(['tribute_id' => $tribute->id], 202);
    }

    /** Polled by altar.js. */
    public function show(Tribute $tribute): JsonResponse
    {
        $items = $tribute->items;
        if (is_string($items)) {
            $items = json_decode($items, true);
        }

        return response()->json([
            'id'     => $tribute->id,
            'status' => $tribute->status, // pending | processing | done | failed
            'story'  => $tribute->story,
            'poem'   => $tribute->poem,
            'items'  => $items ?: [],
            'error'  => $tribute->error,
        ]);
    }
}