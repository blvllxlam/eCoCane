<?php

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'ecocane-child-style',
        get_stylesheet_uri(),
        ['organic-store-style'],
        '1.2.0'
    );
});
