=== Teatatu Homes Theme ===
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.2.0
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

= Design system =

Styled with the Te Atatū Local design system, the shared look of the five Te Atatū sites:

* assets/css/te-atatu-local-tokens.css — colour (light and dark), type, spacing, radius and shadow tokens, generated from the design system's tokens.json. Do not edit by hand.
* assets/css/te-atatu-local.css — the design system's tal-* components (NetworkNav, Button, bands, notices, badges), copied unchanged from its bundle.css.
* <html data-site="homes"> makes every component use the Homes accent, Harakeke green.
* The header is the shared NetworkNav: the black strip linking Te Atatū and Homes, plus Events, News and Market when the Teatatu Events, News or Market plugin is active on the site or network. Each links to the Master Site set in that plugin; Te Atatū links to the network's main site; without a multisite or a Master Site the teatatu.nz addresses are used (filter `tth_network_sites` to change them), the "Te Atatū Homes" wordmark, search, "List a property", and the section links.
* style.css lays out the listing pages using only design-system tokens. When the design system changes, re-copy both files rather than editing them here.
* Font: Plus Jakarta Sans (400, 600, 700, 800) from Google Fonts.

= Updates =

The theme is released separately from the Teatatu Homes plugin, from the same GitHub repository (TeAtatu/Teatatu-homes; repository and token are set in the plugin's Settings > Teatatu Homes). Theme releases are tagged `teatatu-homes-theme-v<version>`; plugin releases (`teatatu-homes-v<version>`) never offer a theme update, and the reverse.

* When the Teatatu Homes plugin is active it runs the theme's update checks (teatatu-homes/includes/theme-updater.php), because WordPress only loads a theme's code where it is active but checks for updates from the main site. This theme's inc/updater.php is a separate copy used only when the plugin isn't active. Neither reads the other's files; keep the two copies in step by hand.
* Updates install automatically only when "Enable auto-updates" is on for the theme (Appearance > Themes; Network Admin > Themes on multisite). Otherwise click Update now.
* Only the teatatu-homes-theme/ folder from the release is installed.

To release the theme: bump Version in style.css, commit, then publish a GitHub release tagged `teatatu-homes-theme-v<that version>` (e.g. teatatu-homes-theme-v1.2.1).

== Changelog ==

= 1.2.0 =
* Te Atatū Local design system.
* GitHub updater for the theme, released separately (tags teatatu-homes-theme-v…).
