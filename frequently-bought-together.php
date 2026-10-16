<?php
/*
Plugin Name: Custom Frequently Bought Together for WooCommerce
Description: Frequently Bought Together products with support for simple, variable, subscription and variable subscription products.
Version: 2.0.0
Author: Shimanta Das
*/

if (!defined('ABSPATH')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| WOOCOMMERCE CHECK
|--------------------------------------------------------------------------
*/

add_action('plugins_loaded', 'cfbt_init_plugin');

function cfbt_init_plugin()
{

    if (!class_exists('WooCommerce')) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | CONSTANTS
    |--------------------------------------------------------------------------
    */

    if (!defined('CFBT_META_KEY')) {
        define('CFBT_META_KEY', '_cfbt_product_ids');
    }


    /*
    |--------------------------------------------------------------------------
    | ADMIN META BOX
    |--------------------------------------------------------------------------
    */

    add_action(
        'add_meta_boxes',
        'cfbt_add_meta_box'
    );

    function cfbt_add_meta_box()
    {

        add_meta_box(
            'cfbt_frequently_bought_together',
            'Frequently Bought Together',
            'cfbt_render_meta_box',
            'product',
            'normal',
            'high'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ADMIN META BOX HTML
    |--------------------------------------------------------------------------
    */

    function cfbt_render_meta_box($post)
    {

        wp_nonce_field(
            'cfbt_save_meta_box',
            'cfbt_meta_box_nonce'
        );

        $product_ids = get_post_meta(
            $post->ID,
            CFBT_META_KEY,
            true
        );

        if (!is_array($product_ids)) {
            $product_ids = array();
        }

        $product_ids = array_map(
            'absint',
            $product_ids
        );

        $product_ids = array_filter(
            $product_ids
        );

        ?>

        <div class="cfbt-admin-box">

            <p>
                <strong>
                    Select Frequently Bought Together Products
                </strong>
            </p>

            <p>
                Search and select the products that should appear
                as Frequently Bought Together products.
            </p>

            <select name="cfbt_product_ids[]" id="cfbt_product_ids" class="wc-product-search" multiple="multiple"
                style="width:100%;" data-placeholder="Search for products..." data-action="woocommerce_json_search_products">

                <?php

                foreach ($product_ids as $product_id) {

                    if ((int) $product_id === (int) $post->ID) {
                        continue;
                    }

                    $product = wc_get_product(
                        $product_id
                    );

                    if (!$product) {
                        continue;
                    }

                    ?>

                    <option value="<?php echo esc_attr($product_id); ?>" selected="selected">
                        <?php
                        echo esc_html(
                            $product->get_formatted_name()
                        );
                        ?>
                    </option>

                    <?php
                }

                ?>

            </select>

            <p class="description">
                You can select multiple products. Variable products and
                subscription products are supported on the frontend.
            </p>

        </div>

        <style>
            .cfbt-admin-box {
                padding: 5px 0 10px;
            }

            .cfbt-admin-box p {
                margin: 8px 0 12px;
            }

            .cfbt-admin-box .select2-container {
                width: 100% !important;
            }
        </style>

        <?php
    }


    /*
    |--------------------------------------------------------------------------
    | SAVE META BOX
    |--------------------------------------------------------------------------
    */

    add_action(
        'save_post_product',
        'cfbt_save_meta_box',
        10,
        3
    );

    function cfbt_save_meta_box(
        $post_id,
        $post,
        $update
    ) {

        if (
            !isset(
            $_POST['cfbt_product_meta_nonce']
        )
        ) {

            if (
                !isset(
                $_POST['cfbt_meta_box_nonce']
            )
            ) {
                return;
            }

            $nonce = sanitize_text_field(
                wp_unslash(
                    $_POST['cfbt_meta_box_nonce']
                )
            );

        } else {

            $nonce = sanitize_text_field(
                wp_unslash(
                    $_POST['cfbt_product_meta_nonce']
                )
            );
        }


        if (
            !wp_verify_nonce(
                $nonce,
                'cfbt_save_meta_box'
            )
        ) {
            return;
        }


        if (
            defined('DOING_AUTOSAVE')
            &&
            DOING_AUTOSAVE
        ) {
            return;
        }


        if (
            wp_is_post_revision(
                $post_id
            )
        ) {
            return;
        }


        if (
            !current_user_can(
                'edit_post',
                $post_id
            )
        ) {
            return;
        }


        $product_ids = array();


        if (
            isset(
            $_POST['cfbt_product_ids']
        )
            &&
            is_array(
                $_POST['cfbt_product_ids']
            )
        ) {

            $product_ids = array_map(
                'absint',
                wp_unslash(
                    $_POST['cfbt_product_ids']
                )
            );

            $product_ids = array_filter(
                $product_ids
            );


            /*
            |--------------------------------------------------------------------------
            | REMOVE CURRENT PRODUCT
            |--------------------------------------------------------------------------
            */

            $product_ids = array_diff(
                $product_ids,
                array(
                    (int) $post_id
                )
            );


            /*
            |--------------------------------------------------------------------------
            | REMOVE DUPLICATES
            |--------------------------------------------------------------------------
            */

            $product_ids = array_values(
                array_unique(
                    $product_ids
                )
            );


            /*
            |--------------------------------------------------------------------------
            | VERIFY PRODUCT IDS
            |--------------------------------------------------------------------------
            */

            $valid_product_ids = array();

            foreach (
                $product_ids
                as $product_id
            ) {

                $product = wc_get_product(
                    $product_id
                );

                if (!$product) {
                    continue;
                }

                $valid_product_ids[] = $product_id;
            }

            $product_ids = $valid_product_ids;
        }


        /*
        |--------------------------------------------------------------------------
        | SAVE
        |--------------------------------------------------------------------------
        */

        if (!empty($product_ids)) {

            update_post_meta(
                $post_id,
                CFBT_META_KEY,
                $product_ids
            );

        } else {

            delete_post_meta(
                $post_id,
                CFBT_META_KEY
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | ADMIN ASSETS
    |--------------------------------------------------------------------------
    */

    add_action(
        'admin_enqueue_scripts',
        'cfbt_admin_assets'
    );

    function cfbt_admin_assets($hook)
    {

        if (
            $hook !== 'post.php'
            &&
            $hook !== 'post-new.php'
        ) {
            return;
        }

        $screen = get_current_screen();

        if (
            !$screen
            ||
            $screen->post_type !== 'product'
        ) {
            return;
        }


        wp_enqueue_script(
            'wc-enhanced-select'
        );

        wp_enqueue_style(
            'woocommerce_admin_styles'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | FRONTEND CSS
    |--------------------------------------------------------------------------
    */

    add_action(
        'wp_enqueue_scripts',
        'cfbt_frontend_assets'
    );

    function cfbt_frontend_assets()
    {

        if (!is_product()) {
            return;
        }


        wp_register_style(
            'cfbt-frontend',
            false
        );

        wp_enqueue_style(
            'cfbt-frontend'
        );


        // $css = '
        // .cfbt-wrapper {
        // 	margin: 30px 0;
        // 	padding: 25px;
        // 	border: 1px solid #ddd;
        // 	background: #fff;
        // 	box-sizing: border-box;
        // }

        // .cfbt-title {
        // 	margin: 0 0 20px;
        // 	font-size: 24px;
        // 	line-height: 1.3;
        // }

        // .cfbt-products {
        // 	display: flex;
        // 	flex-wrap: wrap;
        // 	gap: 20px;
        // }

        // .cfbt-product {
        // 	width: 220px;
        // 	padding: 15px;
        // 	border: 1px solid #ddd;
        // 	box-sizing: border-box;
        // 	background: #fff;
        // }

        // .cfbt-product-image {
        // 	text-align: center;
        // 	margin-bottom: 12px;
        // }

        // .cfbt-product-image img {
        // 	max-width: 100%;
        // 	height: auto;
        // 	display: inline-block;
        // }

        // .cfbt-product-name {
        // 	font-size: 16px;
        // 	line-height: 1.4;
        // 	margin-bottom: 8px;
        // }

        // .cfbt-product-name a {
        // 	text-decoration: none;
        // }

        // .cfbt-product-price {
        // 	margin-bottom: 12px;
        // 	font-weight: 600;
        // }

        // .cfbt-product-check {
        // 	display: flex;
        // 	align-items: flex-start;
        // 	gap: 8px;
        // 	margin-bottom: 12px;
        // }

        // .cfbt-product-check input {
        // 	margin-top: 4px;
        // }

        // .cfbt-variation-fields {
        // 	margin-top: 12px;
        // }

        // .cfbt-variation-field {
        // 	margin-bottom: 12px;
        // }

        // .cfbt-variation-field label {
        // 	display: block;
        // 	margin-bottom: 5px;
        // 	font-weight: 600;
        // 	font-size: 14px;
        // }

        // .cfbt-variation-field select {
        // 	width: 100%;
        // 	min-height: 40px;
        // 	padding: 7px 10px;
        // 	box-sizing: border-box;
        // }

        // .cfbt-variation-message {
        // 	margin-top: 8px;
        // 	font-size: 13px;
        // 	color: #b32d2e;
        // 	display: none;
        // }

        // .cfbt-total {
        // 	margin-top: 25px;
        // 	font-size: 18px;
        // }

        // .cfbt-add-button {
        // 	margin-top: 15px !important;
        // }

        // .cfbt-message {
        // 	margin-top: 15px;
        // }

        // .cfbt-main-product-notice {
        // 	margin-bottom: 15px;
        // 	padding: 10px;
        // 	background: #f7f7f7;
        // 	border: 1px solid #ddd;
        // 	font-size: 14px;
        // }

        // @media (max-width: 767px) {

        // 	.cfbt-products {
        // 		display: grid;
        // 		grid-template-columns: repeat(2, minmax(0, 1fr));
        // 		gap: 12px;
        // 	}

        // 	.cfbt-product {
        // 		width: 100%;
        // 	}

        // }

        // @media (max-width: 480px) {
        // 	.cfbt-products {
        // 		grid-template-columns: 1fr;
        // 	}
        // }
        // ';

        $css = "
        .cfbt-wrapper {
    margin: 30px 0;
    padding: 25px;
    border: 1px solid #ddd;
    background: #fff;
    box-sizing: border-box;
}

.cfbt-title {
    margin: 0 0 20px;
    font-size: 24px;
    line-height: 1.3;
}

.cfbt-products {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.cfbt-product {
    width: 100%;
    padding: 18px 0;
    border: 0;
    border-bottom: 1px solid #e5e5e5;
    box-sizing: border-box;
    background: #fff;

    display: grid;
    grid-template-columns: 100px 1fr;
    column-gap: 18px;
    position: relative;
}

.cfbt-product:first-child {
    padding-top: 0;
}

.cfbt-product:last-child {
    border-bottom: 0;
}


/* Product image */

.cfbt-product-image {
    grid-column: 1;
    grid-row: 1 / span 5;
    text-align: center;
    margin: 0;
}

.cfbt-product-image img {
    width: 100px;
    height: 100px;
    object-fit: cover;
    display: block;
}


/* Checkbox */

.cfbt-product-check {
    grid-column: 2;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
}

.cfbt-product-check input {
    margin: 0;
}


/* Product name */

.cfbt-product-name {
    grid-column: 2;
    font-size: 17px;
    line-height: 1.4;
    margin-bottom: 5px;
}

.cfbt-product-name a {
    text-decoration: none;
}


/* Product price */

.cfbt-product-price {
    grid-column: 2;
    margin-bottom: 8px;
    font-weight: 600;
}


/* Variation fields */

.cfbt-variation-fields {
    grid-column: 2;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 5px;
}

.cfbt-variation-field {
    margin-bottom: 8px;
    min-width: 160px;
}

.cfbt-variation-field label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    font-size: 14px;
}

.cfbt-variation-field select {
    width: 100%;
    min-height: 40px;
    padding: 7px 10px;
    box-sizing: border-box;
}


/* Variation error */

.cfbt-variation-message {
    grid-column: 2;
    margin-top: 5px;
    font-size: 13px;
    color: #b32d2e;
    display: none;
}


/* Total */

.cfbt-total {
    margin-top: 20px;
    padding-top: 18px;
    border-top: 1px solid #ddd;
    font-size: 18px;
}


/* Button */

.cfbt-add-button {
    margin-top: 15px !important;
}


/* Message */

.cfbt-message {
    margin-top: 15px;
}


/* Mobile */

@media (max-width: 600px) {

    .cfbt-product {
        grid-template-columns: 80px 1fr;
        column-gap: 12px;
    }

    .cfbt-product-image img {
        width: 80px;
        height: 80px;
    }

    .cfbt-variation-fields {
        display: block;
    }

    .cfbt-variation-field {
        width: 100%;
        min-width: 0;
    }

}
        ";

        wp_add_inline_style(
            'cfbt-frontend',
            $css
        );
    }


    /*
    |--------------------------------------------------------------------------
    | GET SAVED FBT PRODUCTS
    |--------------------------------------------------------------------------
    */

    function cfbt_get_product_ids($product_id)
    {

        $product_ids = get_post_meta(
            $product_id,
            CFBT_META_KEY,
            true
        );

        if (!is_array($product_ids)) {
            return array();
        }


        $product_ids = array_map(
            'absint',
            $product_ids
        );

        $product_ids = array_filter(
            $product_ids
        );

        return array_values(
            array_unique(
                $product_ids
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | GET VARIABLE PRODUCT DATA
    |--------------------------------------------------------------------------
    */

    function cfbt_get_variations_data($product)
    {

        if (
            !$product
            ||
            !$product->is_type(
                array(
                    'variable',
                    'variable-subscription'
                )
            )
        ) {
            return array();
        }


        $available_variations = $product->get_available_variations();


        $data = array();


        foreach (
            $available_variations
            as $variation
        ) {

            if (
                empty(
                $variation['variation_id']
            )
            ) {
                continue;
            }


            $data[] = array(
                'variation_id' => absint(
                    $variation['variation_id']
                ),

                'attributes' => !empty(
                    $variation['attributes']
                )
                    ? $variation['attributes']
                    : array(),

                'display_price' => isset(
                    $variation['display_price']
                )
                    ? (float) $variation['display_price']
                    : 0,

                'display_regular_price' => isset(
                    $variation['display_regular_price']
                )
                    ? (float) $variation['display_regular_price']
                    : 0,

                'is_in_stock' => !empty(
                    $variation['is_in_stock']
                ),

                'is_purchasable' => !empty(
                    $variation['is_purchasable']
                ),
            );
        }


        return $data;
    }


    /*
    |--------------------------------------------------------------------------
    | RENDER FBT BOX
    |--------------------------------------------------------------------------
    */

    function cfbt_render_frontend_box(
        $product_id = 0
    ) {

        if (!$product_id) {
            $product_id = get_the_ID();
        }


        if (!$product_id) {
            return '';
        }


        $main_product = wc_get_product(
            $product_id
        );


        if (!$main_product) {
            return '';
        }


        $related_ids = cfbt_get_product_ids(
            $product_id
        );


        if (empty($related_ids)) {
            return '';
        }


        $products = array();


        foreach (
            $related_ids
            as $related_id
        ) {

            if (
                (int) $related_id ===
                (int) $product_id
            ) {
                continue;
            }


            $product = wc_get_product(
                $related_id
            );


            if (!$product) {
                continue;
            }


            if (
                !$product->is_purchasable()
                ||
                !$product->is_in_stock()
            ) {
                continue;
            }


            $products[] = $product;
        }


        if (empty($products)) {
            return '';
        }


        ob_start();

        ?>

        <div class="cfbt-wrapper" data-main-product="<?php echo esc_attr($product_id); ?>">

            <h2 class="cfbt-title">
                Frequently Bought Together
            </h2>


            <div class="cfbt-products">


                <?php foreach ($products as $product): ?>

                    <?php

                    $is_variable = $product->is_type(
                        array(
                            'variable',
                            'variable-subscription'
                        )
                    );

                    $variation_data = array();

                    if ($is_variable) {
                        $variation_data =
                            cfbt_get_variations_data(
                                $product
                            );
                    }

                    ?>


                    <div class="cfbt-product" data-product-id="<?php echo esc_attr($product->get_id()); ?>"
                        data-product-type="<?php echo esc_attr($product->get_type()); ?>"
                        data-variations="<?php echo esc_attr(wp_json_encode($variation_data)); ?>">


                        <div class="cfbt-product-image">

                            <a href="<?php echo esc_url($product->get_permalink()); ?>">

                                <?php
                                echo $product->get_image(
                                    'woocommerce_thumbnail'
                                );
                                ?>

                            </a>

                        </div>


                        <div class="cfbt-product-check">

                            <input type="checkbox" class="cfbt-product-checkbox"
                                value="<?php echo esc_attr($product->get_id()); ?>">

                            <span>
                                Add this product
                            </span>

                        </div>


                        <div class="cfbt-product-name">

                            <a href="<?php echo esc_url($product->get_permalink()); ?>">

                                <?php
                                echo esc_html(
                                    $product->get_name()
                                );
                                ?>

                            </a>

                        </div>


                        <div class="cfbt-product-price">

                            <?php
                            echo wp_kses_post(
                                $product->get_price_html()
                            );
                            ?>

                        </div>


                        <?php if ($is_variable): ?>

                            <?php
                            $attributes =
                                $product->get_variation_attributes();
                            ?>


                            <div class="cfbt-variation-fields">

                                <?php foreach (
                                    $attributes
                                    as $attribute_name => $options
                                ): ?>

                                    <?php

                                    $attribute_label =
                                        wc_attribute_label(
                                            $attribute_name
                                        );

                                    $select_name =
                                        sanitize_title(
                                            $attribute_name
                                        );

                                    ?>


                                    <div class="cfbt-variation-field">

                                        <label>

                                            <?php
                                            echo esc_html(
                                                $attribute_label
                                            );
                                            ?>

                                        </label>


                                        <select class="cfbt-variation-select"
                                            data-attribute="<?php echo esc_attr('attribute_' . sanitize_title($attribute_name)); ?>">

                                            <option value="">
                                                Choose <?php echo esc_html($attribute_label); ?>
                                            </option>


                                            <?php foreach (
                                                $options
                                                as $option
                                            ): ?>

                                                <?php

                                                $option_value =
                                                    $option;

                                                $term =
                                                    get_term_by(
                                                        'slug',
                                                        $option,
                                                        $attribute_name
                                                    );

                                                if (
                                                    $term
                                                    &&
                                                    !is_wp_error(
                                                        $term
                                                    )
                                                ) {
                                                    $option_label =
                                                        $term->name;
                                                } else {
                                                    $option_label =
                                                        $option;
                                                }

                                                ?>


                                                <option value="<?php echo esc_attr($option_value); ?>">

                                                    <?php
                                                    echo esc_html(
                                                        $option_label
                                                    );
                                                    ?>

                                                </option>


                                            <?php endforeach; ?>

                                        </select>

                                    </div>


                                <?php endforeach; ?>

                            </div>


                            <div class="cfbt-variation-message">
                                Please select all product options.
                            </div>

                        <?php endif; ?>


                    </div>


                <?php endforeach; ?>


            </div>


            <div class="cfbt-total">

                <strong>
                    Total: <?php echo get_woocommerce_currency_symbol(); ?>
                </strong>

                <span class="cfbt-total-price">
                    <?php
                    echo wp_kses_post(
                        wc_price(
                            (float) $main_product->get_price()
                        )
                    );
                    ?>
                </span>

            </div>


            <button type="button" class="button alt cfbt-add-button">
                Add Selected Products to Cart
            </button>


            <div class="cfbt-message" style="display:none;"></div>

        </div>


        <?php

        return ob_get_clean();
    }


    /*
    |--------------------------------------------------------------------------
    | AUTOMATIC FRONTEND DISPLAY
    |--------------------------------------------------------------------------
    */

    add_action(
        'woocommerce_after_add_to_cart_form',
        'cfbt_automatic_display',
        20
    );

    function cfbt_automatic_display()
    {

        if (!is_product()) {
            return;
        }


        /*
        |--------------------------------------------------------------------------
        | DON'T DISPLAY AUTOMATICALLY IF SHORTCODE WAS USED
        |--------------------------------------------------------------------------
        */

        if (
            !empty(
            $GLOBALS['cfbt_shortcode_used']
        )
        ) {
            return;
        }


        echo cfbt_render_frontend_box(
            get_the_ID()
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SHORTCODE
    |--------------------------------------------------------------------------
    */

    add_shortcode(
        'custom-frequent-brought-together-products',
        'cfbt_shortcode'
    );

    function cfbt_shortcode(
        $atts = array()
    ) {

        $GLOBALS['cfbt_shortcode_used'] = true;


        $product_id = get_the_ID();


        $atts = shortcode_atts(
            array(
                'product_id' => 0,
            ),
            $atts,
            'custom-frequent-brought-together-products'
        );


        if (
            !empty(
            $atts['product_id']
        )
        ) {

            $product_id = absint(
                $atts['product_id']
            );
        }


        return cfbt_render_frontend_box(
            $product_id
        );
    }


    /*
    |--------------------------------------------------------------------------
    | AJAX ADD TO CART
    |--------------------------------------------------------------------------
    */

    add_action(
        'wp_ajax_cfbt_add_to_cart',
        'cfbt_ajax_add_to_cart'
    );

    add_action(
        'wp_ajax_nopriv_cfbt_add_to_cart',
        'cfbt_ajax_add_to_cart'
    );


    function cfbt_ajax_add_to_cart()
    {

        /*
        |--------------------------------------------------------------------------
        | NONCE
        |--------------------------------------------------------------------------
        */

        if (
            empty(
            $_POST['nonce']
        )
            ||
            !wp_verify_nonce(
                sanitize_text_field(
                    wp_unslash(
                        $_POST['nonce']
                    )
                ),
                'cfbt_add_to_cart'
            )
        ) {

            wp_send_json_error(
                array(
                    'message' =>
                        'Security check failed.',
                )
            );
        }


        /*
        |--------------------------------------------------------------------------
        | MAIN PRODUCT
        |--------------------------------------------------------------------------
        */

        $main_product_id =
            isset(
            $_POST['main_product_id']
        )
            ? absint(
                $_POST['main_product_id']
            )
            : 0;


        if (!$main_product_id) {

            wp_send_json_error(
                array(
                    'message' =>
                        'Main product is missing.',
                )
            );
        }


        /*
        |--------------------------------------------------------------------------
        | MAIN VARIATION
        |--------------------------------------------------------------------------
        */

        $main_variation_id =
            isset(
            $_POST['main_variation_id']
        )
            ? absint(
                $_POST['main_variation_id']
            )
            : 0;


        /*
        |--------------------------------------------------------------------------
        | MAIN ATTRIBUTES
        |--------------------------------------------------------------------------
        */

        $main_attributes = array();


        if (
            isset(
            $_POST['main_attributes']
        )
            &&
            is_array(
                $_POST['main_attributes']
            )
        ) {

            foreach (
                wp_unslash(
                    $_POST['main_attributes']
                )
                as $key => $value
            ) {

                $key = sanitize_text_field(
                    $key
                );

                $value = sanitize_text_field(
                    $value
                );


                if (
                    $key !== ''
                    &&
                    $value !== ''
                ) {

                    $main_attributes[
                        $key
                    ] = $value;
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | SELECTED FBT PRODUCTS
        |--------------------------------------------------------------------------
        */

        $selected_products = array();


        if (
            isset(
            $_POST['products']
        )
            &&
            is_array(
                $_POST['products']
            )
        ) {

            $raw_products =
                wp_unslash(
                    $_POST['products']
                );


            foreach (
                $raw_products
                as $item
            ) {

                if (
                    !is_array(
                        $item
                    )
                ) {
                    continue;
                }


                $product_id =
                    isset(
                    $item['product_id']
                )
                    ? absint(
                        $item['product_id']
                    )
                    : 0;


                $variation_id =
                    isset(
                    $item['variation_id']
                )
                    ? absint(
                        $item['variation_id']
                    )
                    : 0;


                $attributes = array();


                if (
                    isset(
                    $item['attributes']
                )
                    &&
                    is_array(
                        $item['attributes']
                    )
                ) {

                    foreach (
                        $item['attributes']
                        as $key => $value
                    ) {

                        $key =
                            sanitize_text_field(
                                $key
                            );

                        $value =
                            sanitize_text_field(
                                $value
                            );


                        if (
                            $key !== ''
                            &&
                            $value !== ''
                        ) {

                            $attributes[
                                $key
                            ] = $value;
                        }
                    }
                }


                if ($product_id) {

                    $selected_products[] = array(
                        'product_id' =>
                            $product_id,

                        'variation_id' =>
                            $variation_id,

                        'attributes' =>
                            $attributes,
                    );
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | ADD MAIN PRODUCT
        |--------------------------------------------------------------------------
        */

        $main_product = wc_get_product(
            $main_product_id
        );


        if (!$main_product) {

            wp_send_json_error(
                array(
                    'message' =>
                        'Main product could not be found.',
                )
            );
        }


        /*
        |--------------------------------------------------------------------------
        | ADD MAIN PRODUCT WITH VARIATION
        |--------------------------------------------------------------------------
        */

        $main_cart_item_key = false;


        if (
            $main_variation_id
            &&
            $main_product->is_type(
                array(
                    'variable',
                    'variable-subscription',
                )
            )
        ) {

            $variation =
                wc_get_product(
                    $main_variation_id
                );


            if (
                !$variation
                ||
                (int) $variation->get_parent_id()
                !== (int) $main_product_id
            ) {

                wp_send_json_error(
                    array(
                        'message' =>
                            'Invalid main product variation.',
                    )
                );
            }


            $main_cart_item_key =
                WC()->cart->add_to_cart(
                    $main_product_id,
                    1,
                    $main_variation_id,
                    $main_attributes
                );

        } else {

            /*
            |--------------------------------------------------------------------------
            | SIMPLE MAIN PRODUCT
            |--------------------------------------------------------------------------
            */

            $main_cart_item_key =
                WC()->cart->add_to_cart(
                    $main_product_id
                );
        }


        if (!$main_cart_item_key) {

            wp_send_json_error(
                array(
                    'message' =>
                        'Unable to add the main product to cart. Please check its required options.',
                )
            );
        }


        /*
        |--------------------------------------------------------------------------
        | GET ALLOWED FBT PRODUCTS
        |--------------------------------------------------------------------------
        */

        $allowed_products =
            cfbt_get_product_ids(
                $main_product_id
            );


        /*
        |--------------------------------------------------------------------------
        | ADD SELECTED FBT PRODUCTS
        |--------------------------------------------------------------------------
        */

        foreach (
            $selected_products
            as $item
        ) {

            $product_id =
                $item['product_id'];


            $variation_id =
                $item['variation_id'];


            $attributes =
                $item['attributes'];


            /*
            |--------------------------------------------------------------------------
            | SECURITY
            |--------------------------------------------------------------------------
            */

            if (
                !in_array(
                    $product_id,
                    $allowed_products,
                    true
                )
            ) {
                continue;
            }


            $product =
                wc_get_product(
                    $product_id
                );


            if (!$product) {
                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | VARIABLE PRODUCT
            |--------------------------------------------------------------------------
            */

            if (
                $product->is_type(
                    array(
                        'variable',
                        'variable-subscription',
                    )
                )
            ) {

                if (!$variation_id) {
                    continue;
                }


                $variation =
                    wc_get_product(
                        $variation_id
                    );


                if (
                    !$variation
                    ||
                    (int) $variation->get_parent_id()
                    !== (int) $product_id
                ) {
                    continue;
                }


                WC()->cart->add_to_cart(
                    $product_id,
                    1,
                    $variation_id,
                    $attributes
                );

            } else {

                /*
                |--------------------------------------------------------------------------
                | SIMPLE / SUBSCRIPTION PRODUCT
                |--------------------------------------------------------------------------
                */

                WC()->cart->add_to_cart(
                    $product_id
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | RETURN
        |--------------------------------------------------------------------------
        */

        wp_send_json_success(
            array(
                'message' =>
                    'Products added to cart.',

                'cart_url' =>
                    wc_get_cart_url(),

                'cart_count' =>
                    WC()->cart->get_cart_contents_count(),
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | FRONTEND JAVASCRIPT
    |--------------------------------------------------------------------------
    */

    add_action(
        'wp_footer',
        'cfbt_frontend_script',
        99
    );


    function cfbt_frontend_script()
    {

        if (!is_product()) {
            return;
        }

        ?>

        <script>

            jQuery(function ($) {


                /*
                |--------------------------------------------------------------------------
                | FIND MATCHING VARIATION
                |--------------------------------------------------------------------------
                */

                function cfbtFindVariation(
                    productBox
                ) {

                    var variations = [];

                    try {

                        variations = JSON.parse(
                            productBox.attr(
                                'data-variations'
                            )
                        );

                    } catch (error) {

                        return null;
                    }


                    var selectedAttributes = {};


                    productBox.find(
                        '.cfbt-variation-select'
                    ).each(function () {

                        var attribute =
                            $(this).data(
                                'attribute'
                            );

                        var value =
                            $(this).val();


                        if (
                            attribute
                            &&
                            value
                        ) {

                            selectedAttributes[
                                attribute
                            ] = value;
                        }

                    });


                    /*
                    |--------------------------------------------------------------------------
                    | CHECK ALL ATTRIBUTES SELECTED
                    |--------------------------------------------------------------------------
                    */

                    var complete = true;


                    productBox.find(
                        '.cfbt-variation-select'
                    ).each(function () {

                        if (
                            !$(this).val()
                        ) {

                            complete = false;
                        }

                    });


                    if (!complete) {
                        return null;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | FIND MATCH
                    |--------------------------------------------------------------------------
                    */

                    for (
                        var i = 0;
                        i < variations.length;
                        i++
                    ) {

                        var variation =
                            variations[i];


                        if (
                            !variation.is_in_stock
                            ||
                            !variation.is_purchasable
                        ) {
                            continue;
                        }


                        var matches = true;


                        for (
                            var attributeName in
                            variation.attributes
                        ) {

                            var variationValue =
                                variation.attributes[
                                attributeName
                                ];


                            var selectedValue =
                                selectedAttributes[
                                attributeName
                                ];


                            /*
                            |--------------------------------------------------------------------------
                            | Empty variation attribute = ANY
                            |--------------------------------------------------------------------------
                            */

                            if (
                                variationValue
                                &&
                                selectedValue
                                &&
                                variationValue !==
                                selectedValue
                            ) {

                                matches = false;

                                break;
                            }

                        }


                        if (matches) {
                            return variation;
                        }
                    }


                    return null;
                }


                /*
                |--------------------------------------------------------------------------
                | UPDATE VARIABLE PRODUCT
                |--------------------------------------------------------------------------
                */

                function cfbtUpdateVariableProduct(
                    productBox
                ) {

                    var variation =
                        cfbtFindVariation(
                            productBox
                        );


                    if (variation) {

                        productBox.attr(
                            'data-selected-variation',
                            variation.variation_id
                        );


                        productBox.find(
                            '.cfbt-variation-message'
                        ).hide();

                    } else {

                        productBox.attr(
                            'data-selected-variation',
                            '0'
                        );

                    }
                }


                /*
                |--------------------------------------------------------------------------
                | UPDATE TOTAL
                |--------------------------------------------------------------------------
                */

                function cfbtUpdateTotal(
                    wrapper
                ) {

                    var total = 0;


                    /*
                    |--------------------------------------------------------------------------
                    | MAIN PRODUCT
                    |--------------------------------------------------------------------------
                    */

                    var mainPrice =
                        parseFloat(
                            wrapper.attr(
                                'data-main-price'
                            )
                        );


                    if (
                        !isNaN(
                            mainPrice
                        )
                    ) {

                        total += mainPrice;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | FBT PRODUCTS
                    |--------------------------------------------------------------------------
                    */

                    wrapper.find(
                        '.cfbt-product'
                    ).each(function () {

                        var box =
                            $(this);


                        var checkbox =
                            box.find(
                                '.cfbt-product-checkbox'
                            );


                        if (
                            !checkbox.is(
                                ':checked'
                            )
                        ) {
                            return;
                        }


                        var type =
                            box.data(
                                'product-type'
                            );


                        if (
                            type === 'variable'
                            ||
                            type === 'variable-subscription'
                        ) {

                            cfbtUpdateVariableProduct(
                                box
                            );


                            var variation =
                                cfbtFindVariation(
                                    box
                                );


                            if (variation) {

                                total += parseFloat(
                                    variation.display_price
                                );
                            }

                        } else {

                            var price =
                                parseFloat(
                                    box.attr(
                                        'data-price'
                                    )
                                );


                            if (
                                !isNaN(
                                    price
                                )
                            ) {

                                total += price;
                            }
                        }

                    });


                    /*
                    |--------------------------------------------------------------------------
                    | DISPLAY
                    |--------------------------------------------------------------------------
                    */

                    wrapper.find(
                        '.cfbt-total-price'
                    ).text(
                        total.toFixed(2)
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | INITIALIZE DATA
                |--------------------------------------------------------------------------
                */

                $('.cfbt-wrapper').each(
                    function () {

                        var wrapper =
                            $(this);


                        /*
                        |--------------------------------------------------------------------------
                        | MAIN PRICE
                        |--------------------------------------------------------------------------
                        */

                        var mainPriceText =
                            $('form.cart .price')
                                .first()
                                .find('.amount')
                                .last()
                                .text();


                        var mainPrice =
                            parseFloat(
                                mainPriceText
                                    .replace(
                                        /[^0-9.,]/g,
                                        ''
                                    )
                                    .replace(
                                        /,/g,
                                        ''
                                    )
                            );


                        if (
                            isNaN(
                                mainPrice
                            )
                        ) {

                            mainPrice = 0;
                        }


                        wrapper.attr(
                            'data-main-price',
                            mainPrice
                        );


                        /*
                        |--------------------------------------------------------------------------
                        | SAVE FBT PRODUCT PRICES
                        |--------------------------------------------------------------------------
                        */

                        wrapper.find(
                            '.cfbt-product'
                        ).each(function () {

                            var box =
                                $(this);


                            var type =
                                box.data(
                                    'product-type'
                                );


                            if (
                                type !== 'variable'
                                &&
                                type !== 'variable-subscription'
                            ) {

                                var priceText =
                                    box.find(
                                        '.cfbt-product-price .amount'
                                    )
                                        .last()
                                        .text();


                                var price =
                                    parseFloat(
                                        priceText
                                            .replace(
                                                /[^0-9.,]/g,
                                                ''
                                            )
                                            .replace(
                                                /,/g,
                                                ''
                                            )
                                    );


                                if (
                                    isNaN(
                                        price
                                    )
                                ) {

                                    price = 0;
                                }


                                box.attr(
                                    'data-price',
                                    price
                                );
                            }

                        });


                        cfbtUpdateTotal(
                            wrapper
                        );

                    }
                );


                /*
                |--------------------------------------------------------------------------
                | FBT CHECKBOX
                |--------------------------------------------------------------------------
                */

                $(document).on(
                    'change',
                    '.cfbt-product-checkbox',
                    function () {

                        var box =
                            $(this).closest(
                                '.cfbt-product'
                            );


                        if (
                            !$(this).is(
                                ':checked'
                            )
                        ) {

                            box.find(
                                '.cfbt-variation-message'
                            ).hide();

                        }


                        cfbtUpdateTotal(
                            $(this).closest(
                                '.cfbt-wrapper'
                            )
                        );
                    }
                );


                /*
                |--------------------------------------------------------------------------
                | VARIATION CHANGE
                |--------------------------------------------------------------------------
                */

                $(document).on(
                    'change',
                    '.cfbt-variation-select',
                    function () {

                        var box =
                            $(this).closest(
                                '.cfbt-product'
                            );


                        cfbtUpdateVariableProduct(
                            box
                        );


                        cfbtUpdateTotal(
                            $(this).closest(
                                '.cfbt-wrapper'
                            )
                        );
                    }
                );


                /*
                |--------------------------------------------------------------------------
                | ADD PRODUCTS
                |--------------------------------------------------------------------------
                */

                $(document).on(
                    'click',
                    '.cfbt-add-button',
                    function (e) {

                        e.preventDefault();


                        var button =
                            $(this);


                        var wrapper =
                            button.closest(
                                '.cfbt-wrapper'
                            );


                        var mainProductId =
                            wrapper.data(
                                'main-product'
                            );


                        /*
                        |--------------------------------------------------------------------------
                        | MAIN PRODUCT VARIATION
                        |--------------------------------------------------------------------------
                        */

                        var mainVariationId = 0;

                        var mainAttributes = {};


                        /*
                        |--------------------------------------------------------------------------
                        | READ MAIN VARIABLE PRODUCT FORM
                        |--------------------------------------------------------------------------
                        */

                        var mainVariationInput =
                            $('form.cart input[name="variation_id"]');


                        if (
                            mainVariationInput.length
                        ) {

                            mainVariationId =
                                parseInt(
                                    mainVariationInput.val(),
                                    10
                                ) || 0;


                            $('form.cart')
                                .find(
                                    'select[name^="attribute_"], input[name^="attribute_"]'
                                )
                                .each(function () {

                                    var name =
                                        $(this).attr(
                                            'name'
                                        );

                                    var value =
                                        $(this).val();


                                    if (
                                        name
                                        &&
                                        value
                                    ) {

                                        mainAttributes[
                                            name
                                        ] = value;
                                    }

                                });


                            if (
                                !mainVariationId
                            ) {

                                alert(
                                    'Please select the options for the main product first.'
                                );

                                return;
                            }
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | COLLECT FBT PRODUCTS
                        |--------------------------------------------------------------------------
                        */

                        var products = [];

                        var validationFailed =
                            false;


                        wrapper.find(
                            '.cfbt-product'
                        ).each(function () {

                            var box =
                                $(this);


                            var checkbox =
                                box.find(
                                    '.cfbt-product-checkbox'
                                );


                            if (
                                !checkbox.is(
                                    ':checked'
                                )
                            ) {
                                return;
                            }


                            var productId =
                                parseInt(
                                    box.data(
                                        'product-id'
                                    ),
                                    10
                                );


                            var productType =
                                box.data(
                                    'product-type'
                                );


                            var variationId =
                                0;


                            var attributes = {};


                            /*
                            |--------------------------------------------------------------------------
                            | VARIABLE PRODUCT
                            |--------------------------------------------------------------------------
                            */

                            if (
                                productType ===
                                'variable'
                                ||
                                productType ===
                                'variable-subscription'
                            ) {

                                variationId =
                                    parseInt(
                                        box.attr(
                                            'data-selected-variation'
                                        ),
                                        10
                                    ) || 0;


                                box.find(
                                    '.cfbt-variation-select'
                                ).each(function () {

                                    var attribute =
                                        $(this).data(
                                            'attribute'
                                        );

                                    var value =
                                        $(this).val();


                                    if (
                                        attribute
                                        &&
                                        value
                                    ) {

                                        attributes[
                                            attribute
                                        ] = value;
                                    }

                                });


                                if (
                                    !variationId
                                ) {

                                    box.find(
                                        '.cfbt-variation-message'
                                    )
                                        .text(
                                            'Please select all product options.'
                                        )
                                        .show();


                                    validationFailed =
                                        true;


                                    return;
                                }
                            }


                            products.push({

                                product_id:
                                    productId,

                                variation_id:
                                    variationId,

                                attributes:
                                    attributes
                            });

                        });


                        if (
                            validationFailed
                        ) {

                            return;
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | BUTTON
                        |--------------------------------------------------------------------------
                        */

                        button.prop(
                            'disabled',
                            true
                        );


                        button.text(
                            'Adding...'
                        );


                        /*
                        |--------------------------------------------------------------------------
                        | AJAX
                        |--------------------------------------------------------------------------
                        */

                        $.ajax({

                            url:
                                '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',

                            type:
                                'POST',

                            dataType:
                                'json',

                            data: {

                                action:
                                    'cfbt_add_to_cart',

                                nonce:
                                    '<?php echo esc_js(wp_create_nonce('cfbt_add_to_cart')); ?>',

                                main_product_id:
                                    mainProductId,

                                main_variation_id:
                                    mainVariationId,

                                main_attributes:
                                    mainAttributes,

                                products:
                                    products

                            },


                            success: function (
                                response
                            ) {

                                if (
                                    response.success
                                ) {

                                    wrapper.find(
                                        '.cfbt-message'
                                    )
                                        .html(
                                            response.data.message
                                        )
                                        .show();


                                    /*
                                    |--------------------------------------------------------------------------
                                    | CART FRAGMENTS
                                    |--------------------------------------------------------------------------
                                    */

                                    $(document.body).trigger(
                                        'wc_fragment_refresh'
                                    );


                                    /*
                                    |--------------------------------------------------------------------------
                                    | GO TO CART
                                    |--------------------------------------------------------------------------
                                    */

                                    window.location.href =
                                        response.data.cart_url;

                                } else {

                                    var message =
                                        'Unable to add products to cart.';


                                    if (
                                        response.data
                                        &&
                                        response.data.message
                                    ) {

                                        message =
                                            response.data.message;
                                    }


                                    wrapper.find(
                                        '.cfbt-message'
                                    )
                                        .html(
                                            message
                                        )
                                        .show();

                                }

                            },


                            error: function () {

                                wrapper.find(
                                    '.cfbt-message'
                                )
                                    .html(
                                        'Something went wrong. Please try again.'
                                    )
                                    .show();

                            },


                            complete: function () {

                                button.prop(
                                    'disabled',
                                    false
                                );


                                button.text(
                                    'Add Selected Products to Cart'
                                );

                            }

                        });

                    }
                );

            });

        </script>

        <?php
    }
}
?>