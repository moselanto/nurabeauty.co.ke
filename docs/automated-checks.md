# Automated checks

## WhatsApp order form console check
- Script: `tools/wa-form-console-check.py` (Playwright, read-only). Loads the live product page, opens the WhatsApp order form fresh for each place and switches every delivery option for Mombasa, Nakuru, Kisumu, Eldoret, Thika, Juja, Ruiru, Kitengela, Westlands and Karen.
- Records browser console errors, page errors, failed requests and unexpected totals (total must equal item + option; known fees: Bus 350, Fargo 550, Westlands rider 300, Karen rider 800; Bus & shuttle must be first and pre-selected in shuttle towns).
- Never submits the form or sends an M-Pesa prompt: requests for `nurax_wa_order` and `nurax_wa_stk` are blocked in the script.
- Run: `pip install playwright && python -m playwright install chromium && python tools/wa-form-console-check.py report.json` (exit 1 on problems). Without root, use `chromium-headless-shell` and extract libatk1.0, libatk-bridge2.0, libxdamage1, libatspi2.0 with `apt-get download` + `dpkg -x`, then set `LD_LIBRARY_PATH`.

## Schedules
- Town routine "NURA WhatsApp order form check": runs the script daily at 08:00 Africa/Nairobi and emails the owner only when something is wrong (or when the check could not run). Test run on 27 Nov 2026 passed silently.
- GitHub Actions (optional): `tools/wa-form-console-check.workflow.yml` is ready to copy to `.github/workflows/wa-form-console-check.yml` (Town's GitHub access cannot write workflow files). It was not added as of 27 Nov 2026.
- Town scheduled action: 8 Dec 2026 07:00 UTC, check the Search Console 404/5xx validations.

## Not set up yet
- Monthly M-Pesa reconciliation routine: needs the read-only X-API-Key (NURA Desk > Accounts > M-Pesa statement > "Monthly check by Ray: access key") saved in Town.
- Daily business-book routine: needs a read-only data endpoint plus the key.
