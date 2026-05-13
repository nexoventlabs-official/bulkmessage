const Redis = require('ioredis');

let client = null;
let ready = false;

const PREFIX = 'bc:';
const SEQ_KEY = PREFIX + 'seq:global';

function init() {
  if (client) return client;
  const host = process.env.REDIS_HOST;
  const port = Number(process.env.REDIS_PORT || 6379);
  const password = process.env.REDIS_PASSWORD || undefined;

  if (!host) {
    console.warn('[redis] REDIS_HOST not set - ordering via Redis disabled');
    return null;
  }

  client = new Redis({
    host,
    port,
    password,
    lazyConnect: false,
    enableReadyCheck: true,
    maxRetriesPerRequest: 3,
    retryStrategy(times) { return Math.min(times * 200, 2000); },
  });

  client.on('connect', () => console.log('[redis] connecting to', host + ':' + port));
  client.on('ready', async () => {
    ready = true;
    console.log('[redis] ready');
  });
  client.on('error', (e) => console.error('[redis] error', e.message));
  client.on('end', () => { ready = false; console.log('[redis] disconnected'); });

  return client;
}

async function nextSeq() {
  if (!client || !ready) return 0;
  try {
    return await client.incr(SEQ_KEY);
  } catch (e) {
    console.error('[redis] nextSeq failed', e.message);
    return 0;
  }
}

async function get(key) {
  if (!client || !ready) return null;
  try { return await client.get(PREFIX + key); } catch { return null; }
}
async function set(key, value, ttlSeconds) {
  if (!client || !ready) return;
  try {
    if (ttlSeconds) await client.set(PREFIX + key, value, 'EX', ttlSeconds);
    else await client.set(PREFIX + key, value);
  } catch (e) { console.error('[redis] set', e.message); }
}
async function del(key) {
  if (!client || !ready) return;
  try { await client.del(PREFIX + key); } catch { /* noop */ }
}

module.exports = { init, nextSeq, get, set, del, isReady: () => ready };
