<?php
/**
 * 4.5.6 — Header banner partagé (logo LWS Optimize, bouton Désactiver, promo
 * hébergement WPEXT15). Extrait de main_page.php pour réutilisation sur les
 * autres pages admin du plugin (RUM dashboard, etc.) afin de garder l'identité
 * visuelle du plugin sur toutes ses pages.
 *
 * Variable requise : $is_deactivated (option lws_optimize_deactivate_temporarily)
 */
if (!defined('ABSPATH')) exit;
if (!isset($is_deactivated)) {
    $is_deactivated = get_option('lws_optimize_deactivate_temporarily', false);
}
?>
<div class="lwsop_title_banner">
    <div class="lwsop_top_banner">
        <img src="<?php echo esc_url(plugins_url('images/plugin_lws_optimize_logo.svg', __DIR__)) ?>" alt="LWS Optimize Logo" width="80px" height="80px">
        <div class="lwsop_top_banner_text">
            <div class="lwsop_top_title_block">
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <div class="lwsop_top_title">
                        <span><?php echo esc_html('LWS Optimize'); ?></span>
                        <span><?php esc_html_e('by', 'lws-optimize'); ?></span>
                        <span class="logo_lws"></span>

                        <button class="lwsop_dropdown_button">
                            <span class="lwsop_dropdown_text">
                                <?php if ($is_deactivated) : ?>
                                    <?php echo esc_html__('Deactivated for: ', 'lws-optimize') . $is_deactivated; ?>
                                <?php else : ?>
                                    <?php esc_html_e('Deactivate temporarily: ', 'lws-optimize'); ?>
                                <?php endif; ?>
                            </span>
                            <span class="lwsop_dropdown_arrow">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </span>
                            <div class="lwsop_dropdown_content">
                                <?php if ($is_deactivated) : ?>
                                    <a href="#" data-config="0"><?php esc_html_e('Activate', 'lws-optimize'); ?></a>
                                <?php else : ?>
                                    <a href="#" data-config="300"><?php esc_html_e('5 minutes', 'lws-optimize'); ?></a>
                                    <a href="#" data-config="1800"><?php esc_html_e('30 minutes', 'lws-optimize'); ?></a>
                                    <a href="#" data-config="3600"><?php esc_html_e('1 hour', 'lws-optimize'); ?></a>
                                    <a href="#" data-config="86400"><?php esc_html_e('1 day', 'lws-optimize'); ?></a>
                                <?php endif; ?>
                            </div>
                        </button>
                    </div>

                    <div class="lwsop_top_description">
                        <?php echo esc_html_e('Your WordPress website, faster, lighter, smoother. LWS Optimize improves loading speed through caching, media optimization, minification, file concatenation...', 'lws-optimize'); ?>
                    </div>
                </div>
                <div class="lwsop_rate_block">
                    <div class="lwsop_top_rateus">
                        <?php echo esc_html_e('You like this plugin ? ', 'lws-optimize'); ?>
                        <?php echo wp_kses(__('A <a href="https://wordpress.org/support/plugin/lws-optimize/reviews/#new-post" target="_blank" class="link_to_rating_with_stars"><div class="lwsop_stars">★★★★★</div> rating</a> will motivate us a lot.', 'lws-optimize'), ['a' => ['class' => [], 'href' => [], 'target' => []], 'div' => ['class' => []]]); ?>
                    </div>
                    <div class="lwsop_bottom_rateus">
                        <img src="<?php echo esc_url(plugins_url('images/flamme.svg', __DIR__)) ?>" alt="Flamme Logo" width="16px" height="20px" style="margin-right: 5px;">
                        <?php echo wp_kses(__('<b>-15%</b> on our <a href="https://www.lws.fr/hebergement_wordpress.php" target="_blank" class="link_to_support">WordPress hostings</a> with the code', 'lws-optimize'), ['b' => [], 'a' => ['class' => [], 'href' => [], 'target' => []]]); ?>
                        <div class="lwsop_top_code">
                            WPEXT15
                            <img src="<?php echo esc_url(plugins_url('images/copier_new.svg', __DIR__)) ?>" alt="Logo Copy Element" width="15px" height="18px" onclick="lwsoptimize_copy_clipboard(this)" readonly text="WPEXT15">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
