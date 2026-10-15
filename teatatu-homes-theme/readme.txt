=== Teatatu Homes Theme ===
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-3.0

Display theme for a Te Atatū Peninsula homes site. Requires the Teatatu Homes plugin.

== Description ==

This theme only displays. Every listing value comes from the Teatatu Homes plugin:

* Listings are read with teatatu_homes_normalize_item() — price line, public address (respecting each listing's "show address" setting), rooms, photos, open homes, sale or rental details.
* The search form is a plain GET form using the plugin's filter names (listing, q, price_min, price_max, beds, baths, cars, type, area, feature, open, pets, sort). The plugin applies them to the main query on /homes/ and on every Homes taxonomy archive.
* The enquiry form posts to the plugin's handler, which emails the agent.

= Templates =

* front-page.php — hero search (Buy / Rent), latest for sale and for rent, Areas, call to action.
* archive-teatatu_home.php — search results with filters, removable filter chips, sorting, pagination and a map. Also used for /listing/sale/, /listing/rent/, /area/<area>/ and the other Homes taxonomies.
* single-teatatu_home.php — gallery and lightbox, key facts, description, sale or rental details, features, location and map, documents, open homes, agent and enquiry form, more homes in the same area.
* page.php, single.php, index.php, 404.php — plain content.

= Customizer (Appearance > Customize > Te Atatū Homes) =

Home page photo URL and alt text, "Get an appraisal" link, "List a home" link, footer line.

= Menus =

Header and Footer menu locations. With no header menu assigned, Buy / Rent / Open homes are built from the plugin's archives.

= Maps =

Leaflet 1.9.4 (unpkg) with OpenStreetMap tiles, loaded only on pages that have at least one pinned home. Homes whose address is hidden or street-only are never pinned.

= Fonts =

Bricolage Grotesque (headings) and Instrument Sans (body) from Google Fonts.
