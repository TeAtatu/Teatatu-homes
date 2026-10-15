# Teatatu Homes — Agent Skill

## Purpose

This skill lets an AI agent draft **Home listings** — houses, townhouses,
units and sections for sale or for rent on **Te Atatū Peninsula** — on a
Teatatu Homes-enabled WordPress site, for a human to review and publish.
The agent works entirely through the WordPress REST API.

**The agent must never attempt to publish directly.** Every listing the
agent creates or edits is saved as a draft. The agent's WordPress role has
no publish capability, and the REST API enforces this.

**Peninsula only.** The site rejects any address that is not on Te Atatū
Peninsula. Don't draft listings for Te Atatū South, Henderson or anywhere
else.

## Authentication

Authenticate as a WordPress user with the `teatatu_homes_agent` role using
HTTP Basic Auth with a WordPress Application Password (Users > Profile >
Application Passwords). Send `Authorization: Basic base64(username:app_password)`
on every request. Call the homes site's own REST base,
`https://<homes-site>/wp-json/`.

## Workflow

1. **Load the vocabulary once.**
   `GET /wp-json/teatatu-homes/v1/vocabulary` returns every allowed value:
   listing types (`sale`, `rent`), statuses, sale methods, pet policies,
   furnishing, title types, address display modes, and the existing Areas,
   Property Types, Features and Agencies. Never invent values.

2. **Check the listing isn't already there.**
   `GET /wp-json/teatatu-homes/v1/search?q=<street name>&status=all` and
   compare addresses and `external_ref`.

3. **Create the listing as a draft.**
   `POST /wp-json/wp/v2/homes` with `"status": "draft"`, a `title`
   (a short headline), `content` (the full description), `excerpt` (one or
   two sentences for cards), and a `home` object holding the listing data.
   All listing data goes in `home` — not in `meta`.

4. **Confirm and hand off.**
   Read back with `GET /wp-json/wp/v2/homes/<id>?context=edit` and check
   `home.edit.missing` (fields still needed before publishing) and
   `home.edit.needs_area`. Give the human reviewer the edit link
   `…/wp-admin/post.php?post=<id>&action=edit`.

## The `home` object

| Key | Type | Notes |
|---|---|---|
| `listing` | `sale` \| `rent` | **Required.** |
| `status` | string | `available` (default), `under_offer` (sale), `sold` (sale), `leased` (rent), `withdrawn`. |
| `property_type` | slug | e.g. `house`, `townhouse`, `unit`, `section`. |
| `price` | integer | Whole dollars. Sale: the price (used for search even if hidden). Rent: **weekly** rent. |
| `price_text` | string | Optional override for the price line. |
| `sale_method` | string | `asking`, `enquiries_over`, `negotiation`, `auction`, `tender`, `deadline`. |
| `method_date` | `"YYYY-MM-DD HH:MM"` | Auction time or tender/deadline close, in New Zealand time. Needed for auction/tender/deadline. |
| `available_from` | `"YYYY-MM-DD"` | Rentals. Blank = available now. |
| `bond`, `bedrooms`, `bathrooms`, `car_parks`, `floor_area`, `land_area` | integer | Areas in m². |
| `pets` | string | `no`, `negotiable`, `yes` (rentals). |
| `furnished` | string | `unfurnished`, `partly`, `furnished` (rentals). |
| `healthy_homes` | boolean | Rentals. |
| `title_type` | string | `freehold`, `cross_lease`, `unit_title`, `leasehold`. |
| `address` | object | `unit`, `number`, `street`, `lat`, `lng`, `display` (`full`, `street`, `hidden`). Suburb, city and postcode are fixed by the site. |
| `photos` | array | In order, first is the main photo. Each item is a link — `"https://…"` or `{"url": "https://…", "alt": "…"}` — or an upload `{"id": 123}`. The agent role cannot upload, so **use links**. Links are shown as given and never downloaded. A human editor can add uploaded photos afterwards. |
| `floor_plan_url`, `video_url`, `tour_url` | URL | Optional. |
| `documents` | `[{label, url}]` | e.g. LIM report, title, building report. |
| `open_homes` | `[{start, end}]` | `"YYYY-MM-DD HH:MM"` in New Zealand time. |
| `features` | string[] | Feature names; new names create new Features. |
| `agency` | slug or ID | An existing Agency. |
| `agent_name`, `agent_phone`, `agent_email`, `agent_photo_url` | string | The email is used to route enquiries and is never shown publicly. |
| `external_url`, `external_ref` | string | The original listing and the agency's reference. |

The **Area** (Matipo, Beach, Harbourview) is worked out from the street
when possible. Don't set it unless you are sure. A human can override it.

## Example request

```bash
curl -u "homes-agent:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -X POST "https://homes.example.nz/wp-json/wp/v2/homes" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Sunny family home near the coastal walkway",
    "excerpt": "Four bedrooms, two living areas and a north-facing deck.",
    "content": "Full description from the agent…",
    "status": "draft",
    "home": {
      "listing": "sale",
      "property_type": "house",
      "sale_method": "auction",
      "method_date": "2026-11-08 14:00",
      "price": 1250000,
      "bedrooms": 4, "bathrooms": 2, "car_parks": 2,
      "floor_area": 180, "land_area": 640,
      "address": { "number": "12", "street": "Harbour View Road", "display": "full" },
      "photos": [{ "url": "https://cdn.example.nz/listing/12hvr-front.jpg", "alt": "Front of the house from the street" }],
      "open_homes": [{ "start": "2026-10-25 11:00", "end": "2026-10-25 11:30" }],
      "features": ["Heat pump", "Double glazing"],
      "agent_name": "Agent Name",
      "agent_email": "agent@example.nz"
    }
  }'
```

## Constraints

- The agent role has no `publish_teatatu_homes`. `"status": "publish"` is rejected with `403`.
- The agent can edit or delete only listings it created, and only while unpublished.
- Listing Types and Areas are fixed vocabularies; the agent may assign them but not create, rename or delete them.

## Errors

| Status | Meaning | What to do |
|---|---|---|
| `400` with `field` | A value failed validation, e.g. an address off the Peninsula, an unknown sale method, a status that doesn't suit the listing type, a bad URL, or a photo `id` that wasn't uploaded to this home. | Fix that field and retry once. If it fails again, hand it to a human. |
| `401` | Bad credentials. | Don't retry. Ask a human to check the Application Password. |
| `403` | Tried to publish, or to edit someone else's or a published listing. | Resubmit as a draft or hand it to a human. |
