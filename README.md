# Virtual Ofrenda & Story Alchemist

A private web app for remembering someone you have lost. You speak or type a few memories of them, and a small open-weight AI running **on your own device** turns those memories into a tribute story and a short poem, then decorates a candlelit digital *ofrenda* (altar) with the things you mentioned: candles, marigolds, a favourite book, a bright yellow raincoat.

Nothing leaves the device. There are no cloud APIs, no accounts and no tracking. The whole project was built on a low-end device with 2 GB of RAM.

Built for the **DEV Hacktoberfest 2026, Week 4 challenge ("Ghost in the Machine")**.

- **Demo video:** https://www.youtube.com/watch?v=wHRRkFlyoqc

---

## What it does

1. **Voice-to-Memory.** Record a memory in the browser. It is transcribed locally with whisper.cpp (`tiny.en`) and lands in a text box where you can edit it. Typing works just as well and is the main path on slow hardware.
2. **Story Alchemist.** A local Gemma model writes one tribute story and one short poem, using only what you said.
3. **Altar Decorator.** The model picks the objects from your memory (a candle, a book, marigolds...). Each one is checked against your text, mapped to an icon, and placed on a three-tier altar where the items fade in one by one.

It also has a calm candlelit theme (Playfair Display for the story, Inter for the interface), a "Lighting the candles..." loader, a retry button when something fails, friendly messages when a model server is offline, and a **"Runs on your device"** privacy badge.

## Built on a low-end device

The project was developed on a low-end device with 2 GB of RAM and a 32-bit ARM processor. Ollama does not support 32-bit ARM, so the project talks to **llama.cpp** directly. That is why the model is a 270M-parameter Gemma and why everything is small. On a laptop or desktop you can point the same app at a larger model and it will be faster and better.

## Tech stack

| Part | Technology |
|---|---|
| Backend | Laravel 13.39 on PHP 8.5, queued job for generation |
| Frontend | Blade, Tailwind CSS, vanilla JavaScript (fetch polling) |
| Database | SQLite |
| Text model | `gemma-3-270m-it-Q8_0.gguf`, served by `llama-server` (llama.cpp) |
| Speech model | `ggml-tiny.en.bin` (Whisper tiny.en), served by `whisper-server` (whisper.cpp) |
| Icons | Emoji (no image credits needed) |

## Models used

| Model | File | Role |
|---|---|---|
| Gemma 3 270M instruction-tuned (Google) | `gemma-3-270m-it-Q8_0.gguf` (~300 MB) | Story, poem and altar items |
| Whisper tiny.en (OpenAI weights, ggml format) | `ggml-tiny.en.bin` | Speech to text |

The model files are **not** included in this repository. Download them as described below.

---

## Setup

### 1. Requirements

- PHP 8.2+ with Composer (developed on PHP 8.5.1)
- Node.js and npm
- A C/C++ toolchain and CMake to build llama.cpp and whisper.cpp
- Git

### 2. The Laravel app

```bash
git clone https://github.com/nikhil-kumar-swe/virtual-ofrenda.git
cd virtual-ofrenda

composer install
npm install

cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate
```

In `.env`, make sure these are set:

```env
DB_CONNECTION=sqlite

LLM_URL=http://127.0.0.1:8081
LLM_MODEL=gemma-3-270m-it-Q8_0
WHISPER_URL=http://127.0.0.1:8080
```

These feed `config/ofrenda.php` (`llm_url`, `llm_model`, `whisper_url`).

### 3. Build llama.cpp and download Gemma

```bash
git clone https://github.com/ggml-org/llama.cpp
cd llama.cpp
cmake -B build
cmake --build build -j
```

On **32-bit ARM** devices, configure with:

```bash
cmake -B build -DGGML_NATIVE=OFF -DGGML_LLAMAFILE=OFF
```

Download the `gemma-3-270m-it` model in GGUF format, quantisation `Q8_0`, from Hugging Face and save it as `~/models/gemma-3-270m-it-Q8_0.gguf`. Check the Gemma terms of use on the model page before you download.

### 4. Build whisper.cpp and download tiny.en

```bash
git clone https://github.com/ggml-org/whisper.cpp
cd whisper.cpp
cmake -B build            # add -DGGML_NATIVE=OFF -DGGML_LLAMAFILE=OFF on 32-bit ARM
cmake --build build -j
sh ./models/download-ggml-model.sh tiny.en
```

---

## Running it

You need four things running. Use a separate terminal window or tab for each.

**1. LLM server**

```bash
cd ~/llama.cpp
./build/bin/llama-server -m ~/models/gemma-3-270m-it-Q8_0.gguf -c 2048 -t 4 --port 8081
```

**2. Whisper server** (only needed for voice input)

```bash
cd ~/whisper.cpp
./build/bin/whisper-server -m models/ggml-tiny.en.bin --port 8080 --convert -t 4
```

**3. Laravel**

```bash
cd ~/virtual-ofrenda
php artisan serve          # http://127.0.0.1:8000
php artisan queue:work     # in another terminal; this runs the generation job
npm run dev                # or: npm run build
```

> **Low-memory devices:** on a device with about 2 GB of RAM (like the development device), run the LLM server and the Whisper server one at a time to save RAM. Use Whisper to transcribe, stop it, then start the LLM server before you press the create button. On a machine with more RAM you can run both at once.

Open `http://127.0.0.1:8000`, enter a name, speak or type your memories, and press the button to light the candles.

## How it works

```
mic -> Laravel upload -> whisper-server -> text saved in SQLite
    -> queued job -> llama-server (Gemma)
    -> story + poem + altar items -> browser polls and renders the altar
```

| Route | Purpose |
|---|---|
| `POST /api/memories` | Save typed memory text |
| `POST /api/memories/audio` | Upload audio and get a transcript |
| `POST /api/memories/{id}/alchemize` | Start generating the tribute |
| `GET /api/tributes/{id}` | Status and results, polled by `altar.js` |

Pages: `/` (home) and `/altar/{id}` (the altar).

### Guards against invented details

A 270M model can make things up, so the app does not trust it blindly:

- **Item grounding.** Each altar item the model suggests is checked against your memory text. Candles and marigolds are always included.
- **Poem from your own words.** The poem is built line by line from your own sentences. A model rewrite is used only if it passes the same grounding check.
- **Duplicate cleanup.** Near-duplicate items ("green shards" and "frosted green shards") are merged.
- **Friendly failures.** If a model server is offline, the app shows a clear message and a retry button instead of crashing.

## Project structure

```
app/Http/Controllers/   MemoryController, AlchemyController
app/Models/             Memory, Tribute
app/Services/           LlmService, WhisperService
app/Jobs/               GenerateTributeJob
config/ofrenda.php      llm_url, llm_model, whisper_url
resources/views/        home.blade.php, altar.blade.php
resources/js/           recorder.js, altar.js
```

## Testing

- **Test environment:** an Android phone (2 GB RAM, 32-bit ARM) running everything in Termux. The setup steps above are standard and should also work on Linux, macOS and Windows (WSL), but they have not been tested there.
- Full flow tested end to end with voice and with typing.
- Tested with **the internet off** (airplane mode). The app, both models and the fonts all work offline, and the code contains no external font or API links.
- Tested at mobile screen size in a browser on an Android phone.
- Tested the "no invented facts" claim on three memories of different lengths (about 38, 85 and 130 words). Details are in the limitations below.

## Known limitations

These are not hidden. They are what a 270M model on low-end hardware really does.

- **The story mostly repeats your own words.** On all three test memories the story added no invented facts, but largely because the small model stays very close to the text it was given.
- **It can get the narrator wrong.** On a short test memory about "Marcus", the story began "I always carried a pocketful of sea glass" instead of "Marcus always carried...".
- **The poem can add imagery.** It does not invent people or events, but it can add phrases such as "secrets held tight" that you never said.
- **Altar items can be imperfect.** The model sometimes returns an idea instead of an object (for example "quiet invitation"), misses an important object, or an item may show the default dove icon.
- **Whisper is slow on low-end hardware.** On the 32-bit ARM development device, an 11-second clip took about two minutes, and `tiny.en` sometimes mishears. Typing is the dependable path there. A desktop CPU will be much faster.
- **No history page.** Tributes are saved in the local SQLite database, but there is no page that lists them. You return to one by its link.
- **Single user, no login.** Anyone who can open the app on the device can see saved memories.

Stretch ideas that were not built: prompt chips, export, "ask the memories", and narration.

## Credits and licences

- [Gemma](https://ai.google.dev/gemma) by Google, used under the Gemma Terms of Use. Model weights are not redistributed here.
- [llama.cpp](https://github.com/ggml-org/llama.cpp) (MIT)
- [whisper.cpp](https://github.com/ggml-org/whisper.cpp) (MIT) and the OpenAI Whisper model (MIT)
- [Laravel](https://laravel.com) and [Tailwind CSS](https://tailwindcss.com) (MIT)
- Fonts: Playfair Display and Inter (SIL Open Font License), bundled locally
- Icons: standard emoji, so there are no icon credits to list

The code in this repository is released under the **MIT License** (see `LICENSE`).

## Built during the challenge

This is a brand-new project built for the Week 4 challenge. The first commit is dated **2026-10-29**, and all commits were made before the contest deadline (11:59 PM PDT on Oct 31, 2026, which is 12:29 PM IST on Nov 1).

Built by Nikhil Kumar ([@nikhil-kumar-swe](https://github.com/nikhil-kumar-swe)).
