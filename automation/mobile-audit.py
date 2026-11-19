#!/usr/bin/env python3
"""NURA Beauty mobile audit: loads every URL in the sitemap on a 390x844 phone screen and reports
pages that fail to load, broken images, and images or text running off the screen.
Usage: python3 mobile-audit.py [out.json]   (needs: pip playwright + chromium; see AUTOMATION.md)"""
import asyncio, json, re, sys, urllib.request
SITE = 'https://nurabeauty.co.ke'
def fetch(u):
    req = urllib.request.Request(u, headers={'User-Agent': 'Mozilla/5.0 NURA-audit'})
    return urllib.request.urlopen(req, timeout=40).read().decode('utf-8', 'ignore')
def sitemap_urls():
    idx = fetch(SITE + '/sitemap_index.xml')
    urls = []
    for sm in re.findall(r'<loc>([^<]+)</loc>', idx):
        for t in range(3):
            try:
                urls += [u for u in re.findall(r'<loc>([^<]+)</loc>', fetch(sm)) if not re.search(r'\.(jpe?g|png|webp|gif)$', u, re.I)]
                break
            except Exception:
                pass
    return list(dict.fromkeys(urls))
JS = r"""() => {
 const W = innerWidth, out = {db: /Error establishing a database connection/i.test(document.body ? document.body.innerText.slice(0, 800) : ''), offImg: [], offText: [], broken: []};
 const name = e => e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + (typeof e.className == 'string' && e.className.trim() ? '.' + e.className.trim().split(/\s+/).slice(0, 2).join('.') : '');
 const hidden = e => { for (let p = e; p && p !== document.body; p = p.parentElement) { const c = getComputedStyle(p); if (c.display == 'none' || c.visibility == 'hidden' || +c.opacity == 0 || c.position == 'fixed') return true; } return false; };
 const inScroller = e => { for (let p = e.parentElement; p && p !== document.body && p !== document.documentElement; p = p.parentElement) { const c = getComputedStyle(p); if (/hidden|auto|scroll|clip/.test(c.overflowX)) return true; } return false; };
 for (const i of document.images) {
   const r = i.getBoundingClientRect(); if (r.width < 40 || r.height < 40 || hidden(i)) continue;
   if (i.complete && i.naturalWidth === 0 && (i.currentSrc || i.src)) out.broken.push((i.currentSrc || i.src).split('/').pop().slice(0, 70));
   else if (r.right > W + 2 && r.left < W && !inScroller(i)) out.offImg.push(name(i) + ' ' + Math.round(r.width) + 'px wide');
   else if (r.right > W + 2 && r.left < W && inScroller(i) && i.closest('.entry-content,article')) {
     let clipped = false; for (let p = i.parentElement; p && p !== document.body; p = p.parentElement) { const c = getComputedStyle(p); if (/hidden|auto|scroll|clip/.test(c.overflowX)) { const pr = p.getBoundingClientRect(); if (pr.right <= W + 2 && pr.width >= W - 60 && !/flex-viewport|slider|swiper|carousel|gallery|rail|scroll/i.test(p.className + ' ' + (p.parentElement ? p.parentElement.className : ''))) clipped = true; break; } }
     if (clipped) out.offImg.push(name(i) + ' ' + Math.round(r.width) + 'px wide (cut off)');
   }
 }
 for (const e of document.querySelectorAll('body *')) {
   if (!e.childNodes.length || ![...e.childNodes].some(n => n.nodeType === 3 && n.textContent.trim().length > 2)) continue;
   const r = e.getBoundingClientRect(); if (!r.width || r.top > 20000 || hidden(e)) continue;
   const c = getComputedStyle(e);
   const cut = (r.right > W + 2 && r.left < W && !inScroller(e)) || (c.whiteSpace == 'nowrap' && e.scrollWidth > e.clientWidth + 2 && r.right > W - 30 && !e.closest('nav.nura-filter,[class*=chip],[class*=rail],[class*=marquee],[class*=ticker]'));
   if (cut) out.offText.push(name(e) + ': "' + e.textContent.trim().slice(0, 50) + '"');
 }
 out.offImg = [...new Set(out.offImg)].slice(0, 5); out.offText = [...new Set(out.offText)].slice(0, 5); out.broken = [...new Set(out.broken)].slice(0, 5);
 out.hscroll = document.documentElement.scrollWidth > W + 2;
 return out; }"""
async def main(outfile):
    from playwright.async_api import async_playwright
    urls = sitemap_urls()
    res = {}
    async with async_playwright() as p:
        b = await p.chromium.launch()
        ctx = await b.new_context(viewport={'width': 390, 'height': 844}, device_scale_factor=2, is_mobile=True, has_touch=True,
            user_agent='Mozilla/5.0 (Linux; Android 14; SM-A546E) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Mobile Safari/537.36')
        sem = asyncio.Semaphore(4)
        async def one(u):
            async with sem:
                last = None
                for attempt in range(2):
                    pg = await ctx.new_page()
                    try:
                        r = await pg.goto(u, wait_until='load', timeout=45000)
                        await pg.wait_for_timeout(1200)
                        d = await pg.evaluate(JS); d['status'] = r.status if r else 0
                        await pg.close()
                        if d['status'] >= 500 or d['db']:
                            last = d; await asyncio.sleep(5); continue
                        res[u] = d; return
                    except Exception as e:
                        last = {'status': 0, 'error': str(e)[:120]}
                        try: await pg.close()
                        except Exception: pass
                res[u] = last
        await asyncio.gather(*[one(u) for u in urls])
        await b.close()
    summary = {'checked': len(res), 'failed': [], 'broken_images': [], 'images_off_screen': [], 'text_off_screen': [], 'sideways_scroll': []}
    for u, d in sorted(res.items()):
        path = u.replace(SITE, '') or '/'
        if d.get('error') or d.get('status', 0) >= 400 or d.get('db'):
            summary['failed'].append({'page': path, 'why': d.get('error') or ('database error' if d.get('db') else 'HTTP %s' % d.get('status'))}); continue
        if d['broken']: summary['broken_images'].append({'page': path, 'images': d['broken']})
        if d['offImg']: summary['images_off_screen'].append({'page': path, 'items': d['offImg']})
        if d['offText']: summary['text_off_screen'].append({'page': path, 'items': d['offText']})
        if d['hscroll']: summary['sideways_scroll'].append(path)
    json.dump(summary, open(outfile, 'w'), indent=1)
    print(json.dumps(summary, indent=1)[:6000])
if __name__ == '__main__':
    asyncio.run(main(sys.argv[1] if len(sys.argv) > 1 else 'audit-summary.json'))
