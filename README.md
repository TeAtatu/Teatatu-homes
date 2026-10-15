# Teatatu Homes

A homes site for Te Atatū Peninsula: property sales and rentals, nowhere else.

| Folder | What it is |
|---|---|
| [`teatatu-homes/`](teatatu-homes/) | **Plugin: the data.** Listing post type, taxonomies, the validated field and address model, search query, REST API, enquiries, AI-agent role. Renders nothing on the front end. See [`readme.txt`](teatatu-homes/readme.txt) and [`SKILL.md`](teatatu-homes/SKILL.md). |
| [`teatatu-homes-theme/`](teatatu-homes-theme/) | **Theme: the display.** Home page, search results, listing page. Reads only the plugin's functions. See [`readme.txt`](teatatu-homes-theme/readme.txt). |

## Install

1. Copy `teatatu-homes/` to `wp-content/plugins/` and activate it (network-activate on multisite, or activate on the homes sub-site only).
2. Copy `teatatu-homes-theme/` to `wp-content/themes/` and activate it on the homes sub-site.
3. Settings > Permalinks > Save, so `/homes/`, `/listing/sale/`, `/area/matipo/` resolve.
4. Optional: activate Teatatu Events on the same site so each listing's Area is worked out from its street.

## Boundaries

- **The plugin owns the data.** That includes everything about addresses: storage, validation (Peninsula only), Area resolution, the "show address" privacy setting, and every formatted address string.
- **The theme owns the display.** It never reads post meta and never joins address fields. If the theme needs a value that isn't in `teatatu_homes_normalize_item()`, add it to the plugin first.
