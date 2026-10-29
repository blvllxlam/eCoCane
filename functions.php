<?php

add_action('after_setup_theme', function () {
    $editor_stylesheet = get_stylesheet_directory_uri() . '/editor.css';
    $editor_stylesheet .= '?ver=' . filemtime(get_stylesheet_directory() . '/editor.css');
    add_editor_style($editor_stylesheet);
});

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'ecocane-child-style',
        get_stylesheet_uri(),
        ['organic-store-style'],
        (string) filemtime(get_stylesheet_directory() . '/style.css')
    );

    $stylesheets = [
        'ecocane-base-style' => 'base.css',
        'ecocane-header-style' => 'header.css',
        'ecocane-home-style' => 'home.css',
        'ecocane-catalog-style' => 'catalog.css',
        'ecocane-cart-style' => 'cart.css',
        'ecocane-checkout-style' => 'checkout.css',
        'ecocane-account-style' => 'account.css',
        'ecocane-footer-style' => 'footer.css',
        'ecocane-responsive-style' => 'responsive.css',
    ];
    $dependency = 'ecocane-child-style';

    foreach ($stylesheets as $handle => $filename) {
        $path = get_stylesheet_directory() . '/' . $filename;
        wp_enqueue_style(
            $handle,
            get_stylesheet_directory_uri() . '/' . $filename,
            [$dependency],
            (string) filemtime($path)
        );
        $dependency = $handle;
    }

    if (is_product() || is_front_page() || is_shop() || is_product_taxonomy()) {
        $script_path = get_stylesheet_directory() . '/assets/js/pack-calculator.js';
        if (!is_product()) {
            wp_enqueue_script('wc-add-to-cart');
        }
        wp_enqueue_script(
            'ecocane-pack-calculator',
            get_stylesheet_directory_uri() . '/assets/js/pack-calculator.js',
            ['jquery', 'wc-add-to-cart'],
            (string) filemtime($script_path),
            true
        );
    }
});

add_action('woocommerce_product_options_inventory_product_data', static function () {
    global $post;

    woocommerce_wp_text_input([
        'id' => '_ecocane_pack_size',
        'label' => 'Минимальный заказ / штук в упаковке',
        'description' => 'Количество штук в одной упаковке. Оно станет минимумом и шагом заказа. У дочерних товаров сгруппированного товара собственное значение переопределит это значение. Если не указано, товар можно заказать от 1 штуки.',
        'desc_tip' => true,
        'type' => 'number',
        'value' => $post ? max(0, (int) get_post_meta($post->ID, '_ecocane_pack_size', true)) : 0,
        'custom_attributes' => [
            'min' => '0',
            'step' => '1',
        ],
    ]);
});

add_action('woocommerce_admin_process_product_object', static function ($product) {
    if (isset($_POST['_ecocane_pack_size'])) {
        $pack_size = max(0, (int) wc_clean(wp_unslash($_POST['_ecocane_pack_size'])));
        $product->update_meta_data('_ecocane_pack_size', $pack_size);
    }

    foreach (ecocane_product_description_fields() as $field) {
        if (isset($_POST[$field['id']]) && is_string($_POST[$field['id']])) {
            $product->update_meta_data(
                $field['id'],
                sanitize_textarea_field(wp_unslash($_POST[$field['id']]))
            );
        }
    }
});

function ecocane_product_description_fields()
{
    return [
        [
            'id' => '_ecocane_description_composition',
            'label' => 'Состав',
            'heading' => 'Состав:',
            'tag' => 'h3',
        ],
        [
            'id' => '_ecocane_description_properties',
            'label' => 'Свойства',
            'heading' => 'Свойства:',
            'tag' => 'h3',
        ],
        [
            'id' => '_ecocane_description_applications',
            'label' => 'Сферы применения',
            'heading' => 'Сферы применения:',
            'tag' => 'h3',
        ],
        [
            'id' => '_ecocane_description_supply',
            'label' => 'Объемы и поставка',
            'heading' => 'Объемы и поставка:',
            'tag' => 'h3',
        ],
    ];
}

add_filter('woocommerce_product_data_tabs', static function ($tabs) {
    $tabs['ecocane_description'] = [
        'label' => 'Блоки описания',
        'target' => 'ecocane_description_product_data',
        'class' => [],
        'priority' => 25,
    ];

    return $tabs;
});

add_action('woocommerce_product_data_panels', static function () {
    global $product_object;

    echo '<div id="ecocane_description_product_data" class="panel woocommerce_options_panel hidden">';
    echo '<div class="options_group">';

    foreach (ecocane_product_description_fields() as $field) {
        woocommerce_wp_textarea_input([
            'id' => $field['id'],
            'label' => $field['label'],
            'description' => 'Введите текст раздела. Переносы строк сохраняются на странице товара.',
            'desc_tip' => true,
            'rows' => 6,
            'class' => 'large',
            'style' => 'width: 100%; max-width: 760px;',
            'value' => $product_object instanceof WC_Product
                ? $product_object->get_meta($field['id'], true)
                : '',
        ], $product_object instanceof WC_Product ? $product_object : null);
    }

    echo '</div></div>';
});

add_filter('woocommerce_product_tabs', static function ($tabs) {
    $product = wc_get_product(get_queried_object_id());
    if (!($product instanceof WC_Product)) {
        return $tabs;
    }

    $has_description_sections = false;
    foreach (ecocane_product_description_fields() as $field) {
        if (trim((string) $product->get_meta($field['id'], true)) !== '') {
            $has_description_sections = true;
            break;
        }
    }

    if (!$has_description_sections) {
        return $tabs;
    }

    if (!isset($tabs['description'])) {
        $tabs['description'] = [
            'title' => __('Description', 'woocommerce'),
            'priority' => 10,
        ];
    }

    $tabs['description']['callback'] = 'ecocane_product_description_tab_with_sections';

    return $tabs;
}, 20);

function ecocane_product_description_tab_with_sections()
{
    global $post;

    $product = wc_get_product(get_queried_object_id());
    if (!($product instanceof WC_Product)) {
        return;
    }

    $product_description = $post instanceof WP_Post
        ? trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($post->post_content)))
        : '';

    if ($post instanceof WP_Post && trim($post->post_content) !== '') {
        woocommerce_product_description_tab();
    }

    echo '<div class="ecocane-product-description-sections">';
    foreach (ecocane_product_description_fields() as $field) {
        $content = trim((string) $product->get_meta($field['id'], true));
        if ($content === '') {
            continue;
        }

        if (
            $field['id'] === '_ecocane_description_composition'
            && $product_description !== ''
            && trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($content))) === $product_description
        ) {
            continue;
        }

        printf(
            '<section class="ecocane-product-description-section"><%1$s>%2$s</%1$s>%3$s</section>',
            esc_attr($field['tag']),
            esc_html($field['heading']),
            wpautop(esc_html($content))
        );
    }
    echo '</div>';
}

function ecocane_get_product_pack_size($product, $fallback_product = null)
{
    if (!($product instanceof WC_Product)) {
        return 1;
    }

    $pack_size = max(0, (int) $product->get_meta('_ecocane_pack_size', true));

    if (!$pack_size && ($product->is_type('variation') || $product->get_parent_id())) {
        $parent_product = wc_get_product($product->get_parent_id());
        if ($parent_product) {
            $pack_size = max(0, (int) $parent_product->get_meta('_ecocane_pack_size', true));
        }
    }

    if (
        !$pack_size
        && $fallback_product instanceof WC_Product
        && $fallback_product->is_type('grouped')
    ) {
        $pack_size = max(0, (int) $fallback_product->get_meta('_ecocane_pack_size', true));
    }

    return max(1, $pack_size);
}

function ecocane_is_valid_pack_quantity($quantity, $product, $pack_size_override = null)
{
    $pack_size = $pack_size_override
        ? max(1, (int) $pack_size_override)
        : ecocane_get_product_pack_size($product);
    if (!is_numeric($quantity)) {
        return false;
    }

    $integer_quantity = (int) $quantity;

    return (float) $quantity === (float) $integer_quantity
        && $integer_quantity >= $pack_size
        && $integer_quantity % $pack_size === 0;
}

function ecocane_pack_quantity_error($product, $pack_size_override = null)
{
    $pack_size = $pack_size_override
        ? max(1, (int) $pack_size_override)
        : ecocane_get_product_pack_size($product);

    return sprintf(
        'Для товара «%1$s» минимальный заказ — %2$s шт.; количество должно быть кратно этому значению.',
        $product->get_name(),
        number_format_i18n($pack_size)
    );
}

function ecocane_group_contains_product($grouped_product, $product_id)
{
    if (!($grouped_product instanceof WC_Product) || !$grouped_product->is_type('grouped')) {
        return false;
    }

    $child_ids = array_map('absint', $grouped_product->get_children());

    return in_array(absint($product_id), $child_ids, true);
}

function ecocane_pack_calculator_summary($product, $pack_size, $compact = false)
{
    $unit_price = (float) wc_get_price_to_display($product);
    $minimum_label = $compact
        ? __('Мин. заказ', 'ecocane-child')
        : __('Минимальный заказ', 'ecocane-child');

    return sprintf(
        '<div class="ecocane-pack-calculator__summary"><p class="ecocane-pack-calculator__minimum"><span>%1$s</span><strong>%2$s шт.</strong></p><p class="ecocane-pack-calculator__total"><span>Итого</span><strong class="ecocane-pack-calculator__amount" data-pack-total>%3$s</strong></p></div>',
        esc_html($minimum_label),
        esc_html(number_format_i18n($pack_size)),
        wp_kses_post(wc_price($unit_price * $pack_size, ['currency' => get_woocommerce_currency()]))
    );
}

function ecocane_pack_calculator_unit_price($product)
{
    $unit_price = (float) wc_get_price_to_display($product);

    return sprintf(
        '<div class="ecocane-pack-calculator__price"><span>Цена за 1 шт.</span><strong><span data-pack-unit-price>%1$s</span></strong></div>',
        wp_kses_post(wc_price($unit_price, ['currency' => get_woocommerce_currency()]))
    );
}

function ecocane_product_supports_loop_calculator($product)
{
    if (
        !($product instanceof WC_Product)
        || !$product->is_type('simple')
        || !$product->is_purchasable()
        || (!$product->is_in_stock() && !$product->backorders_allowed())
        || $product->is_sold_individually()
    ) {
        return false;
    }

    $pack_size = ecocane_get_product_pack_size($product);
    $maximum = $product->get_max_purchase_quantity();

    return $maximum < 0 || $maximum >= $pack_size;
}

function ecocane_loop_pack_calculator($product)
{
    if (!ecocane_product_supports_loop_calculator($product)) {
        return '';
    }

    $pack_size = ecocane_get_product_pack_size($product);
    $maximum = $product->get_max_purchase_quantity();
    $unit_price = (float) wc_get_price_to_display($product);
    $attributes = sprintf(
        ' class="ecocane-pack-calculator ecocane-pack-calculator--loop" data-loop-calculator="true" data-pack-size="%1$d" data-unit-price="%2$s" data-decimals="%3$d" data-decimal-separator="%4$s" data-thousand-separator="%5$s" data-currency-symbol="%6$s" data-currency-position="%7$s"',
        $pack_size,
        esc_attr(wc_format_decimal($unit_price, wc_get_price_decimals())),
        wc_get_price_decimals(),
        esc_attr(wc_get_price_decimal_separator()),
        esc_attr(wc_get_price_thousand_separator()),
        esc_attr(get_woocommerce_currency_symbol(get_woocommerce_currency())),
        esc_attr(get_option('woocommerce_currency_pos', 'left'))
    );
    $max_attribute = $maximum > 0
        ? ' max="' . esc_attr((string) $maximum) . '"'
        : '';

    return sprintf(
        '<div%1$s><div class="ecocane-pack-calculator__top">%2$s<div class="ecocane-pack-calculator__controls"><button class="ecocane-pack-calculator__step" type="button" data-pack-direction="-1" aria-label="%3$s">−</button><input class="input-text qty text ecocane-pack-calculator__quantity" type="number" inputmode="numeric" value="%4$d" min="%4$d" step="%4$d"%5$s aria-label="%6$s"><span class="ecocane-pack-calculator__unit">шт.</span><button class="ecocane-pack-calculator__step" type="button" data-pack-direction="1" aria-label="%7$s">+</button></div></div>%8$s</div>',
        $attributes,
        ecocane_pack_calculator_unit_price($product),
        esc_attr__('Уменьшить количество', 'ecocane-child'),
        $pack_size,
        $max_attribute,
        esc_attr(sprintf(__('Количество товара: %s', 'ecocane-child'), $product->get_name())),
        esc_attr__('Увеличить количество', 'ecocane-child'),
        ecocane_pack_calculator_summary($product, $pack_size, true)
    );
}

function ecocane_loop_add_to_cart_button($product)
{
    if (!ecocane_product_supports_loop_calculator($product)) {
        return '';
    }

    $button_classes = [
        'button',
        'wp-element-button',
        'wc-block-components-product-button__button',
        'product_type_simple',
        'add_to_cart_button',
    ];
    if (
        $product->supports('ajax_add_to_cart')
        && ($product->is_in_stock() || $product->backorders_allowed())
    ) {
        $button_classes[] = 'ajax_add_to_cart';
    }

    return sprintf(
        '<div class="wp-block-button wc-block-components-product-button"><a href="%1$s" class="%2$s" data-quantity="1" data-product_id="%3$d" data-product_sku="%4$s" aria-label="%5$s" rel="nofollow" role="button">%6$s</a></div>',
        esc_url($product->add_to_cart_url()),
        esc_attr(implode(' ', $button_classes)),
        absint($product->get_id()),
        esc_attr($product->get_sku()),
        esc_attr(wp_strip_all_tags($product->add_to_cart_description())),
        esc_html($product->add_to_cart_text())
    );
}

add_action('wp', static function () {
    if (!is_front_page()) {
        return;
    }

    remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10);
    add_action('woocommerce_after_shop_loop_item', static function () {
        global $product;

        if (ecocane_product_supports_loop_calculator($product)) {
            echo ecocane_loop_pack_calculator($product);
            return;
        }

        woocommerce_template_loop_price();
    }, 6);
});

add_filter('render_block_data', static function ($parsed_block, $source_block, $parent_block) 
{
    if (($parsed_block['blockName'] ?? '') === 'woocommerce/product-sku' && is_product()) {
        $parsed_block['attrs']['prefix'] = 'Артикул: ';
        return $parsed_block;
    }

    if (($parsed_block['blockName'] ?? '') !== 'core/navigation' || empty($parsed_block['innerBlocks'])) {
        return $parsed_block;
    }

    $page_urls = [
        'Каталог' => function_exists('wc_get_page_permalink')
            ? wc_get_page_permalink('shop')
            : home_url('/%d0%ba%d0%b0%d1%82%d0%b0%d0%bb%d0%be%d0%b3/'),
        'Корзина' => function_exists('wc_get_page_permalink')
            ? wc_get_page_permalink('cart')
            : home_url('/%d0%ba%d0%be%d1%80%d0%b7%d0%b8%d0%bd%d0%b0/'),
        'Личный кабинет' => function_exists('wc_get_page_permalink')
            ? wc_get_page_permalink('myaccount')
            : home_url('/%d0%bb%d0%b8%d1%87%d0%bd%d1%8b%d0%b9-%d0%ba%d0%b0%d0%b1%d0%b8%d0%bd%d0%b5%d1%82/'),
        'Оформление заказа' => function_exists('wc_get_page_permalink')
            ? wc_get_page_permalink('checkout')
            : home_url('/%d0%be%d1%84%d0%be%d1%80%d0%bc%d0%bb%d0%b5%d0%bd%d0%b8%d0%b5-%d0%b7%d0%b0%d0%ba%d0%b0%d0%b7%d0%b0/'),
    ];
    $company_page = get_page_by_path('о-компании', OBJECT, 'page');
    $company_url = $company_page
        ? get_permalink($company_page)
        : home_url('/%d0%be-%d0%ba%d0%be%d0%bc%d0%bf%d0%b0%d0%bd%d0%b8%d0%b8/');

    $contacts_index = null;
    $about_index = null;
    foreach ($parsed_block['innerBlocks'] as $index => &$item) {
        if (($item['blockName'] ?? '') !== 'core/navigation-link') {
            continue;
        }

        $label = $item['attrs']['label'] ?? '';
        if ($label === 'О бренде' || $label === 'О компании') {
            $item['attrs']['label'] = 'О компании';
            $item['attrs']['url'] = $company_url;
            $about_index = $index;
        } elseif ($label === 'Контакты') {
            $item['attrs']['url'] = home_url('/#контакты');
            $contacts_index = $index;
        } elseif (isset($page_urls[$label])) {
            $item['attrs']['url'] = $page_urls[$label];
        }
    }
    unset($item);

    if ($contacts_index !== null && $about_index !== null && $contacts_index > $about_index) {
        [$parsed_block['innerBlocks'][$contacts_index], $parsed_block['innerBlocks'][$about_index]] = [
            $parsed_block['innerBlocks'][$about_index],
            $parsed_block['innerBlocks'][$contacts_index],
        ];
    }

    return $parsed_block;
}, 10, 3);

add_filter('render_block', static function ($content, $parsed_block, $block_instance) {
    if (
        !in_array($parsed_block['blockName'] ?? '', ['woocommerce/product-price', 'woocommerce/product-button'], true)
        || !(is_shop() || is_product_taxonomy() || is_product())
        || empty($block_instance->context['query']['isProductCollectionBlock'])
        || empty($block_instance->context['postId'])
    ) {
        return $content;
    }

    $product = wc_get_product(absint($block_instance->context['postId']));
    if (!ecocane_product_supports_loop_calculator($product)) {
        return $content;
    }

    if ('woocommerce/product-price' === $parsed_block['blockName']) {
        return '<div class="wc-block-components-product-price wc-block-grid__product-price">' . ecocane_loop_pack_calculator($product) . '</div>';
    }

    return ecocane_loop_add_to_cart_button($product);
}, 10, 3);

add_filter('render_block_core/post-terms', static function ($content, $parsed_block) {
    if (
        is_product()
        && ($parsed_block['attrs']['term'] ?? '') === 'product_cat'
        && ($parsed_block['attrs']['prefix'] ?? '') === 'Category: '
    ) {
        return str_replace('Category: ', 'Категория: ', $content);
    }

    return $content;
}, 10, 2);

add_filter('render_block_core/heading', static function ($content) {
    if (is_product() && trim(wp_strip_all_tags($content)) === 'Related products') {
        return str_replace('Related products', 'Похожие товары', $content);
    }

    return $content;
}, 10, 1);

add_filter('woocommerce_quantity_input_args', static function ($args, $product) {
    $pack_size = ecocane_get_product_pack_size($product);
    $args['min_value'] = $pack_size;
    $args['step'] = $pack_size;

    if (
        is_product()
        && (int) get_queried_object_id() === (int) $product->get_id()
        && empty($_REQUEST['quantity'])
    ) {
        $args['input_value'] = $pack_size;
    }

    return $args;
}, 10, 2);

add_filter('woocommerce_add_to_cart_validation', static function ($passed, $product_id, $quantity, $variation_id = 0) {
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return $passed;
    }

    $product = wc_get_product($variation_id ?: $product_id);
    $grouped_parent = !empty($_REQUEST['add-to-cart'])
        ? wc_get_product(absint(wp_unslash($_REQUEST['add-to-cart'])))
        : null;
    $pack_size = ecocane_group_contains_product($grouped_parent, $product_id)
            ? ecocane_get_product_pack_size($product, $grouped_parent)
            : ecocane_get_product_pack_size($product);

    if (!$product || ecocane_is_valid_pack_quantity($quantity, $product, $pack_size)) {
        return $passed;
    }

    wc_add_notice(ecocane_pack_quantity_error($product, $pack_size), 'error');
    return false;
}, 10, 4);

add_filter('woocommerce_add_cart_item_data', static function ($cart_item_data, $product_id) {
    if (empty($_REQUEST['add-to-cart'])) {
        return $cart_item_data;
    }

    $grouped_parent = wc_get_product(absint(wp_unslash($_REQUEST['add-to-cart'])));
    if (
        !($grouped_parent instanceof WC_Product)
        || !ecocane_group_contains_product($grouped_parent, $product_id)
    ) {
        return $cart_item_data;
    }

    $child_product = wc_get_product($product_id);
    if ($child_product) {
        $cart_item_data['_ecocane_pack_size'] = ecocane_get_product_pack_size($child_product, $grouped_parent);
    }

    return $cart_item_data;
}, 10, 2);

add_filter('woocommerce_update_cart_validation', static function ($passed, $cart_item_key, $cart_item, $quantity) {
    $product = $cart_item['data'] ?? null;
    $pack_size = $cart_item['_ecocane_pack_size'] ?? null;
    if (!$passed || !$product || ecocane_is_valid_pack_quantity($quantity, $product, $pack_size)) {
        return $passed;
    }

    wc_add_notice(ecocane_pack_quantity_error($product, $pack_size), 'error');
    return false;
}, 10, 4);

add_filter('woocommerce_store_api_product_quantity_minimum', static function ($minimum, $product, $cart_item) {
    return isset($cart_item['_ecocane_pack_size'])
        ? max(1, (int) $cart_item['_ecocane_pack_size'])
        : $minimum;
}, 10, 3);

add_filter('woocommerce_store_api_product_quantity_multiple_of', static function ($multiple_of, $product, $cart_item) {
    return isset($cart_item['_ecocane_pack_size'])
        ? max(1, (int) $cart_item['_ecocane_pack_size'])
        : $multiple_of;
}, 10, 3);

add_filter('woocommerce_store_api_cart_item_quantity_validation', static function ($valid, $quantity, $product, $cart_item) {
    $pack_size = $cart_item['_ecocane_pack_size'] ?? null;
    if ($valid && !ecocane_is_valid_pack_quantity($quantity, $product, $pack_size)) {
        return new WP_Error(
            'ecocane_invalid_pack_quantity',
            ecocane_pack_quantity_error($product, $pack_size)
        );
    }

    return $valid;
}, 10, 4);

add_filter('woocommerce_grouped_product_list_column_quantity', static function ($quantity_html, $child_product) {
    $grouped_product = wc_get_product(get_queried_object_id());

    if (
        !($grouped_product instanceof WC_Product)
        || !($child_product instanceof WC_Product)
        || !ecocane_group_contains_product($grouped_product, $child_product->get_id())
        || $child_product->is_sold_individually()
        || strpos($quantity_html, 'name="quantity[') === false
    ) {
        return $quantity_html;
    }

    $pack_size = ecocane_get_product_pack_size($child_product, $grouped_product);
    $input_name = 'quantity[' . $child_product->get_id() . ']';
    $controls = sprintf(
        '<div class="ecocane-pack-calculator__controls"><button class="ecocane-pack-calculator__step" type="button" data-pack-direction="-1" aria-label="%1$s">−</button>%2$s<button class="ecocane-pack-calculator__step" type="button" data-pack-direction="1" aria-label="%3$s">+</button></div>',
        esc_attr__('Уменьшить количество', 'ecocane-child'),
        $quantity_html,
        esc_attr__('Увеличить количество', 'ecocane-child')
    );

    return sprintf(
        '<div class="ecocane-pack-calculator" data-pack-size="%1$d" data-unit-price="%2$s" data-decimals="%3$d" data-decimal-separator="%4$s" data-thousand-separator="%5$s" data-currency-symbol="%6$s" data-currency-position="%7$s" data-input-name="%8$s" data-optional="true"><div class="ecocane-pack-calculator__top">%9$s%10$s</div>%11$s</div>',
        $pack_size,
        esc_attr(wc_format_decimal((float) wc_get_price_to_display($child_product), wc_get_price_decimals())),
        wc_get_price_decimals(),
        esc_attr(wc_get_price_decimal_separator()),
        esc_attr(wc_get_price_thousand_separator()),
        esc_attr(get_woocommerce_currency_symbol(get_woocommerce_currency())),
        esc_attr(get_option('woocommerce_currency_pos', 'left')),
        esc_attr($input_name),
        ecocane_pack_calculator_unit_price($child_product),
        $controls,
        ecocane_pack_calculator_summary($child_product, $pack_size)
    );
}, 10, 2);

add_filter('woocommerce_grouped_product_columns', static function ($columns, $product) {
    if (is_product() && $product instanceof WC_Product && $product->is_type('grouped')) {
        return array_values(array_diff($columns, ['price']));
    }

    return $columns;
}, 10, 2);

add_action('woocommerce_before_add_to_cart_quantity', static function () {
    $product = wc_get_product(get_queried_object_id());

    if (!is_product() || !($product instanceof WC_Product) || $product->is_type('grouped')) {
        return;
    }

    $pack_size = ecocane_get_product_pack_size($product);
    $unit_price = (float) wc_get_price_to_display($product);
    $currency = get_woocommerce_currency();
    $currency_symbol = get_woocommerce_currency_symbol($currency);
    $currency_position = get_option('woocommerce_currency_pos', 'left');

    printf(
        '<div class="ecocane-pack-calculator" data-pack-size="%1$d" data-unit-price="%2$s" data-decimals="%3$d" data-decimal-separator="%4$s" data-thousand-separator="%5$s" data-currency-symbol="%6$s" data-currency-position="%7$s"><div class="ecocane-pack-calculator__top">%9$s<div class="ecocane-pack-calculator__controls"><button class="ecocane-pack-calculator__step" type="button" data-pack-direction="-1" aria-label="%8$s">−</button>',
        $pack_size,
        esc_attr(wc_format_decimal($unit_price, wc_get_price_decimals())),
        wc_get_price_decimals(),
        esc_attr(wc_get_price_decimal_separator()),
        esc_attr(wc_get_price_thousand_separator()),
        esc_attr($currency_symbol),
        esc_attr($currency_position),
        esc_attr__('Уменьшить количество', 'ecocane-child'),
        ecocane_pack_calculator_unit_price($product)
    );
});

add_action('woocommerce_after_add_to_cart_quantity', static function () {
    $product = wc_get_product(get_queried_object_id());

    if (!is_product() || !($product instanceof WC_Product) || $product->is_type('grouped')) {
        return;
    }

    $pack_size = ecocane_get_product_pack_size($product);

    echo '<button class="ecocane-pack-calculator__step" type="button" data-pack-direction="1" aria-label="' . esc_attr__('Увеличить количество', 'ecocane-child') . '">+</button>';
    echo '</div></div>' . ecocane_pack_calculator_summary($product, $pack_size) . '</div>';
});

add_filter('the_title', static function ($title, $post_id) {
    if (
        is_admin()
        || !function_exists('wc_get_page_id')
        || (int) $post_id !== (int) wc_get_page_id('myaccount')
        || $title !== 'My account'
    ) {
        return $title;
    }

    return 'Личный кабинет';
}, 10, 2);

add_filter('document_title_parts', static function ($title) {
    if (
        !is_admin()
        && function_exists('wc_get_page_id')
        && is_page(wc_get_page_id('myaccount'))
        && ($title['title'] ?? '') === 'My account'
    ) {
        $title['title'] = 'Личный кабинет';
    }

    return $title;
});

add_action('woocommerce_init', static function () {
    woocommerce_register_additional_checkout_field([
        'id' => 'ecocane/company-name',
        'label' => 'Компания или ИП',
        'optionalLabel' => 'Компания или ИП (необязательно)',
        'location' => 'order',
        'type' => 'text',
        'attributes' => [
            'autocomplete' => 'organization',
        ],
    ]);

    woocommerce_register_additional_checkout_field([
        'id' => 'ecocane/inn',
        'label' => 'ИНН',
        'optionalLabel' => 'ИНН (необязательно)',
        'location' => 'order',
        'type' => 'text',
        'attributes' => [
            'pattern' => '[0-9]{10}([0-9]{2})?',
            'title' => 'Введите 10 цифр для компании или 12 цифр для ИП.',
        ],
    ]);
});

add_shortcode('ecocane_categories', function ($attributes) {
    $attributes = shortcode_atts(
        ['layout' => 'cards'],
        $attributes,
        'ecocane_categories'
    );
    $layout = $attributes['layout'] === 'links' ? 'links' : 'cards';
    $terms = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => true,
        'orderby' => 'name',
        'order' => 'ASC',
    ]);

    if (is_wp_error($terms)) {
        error_log('eCoCane category listing failed: ' . $terms->get_error_message());
        return '';
    }

    $terms = array_values(array_filter($terms, static function ($term) {
        return $term->slug !== 'uncategorized';
    }));

    if (!$terms) {
        return '<p class="ecocane-empty-state">Категории появятся здесь, когда товары будут распределены по каталогу.</p>';
    }

    $html = sprintf(
        '<nav class="ecocane-categories ecocane-categories--%s" aria-label="%s">',
        esc_attr($layout),
        esc_attr__('Категории товаров', 'ecocane-child')
    );

    foreach ($terms as $term) {
        $url = get_term_link($term);
        if (is_wp_error($url)) {
            continue;
        }

        $html .= '<a class="ecocane-category" href="' . esc_url($url) . '">';

        if ($layout === 'cards') {
            $thumbnail_id = (int) get_term_meta($term->term_id, 'thumbnail_id', true);
            if ($thumbnail_id) {
                $image = wp_get_attachment_image(
                    $thumbnail_id,
                    'medium',
                    false,
                    ['class' => 'ecocane-category__image', 'loading' => 'lazy']
                );
                if ($image) {
                    $html .= $image;
                } else {
                    $html .= '<span class="ecocane-category__placeholder" aria-hidden="true"></span>';
                }
            } else {
                $html .= '<span class="ecocane-category__placeholder" aria-hidden="true"></span>';
            }
        }

        $html .= '<span class="ecocane-category__name">' . esc_html($term->name) . '</span>';
        $html .= '</a>';
    }

    return $html . '</nav>';
});

add_shortcode('ecocane_catalog_title', static function () {
    if (is_tax('product_cat')) {
        $category = get_queried_object();
        if ($category instanceof WP_Term) {
            return '<h1 class="wp-block-heading ecocane-shop__title">' . esc_html($category->name) . '</h1>';
        }
    }

    return '<h1 class="wp-block-heading ecocane-shop__title">Каталог</h1>';
});

add_filter('gettext_woocommerce', static function ($translation, $text) {
    if (is_admin()) {
        return $translation;
    }

    $labels = [
        'Add to cart' => 'В корзину',
        'Read more' => 'Подробнее',
        'Sale!' => 'Скидка',
        'Sale' => 'Скидка',
        'Default sorting' => 'Сначала популярные',
        'Sort by popularity' => 'По популярности',
        'Sort by average rating' => 'По рейтингу',
        'Sort by latest' => 'Сначала новые',
        'Sort by price: low to high' => 'Сначала дешевле',
        'Sort by price: high to low' => 'Сначала дороже',
        'Your cart' => 'Корзина',
        'Products in cart' => 'Товары в корзине',
        'Your cart is currently empty.' => 'Ваша корзина пока пуста.',
        'Return to shop' => 'Вернуться в каталог',
        'Proceed to checkout' => 'Перейти к оформлению заказа',
        'Update cart' => 'Обновить корзину',
        'Order summary' => 'Ваш заказ',
        'Contact information' => 'Контактные данные',
        'Billing address' => 'Адрес покупателя',
        'Shipping address' => 'Адрес доставки',
        'Place order' => 'Оформить заказ',
        'Read more about “%s”' => 'Подробнее о товаре «%s»',
    ];

    

    return $labels[$text] ?? $translation;
}, 10, 2);

add_filter('document_title_parts', static function ($title) {
    if (is_admin()) {
        return $title;
    }

    $title['site'] = 'eCoCane';

    if (is_front_page()) {
        $title['title'] = 'Посуда из сахарного тростника для дома и бизнеса';
    } elseif (function_exists('is_shop') && is_shop()) {
        $title['title'] = 'Каталог посуды из сахарного тростника';
    } elseif (function_exists('is_product_taxonomy') && is_product_taxonomy()) {
        $term = get_queried_object();
        if ($term instanceof WP_Term) {
            $name = preg_replace('/^[^\p{L}\p{N}]+/u', '', $term->name);
            $title['title'] = 'Посуда из сахарного тростника — ' . $name;
        }
    }

    return $title;
});

function ecocane_get_meta_description()
{
    if (is_front_page()) {
        return 'Посуда из сахарного тростника для дома и бизнеса: тарелки, ланч-боксы и упаковка eCoCane. Выберите товары и узнайте условия оптового заказа.';
    }

    if (function_exists('is_shop') && is_shop()) {
        return 'Каталог посуды из сахарного тростника eCoCane: тарелки, ланч-боксы и упаковка. Смотрите характеристики, цену за штуку и минимальный заказ.';
    }

    if (function_exists('is_product_taxonomy') && is_product_taxonomy()) {
        $term = get_queried_object();
        if (!($term instanceof WP_Term)) {
            return '';
        }

        $description = trim(wp_strip_all_tags(term_description($term->term_id, $term->taxonomy)));
        if ($description === '') {
            $name = preg_replace('/^[^\p{L}\p{N}]+/u', '', $term->name);
            $description = sprintf(
                'Посуда из сахарного тростника: %s для дома и профессиональных закупок. Смотрите товары, характеристики и условия заказа.',
                $name
            );
        }
    } elseif (function_exists('is_product') && is_product()) {
        $product = wc_get_product(get_queried_object_id());
        if (!($product instanceof WC_Product)) {
            return '';
        }

        $description = $product->get_short_description() ?: $product->get_description();
    } elseif (is_page()) {
        $page = get_queried_object();
        if (!($page instanceof WP_Post)) {
            return '';
        }

        $description = $page->post_excerpt ?: $page->post_content;
    } else {
        return '';
    }

    $description = html_entity_decode(
        wp_strip_all_tags(strip_shortcodes((string) $description), true),
        ENT_QUOTES,
        get_bloginfo('charset') ?: 'UTF-8'
    );
    $description = trim(preg_replace('/\s+/u', ' ', $description));

    return $description === '' ? '' : trim(wp_html_excerpt($description, 155, '…'));
}

add_action('wp_head', static function () {
    $description = ecocane_get_meta_description();
    if ($description !== '') {
        printf("<meta name=\"description\" content=\"%s\" />\n", esc_attr($description));
    }

    if (
        (function_exists('is_shop') && is_shop())
        || (function_exists('is_product_taxonomy') && is_product_taxonomy())
    ) {
        if ((int) get_query_var('paged') > 1) {
            return;
        }

        if (function_exists('is_shop') && is_shop()) {
            $shop_id = wc_get_page_id('shop');
            $canonical = $shop_id > 0 ? get_permalink($shop_id) : '';
        } else {
            $term = get_queried_object();
            $canonical = $term instanceof WP_Term ? get_term_link($term) : '';
            if (is_wp_error($canonical)) {
                $canonical = '';
            }
        }

        if ($canonical !== '') {
            printf("<link rel=\"canonical\" href=\"%s\" />\n", esc_url($canonical));
        }
    }
}, 2);

add_filter('wp_robots', static function ($robots) {
    if (function_exists('is_product_taxonomy') && is_product_taxonomy()) {
        $term = get_queried_object();
        if ($term instanceof WP_Term && (int) $term->count === 0) {
            $robots['noindex'] = true;
            $robots['follow'] = true;
        }
    }

    return $robots;
});

add_shortcode('ecocane_featured_products', static function () {
    $products = wc_get_products([
        'status' => 'publish',
        'limit' => 24,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);
    $product_ids = [];

    foreach ($products as $product) {
        if (!($product instanceof WC_Product) || !$product->get_image_id()) {
            continue;
        }

        $product_ids[] = $product->get_id();
        if (count($product_ids) === 4) {
            break;
        }
    }

    if (!$product_ids) {
        return '<p class="ecocane-empty-state">Товары с фотографиями скоро появятся в каталоге.</p>';
    }

    return do_shortcode(
        '[products ids="' . esc_attr(implode(',', $product_ids)) . '" columns="2"]'
    );
});


/* eCoCane: пользовательская социальная иконка MAX.*/

add_filter('block_core_social_link_get_services', function ($services) {
    $icon_path = get_stylesheet_directory() . '/assets/icons/max.svg';

    if (file_exists($icon_path)) {
        $svg = file_get_contents($icon_path);

$svg_content = preg_replace(
    '/^.*?<svg\b[^>]*>|<\/svg>.*$/si',
    '',
    $svg
);

$icon = '<g transform="scale(0.0536912752)">'
    . $svg_content
    . '</g>';

        $services['max'] = [
            'name' => 'MAX',
            'icon' => $icon,
        ];
    }

    return $services;
});


add_action('enqueue_block_editor_assets', function () {
    wp_enqueue_script('wp-blocks');
    wp_enqueue_script('wp-element');

    $icon_url = get_stylesheet_directory_uri() . '/assets/icons/max.svg';

    $script = <<<'JS'
(function (blocks, element, iconUrl) {
    if (!blocks || !element) return;

    var el = element.createElement;

    blocks.registerBlockVariation('core/social-link', {
        name: 'max',
        title: 'MAX',
        icon: el('img', {
            src: iconUrl,
            style: {
                width: '24px',
                height: '24px',
                objectFit: 'contain'
            }
        }),
        attributes: {
            service: 'max'
        },
        isActive: ['service']
    });
})(window.wp.blocks, window.wp.element, ICON_URL_PLACEHOLDER);
JS;

    $script = str_replace(
        'ICON_URL_PLACEHOLDER',
        wp_json_encode($icon_url),
        $script
    );

    wp_add_inline_script('wp-blocks', $script, 'after');
});
