=== Teatatu Homes ===
Contributors: teatatu
Tags: real estate, property, listings, rentals, te atatu
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPL-3.0
License URI: https://www.gnu.org/licenses/gpl-3.0.html

The data layer for a Te Atatū Peninsula homes site: listings for sale and for rent, with a validated field model, search, enquiries and a draft-only AI-agent role. Display is left to the theme.

== Description ==

Teatatu Homes stores and serves property listings for **Te Atatū Peninsula only**. It deliberately renders nothing on the front end: pair it with the Teatatu Homes theme (or any theme that reads the functions below).

= Data model =

* Post type `teatatu_home` (REST `/wp/v2/homes`, archive `/homes/`). Title = headline, content = full description, excerpt = card summary.
* Taxonomies:
  * Listing Type `teatatu_home_listing` — fixed: `sale`, `rent` (`/listing/sale/`, `/listing/rent/`).
  * Property Type `teatatu_home_type` — House, Townhouse, Unit / apartment, Section (editable).
  * Area `teatatu_home_area` — fixed: Matipo, Beach, Harbourview (same split and slugs as Teatatu Events).
  * Features `teatatu_home_feature` — free tags.
  * Agency `teatatu_home_agency` — with logo, phone, email and website term meta.
* Fields (post meta `_teatatu_homes_<field>`): status, sale method, price (sale price or weekly rent, whole dollars), price wording override, auction/closing date, rental terms (available from, bond, pets, furnishing, Healthy Homes), bedrooms, bathrooms, car parks, floor and land area, title type, photo URLs, floor plan / video / tour URLs, documents, open homes, agent contact, original listing URL and reference.
* Address (post meta `_teatatu_homes_addr_<segment>`): unit, number, street, suburb, city, postcode, lat, lng, and a display mode (full, street only, hidden). Suburb, city and postcode are fixed to Te Atatū Peninsula, Auckland 0610. Coordinates outside the Peninsula are rejected.
* Photos (`photos`, in order, first = main photo) can be **uploaded** or **linked**, mixed freely:
  * Uploads are Media Library attachments saved in the home's own folder, `wp-content/uploads/homes/<home ID>/`, and attached to the home ("Uploaded to" in the Media Library). A home can only use photos uploaded to it. They are deleted when the home is permanently deleted.
  * Links are remote image URLs, stored and served as given, never downloaded.
* Uses only standard WordPress tables.

= One writer, one reader =

* `teatatu_homes_save_fields( $post_id, $fields )` validates and saves a whole listing — the admin form and REST both use it.
* `teatatu_homes_normalize_item( $post )` returns the display-ready array every consumer reads: price label, public address (respecting the display mode), area, rooms, features, photos (resolved to `url`, `thumb`, `full`, `srcset`, `alt` whether uploaded or linked), upcoming open homes, sale or rental details, agent (no email) and agency.

= Search =

The Homes archive and every Homes taxonomy archive accept these URL parameters, so a theme only needs a GET form:

`listing` (sale|rent), `q`, `price_min`, `price_max`, `beds`, `baths`, `cars`, `type`, `area`, `feature`, `agency` (comma-separated slugs), `open` (weekend|any), `pets` (1), `status`, `sort` (newest|price_asc|price_desc|open).

Only Available and Under offer listings are shown unless `status` says otherwise.

= Theme API =

* `teatatu_homes_normalize_item( $post )`
* `teatatu_homes_current_filters()`, `teatatu_homes_active_filters( $filters )`, `teatatu_homes_filter_defs()`, `teatatu_homes_sort_options()`, `teatatu_homes_price_steps( $listing )`, `teatatu_homes_format_money( $amount )`
* `teatatu_homes_latest( $listing, $count )`, `teatatu_homes_search( $filters, $page, $per_page )`
* `teatatu_homes_area_defs()`, `teatatu_homes_listing_types()`
* `teatatu_homes_enquiry_action_url()`, `teatatu_homes_enquiry_hidden_fields( $post_id )`, `teatatu_homes_enquiry_status()`, `teatatu_homes_enquiry_message( $status )`

= REST =

* `GET/POST /wp-json/wp/v2/homes` with a `home` field (public item in `view`; raw fields under `home.edit` in `edit` context). Raw meta is not exposed.
* `GET /wp-json/teatatu-homes/v1/search` — same parameters as the archive, plus `page` and `per_page`.
* `GET /wp-json/teatatu-homes/v1/vocabulary` — every allowed value.

= Roles =

* `teatatu_homes_agent` — drafts listings over REST, never publishes. See SKILL.md.
* Administrator and Editor get full Homes capabilities.

= Master Site (multisite) =

Network Admin > Settings > Teatatu Homes > Master Site picks the one site — usually the network's main site — that can show homes from every site together. Each site keeps its own listings; nothing is copied.

* Shortcodes (Master Site only; elsewhere they show nothing, or a note for admins): `[teatatu_homes_network_grid]`, `[teatatu_homes_network_list]`, `[teatatu_homes_network_latest]`.
* Options: `listing="sale|rent"`, `count="6"`, `columns="3"`, `area`, `type`, `feature` (comma-separated slugs), `beds`, `price_max`, `open="weekend|any"`, `sort="newest|price_asc|price_desc|open"`, `exclude_sites="2,4"`, `show_photo`, `show_specs`, `show_site`, `link_target="_self|_blank"`.
* REST: `GET /wp-json/teatatu-homes/v1/network-feed` with the same filter options returns the same items (404 on other sites).
* Results are cached for an hour; any change to a home on any site clears the cache.
* The shortcodes' small stylesheet uses the Te Atatū Local design-system tokens when the Master Site's theme loads them, and falls back to the Homes look otherwise.

= Areas =

If the Teatatu Events plugin is active on the same site, a listing's Area is worked out from its street using Events' street rules. Otherwise an editor ticks it, and listings without one are flagged "Needs Area".

== Changelog ==

= 1.2.0 =
* Homes Master Site for multisite: network-wide [teatatu_homes_network_*] shortcodes and the network-feed REST endpoint, like Teatatu News.
* The plugin and the Teatatu Homes theme are released separately from the same GitHub repository: plugin releases are tagged teatatu-homes-v<version>, theme releases teatatu-homes-theme-v<version>. Each only looks at its own tags, so either can be edited and released without the other.
* The theme's updates are run by this plugin (includes/theme-updater.php, a standalone copy) because theme code only loads where the theme is active; this keeps its "Enable auto-updates" switch available in Network Admin > Themes. The theme keeps its own copy for when the plugin isn't active.
* Settings > Teatatu Homes shows the plugin's and theme's versions and auto-update state.
* Updates install automatically only when "Enable auto-updates" is on for the plugin. When it is off, the update notice still shows and Update now installs it by hand.
* GitHub token is now also used to download the release, so private repositories update.
* Fixed: an update from a GitHub release now installs the plugin's own folder instead of the whole repository, which previously deactivated the plugin.
* Dates use the Te Atatū Local NZ style ("Sat 25 October, 11:00AM–11:30AM").

= 1.1.0 =
* Photos can be uploaded to a per-home Media Library folder as well as linked. The `image_urls` field is replaced by `photos`.

= 1.0.0 =
* First release.
