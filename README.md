# OS Product Gallery

A simple, filterable product gallery for WordPress with a popup image gallery.

## Features

- `Products` custom post type with `Product Categories` taxonomy
- Per-product gallery images (drag to reorder) and optional video URL (YouTube, Vimeo or mp4/webm)
- Ajax category filtering and "Load more" pagination
- Accessible popup gallery with thumbnails, keyboard navigation and focus trap
- Assets load only on pages that use the shortcode

## Requirements

- WordPress 6.3+
- PHP 7.4+

## Installation

1. Copy the `os-product-gallery` folder into `wp-content/plugins/`.
2. Activate **OS Product Gallery** in the Plugins screen.
3. Add products under **Products** and assign them to categories.

## Usage

```
[os_product_gallery]
[os_product_gallery category="shoes,bags" per_page="12" columns="4" orderby="title" order="ASC" filter="no"]
```

| Attribute  | Default | Description                                                        |
| ---------- | ------- | ------------------------------------------------------------------ |
| `category` | (all)   | Comma separated category slugs to include                          |
| `per_page` | `4`     | Items per page (1-48)                                              |
| `columns`  | `2`     | Desktop columns (1-6). Tablet shows 2, mobile shows 1              |
| `orderby`  | `date`  | `date`, `title`, `menu_order`, `rand`                              |
| `order`    | `DESC`  | `ASC` or `DESC`                                                    |
| `filter`   | `yes`   | Show the category filter buttons (`yes` / `no`)                    |

## Development

```bash
npm install      # front-end tooling
composer install # coding standards

npm run watch    # compile SCSS (expanded + source maps) and minify JS on change
npm run build    # production build: compressed CSS + minified JS

composer lint      # run PHPCS (WordPress standards)
composer lint:fix  # auto-fix with PHPCBF
```

- Edit only `assets/scss/**` and `assets/js/*.js` (not `*.min.js`). Compiled files are generated.
- Add new SCSS partials under `assets/scss/components/` and `@use` them in `os-product-gallery.scss`.
- With `SCRIPT_DEBUG` enabled, the non-minified JS is loaded.

## Architecture

Singleton `OS_Product_Gallery` loads independent components from `includes/`:

| File                      | Responsibility                                  |
| ------------------------- | ----------------------------------------------- |
| `class-cpt.php`           | Post type, taxonomy, rewrite flush              |
| `class-meta-boxes.php`    | Gallery images and video URL fields             |
| `class-shortcodes.php`    | Shortcode, asset registration, query/render API |
| `class-ajax.php`          | Filtering/pagination and popup data endpoints   |

Activation stores the version and schedules a rewrite flush. On every load the stored version is compared with
`OSPG_VERSION`, and `OS_Product_Gallery::upgrade()` runs migrations when needed. Deactivation flushes rewrite rules.

## Hooks

| Hook                  | Type   | Description                         |
| --------------------- | ------ | ----------------------------------- |
| `ospg_loaded`         | action | All components are loaded           |
| `ospg_upgraded`       | action | Plugin upgraded (`$from`, `$to`)    |
| `ospg_post_type_args` | filter | Products post type arguments        |
| `ospg_taxonomy_args`  | filter | Product Categories arguments        |
| `ospg_query_args`     | filter | `WP_Query` arguments for the gallery |

## Changelog

### 1.0.0
- Initial release.