<?php
    $contacts_data = get_fields('contacts');
    $footer_data = get_fields('footer');
	$cart_count = WC()->cart->get_cart_contents_count();
	$favorites_count = get_favorites_count_by_type('product');

    $hide_footer = get_query_var('hide_footer', false);
?>

<?php if (!$hide_footer && !((function_exists('is_cart') && is_cart()) || (function_exists('is_checkout') && is_checkout()))): ?>
    <div class="footer">
        <div class="container">
            <div class="footer-main">
                <div class="footer-info">
                    <?php if(isset($footer_data['logo_footer']) && !empty($footer_data['logo_footer'])) { ?>
                        <div class="footer-info-logo">
                            <?php if(is_front_page()) { ?>
                                <img src="<?php echo esc_url(finespirits_optimize_image_url($footer_data['logo_footer'])) ?>" alt="logo" />
                            <?php } else { ?>
                                <a href="<?php echo get_home_url(); ?>">
                                    <img src="<?php echo esc_url(finespirits_optimize_image_url($footer_data['logo_footer'])) ?>" alt="logo" />
                                </a>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    <?php if(isset($footer_data['text_footer']) && !empty($footer_data['text_footer'])) { ?>
                        <div class="footer-info-text"><?php echo $footer_data['text_footer'] ?></div>
                    <?php } ?>
                    <?php if(isset($footer_data['socials_footer']) && !empty($footer_data['socials_footer'])) { ?>
                        <div class="footer-info-socials">
                            <?php foreach($footer_data['socials_footer'] as $socials_footer_key => $socials_footer) { ?>
                                <?php if(
                                        (isset($socials_footer['url']) && !empty($socials_footer['url'])) &&
                                        (isset($socials_footer['icon']) && !empty($socials_footer['icon']))
                                    ) { ?>
                                        <a href="<?php echo $socials_footer['url'] ?>">
                                            <img src="<?php echo $socials_footer['icon'] ?>" alt="icon_<?php echo $socials_footer_key ?>" />
                                        </a>
                                <?php } ?>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
                <div class="footer-menu-wrap">
                    <div class="footer-menu">
                        <div class="footer-menu-title">
                            <?php esc_html_e('Customer Service', 'footer'); ?>
                        </div>
                        <div class="footer-menu-nav">
                            <?php wp_nav_menu( ['theme_location' => 'footer_pos_1'] ); ?>
                        </div>
                    </div>
                    <div class="footer-menu">
                        <div class="footer-menu-title">
                            <?php esc_html_e('Fine Spirits', 'footer'); ?>
                        </div>
                        <div class="footer-menu-nav">
                            <?php wp_nav_menu( ['theme_location' => 'footer_pos_2'] ); ?>
                        </div>
                    </div>
                    <div class="footer-menu">
                        <div class="footer-menu-title">
                            <?php esc_html_e('Catalog', 'footer'); ?>
                        </div>
                        <div class="footer-menu-nav">
                            <?php wp_nav_menu( ['theme_location' => 'footer_pos_3'] ); ?>
                        </div>
                    </div>
                    <div class="footer-menu">
                        <div class="footer-menu-title">
                            <?php esc_html_e('Other products', 'footer'); ?>
                        </div>
                        <div class="footer-menu-nav">
                            <?php wp_nav_menu( ['theme_location' => 'footer_pos_4'] ); ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="footer-contact">
                <div class="footer-contact-block">
                    <?php if(isset($contacts_data['phone']) && !empty($contacts_data['phone'])) { ?>
                        <div class="footer-contact-item">
                            <div class="footer-contact-item-head"><?php esc_html_e('Phone', 'footer'); ?></div>
                            <div class="footer-contact-item-value"><a href="tel:<?php echo $contacts_data['phone'] ?>"><?php echo $contacts_data['phone'] ?></a></div>
                        </div>
                    <?php } ?>
                    <?php if(isset($contacts_data['opening_hours']) && !empty($contacts_data['opening_hours'])) { ?>
                        <div class="footer-contact-item">
                            <div class="footer-contact-item-head"><?php esc_html_e('Opening hours', 'footer'); ?></div>
                            <div class="footer-contact-item-value">
                                <?php foreach($contacts_data['opening_hours'] as $opening_hours) { ?>
                                    <?php if(
                                        (isset($opening_hours['days']) && !empty($opening_hours['days'])) &&
                                        (isset($opening_hours['hours']) && !empty($opening_hours['hours']))
                                    ) { ?>
                                        <div class="footer-contact-item-work">
                                            <div class="footer-contact-item-days"><?php echo $opening_hours['days'] ?>:</div>
                                            <div class="footer-contact-item-hours"><?php echo $opening_hours['hours'] ?></div>
                                        </div>
                                    <?php } ?>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                    <?php if(isset($contacts_data['address']) && !empty($contacts_data['address'])) { ?>
                        <div class="footer-contact-item">
                            <div class="footer-contact-item-head"><?php esc_html_e('Address', 'footer'); ?></div>
                            <div class="footer-contact-item-value"><a target="_blank" href="https://maps.app.goo.gl/PXKCLyKQ2LUp8z888"><?php echo $contacts_data['address'] ?></a></div>
                        </div>
                    <?php } ?>
                    <?php if(isset($contacts_data['email_support']) && !empty($contacts_data['email_support'])) { ?>
                        <div class="footer-contact-item">
                            <div class="footer-contact-item-head">
                                <?php echo esc_html_e('Customer', 'footer') ?>
                                <span><?php esc_html_e('Service Support', 'footer'); ?></span>
                            </div>
                            <div class="footer-contact-item-value">
                                <a href="mailto:<?php echo $contacts_data['email_support'] ?>"><?php echo $contacts_data['email_support'] ?></a>
                            </div>
                        </div>
                    <?php } ?>
                    <?php if(isset($contacts_data['email_b2b']) && !empty($contacts_data['email_b2b'])) { ?>
                        <div class="footer-contact-item">
                            <div class="footer-contact-item-head"><?php esc_html_e('B2B cooperation, HoReCa, Corporate orders', 'footer'); ?></div>
                            <div class="footer-contact-item-value">
                                <a href="mailto:<?php echo $contacts_data['email_b2b'] ?>"><?php echo $contacts_data['email_b2b'] ?></a>
                            </div>
                        </div>
                    <?php } ?>
                    <?php if(isset($contacts_data['email_marketing']) && !empty($contacts_data['email_marketing'])) { ?>
                        <div class="footer-contact-item">
                            <div class="footer-contact-item-head"><?php esc_html_e('Partnerships, Marketing activities, Influencers, PR', 'footer'); ?></div>
                            <div class="footer-contact-item-value"><a href="mailto:<?php echo $contacts_data['email_marketing'] ?>"><?php echo $contacts_data['email_marketing'] ?></a></div>
                        </div>
                    <?php } ?>
                </div>
                <div class="footer-subscribe">
                    <?php if(isset($footer_data['title_subscribe']) && !empty($footer_data['title_subscribe'])) { ?>
                        <div class="footer-subscribe-head"><?php echo $footer_data['title_subscribe'] ?></div>
                    <?php } ?>
                    <?php if(isset($footer_data['description_subscribe']) && !empty($footer_data['description_subscribe'])) { ?>
                        <div class="footer-subscribe-text"><?php echo $footer_data['description_subscribe'] ?></div>
                    <?php } ?>
                    <div class="footer-subscribe-form">
                        <?php if(isset($footer_data['shortcode_subscribe']) && !empty($footer_data['shortcode_subscribe'])) { ?>
                            <?php echo do_shortcode($footer_data['shortcode_subscribe']) ?>
                        <?php } ?> 
                    </div>
                </div>
            </div>
            <div class="footer-copyright">
                <?php if(isset($footer_data['pay_items_copyright']) && !empty($footer_data['pay_items_copyright'])) { ?>
                    <div class="footer-copyright-pay">
                        <?php foreach($footer_data['pay_items_copyright'] as $pay_item_copyright) { ?>
                            <?php if(
                                (isset($pay_item_copyright['icon']) && !empty($pay_item_copyright['icon'])) &&
                                (isset($pay_item_copyright['alt']) && !empty($pay_item_copyright['alt']))
                            ) { ?>
                                <img src="<?php echo $pay_item_copyright['icon'] ?>" alt="<?php echo $pay_item_copyright['alt'] ?>" />
                            <?php } ?>
                        <?php } ?>
                    </div>
                <?php } ?>
                <div class="footer-copyright-block">
                    <?php if(isset($footer_data['text_copyright']) && !empty($footer_data['text_copyright'])) { ?>
                        <div class="footer-copyright-text"><?php echo $footer_data['text_copyright'] ?></div>
                    <?php } ?>
                    <div class="footer-copyright-menu"><?php wp_nav_menu( ['theme_location' => 'footer_pos_5'] ); ?></div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if(wp_is_mobile()) { ?>
    <div class="footer__panel">
        <div class="footer__panel-blocks">
            <div class="footer__panel-block <?= is_front_page() ? 'active' : '' ?>">
                <div class="footer__panel-icon"><?= finespirits_icon('home', array('width' => 24, 'height' => 24)) ?></div>
                <div class="footer__panel-title"><?php esc_html_e('Home', 'footer'); ?></div>
                <a href="<?= home_url('/') ?>"></a>
            </div>
            <?php if (!((function_exists('is_cart') && is_cart()) || (function_exists('is_checkout') && is_checkout()))): ?>
                <div class="footer__panel-block footer__panel-block--catalog">
                    <div class="footer__panel-icon"><?= finespirits_icon('menu-catalog', array('width' => 24, 'height' => 24)) ?></div>
                    <div class="footer__panel-title"><?php esc_html_e('Catalog', 'footer'); ?></div>
                    <a href="#" class="catalog-mobile-trigger"></a>
                </div>
            <?php endif; ?>
            <div class="footer__panel-block <?= ((function_exists('is_cart') && is_cart()) || (function_exists('is_checkout') && is_checkout()) ? 'active' : '') ?>">
                <div class="footer__panel-icon"><?= finespirits_icon('basket', array('width' => 24, 'height' => 24)) ?></div>
                <div class="footer__panel-title"><?php esc_html_e('Basket', 'footer'); ?></div>
				<span class="footer__action-count action__count" data-action-count="cart" <?= ($cart_count == 0 ? 'style="display: none"' : '') ?>><?= $cart_count ?></span>
                <a href="<?= home_url('/cart/') ?>"></a>
            </div>
            <div class="footer__panel-block <?= is_page('favorites') ? 'active' : '' ?>">
                <div class="footer__panel-icon"><?= finespirits_icon('heart', array('width' => 24, 'height' => 24)) ?></div>
                <div class="footer__panel-title"><?php esc_html_e('Favorites', 'footer'); ?></div>
				<span class="footer__action-count action__count" data-action-count="favorites" <?= ($favorites_count == 0 ? 'style="display: none"' : '') ?>><?= $favorites_count ?></span>
                <a href="<?= home_url('/favorites/') ?>"></a>
            </div>
            <?php
                $auth_active = '';
                $auth_url = is_user_logged_in() ? home_url('/account/') : home_url('/log-in/');
                
                $current_url = home_url($_SERVER['REQUEST_URI']);
                $account_url = home_url('/account/');
                
                if(
                    is_page('account') || 
                    strpos($current_url, $account_url) === 0 ||
                    is_page_template('pages/login.php') ||
                    is_page_template('pages/signup.php')
                ) {
                    $auth_active = 'active';
                }
            ?>
            <div class="footer__panel-block <?php echo $auth_active ?>">
                <div class="footer__panel-icon"><?= finespirits_icon('account', array('width' => 24, 'height' => 24)) ?></div>
                <div class="footer__panel-title"><?php esc_html_e('Account', 'footer'); ?></div>
                <a href="<?php echo $auth_url ?>"></a>
            </div>
        </div>
    </div>
<?php } ?>

<?php get_template_part("components/mobile/menu/main", '', []); ?>
<?php get_template_part("components/mobile/catalog", '', []); ?>

<?php if ( is_product_category() ) { ?>
    <?php get_template_part("blocks/modals/block", 'modal-newsletter', []); ?>
<?php } ?>

<?php get_template_part("blocks/modals/block", 'modal-age', []); ?>
<?php get_template_part("blocks/modals/block", 'modal-favorites-share', []); ?>
<?php get_template_part("blocks/modals/block", 'modal-notify-me', []); ?>
<?php get_template_part("blocks/modals/block", 'modal-birthday', []); ?>
<?php get_template_part("blocks/modals/block", 'modal-postcard', []); ?>
<?php get_template_part("blocks/modals/block", 'modal-giftbox', []); ?>
<?php get_template_part("blocks/modals/block", 'modal-giftbox-warning', []); ?>
<?php get_template_part("blocks/modals/block", 'modal-reviews', []); ?>
<?php get_template_part("blocks/modals/block", 'modal-review-write', []); ?>

<button type="button" class="button-go__up go-up"><?= finespirits_icon('arrow-up', array('width' => 40, 'height' => 40)) ?></button>

<?php wp_footer(); ?>
</body>
</html>
