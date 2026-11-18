// Convert the chat reply into WhatsApp formatting and pair it with the sender.
// v2 (18 Nov 2026): welcome line only once per chat (12h), catalogue fallback at most once per chat (6h),
// one reply per incoming message, and a 2-hour bot pause after handing a chat to the team.
const sd = $getWorkflowStaticData('global');
sd.welcomed = sd.welcomed || {}; sd.fallback = sd.fallback || {}; sd.human = sd.human || {};
const now = Date.now();
const FALLBACK_MARK = 'wa.me/c/254714994898';
const src = $('Extract Messages').all();
const res = [];
const done = {};
$input.all().forEach((item, i) => {
  const r = item.json || {};
  const s = (src[i] || src[0]).json;
  let text = String(r.reply || '').trim();
  if (r.silent || !text) return;
  const to = String(r.phone || s.phone || ''); const uid = String(s.user_id || '');
  if (!to && !uid) return;
  const who = to || uid;
  if (done[who]) return; done[who] = 1;                                         // one reply per chat per run
  // Welcome line: only the first time in 12 hours
  const W = /^\s*Welcome to NURA Beauty[^!.?\n]*[!.]?\s*/i;
  if (W.test(text)) {
    if (sd.welcomed[who] && now - sd.welcomed[who] < 12 * 3600e3) text = text.replace(W, '').trim();
    else sd.welcomed[who] = now;
  }
  if (!text) return;
  // Catalogue fallback (AI failed): at most once per chat in 6 hours, otherwise stay quiet
  const isFallback = text.startsWith('Thank you for reaching out to NURA Beauty! View all our wigs');
  if (isFallback) {
    if (sd.fallback[who] && now - sd.fallback[who] < 6 * 3600e3) return;
    sd.fallback[who] = now;
  }
  if (r.needs_human || /team member will (follow up|get back|contact)/i.test(text)) sd.human[who] = now;
  text = text
    .replace(/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g, '$1: $2')
    .replace(/\*\*([^*]+)\*\*/g, '*$1*')
    .replace(/^#{1,6}\s*/gm, '');
  if (r.checkout_url && text.indexOf(r.checkout_url) === -1) text += '\n\nCheckout: ' + r.checkout_url;
  res.push({ json: { to, user_id: uid, phone_number_id: s.phone_number_id, text: text.slice(0, 4000) } });
});
for (const m of [sd.welcomed, sd.fallback, sd.human]) for (const k of Object.keys(m)) if (now - m[k] > 2 * 86400e3) delete m[k];
return res;
