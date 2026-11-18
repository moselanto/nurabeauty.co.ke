-- NURA follow-ups (Supabase). Applied 18 Nov 2026 by Ray. Safe to re-run.
-- A customer who promises to order / come back later gets a follow-up row; n8n "NURA 04 - Follow-ups"
-- sends it at 10:00 EAT on the due date and sends the owner a daily list at 10:05.

create table if not exists public.nura_followups (
  id            bigserial primary key,
  session_id    text not null,
  channel       text not null default 'whatsapp',
  customer_phone text,
  customer_name text,
  promise       text,
  due_date      date not null,
  status        text not null default 'pending',   -- pending | done | cancelled | needs_owner
  attempts      int  not null default 0,            -- max 2 follow-ups per promise
  product_name  text,
  product_price text,
  product_url   text,
  last_result   text,
  last_wamid    text,
  last_sent_at  timestamptz,
  created_at    timestamptz not null default now(),
  updated_at    timestamptz not null default now()
);
create index if not exists nura_followups_due_idx on public.nura_followups (status, due_date);
create index if not exists nura_followups_session_idx on public.nura_followups (session_id);
alter table public.nura_followups enable row level security;

-- Due date from what the customer said (English + Kiswahili). Default: 3 days.
create or replace function public.nura_followup_due(msg text, base timestamptz default now())
returns date language plpgsql stable as $f$
declare
  t text := lower(coalesce(msg, ''));
  d date := (base at time zone 'Africa/Nairobi')::date;
  n int;
  target int := null;
  wd int;
begin
  if t ~ '(day after tomorrow|kesho kutwa)' then return d + 2; end if;
  if t ~ '(tomorrow|tmrw|tomoro|kesho)' then return d + 1; end if;
  n := nullif(substring(t from '(\d{1,2})\s*(?:days|day|dys)'), '')::int;
  if n is null then n := nullif(substring(t from 'siku\s*(\d{1,2})'), '')::int; end if;
  if n is not null and n between 1 and 60 then return d + n; end if;
  if t ~ '(two weeks|2 weeks|wiki mbili)' then return d + 14; end if;
  if t ~ '(end of (the )?month|end month|month end|mwisho wa mwezi|salary|payday|pay day|mshahara)' then
    return (date_trunc('month', d) + interval '1 month')::date;
  end if;
  if t ~ '(next week|wiki ijayo|wiki ijao|in a week|after a week|baada ya wiki)' then return d + 7; end if;
  if    t ~ '(sunday|jumapili)'   then target := 0;
  elsif t ~ '(monday|jumatatu)'   then target := 1;
  elsif t ~ '(tuesday|jumanne)'   then target := 2;
  elsif t ~ '(wednesday|jumatano)' then target := 3;
  elsif t ~ '(thursday|alhamisi)' then target := 4;
  elsif t ~ '(friday|ijumaa)'     then target := 5;
  elsif t ~ '(saturday|jumamosi|weekend|wikendi|week end)' then target := 6;
  end if;
  if target is not null then
    wd := extract(dow from d)::int;
    return d + (case when ((target - wd + 7) % 7) = 0 then 7 else ((target - wd + 7) % 7) end);
  end if;
  return d + 3;
end $f$;

-- Does the message promise a later order / visit / reply?
create or replace function public.nura_followup_is_promise(msg text)
returns boolean language sql immutable as $f$
  select lower(replace(coalesce(msg, ''), chr(8217), '''')) ~ (
    '(i''ll|i will|i shall|ill|let me|we will|we''ll)\s+(\w+\s+)?(order|buy|pay|come|visit|pass by|get back|check|think|confirm|let you know|send|call|reach out|talk|book|decide|inform)'
    || '|get back to you|let you know|i''ll be back|will be back|not now|maybe later|later this|next week|end month|end of (the )?month|after (payday|salary|pay day)|when i get (paid|money|my salary|the money)'
    || '|nitarudi|nitakuja|nitaagiza|nitanunua|nitalipa|nitakujulisha|nitawajulisha|nitawaambia|nitakuambia|nitachukua|nitakutumia|nitawatumia|nitakupigia|nitaorder|nitapay|nitacheki|nitaconfirm|nitabook|nitapita'
    || '|ngoja (kwanza|nikiwa|nipate)|nikipata (pesa|doo|salary|mshahara|cash)|baadaye|baadae|wiki ijayo|mwisho wa mwezi'
  );
$f$;

-- Signs that the customer already paid / is ordering now.
create or replace function public.nura_followup_is_paid_msg(msg text)
returns boolean language sql immutable as $f$
  select lower(coalesce(msg, '')) ~ '(confirmed\.|ksh ?[0-9]|i have paid|i''ve paid|ive paid|paid already|already paid|nimelipa|nimetuma|sent the money|transaction code)';
$f$;

-- New customer message (nura_leads row) -> create / cancel follow-ups.
create or replace function public.nura_followup_on_lead()
returns trigger language plpgsql security definer set search_path = public as $f$
declare
  c record;
  owner_phone constant text := '254703201241';
  has_contact boolean;
begin
  if coalesce(new.session_id, '') in ('', 'wa:') then return new; end if;
  if coalesce(new.customer_phone, '') = owner_phone or new.session_id = 'wa:' || owner_phone then return new; end if;

  -- Customer came back (after a follow-up was sent, or 6+ hours later), ordered, or paid: close open follow-ups.
  update nura_followups f
     set status = 'cancelled', last_result = 'customer replied / ordered', updated_at = now()
   where f.session_id = new.session_id
     and f.status in ('pending', 'needs_owner')
     and (f.attempts > 0 or f.created_at < now() - interval '6 hours'
          or new.order_id is not null or nura_followup_is_paid_msg(new.message));

  if new.order_id is not null or nura_followup_is_paid_msg(new.message) then return new; end if;
  if not nura_followup_is_promise(new.message) then return new; end if;

  select * into c from nura_conversations where session_id = new.session_id;
  has_contact := coalesce(nullif(new.customer_phone, ''), nullif(c.customer_phone, '')) is not null
                 or new.session_id like 'wa:u.%';
  if not has_contact then return new; end if;

  -- A newer promise replaces an older one that has not been sent yet.
  update nura_followups f
     set status = 'cancelled', last_result = 'replaced by newer promise', updated_at = now()
   where f.session_id = new.session_id and f.status = 'pending' and f.attempts = 0;

  insert into nura_followups (session_id, channel, customer_phone, customer_name, promise, due_date,
                              product_name, product_price, product_url)
  values (new.session_id, coalesce(new.channel, 'whatsapp'),
          coalesce(nullif(new.customer_phone, ''), c.customer_phone),
          coalesce(nullif(new.customer_name, ''), c.customer_name),
          left(new.message, 300),
          nura_followup_due(new.message, coalesce(new.created_at, now())),
          c.last_recommended -> 0 ->> 'name', c.last_recommended -> 0 ->> 'price', c.last_recommended -> 0 ->> 'url');
  return new;
end $f$;

drop trigger if exists nura_followup_on_lead on public.nura_leads;
create trigger nura_followup_on_lead after insert on public.nura_leads
  for each row execute function public.nura_followup_on_lead();

-- Verified Paystack payment -> cancel that customer's open follow-ups (match last 9 digits of phone).
create or replace function public.nura_followup_on_payment()
returns trigger language plpgsql security definer set search_path = public as $f$
begin
  if new.status = 'success' and coalesce(new.customer_phone, '') <> '' then
    update nura_followups
       set status = 'cancelled', last_result = 'customer paid', updated_at = now()
     where status in ('pending', 'needs_owner')
       and right(regexp_replace(coalesce(customer_phone, ''), '\D', '', 'g'), 9)
         = right(regexp_replace(new.customer_phone, '\D', '', 'g'), 9);
  end if;
  return new;
end $f$;

drop trigger if exists nura_followup_on_payment on public.nura_payments;
create trigger nura_followup_on_payment after insert or update of status on public.nura_payments
  for each row execute function public.nura_followup_on_payment();

-- Paid event for a chat session -> cancel.
create or replace function public.nura_followup_on_event()
returns trigger language plpgsql security definer set search_path = public as $f$
begin
  if new.event_type = 'paid' and coalesce(new.session_id, '') <> '' then
    update nura_followups set status = 'cancelled', last_result = 'customer paid', updated_at = now()
     where session_id = new.session_id and status in ('pending', 'needs_owner');
  end if;
  return new;
end $f$;

drop trigger if exists nura_followup_on_event on public.nura_events;
create trigger nura_followup_on_event after insert on public.nura_events
  for each row execute function public.nura_followup_on_event();

-- Follow-ups due today (used by n8n at 10:00 EAT).
create or replace function public.nura_due_followups(lim int default 50)
returns table (id bigint, session_id text, phone text, name text, product_name text, product_price text,
               product_url text, in_window boolean, attempts int, channel text)
language sql stable security definer set search_path = public as $f$
  select f.id, f.session_id,
         coalesce(nullif(f.customer_phone, ''), c.customer_phone),
         coalesce(nullif(f.customer_name, ''), c.customer_name, ''),
         coalesce(f.product_name, c.last_recommended -> 0 ->> 'name'),
         coalesce(f.product_price, c.last_recommended -> 0 ->> 'price'),
         coalesce(f.product_url, c.last_recommended -> 0 ->> 'url'),
         coalesce(c.last_inbound_at > now() - interval '23 hours', false),
         f.attempts, f.channel
    from nura_followups f
    left join nura_conversations c on c.session_id = f.session_id
   where f.status = 'pending'
     and f.attempts < 2
     and f.due_date <= (now() at time zone 'Africa/Nairobi')::date
     and coalesce(c.opted_out, false) = false
     and coalesce(c.human_takeover, false) = false
     and (coalesce(nullif(f.customer_phone, ''), c.customer_phone) is not null or f.session_id like 'wa:u.%')
   order by f.due_date, f.id
   limit lim;
$f$;

-- Record the send result. p = {"id":..,"ok":true/false,"wamid":"..","result":".."}
create or replace function public.nura_mark_followup(p jsonb)
returns int language plpgsql security definer set search_path = public as $f$
declare ok boolean := coalesce((p ->> 'ok')::boolean, false);
begin
  update nura_followups
     set attempts     = attempts + case when ok then 1 else 0 end,
         status       = case when not ok then 'needs_owner' when attempts + 1 >= 2 then 'done' else 'pending' end,
         due_date     = case when ok then (now() at time zone 'Africa/Nairobi')::date + 3 else due_date end,
         last_result  = left(coalesce(p ->> 'result', ''), 500),
         last_wamid   = nullif(p ->> 'wamid', ''),
         last_sent_at = case when ok then now() else last_sent_at end,
         updated_at   = now()
   where id = (p ->> 'id')::bigint;
  return 1;
end $f$;

-- Owner's morning list (used by n8n at 10:05 EAT).
create or replace function public.nura_followup_digest()
returns table (today text, sent_count int, needs_count int, owner_in_window boolean, lines text)
language sql stable security definer set search_path = public as $f$
  with d as (select (now() at time zone 'Africa/Nairobi')::date as day),
  sent as (
    select f.* from nura_followups f, d
     where f.last_sent_at is not null and (f.last_sent_at at time zone 'Africa/Nairobi')::date = d.day
  ),
  needs as (
    select coalesce(nullif(f.customer_name, ''), 'Customer') as nm, f.customer_phone as ph,
           coalesce(f.product_name, '') as what,
           'follow-up not delivered (' || coalesce(f.last_result, '') || ')' as why, f.updated_at as at
      from nura_followups f
     where f.status = 'needs_owner' and f.updated_at > now() - interval '3 days'
    union all
    select coalesce(nullif(e.customer_name, ''), 'Customer'), e.customer_phone,
           left(coalesce(e.message, ''), 80),
           'needs a person (' || coalesce(e.intent, '') || ')', e.created_at
      from nura_escalations e
     where coalesce(e.status, 'open') <> 'resolved' and e.created_at > now() - interval '7 days'
  )
  select to_char(d.day, 'Dy DD Mon'),
         (select count(*) from sent)::int,
         (select count(*) from needs)::int,
         coalesce((select c.last_inbound_at > now() - interval '23 hours'
                     from nura_conversations c where c.session_id = 'wa:254703201241'), false),
         coalesce(concat_ws(E'\n',
           (select 'Followed up today:' || E'\n' || string_agg('- ' || coalesce(nullif(s.customer_name, ''), 'Customer')
                    || coalesce(' +' || s.customer_phone, '') || coalesce(': ' || s.product_name, ''), E'\n')
              from sent s having count(*) > 0),
           (select 'Need you:' || E'\n' || string_agg(x.line, E'\n')
              from (select '- ' || n.nm || coalesce(' +' || n.ph, '') || ': ' || n.why
                           || case when n.what <> '' then ' - ' || n.what else '' end as line
                      from needs n order by n.at desc limit 25) x
            having count(*) > 0)
         ), '')
    from d;
$f$;

revoke all on function public.nura_due_followups(int), public.nura_mark_followup(jsonb), public.nura_followup_digest() from anon, authenticated;
