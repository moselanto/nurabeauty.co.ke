# AUTOMATION.md - NURA AI chat, WhatsApp and payment automation (n8n + Supabase)

Last updated: 18 Nov 2026 (Ray). Read this before touching the chat, WhatsApp or Paystack automation.
Secrets are NEVER stored in this repo. Where a secret is needed this file says where it lives.

## 1. What runs where

| Piece | Where | Purpose |
|---|---|---|
| Website chat widget "Ask NURA Stylist" | plugin `nura-experience/includes/class-ai-stylist.php` (REST `POST /wp-json/nurax/v1/stylist`, booking `POST /wp-json/nurax/v1/stylist-booking`) | Sends the shopper's message server-to-server to n8n; if n8n fails it falls back to the plugin's own AI (provider set on Settings > NURA AI Assistant, currently Groq) and then to rule-based picks. |
| n8n workspace | https://nurabeautykenya.app.n8n.cloud (owner login mosemqute@gmail.com, Personal project) | The "brain": chat, WhatsApp connector, Paystack reconciliation. |
| Supabase | project ref `pwwusetgvwdeccmwxzex` ("nurabeautyke-lgtm's Project") | Memory + CRM: `nura_conversations`, `nura_leads`, `nura_events`, `nura_payments`, `nura_processed_events`, `nura_escalations`, `nura_consultations`, `nura_abandoned_carts`. |
| Meta app | "NURA BEAUTY Automation", app ID `1054325127225105`, business ID `1163951186812680` | WhatsApp Cloud API webhook + sending. |
| WooCommerce webhooks | WooCommerce > Settings > Advanced > Webhooks | "NURA n8n - Order created" and "NURA n8n - Order updated" -> n8n payment workflow. |

## 2. n8n workflows

| ID | Name | Status | Trigger |
|---|---|---|---|
| `Ub28zvwWE0Izydkh` | NURA 01 - Chat (configurable AI, Groq) | **Published (live)** | `POST /webhook/nura-chat` (website) and "From WhatsApp Workflow" (called by NURA 03) |
| `PvGNHCWDDGvqs8JL` | NURA 01 - Chat (OpenAI version) | **Off - keep off** | same path `/webhook/nura-chat`, so only one of the two chat workflows can be active |
| `Gp7M1Lg8MDnAFuT3` | NURA 02 - Paystack Payments | **Published** | `POST /webhook/nura-wc-order` (WooCommerce webhooks) |
| `EPxYROEkRw84XZIz` | NURA 03 - WhatsApp Connector | **Published** | `GET` + `POST /webhook/nura-whatsapp` (Meta) |

Production URLs: `https://nurabeautykenya.app.n8n.cloud/webhook/<path>`.

### NURA 01 - Chat (Groq)
- **Normalize Input** (Code): reads website body or WhatsApp item; builds `sessionId` = `web:<id>` or `wa:<phone>` (`wa:u.<user_id>` for WhatsApp username users with no phone). Holds the **business facts block** (studio address, hours, services/prices, delivery, payment, returns, contact) and the reply rules. Edit facts HERE when anything changes (keep them identical to the website / llms.txt).
- Load Memory (Postgres, NURA Supabase) -> Classify Message (Groq, NURA AI Provider) -> WooCommerce Product Search / Get WooCommerce Order (NURA WooCommerce) -> AI Reply (Groq) -> Finalize Reply -> Save to Supabase (Postgres) -> Output.
- SQL is built from a JSON string inside a dollar-quoted literal `$nq$...$nq$`; every JSON string is built with `.replace(/\$/g, '\\u0024')` so user text can never close the quote. Keep that pattern for any new query.

### NURA 02 - Paystack Payments
WooCommerce Order Webhook (Raw Body ON) -> **Verify WC Signature** (Code: HMAC-SHA256 of the raw body with the WooCommerce webhook secret, base64, compared to `X-WC-Webhook-Signature`; anything unsigned or wrong is dropped) -> Get Order ID -> Get WooCommerce Order -> Paystack Payment? -> Verify with Paystack (`GET https://api.paystack.co/transaction/verify/<ref>`, NURA Paystack) -> Reconcile Payment -> Save Payment -> New Result? -> Add Order Note (private) -> Update Order? -> Mark Order Processing -> Record Order Updated.
- De-duplication key: `nura_processed_events.event_key = paystack:<reference>:<status>`. A replay with the same result does nothing new.
- The webhook secret is set in BOTH places: the two WooCommerce webhooks (Secret field) and the `SECRET` constant in "Verify WC Signature". If you change one, change the other.

### NURA 03 - WhatsApp Connector
Meta Verify (GET) -> Check Verify Token -> Answer Meta. WhatsApp Message (POST) -> **Extract Messages** -> NURA 01 Chat (sub-workflow) -> Format for WhatsApp -> Send WhatsApp Reply (`POST https://graph.facebook.com/v25.0/<phone_number_id>/messages`, NURA WhatsApp).
- Verify token: `nura-wa-5db544dce3bd8079` (not a secret API key; must match Meta).
- `PHONE_NUMBER_ID` in Extract Messages = **1190576524148420** (+254 714 994898, WhatsApp account ID 2242482590005279). It is typed as `'1190576' + '524148420'` because the browser blocks 16-digit numbers. Messages for any other phone number ID are ignored.
- Delivery/read **status** events are ignored by Extract Messages but each one still starts an n8n execution (n8n counts every webhook call). See section 6.
- Meta "username" users may arrive with no `from`/`wa_id`, only `from_user_id`; Extract Messages passes `user_id` and the session becomes `wa:u.<user_id>`.

## 3. n8n credentials (names must match exactly)
| Credential | Type | Value / where it comes from | Used by |
|---|---|---|---|
| NURA Supabase | Postgres | host `aws-0-eu-west-1.pooler.supabase.com`, port 5432, db `postgres`, user `postgres.pwwusetgvwdeccmwxzex`, SSL on (Supabase CA cert); password from Supabase > Project Settings > Database | Load Memory, Save to Supabase, Save Payment, Record Order Updated |
| NURA AI Provider | Header Auth | `Authorization: Bearer <Groq API key>` (console.groq.com) | Classify Message, AI Reply |
| NURA WooCommerce | WooCommerce API | Read/Write REST key from WooCommerce > Settings > Advanced > REST API; URL `https://nurabeauty.co.ke` | Product search, order lookups, order note, mark processing |
| NURA Paystack | Header Auth | `Authorization: Bearer sk_live_...` (Paystack > Settings > API Keys & Webhooks, Live) | Verify with Paystack |
| NURA WhatsApp | Header Auth | `Authorization: Bearer <Meta system-user permanent token>` (Business settings > System users > NURA n8n, never-expiring, whatsapp_business_messaging + whatsapp_business_management) | Send WhatsApp Reply |

## 4. WordPress side
- Plugin `class-ai-stylist.php`: default chat URL `https://nurabeautykenya.app.n8n.cloud/webhook/nura-chat` (since 1.118.1). Override without a release: `define( 'NURAX_N8N_CHAT_URL', '...' );` in wp-config.php or filter `nurax_n8n_chat_url`. Empty string = n8n off (plugin AI only).
- Settings > NURA AI Assistant: enabled, provider Groq (Gemini 2.5 Flash is retired for new users).
- Settings > NURA WhatsApp: **"Reply to incoming WhatsApp messages" must stay OFF** (n8n answers WhatsApp; ticking it could double-reply if Meta ever points at WordPress). Phone number ID field = 1190576524148420 (used only for website order alerts).
- Theme: the phone chat button is always shown on phones since theme 1.161.28 (hides only while scrolling).

## 5. How to change things
- **Business facts / tone / sales rules:** n8n > NURA 01 Chat (Groq) > Normalize Input (facts) and AI Reply (system prompt). Save, keep Published. Test with a website chat (section 7) and delete the test rows.
- **Swap AI provider/key:** edit credential NURA AI Provider (and the model/URL in Classify Message + AI Reply if leaving Groq).
- **New WhatsApp number:** Meta > WhatsApp > API Setup > Phone number ID -> update `PHONE_NUMBER_ID` in Extract Messages and the WordPress NURA WhatsApp field; make sure the system-user token has that WhatsApp account as an asset.
- **Rotate the WooCommerce webhook secret:** set the new value in both WooCommerce webhooks and in "Verify WC Signature".
- Never activate both chat workflows. Never run raw SQL with string-concatenated user text.

## 6. Execution budget
n8n Cloud counts every webhook call as an execution, including WhatsApp delivered/read status events (about 3 per reply) and Meta's re-delivery of old messages. Filtering inside n8n does not reduce the count. Options: a paid plan sized for it, or a relay in front of n8n (Cloudflare Worker or the WordPress endpoint) that drops `statuses` and forwards only `messages`. The trial (1,000 executions) is not enough for live WhatsApp.

## 7. Testing
- Website chat: `curl -X POST https://nurabeauty.co.ke/wp-json/nurax/v1/stylist -H 'Content-Type: application/json' -d '{"messages":[{"role":"user","content":"test"}],"session_id":"raytest-x"}'` -> `"engine":"nura-ai"` means n8n answered. Delete rows with session `web:raytest-x` from nura_leads, nura_events, nura_conversations.
- Payment: in wp-admin open a Paystack order and click Update (WooCommerce sends a signed order.updated). Check n8n Executions and `nura_payments`.
- WhatsApp: message +254 714 994898 from another phone; check NURA 03 executions and `nura_conversations` (`wa:<phone>`).

## 8. Moving to a different n8n account / workspace
1. Old account: open each workflow (Ub28..., Gp7M..., EPxY..., optionally PvGN...) > ... > Download (JSON). Credentials do not export with secrets.
2. New account: Import from file for each workflow. Re-create the five credentials in section 3 with the SAME names, then open every Postgres / HTTP / WooCommerce node and select the credential (imported workflows may keep stale credential IDs).
3. NURA 03 "NURA 01 Chat" node: re-select the new ID of the imported chat workflow (sub-workflow IDs change on import).
4. Re-enter the "Verify WC Signature" secret if it was blanked; check `PHONE_NUMBER_ID`.
5. Publish NURA 01 (Groq), NURA 02, NURA 03. Note the new host `https://<new>.app.n8n.cloud`.
6. Point everything at the new host:
   - WordPress chat: plugin default URL in `class-ai-stylist.php` (two places) + version bump, or `NURAX_N8N_CHAT_URL` in wp-config.php.
   - WooCommerce > Webhooks: both Delivery URLs -> `<new host>/webhook/nura-wc-order`.
   - Meta app > WhatsApp > Configuration: Callback URL `<new host>/webhook/nura-whatsapp`, same verify token, Verify and save, keep "messages" subscribed.
   - Sticky note in NURA 03.
7. Test all three (section 7), then deactivate the workflows in the old account so nothing answers twice.
8. Update this file and HANDOVER.md.

## 9. Follow-ups (NURA 04 + Supabase) - added 18 Nov 2026
- **Supabase** (`automation/supabase-followups.sql`, applied 18 Nov, safe to re-run): table `nura_followups`; trigger on `nura_leads` insert detects promises ("I'll order Friday", "nitakuja kesho", "get back to you", "nikipata pesa"...) with `nura_followup_is_promise()` and sets the due date with `nura_followup_due()` (tomorrow/kesho +1, N days, weekday names, next week +7, end month/salary -> 1st of next month, default +3). Product = the conversation's last recommended item. Owner number 254703201241 is never followed up.
- Cancels automatically when the customer writes again 6+ hours later or after a follow-up was sent, orders, pays (M-Pesa confirmation text, Paystack success, `paid` event), or makes a newer promise. Max 2 follow-ups per promise (second one 3 days after the first). Opted-out and human-takeover chats are skipped.
- **n8n `NURA 04 - Follow-ups`** (ID `Tgp7uLqJxHcYZDQt`, export `automation/n8n-NURA-04-Follow-ups.json`, timezone Africa/Nairobi): 10:00 `nura_due_followups()` -> Build Message (normal text if the customer wrote in the last 23h, else template `nura_followup`) -> Send (NURA WhatsApp) -> `nura_mark_followup()`. 10:05 `nura_followup_digest()` -> owner list to 0703 201 241 (text if the owner wrote in the last 23h, else template `nura_owner_digest`), listing who was followed up and who needs a person (failed sends + open escalations from the last 7 days). 2 executions a day.
- Templates (WhatsApp account 2242482590005279): `nura_followup` Marketing - "Hi {{1}}, this is NURA Beauty. You asked us about the {{2}}, available at {{3}}. Would you like us to reserve it for you? Reply here and we will help you order." footer "Reply STOP to opt out"; `nura_owner_digest` Utility. Submitted 18 Nov, in review.
- Tested 18 Nov: trigger created a follow-up due Fri 20 Nov from "I will order on Friday"; manual run sent the follow-up and the owner list to 0703 201 241 (HTTP 200).
- Importing on a new n8n account: Import from URL `https://raw.githubusercontent.com/moselanto/nurabeauty.co.ke/main/automation/n8n-NURA-04-Follow-ups.json`, then select NURA Supabase / NURA WhatsApp on the nodes and publish.
