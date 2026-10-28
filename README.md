# OS Product Gallery

A simple, filterable product gallery for WordPress with a popup image gallery.

## Features

- `Products` custom post type with a `Product Categories` taxonomy
- Per-product gallery images
- Ajax category filtering with numbered pagination (`Prev 1 2 3 … 10 Next`) built by `paginate_links()`
- Second image shown on hover, fetched on demand (nothing happens if the product has no second image)
- Accessible popup gallery with thumbnails, keyboard navigation and focus trap
- Assets load only on pages that use the shortcode

## Installation

1. Copy the `os-product-gallery` folder into `wp-content/plugins/`.
2. Run `npm install && npm run build` to generate the minified assets.
3. Activate **OS Product Gallery** in the Plugins screen.
4. Add products under **Products** and assign them to categories.

## Usage

```
[os_product_gallery]
[os_product_gallery category="shoes,bags" per_page="12" columns="4" orderby="title" order="ASC" filter="no"]
```

| Attribute  | Default | Description                                                  |
| ---------- | ------- | ------------------------------------------------------------ |
| `category` | (all)   | Comma separated category slugs to include                    |
| `per_page` | `4`     | Items per page (1-48)                                        |
| `columns`  | `2`     | Desktop columns (1-6). Collapses to 2 on tablet (see CSS)    |
| `orderby`  | `date`  | `date`, `title`, `menu_order`, `rand`                        |
| `order`    | `DESC`  | `ASC` or `DESC`                                              |
| `filter`   | `yes`   | Show the category filter buttons (`yes` / `no`)              |


## Architecture

Singleton `OS_Product_Gallery` loads independent components from `includes/`:

| File                   | Responsibility                                                  |
| ---------------------- | --------------------------------------------------------------- |
| `class-cpt.php`        | Post type, taxonomy, rewrite flush                              |
| `class-meta-boxes.php` | Gallery images fields                             |
| `class-shortcodes.php` | Shortcode, asset registration, shared query / render helpers    |
| `class-ajax.php`       | Ajax endpoints: filter + paginate, popup data, hover image      |

Activation stores the version and schedules a rewrite flush. On every load the stored version is compared with `OSPG_VERSION`, and `OS_Product_Gallery::upgrade()` runs migrations when needed. Deactivation flushes rewrite rules.

### Request flow

1. The shortcode renders page 1 on the server: filter buttons, grid items and the pagination links. The first view needs no Ajax, has no layout jump and is readable by crawlers.
2. Filter clicks and page clicks call `ospg_filter_products`. The server returns ready-made HTML for the grid and the pagination, and JS swaps it in.
3. Clicking a card calls `ospg_get_product`, which returns the popup data (images, title, excerpt).
4. The first hover on a card calls `ospg_get_hover_image`.

Grid items and pagination are built by one PHP function each (`render_items()` and `render_pagination()`), used for both the first render and the Ajax responses. The markup can't drift between the two, and there is only one place to change it.

### Why Ajax (admin-ajax) and not the REST API

- It works on every WordPress install without depending on permalinks, REST being enabled, or security plugins that block `/wp-json/`.
- The nonce check is one line (`check_ajax_referer()`), and the handlers are small and easy to follow.
- The trade-offs are real. `admin-ajax.php` loads more of WordPress per request than REST does, it uses POST so responses can't be cached by CDNs or browsers, and it has no schema or `permission_callback`. For a small plugin this is acceptable. For a high-traffic site I would use REST `GET` endpoints (see "What I would do differently").

### How gallery images are stored

- Gallery images are stored as one post meta row, `_ospg_gallery_ids`, holding a comma-separated list of attachment IDs (the same approach WooCommerce uses for `_product_image_gallery`).
- One row means one meta read per product (WP_Query primes the meta cache for the whole page), and the order is the order of the list, so drag-to-reorder needs no extra column.

### How the second (hover) image works

- **There is no separate "second image" field.** It is derived: the first gallery image that is not the image already shown on the card. The card image is the featured image, falling back to the first gallery image (`get_card_image_id()`). So editors manage one gallery, and there is no second field to keep in sync.
- **It is fetched lazily.** On the first `mouseenter` / `focusin` on a card, JS asks `ospg_get_hover_image`. If an image comes back, it is preloaded and added to the card, and CSS fades it in. If not, nothing is added.
- **The result is cached in memory** (`hoverCache`), including the "no second image" answer, so each product is requested once per page view.
- **Why lazy:** a grid of 4 to 48 cards never downloads second images that most visitors won't hover, and the initial HTML stays small.
- **The cost:** the first hover has a short delay, and touch devices have no hover, so they never see the second image. See below.

### How pagination state is held

- **Server: stateless.** Every Ajax request carries everything it needs: `category`, the shortcode's `categories`, `per_page`, `orderby`, `order` and `paged`. The server keeps no session or transient per visitor.
- **Shortcode settings** live in `data-*` attributes on the `.ospg` wrapper, so several galleries on one page are independent.
- **Client: in memory, per gallery.** Each `Gallery` instance keeps `{ category, paged, busy }`. A filter click resets `paged` to 1. `busy` blocks overlapping requests.
- **Pagination links** come from `paginate_links()` with `#page-N` hrefs. JS intercepts the click, reads the number and loads that page.
- **Not in the URL:** refreshing the page or sharing a link returns to page 1 of "All". That is a deliberate simplification, listed below.

## Security notes

### What is sanitised (input)

| Input | Handling |
| ----- | -------- |
| Shortcode `per_page`, `columns` | `absint()`, then clamped to 1-48 and 1-6 |
| Shortcode / Ajax category slugs | `sanitize_title()` per slug (`parse_slugs()`) |
| `orderby` | `sanitize_key()`, then checked against an allowlist (`date`, `title`, `menu_order`, `rand`); anything else becomes `date` |
| `order` | Only `ASC` or `DESC` is accepted; anything else becomes `DESC` |
| `paged`, `product_id` | `absint()` |
| Meta box: gallery IDs | `sanitize_text_field()`, then `absint()` per ID, with empty values removed and duplicates dropped
| All `$_POST` reads | `isset()` check, `wp_unslash()`, then the sanitiser above |

All queries go through `WP_Query` with sanitised values, so there is no hand-built SQL.

### What is escaped (output)

- PHP templates: `esc_html()`, `esc_attr()`, `esc_url()` and their `_e` / `__` variants on every dynamic value.
- Images: `wp_get_attachment_image()` (escapes its own attributes); image URLs in JSON go through `esc_url_raw()`.
- Popup description: the excerpt passes through `wp_kses_post( wpautop() )`, so only post-safe HTML is allowed.
  - Titles and error messages are set with `.text()`.
  - Images are built as jQuery elements, so `src` and `alt` are set as attributes and never concatenated into HTML strings.
  - `.html()` is used only for server-built HTML (grid, pagination, kses-filtered description). Each use is commented in the source.

### How the Ajax endpoints are protected

- Every handler calls `check_ajax_referer( 'ospg_nonce', 'nonce' )`.
- The endpoints are registered for logged-out visitors too (`wp_ajax_nopriv_*`), because the gallery is public. They are **read-only**: nothing is written, created or deleted.
- `ospg_get_product` and `ospg_get_hover_image` return data only if the post exists, is the `ospg_product` type, has status `publish`, and is not password-protected. Drafts, private posts and other post types can't be read by guessing an ID.
- `per_page` is capped at 48 on the server, so a request can't ask for an unbounded result set.
- The admin side is separate: the meta box save checks a nonce (`ospg_save_meta`), skips autosaves, and requires `current_user_can( 'edit_post', $post_id )`.

### What this protects against

- **SQL injection:** values are sanitised and passed to `WP_Query`, which prepares its queries.
- **XSS:** input is sanitised, output is escaped, and the JS avoids string-built HTML for untrusted values.
- **CSRF on saving products:** nonce plus capability check.
- **Reading unpublished or protected content** through the Ajax endpoints.
- **Oversized or malformed query parameters** (clamped, allowlisted).

## What I would do differently with more time

- **Move to REST `GET` endpoints.** They are cacheable by browsers and CDNs, have `permission_callback` and argument schemas (sanitising and validating declaratively), and respond faster than `admin-ajax.php`.
- **Put the hover image in the initial markup.** A second `<img loading="lazy">` per card removes the first-hover delay and the extra request entirely, and works on touch devices. I chose lazy fetching to keep the first payload small, but the images are lazy-loaded either way, so inline would be simpler and faster.
- **Keep pagination and filter state in the URL** (`history.pushState` or a hash such as `#cat=shoes&page=3`) so refresh, back/forward and sharing work. It would also help to make the pagination real links (`?ospg_page=3`) so the gallery works without JavaScript.
- **Cache query results** with transients or the object cache, invalidated on `save_post_ospg_product` and term changes, because filter and pagination requests repeat a lot.
- **Tests:** PHPUnit for the query, sanitising and Ajax handlers, and Playwright for filter, pagination, popup and hover behaviour.
- **Accessibility and UX polish:** announce page changes to screen readers, return focus to the grid after paging, and add swipe and touch support in the popup.
- **A Gutenberg block and a settings screen** (accent colour, default columns and per page) on top of the existing shortcode.
- **Add a build tool** i would use .scss/js to generate minified minified assets, and finally generate distribution zip file by command like `npm run build`.

## Hooks

| Hook                  | Type   | Description                            |
| --------------------- | ------ | -------------------------------------- |
| `ospg_loaded`         | action | All components are loaded              |
| `ospg_upgraded`       | action | Plugin upgraded (`$from`, `$to`)       |
| `ospg_post_type_args` | filter | Products post type arguments           |
| `ospg_taxonomy_args`  | filter | Product Categories arguments           |
| `ospg_query_args`     | filter | `WP_Query` arguments for the gallery   |

## Changelog

### 1.0.0
- Initial release.