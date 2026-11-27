#!/usr/bin/env python3
"""NURA Beauty - WhatsApp order form console check (read-only).

Loads the live product page, opens the 'Order on WhatsApp' form, and for
Mombasa, Nakuru and Nairobi/Karen switches between every delivery option
(rider, Bus & shuttle, Fargo Courier / G4S). Records browser console errors,
uncaught page errors and failed requests. Never submits the form and never
presses the M-Pesa prompt button. Writes a JSON report and exits 1 on errors.

Usage: pip install playwright && python -m playwright install chromium
       python tools/wa-form-console-check.py [report.json]
"""
import json, sys, time
from datetime import datetime, timezone
from playwright.sync_api import sync_playwright

URL = "https://nurabeauty.co.ke/product/astylsst-texture-setting-spray-400-ml-hair-styling-spray-for-hold-and-shine/"
ITEM_PRICE = 500  # ASTYLSST Texture Spray, quantity 1
# (county, town typed). Expected fees are checked where known; every option is also
# checked for "total = item + option price".
CASES = [("Mombasa", "Mombasa"), ("Nakuru", "Nakuru"), ("Kisumu", "Kisumu"), ("Uasin Gishu", "Eldoret"),
         ("Kiambu", "Thika"), ("Kiambu", "Juja"), ("Kiambu", "Ruiru"), ("Kajiado", "Kitengela"),
         ("Nairobi", "Westlands"), ("Nairobi", "Karen")]
# Shuttle towns: Bus & shuttle must be listed first and pre-selected (plugin 1.136.0).
SHUTTLE_FIRST = {"Thika", "Juja", "Ruiru", "Kitengela"}
EXPECTED = {  # option label contains -> fee in KSh (normal pricing, order under KSh 10,000)
    "Bus & shuttle": 350,
    "Fargo Courier": 550,
    "Rider delivery to Westlands": 300,
    "Rider delivery to Karen": 800,
}

def ksh(text):
    import re
    if "FREE" in text.upper() and not re.search(r"KSh\s*[1-9]", text):
        return 0
    m = re.findall(r"KSh\s*([\d,]+)", text)
    return int(m[-1].replace(",", "")) if m else None

def main(out):
    rep = {"url": URL, "started": datetime.now(timezone.utc).isoformat(), "console_errors": [],
           "page_errors": [], "failed_requests": [], "unexpected_totals": [], "steps": []}
    with sync_playwright() as p:
        b = p.chromium.launch()
        pg = b.new_page(viewport={"width": 390, "height": 844})
        pg.on("console", lambda m: m.type == "error" and rep["console_errors"].append({"text": m.text, "at": m.location.get("url", "")}))
        pg.on("pageerror", lambda e: rep["page_errors"].append(str(e)))
        pg.on("requestfailed", lambda r: rep["failed_requests"].append({"url": r.url, "error": r.failure}))
        # Safety net: block any order submission or M-Pesa prompt request.
        def guard(route):
            body = route.request.post_data or ""
            if "nurax_wa_order" in body or "nurax_wa_stk" in body:
                rep.setdefault("blocked", []).append(route.request.url)
                return route.abort()
            return route.continue_()
        pg.route("**/admin-ajax.php*", guard)
        pg.goto(URL, wait_until="domcontentloaded", timeout=45000)
        pg.wait_for_timeout(2500)
        pg.locator("[data-nwo-open='product']").first.click()
        pg.wait_for_selector("#nwo-location", state="visible", timeout=15000)
        for county, town in CASES:
            pg.select_option("#nwo-location", county)
            pg.fill("#nwo-town", town)
            if pg.locator("#nwo-area").is_visible():
                pg.fill("#nwo-area", town)
            pg.wait_for_timeout(2000)
            opts = pg.locator("[data-nwo-ship] input[name='ship_opt']")
            n = opts.count()
            if town in SHUTTLE_FIRST and n:
                first = opts.nth(0).locator("xpath=..").inner_text().strip().replace("\n", " ")
                checked = opts.nth(0).is_checked()
                if "Bus & shuttle" not in first or not checked:
                    rep["unexpected_totals"].append({"county": county, "town": town, "option": first,
                        "problems": ["Bus & shuttle should be first and pre-selected (first: %s, selected: %s)" % (first, checked)]})
            if n == 0:
                rep["steps"].append({"county": county, "town": town, "note": "no delivery options shown"})
            for i in range(n):
                o = opts.nth(i)
                o.check(force=True)
                pg.wait_for_timeout(600)
                label = o.locator("xpath=..").inner_text().strip().replace("\n", " ")
                tl = pg.locator("[data-nwo-foottotal]")
                total = tl.first.inner_text() if tl.count() else ""
                step = {"county": county, "town": town, "option": label, "total": total.strip()}
                fee, tot = ksh(label), ksh(total)
                problems = []
                if fee is None or tot is None:
                    problems.append("could not read option price or total")
                elif tot != ITEM_PRICE + fee:
                    problems.append("total %s is not item %s + option %s" % (tot, ITEM_PRICE, fee))
                for key, want in EXPECTED.items():
                    if key in label and fee is not None and fee != want:
                        problems.append("expected %s at KSh %s, form shows KSh %s" % (key, want, fee))
                if problems:
                    step["problems"] = problems
                    rep["unexpected_totals"].append(step)
                rep["steps"].append(step)
        b.close()
    rep["finished"] = datetime.now(timezone.utc).isoformat()
    rep["ok"] = not (rep["console_errors"] or rep["page_errors"] or rep["failed_requests"] or rep["unexpected_totals"])
    with open(out, "w") as f:
        json.dump(rep, f, indent=2)
    print(json.dumps(rep, indent=2))
    return 0 if rep["ok"] else 1

if __name__ == "__main__":
    sys.exit(main(sys.argv[1] if len(sys.argv) > 1 else "wa-form-console-report.json"))
