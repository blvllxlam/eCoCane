# eCoCane

## Styles

The theme styles are split into focused files and loaded in this order from `functions.php`:

- `base.css` — shared colors, typography, buttons, and standard elements.
- `header.css` — site header, logo, navigation, account, and cart.
- `home.css` — homepage hero, category cards, and homepage product cards.
- `catalog.css` — product catalog, shared product-card background, and product details.
- `cart.css` — shopping cart layout and controls.
- `checkout.css` — checkout form, order summary, and confirmation button.
- `account.css` — WooCommerce account page and login form.
- `footer.css` — site footer.
- `responsive.css` — mobile layouts and accessibility preferences.

Keep the WordPress theme metadata in `style.css`; add component styles to the matching file above.

The site header uses a solid green background with `assets/images/header-grass-texture.jpg` behind the logo panel. Header layout and colors belong in `header.css`; its compact responsive layout is in `responsive.css`.

Product listing cards and the main image gallery on single-product pages use `assets/images/product-card-background.png` as a shared background beneath transparent product images.

The child `templates/page.html` keeps regular pages to one semantic `<main>` and renders the page title as an H1. The homepage product shortcode selects only recent published products with assigned images. Public product, product-category, catalog, and page templates also receive localized title and description metadata from `functions.php`; avoid adding a second SEO plugin output without first removing or guarding these tags.

Set the **Минимальный заказ / штук в упаковке** value in a product's WooCommerce **Product data → Inventory** panel. It controls the minimum quantity and order increment; leave it at 0 (or unset) to allow orders from one piece. On a grouped product, the group value is the default for its child products; a value set on a child product takes precedence. Each purchasable child has its own calculator, and unselected children stay out of the cart. The single-product calculator shows the per-piece price beside the quantity controls, along with the pack minimum, and recalculates the total as quantity changes. Simple, purchasable products also show a compact quantity calculator in the homepage and catalog cards; the selected quantity is submitted when the add-to-cart button is used.
