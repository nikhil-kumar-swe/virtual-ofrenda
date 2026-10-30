// Home page logic: typing (always works), optional voice, then create the tribute.
const form = document.getElementById('memory-form');

if (form) {
  // If your audio route reads a different file key, change it here.
  const AUDIO_FIELD = 'audio';
  const MAX_RECORD_SECONDS = 15;

  const nameEl = document.getElementById('name');
  const textEl = document.getElementById('memory-text');
  const sourceEl = document.getElementById('source');
  const micBtn = document.getElementById('mic-btn');
  const micLabel = document.getElementById('mic-label');
  const micStatus = document.getElementById('mic-status');
  const submitBtn = document.getElementById('submit-btn');
  const errorBox = document.getElementById('error-box');
  const loader = document.getElementById('loader');
  const loaderStep = document.getElementById('loader-step');

  // ---------- helpers ----------
  const showError = (msg) => {
    errorBox.textContent = msg;
    errorBox.classList.remove('hidden');
  };
  const clearError = () => errorBox.classList.add('hidden');

  const setLoader = (on, step) => {
    loader.classList.toggle('hidden', !on);
    loader.classList.toggle('flex', on);
    if (step) loaderStep.textContent = step;
  };

  const setStatus = (msg) => {
    micStatus.textContent = msg || '';
    micStatus.classList.toggle('hidden', !msg);
  };

  async function request(url, options = {}) {
    const headers = { Accept: 'application/json', ...(options.headers || {}) };
    let res;
    try {
      res = await fetch(url, { ...options, headers });
    } catch (e) {
      throw new Error('Could not reach the app. Is `php artisan serve` still running?');
    }
    let data = {};
    try {
      data = await res.json();
    } catch (e) { /* non-JSON reply */ }

    if (!res.ok) {
      const firstValidation = data.errors ? Object.values(data.errors).flat()[0] : null;
      throw new Error(firstValidation || data.message || data.error || `Something went wrong (${res.status}).`);
    }
    return data;
  }

  // ---------- voice ----------
  const canRecord = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder);
  let recorder = null;
  let stream = null;
  let chunks = [];
  let timer = null;
  let seconds = 0;
  let transcribing = false;

  if (!canRecord) {
    micBtn.classList.add('hidden'); // typing fallback only
  }

  function resetMicUi() {
    micLabel.textContent = 'Speak';
    micBtn.disabled = false;
  }

  async function startRecording() {
    clearError();
    try {
      stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    } catch (e) {
      setStatus('');
      showError('Microphone not available. Please allow access, or just type your memory.');
      return;
    }

    const preferred = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4'];
    const mimeType = preferred.find((t) => MediaRecorder.isTypeSupported(t)) || '';
    recorder = new MediaRecorder(stream, mimeType ? { mimeType } : undefined);
    chunks = [];

    recorder.ondataavailable = (e) => { if (e.data.size) chunks.push(e.data); };
    recorder.onstop = handleRecordingStopped;
    recorder.start();

    seconds = 0;
    micLabel.textContent = 'Stop';
    setStatus(`● Recording… 0s (auto-stops at ${MAX_RECORD_SECONDS}s)`);
    timer = setInterval(() => {
      seconds += 1;
      setStatus(`● Recording… ${seconds}s (auto-stops at ${MAX_RECORD_SECONDS}s)`);
      if (seconds >= MAX_RECORD_SECONDS) stopRecording();
    }, 1000);
  }

  function stopRecording() {
    clearInterval(timer);
    if (recorder && recorder.state !== 'inactive') recorder.stop();
    if (stream) stream.getTracks().forEach((t) => t.stop());
  }

  async function handleRecordingStopped() {
    const type = recorder.mimeType || 'audio/webm';
    const blob = new Blob(chunks, { type });
    if (!blob.size) {
      resetMicUi();
      setStatus('');
      return;
    }

    transcribing = true;
    micBtn.disabled = true;
    submitBtn.disabled = true;
    micLabel.textContent = 'Transcribing…';
    setStatus('Transcribing on your device. On a small phone this can take a couple of minutes. You can keep this tab open.');

    const ext = type.includes('mp4') ? 'm4a' : 'webm';
    const fd = new FormData();
    fd.append(AUDIO_FIELD, blob, `memory.${ext}`);

    try {
      const data = await request('/api/memories/audio', { method: 'POST', body: fd });
      const transcript = data.text ?? data.transcript ?? data.memory?.text ?? data.data?.text ?? '';
      if (!transcript.trim()) {
        throw new Error('We could not hear any words in that clip. Try again, or type your memory.');
      }
      const existing = textEl.value.trim();
      textEl.value = existing ? `${existing} ${transcript.trim()}` : transcript.trim();
      sourceEl.value = 'voice';
      setStatus('Done. Check the words below and fix anything before continuing.');
    } catch (e) {
      setStatus('');
      showError(e.message);
    } finally {
      transcribing = false;
      submitBtn.disabled = false;
      resetMicUi();
    }
  }

  micBtn.addEventListener('click', () => {
    if (transcribing) return;
    if (recorder && recorder.state === 'recording') stopRecording();
    else startRecording();
  });

  // ---------- submit ----------
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearError();
    if (transcribing) return;

    const name = nameEl.value.trim();
    const text = textEl.value.trim();

    if (!name) { showError('Please tell us who we are remembering.'); nameEl.focus(); return; }
    if (!text) { showError('Please share at least one memory, spoken or typed.'); textEl.focus(); return; }

    submitBtn.disabled = true;
    setLoader(true, 'Saving your memory');

    try {
      // Step 1: save memory
      const mem = await request('/api/memories', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, text, source: sourceEl.value }),
      });
      const memoryId = mem.id ?? mem.memory_id ?? mem.memory?.id ?? mem.data?.id;
      if (!memoryId) { console.log('memories reply:', mem); throw new Error('Unexpected reply when saving the memory.'); }

      // Step 2: start generation
      setLoader(true, 'Starting the story');
      const alch = await request(`/api/memories/${memoryId}/alchemize`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: '{}',
      });
      const tributeId = alch.tribute_id ?? alch.tribute?.id ?? alch.data?.tribute_id ?? alch.data?.id ?? alch.id;
      if (!tributeId) { console.log('alchemize reply:', alch); throw new Error('Unexpected reply when starting the tribute.'); }

      // Step 3: go to altar (it polls for the result)
      setLoader(true, 'Opening the altar');
      window.location.href = `/altar/${tributeId}`;
    } catch (err) {
      setLoader(false);
      submitBtn.disabled = false;
      showError(err.message);
    }
  });
}