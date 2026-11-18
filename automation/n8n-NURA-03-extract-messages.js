// NURA 03 - turn Meta's webhook payload into chat messages for NURA 01.
// v2 (18 Nov 2026): skips old/redelivered messages, duplicates, reactions/stickers,
// short "ok/thanks" replies, and chats handed to a human in the last 2 hours.
// ---- CONFIG ----
const PHONE_NUMBER_ID = '1190576' + '524148420';
const MAX_AGE_SEC = 15 * 60;          // ignore messages older than 15 minutes (Meta redeliveries)
const HUMAN_PAUSE_MS = 2 * 3600e3;    // bot stays quiet after "a team member will follow up"
// ----------------
const sd = $getWorkflowStaticData('global');
sd.seen = sd.seen || {}; sd.lastIn = sd.lastIn || {}; sd.human = sd.human || {};
const nowMs = Date.now(), nowSec = Math.floor(nowMs / 1000);
for (const k of Object.keys(sd.seen)) if (nowMs - sd.seen[k] > 2 * 86400e3) delete sd.seen[k];
const ACK = /^(ok+|okay|okey|k+|its ok(ay)?|it'?s ok(ay)?|ok(ay)? thanks?|sawa( sawa)?|poa|fine|alright|noted|cool|nice|good|great|thanks?|thank ?(you|u)( so much)?|asante( sana)?|welcome|you'?re welcome|bye|goodbye|good night|usiku mwema)[\s.!]*$/i;
const body = $input.first().json.body || {};
const out = [];
if (body.object !== 'whatsapp_business_account') return [];
for (const entry of body.entry || []) {
  for (const change of entry.changes || []) {
    const v = change.value || {};
    if (change.field !== 'messages' || !v.messages) continue; // delivery/read statuses are ignored
    const pnid = String(v.metadata?.phone_number_id || '');
    if (PHONE_NUMBER_ID && pnid !== PHONE_NUMBER_ID) continue;
    const names = {};
    for (const c of v.contacts || []) { const k = String(c.wa_id || c.user_id || ''); if (k) names[k] = c.profile?.name || ''; }
    for (const m of v.messages) {
      const from = String(m.from || '');
      const uid = String(m.from_user_id || '');
      if (!from && !uid) continue;
      const who = from || uid;
      const mid = String(m.id || '');
      if (mid) { if (sd.seen[mid]) continue; sd.seen[mid] = nowMs; }           // duplicate delivery
      const ts = Number(m.timestamp || 0);
      if (ts && nowSec - ts > MAX_AGE_SEC) continue;                            // old message redelivered
      if (['reaction', 'sticker', 'system', 'unsupported', 'ephemeral', 'request_welcome', 'order'].includes(m.type)) continue;
      if (sd.human[who] && nowMs - sd.human[who] < HUMAN_PAUSE_MS) continue;   // owner is handling this chat
      let text = '';
      if (m.type === 'text') text = m.text?.body || '';
      else if (m.type === 'button') text = m.button?.text || '';
      else if (m.type === 'interactive') text = m.interactive?.button_reply?.title || m.interactive?.list_reply?.title || '';
      else if (m.type === 'image' && m.image?.caption) text = m.image.caption;
      else text = '[The customer sent a ' + m.type + ' message, which you cannot open. Ask them politely to type their question.]';
      text = String(text).trim();
      if (!text) continue;
      const known = !!sd.lastIn[who];
      sd.lastIn[who] = nowMs;
      const noEmoji = text.replace(/[\p{Extended_Pictographic}\uFE0F\u200d\s]/gu, '');
      if (known && (ACK.test(text) || noEmoji === '')) continue;                // "ok", "thanks", emoji only: no reply
      out.push({ json: { channel: 'whatsapp', phone: from, user_id: uid, name: names[from] || names[uid] || '', message: text, message_id: mid, phone_number_id: pnid } });
    }
  }
}
for (const k of Object.keys(sd.lastIn)) if (nowMs - sd.lastIn[k] > 30 * 86400e3) delete sd.lastIn[k];
return out;
