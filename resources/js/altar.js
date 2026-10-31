// Altar page: poll the tribute, show story/poem as they arrive, fade items in one by one.
const root = document.getElementById('altar-root');

if (root) {
  const id = root.dataset.id;
  const POLL_MS = 3000;
  const MAX_POLL_MS = 15 * 60 * 1000;
  const startedAt = Date.now();

  const $ = (sel) => document.getElementById(sel);
  const statusPanel = $('status-panel');
  const statusText = $('status-text');
  const statusHint = $('status-hint');
  const failedPanel = $('failed-panel');
  const failedMsg = $('failed-msg');
  const retryBtn = $('retry-btn');
  const storyCard = $('story-card');
  const storyBody = $('story-body');
  const poemCard = $('poem-card');
  const poemBody = $('poem-body');
  const title = $('altar-title');
  const doneActions = $('done-actions');
  const tiers = [$('tier-0'), $('tier-1'), $('tier-2')];

  // ---------- icon map (keyword -> emoji). Unknown items get DEFAULT_ICON ----------
  const DEFAULT_ICON = '🕊️';
  const ICONS = [
      [/glass|shard|pebble|gem|stone/, '💎'],
[/ocean|\bsea\b|wave|shell|beach/, '🌊'],
      [/raincoat|rain ?coat|jacket|coat|cardigan|sweater/, '🧥'],
[/lavender|lilac/, '💜'],
[/paint|watercolor|sketch|brush|canvas/, '🎨'],
[/\bbirds?\b|\bwings?\b|feather/, '🐦'],
[/thunder|storm|\brain\b/, '⛈️'],
[/leaf|leaves/, '🍃'],
[/jeep|wrangler|truck|\bcar\b/, '🚙'],
[/boots?\b|shoes?\b/, '🥾'],
[/ticket|stub/, '🎟️'],
[/receipt/, '🧾'],
    [/marigold|cempas|flower petal/, '🌼'],
        [/sunflower/, '🌻'],
    [/cider/, '🍎'],
    [/porch|bench|chair/, '🏡'],
    [/bush|shrub/, '🌿'],
    [/candle|velas?\b|flame|lantern|lamp/, '🕯️'],
    [/rose|flower|bouquet|lily|orchid/, '🌹'],
    [/photo|picture|portrait|frame/, '📷'],
    [/bread|pan de|roll|bun/, '🍞'],
    [/tamale|corn|masa|tortilla/, '🌽'],
    [/coffee|café|cafe/, '☕'],
    [/tea\b|chai/, '🍵'],
    [/soup|stew|pozole|mole|broth|rice|beans?/, '🍲'],
    [/cake|dessert|sweet|candy|cookie|pie|sugar/, '🍰'],
    [/chocolate|cocoa/, '🍫'],
    [/mango|fruit|apple|orange|banana|peach|grape/, '🍎'],
    [/tequila|wine|beer|drink|whisk|rum/, '🍷'],
    [/guitar|violin|piano|instrument/, '🎸'],
    [/music|song|radio|sing|hum|dance|record/, '🎶'],
    [/book|bible|letter|poem|story|note|diary/, '📖'],
    [/rosary|cross|prayer|church/, '📿'],
    [/hat|cap|glasses|scarf|shawl|apron/, '👒'],
    [/dog|puppy/, '🐕'],
    [/cat\b|kitten/, '🐈'],
    [/fish|fishing|boat/, '🐟'],
    [/garden|plant|tree|seed/, '🌱'],
    [/skull|calavera/, '💀'],
    [/watch|clock|time/, '⏰'],
    [/pipe|cigar|tobacco/, '🍂'],
  ];
  const iconFor = (name) => {
    const n = String(name).toLowerCase();
    for (const [re, icon] of ICONS) if (re.test(n)) return icon;
    return DEFAULT_ICON;
  };

  // ---------- helpers ----------
  const show = (el) => el.classList.remove('hidden');
  const hide = (el) => el.classList.add('hidden');

  function normalize(body) {
    const t = body?.tribute ?? body?.data ?? body ?? {};
    let items = t.items ?? t.altar_items ?? [];
    if (typeof items === 'string') {
      try { items = JSON.parse(items); } catch (e) { items = []; }
    }
    if (items && !Array.isArray(items) && Array.isArray(items.items)) items = items.items;
    if (!Array.isArray(items)) items = [];
    items = items
      .map((i) => (typeof i === 'string' ? i : i?.name ?? i?.item ?? i?.label ?? ''))
      .map((s) => String(s).trim())
      .filter(Boolean);
    return {
      status: t.status ?? 'pending',
      story: t.story ?? '',
      poem: t.poem ?? '',
            items: (() => {
        const u = [...new Set(items.map((s) => s.toLowerCase()))];
        return u.filter((a) => !u.some((b) => b !== a && b.includes(a)));
      })(),
      error: t.error ?? t.error_message ?? t.message ?? '',
      name: t.name ?? t.memory?.name ?? body?.name ?? '',
      memoryId: t.memory_id ?? t.memory?.id ?? null,
    };
  }

  let storyShown = false;
  let poemShown = false;
  let itemsShown = false;

  function renderStory(text) {
    if (storyShown || !text.trim()) return;
    storyShown = true;
    storyBody.innerHTML = '';
    text.trim().split(/\n{2,}/).forEach((para) => {
      const p = document.createElement('p');
      p.textContent = para.trim();
      storyBody.appendChild(p);
    });
    show(storyCard);
    storyCard.classList.add('fade-in');
  }

  function renderPoem(text) {
    if (poemShown || !text.trim()) return;
    poemShown = true;
    poemBody.textContent = text.trim();
    show(poemCard);
    poemCard.classList.add('fade-in');
  }

  function renderItems(list) {
    if (itemsShown || !list.length) return;
    itemsShown = true;

    // candles go on top, marigolds at the bottom, everything else in between
    const rank = (s) => (/candle|flame/.test(s) ? 0 : /marigold/.test(s) ? 2 : 1);
    const sorted = [...list].sort((a, b) => rank(a) - rank(b));

    const n = sorted.length;
const top = n >= 2 ? (n > 7 ? 2 : 1) : 0;   // candle(s) always get the top tier
const rest = n - top;
const bottom = Math.ceil(rest / 2);
const middle = rest - bottom;
const sizes = [top, middle, bottom];

    let idx = 0;
    let delay = 0;
    sizes.forEach((size, tierIdx) => {
      for (let k = 0; k < size; k++) {
        const name = sorted[idx++];
        const el = document.createElement('div');
        el.className = 'altar-item';
        const icon = document.createElement('div');
        icon.className = 'altar-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = iconFor(name);
        const label = document.createElement('span');
        label.className = 'altar-label';
        label.textContent = name;
        el.append(icon, label);
        tiers[tierIdx].appendChild(el);
        setTimeout(() => el.classList.add('show'), delay);
        delay += 700;
      }
    });
  }

  function setProgress(t) {
    if (t.status === 'pending') {
      statusText.textContent = 'Waiting in line…';
      if (Date.now() - startedAt > 30000) {
        statusHint.textContent = 'Still waiting? Check that `php artisan queue:work` and the LLM server are running.';
        show(statusHint);
      }
    } else if (!t.story) {
      statusText.textContent = 'Writing the tribute from your memories…';
    } else if (!t.poem) {
      statusText.textContent = 'Composing the poem…';
    } else {
      statusText.textContent = 'Choosing what to place on the altar…';
    }
  }

  function showFailed(t) {
    hide(statusPanel);
    failedMsg.textContent = t.error || 'Something went wrong while making the tribute.';
    show(failedPanel);
    if (t.memoryId) show(retryBtn);
    retryBtn.onclick = async () => {
      retryBtn.disabled = true;
      try {
        const res = await fetch(`/api/memories/${t.memoryId}/alchemize`, {
          method: 'POST',
          headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
          body: '{}',
        });
        const data = await res.json();
        const newId = data.tribute_id ?? data.tribute?.id ?? data.data?.tribute_id ?? data.data?.id ?? data.id;
        if (!res.ok || !newId) throw new Error(data.message || 'Could not restart.');
        window.location.href = `/altar/${newId}`;
      } catch (e) {
        failedMsg.textContent = e.message;
        retryBtn.disabled = false;
      }
    };
  }

  // ---------- polling ----------
  async function poll() {
    if (Date.now() - startedAt > MAX_POLL_MS) {
      showFailed({ error: 'This is taking too long. Please check the servers and try again.' });
      return;
    }

    let t;
    try {
      const res = await fetch(`/api/tributes/${id}`, { headers: { Accept: 'application/json' } });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      t = normalize(await res.json());
    } catch (e) {
      statusText.textContent = 'Reconnecting to the app…';
      setTimeout(poll, POLL_MS);
      return;
    }

    if (t.name) title.textContent = `For ${t.name}`;

    // show whatever has arrived so far
    renderStory(t.story);
    renderPoem(t.poem);
    renderItems(t.items);

    if (t.status === 'failed') {
      showFailed(t);
      return;
    }
    if (t.status === 'done') {
      hide(statusPanel);
      show(doneActions);
      return;
    }

    setProgress(t);
    setTimeout(poll, POLL_MS);
  }

  poll();
}