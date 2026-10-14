<?php
/**
 * Plugin Name: ClickAdu
 * Plugin URI: https://github.com/RipoN4/ClickADU
 * Description: View Clickadu performance (Earnings, Impressions, and CPM) inside WordPress dashboard and toolbar.
 * Version: 1.1.0
 * Author: RipoN
 * Author URI: https://ripon.in/
 * Author Email: riponsahaji4@gmail.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: clickadu
 * Requires at least: 5.6
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

class Clickadu_Dashboard_Stats {
    const OPT_KEY = 'clickadu_dash_options';
    const API_BASE = 'https://v2.api.clickadu.com/partner/stats';

    public function __construct() {
        // Settings page under Settings submenu
        add_action('admin_menu', [$this, 'add_settings_submenu']);
        add_action('admin_init', [$this, 'register_settings']);

        // Plugin action links (Settings link on Plugins page)
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_plugin_action_links']);

        // Dashboard Widget
        add_action('wp_dashboard_setup', [$this, 'register_dashboard_widget']);

        // Admin Bar (toolbar) earnings badge
        add_action('admin_bar_menu', [$this, 'add_toolbar_earnings'], 1000);
        add_action('admin_head', [$this, 'toolbar_css']);
        add_action('wp_head', [$this, 'toolbar_css']); // show color on frontend too when admin bar is visible
    }

    /**
     * Add "Settings" shortcut link on the Plugins list table.
     */
    public function add_plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('options-general.php?page=clickadu-dash-stats')),
            esc_html__('Settings', 'clickadu')
        );
        array_unshift($links, $settings_link);
        return $links;
    }

    /* ----------------------------
     *  Settings (under Settings → ClickAdu)
     * ---------------------------- */
    public function add_settings_submenu() {
        add_options_page(
            __('ClickAdu', 'clickadu'),
            __('ClickAdu', 'clickadu'),
            'manage_options',
            'clickadu-dash-stats',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting(self::OPT_KEY, self::OPT_KEY, [$this, 'sanitize_options']);

        add_settings_section(
            'clickadu_main',
            __('API Settings', 'clickadu'),
            function () {
                echo '<p>' . esc_html__('Enter your Clickadu API details. These are used server-side only.', 'clickadu') . '</p>';
            },
            self::OPT_KEY
        );

        add_settings_field('token', __('API Token', 'clickadu'), function () {
            $o = $this->get_options();
            printf(
                '<input type="password" name="%s[token]" value="%s" class="regular-text" autocomplete="off" /> 
                <a href="https://pub.clickadu.com/apiIntegration" target="_blank" rel="noopener noreferrer" style="margin-left:10px;">%s</a>',
                esc_attr(self::OPT_KEY),
                esc_attr($o['token'] ?? ''),
                esc_html__('API Docs', 'clickadu')
            );
        }, self::OPT_KEY, 'clickadu_main');

        add_settings_field('timezone', __('API Timezone', 'clickadu'), function () {
            $o = $this->get_options();
            $tz = !empty($o['timezone']) ? $o['timezone'] : 'EST';
            printf(
                '<input type="text" name="%s[timezone]" value="%s" class="regular-text" placeholder="EST or Asia/Kolkata" />',
                esc_attr(self::OPT_KEY),
                esc_attr($tz)
            );
            echo ' <a href="https://faq.clickadu.com/en/articles/6234945-available-api-timezones" target="_blank" rel="noopener noreferrer">' . esc_html__('Available timezones', 'clickadu') . '</a>';
        }, self::OPT_KEY, 'clickadu_main');

        add_settings_field('zoneId', __('Zone ID', 'clickadu'), function () {
            $o = $this->get_options();
            printf(
                '<input type="text" name="%s[zoneId]" value="%s" class="regular-text" placeholder="Optional" />',
                esc_attr(self::OPT_KEY),
                esc_attr($o['zoneId'] ?? '')
            );
            echo '<p class="description">' . esc_html__('Filter to a specific zone. (Optional)', 'clickadu') . '</p>';
        }, self::OPT_KEY, 'clickadu_main');

        add_settings_field('toolbar_color', __('Toolbar Badge Color', 'clickadu'), function () {
            $o = $this->get_options();
            $color = $o['toolbar_color'] ?? 'green';
            ?>
            <select name="<?php echo esc_attr(self::OPT_KEY); ?>[toolbar_color]">
                <option value="green"  <?php selected($color, 'green');  ?>><?php esc_html_e('Green', 'clickadu'); ?></option>
                <option value="yellow" <?php selected($color, 'yellow'); ?>><?php esc_html_e('Yellow', 'clickadu'); ?></option>
            </select>
            <p class="description"><?php esc_html_e("Controls the color of the Today's earnings badge in the top admin bar.", 'clickadu'); ?></p>
            <?php
        }, self::OPT_KEY, 'clickadu_main');
    }

    public function sanitize_options($in) {
        $out = [];
        $out['token']         = isset($in['token']) ? sanitize_text_field($in['token']) : '';
        $out['timezone']      = !empty($in['timezone']) ? sanitize_text_field($in['timezone']) : 'EST';
        $out['zoneId']        = isset($in['zoneId']) ? sanitize_text_field($in['zoneId']) : '';
        $allowed_colors       = ['green', 'yellow'];
        $out['toolbar_color'] = in_array($in['toolbar_color'] ?? 'green', $allowed_colors, true)
                                ? $in['toolbar_color']
                                : 'green';
        return $out;
    }

    private function get_options() {
        return get_option(self::OPT_KEY, []);
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php settings_errors(); ?>
            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPT_KEY);
                do_settings_sections(self::OPT_KEY);
                submit_button(__('Save Settings', 'clickadu'));
                ?>
            </form>
        </div>
        <?php
    }

    /* ----------------------------
     *  Dashboard Widget
     * ---------------------------- */
    public function register_dashboard_widget() {
        wp_add_dashboard_widget(
            'clickadu_dash_widget',
            __('ClickAdu', 'clickadu'),
            [$this, 'render_dashboard_widget']
        );
    }

    public function render_dashboard_widget() {
        if (!current_user_can('manage_options')) {
            echo '<p>' . esc_html__('You do not have permission to view this widget.', 'clickadu-stats') . '</p>';
            return;
        }

        $o = $this->get_options();
        if (empty($o['token'])) {
            printf(
                '<p><strong>%s</strong> <a href="%s">%s</a></p>',
                esc_html__('Missing API token.', 'clickadu-stats'),
                esc_url(admin_url('options-general.php?page=clickadu-dash-stats')),
                esc_html__('Go to Settings → Clickadu Stats to configure.', 'clickadu-stats')
            );
            return;
        }

        $tz = !empty($o['timezone']) ? $o['timezone'] : 'EST';

        // Date helpers in the selected timezone
        try {
            $tzObj = new DateTimeZone($tz);
        } catch (Exception $e) {
            $tzObj = new DateTimeZone('EST');
        }

        $now           = new DateTime('now', $tzObj);
        $todayStr      = $now->format('Y-m-d');
        $yesterday     = (clone $now)->modify('-1 day');
        $yestStr       = $yesterday->format('Y-m-d');
        $monthStart    = (clone $now)->modify('first day of this month');
        $monthStartStr = $monthStart->format('Y-m-d');

        $ranges = [
            'today'      => [$todayStr, $todayStr],
            'yesterday'  => [$yestStr,  $yestStr],
            'this_month' => [$monthStartStr, $todayStr],
        ];

        $cards = [];
        foreach ($ranges as $label => [$from, $to]) {
            $cards[$label] = $this->get_totals($from, $to, $tz, $o['token'], $o['zoneId'] ?? '');
        }

        ?>
        <style>
            .clickadu-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 12px;
                margin-top: 5px;
            }
            .clickadu-card {
                background: #fff;
                border: 1px solid #dcdcde;
                border-radius: 6px;
                overflow: hidden;
            }
            .clickadu-card h4 {
                text-align: center;
                border-bottom: 1px solid #eee;
                padding: 7px 10px !important;
                margin: 0 !important;
                font-size: 13px;
                font-weight: 600 !important;
                background: #f8fafc;
                color: #1d2327;
            }
            .clickadu-metric {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 6px 10px;
                font-size: 13px;
                border-bottom: 1px dashed #f0f0f1;
            }
            .clickadu-metric:last-child {
                border-bottom: none;
            }
            .clickadu-metric .label {
                color: #646970;
                display: inline-flex;
                align-items: center;
            }
            .clickadu-metric .value {
                font-weight: 600;
                color: #2c3338;
            }
            .clickadu-metric .value.cadmony {
                font-size: 16px;
                color: #16a34a;
                font-weight: 700;
            }
            .clickadu-filter-info {
                margin-top: 12px;
                font-size: 12px;
                color: #646970;
                border-top: 1px solid #f0f0f1;
                padding-top: 8px;
            }
            @media (max-width: 782px) {
                .clickadu-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>

        <div class="clickadu-grid">
            <?php
            $labels = [
                'today'      => __('Today', 'clickadu-stats'),
                'yesterday'  => __('Yesterday', 'clickadu-stats'),
                'this_month' => __('This Month', 'clickadu-stats'),
            ];

            foreach ($labels as $key => $title) :
                $t = $cards[$key];
                ?>
                <div class="clickadu-card">
                    <h4><?php echo esc_html($title); ?></h4>
                    <?php if (isset($t['error'])) : ?>
                        <p style="color:#d63638; padding:8px 10px; margin:0; font-size:12px;"><?php echo esc_html($t['error']); ?></p>
                    <?php else :
                        $money = isset($t['money']) ? number_format_i18n((float)$t['money'], 2) : '0.00';
                        $imprN = isset($t['impressions']) ? (float)$t['impressions'] : 0.0;
                        $impr  = number_format_i18n($imprN);
                        $cpmN  = isset($t['cpm']) ? (float)$t['cpm'] : (($imprN > 0) ? ((float)$t['money'] / $imprN) * 1000 : 0.0);
                        $cpm   = number_format_i18n($cpmN, 2);
                        ?>
                        <div class="clickadu-metric" title="<?php esc_attr_e('Earnings ($ USD)', 'clickadu-stats'); ?>">
                            <span class="label">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" style="vertical-align:middle; margin-right:4px;">
                                    <g fill="none" stroke="currentColor" stroke-width="1.75">
                                        <circle cx="12" cy="12" r="10" opacity="0.5"/>
                                        <path stroke-linecap="round" d="M12 6v12m3-8.5C15 8.12 13.657 7 12 7S9 8.12 9 9.5s1.343 2.5 3 2.5s3 1.12 3 2.5s-1.343 2.5-3 2.5s-3-1.12-3-2.5"/>
                                    </g>
                                </svg>
                                <?php esc_html_e('Earnings', 'clickadu-stats'); ?>
                            </span>
                            <span class="value cadmony">$<?php echo esc_html($money); ?></span>
                        </div>

                        <div class="clickadu-metric cadimp" title="<?php esc_attr_e('Impressions', 'clickadu-stats'); ?>">
                            <span class="label">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" style="vertical-align:middle; margin-right:4px;">
                                    <g fill="none" stroke="currentColor" stroke-width="1.75">
                                        <path d="M3.275 15.296C2.425 14.192 2 13.639 2 12c0-1.64.425-2.191 1.275-3.296C4.972 6.5 7.818 4 12 4s7.028 2.5 8.725 4.704C21.575 9.81 22 10.361 22 12c0 1.64-.425 2.191-1.275 3.296C19.028 17.5 16.182 20 12 20s-7.028-2.5-8.725-4.704Z" opacity="0.5"/>
                                        <path d="M15 12a3 3 0 1 1-6 0a3 3 0 0 1 6 0Z"/>
                                    </g>
                                </svg>
                                <?php esc_html_e('Impressions', 'clickadu-stats'); ?>
                            </span>
                            <span class="value"><?php echo esc_html($impr); ?></span>
                        </div>

                        <div class="clickadu-metric cadpm" title="<?php esc_attr_e('CPM ($ per 1,000 Impressions)', 'clickadu-stats'); ?>">
                            <span class="label">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" style="vertical-align:middle; margin-right:4px;">
                                    <g fill="none" stroke="currentColor" stroke-width="1.75">
                                        <path d="M6.444 3.685a10 10 0 0 1 1.662-.896c1.403-.593 2.104-.89 3-.296C12 3.086 12 4.057 12 6v2c0 1.886 0 2.828.586 3.414S14.114 12 16 12h2c1.942 0 2.914 0 3.507.895s.297 1.596-.296 3a10 10 0 0 1-11.162 5.913A10 10 0 0 1 6.444 3.685Z"/>
                                        <path stroke-linecap="round" d="M14.5 2.315A10.02 10.02 0 0 1 21.685 9.5" opacity="0.5"/>
                                    </g>
                                </svg>
                                <?php esc_html_e('CPM', 'clickadu-stats'); ?>
                            </span>
                            <span class="value">$<?php echo esc_html($cpm); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php
        $zoneId  = $o['zoneId'] ?? '';
        $filters = [];
        if ($zoneId !== '') {
            $filters[] = __('Zone ID: ', 'clickadu-stats') . esc_html($zoneId);
        }
        $filters[] = __('Timezone: ', 'clickadu-stats') . esc_html($tz);

        echo '<p class="clickadu-filter-info">';
        if ($zoneId !== '') {
            printf(
                /* translators: %s: list of filters */
                esc_html__('Filtered by %s', 'clickadu-stats'),
                esc_html(implode(' | ', $filters))
            );
        } else {
            printf(
                /* translators: %s: timezone */
                esc_html__('Showing all zones | Timezone: %s', 'clickadu-stats'),
                esc_html($tz)
            );
        }
        echo '</p>';
    }

    /* ----------------------------
     *  Admin Bar Badge (Today’s Earnings)
     * ---------------------------- */
    public function add_toolbar_earnings($wp_admin_bar) {
        if (!is_user_logged_in() || !current_user_can('manage_options') || !is_admin_bar_showing()) {
            return;
        }

        $o = $this->get_options();
        if (empty($o['token'])) {
            return;
        }

        $tz = !empty($o['timezone']) ? $o['timezone'] : 'EST';
        try {
            $tzObj = new DateTimeZone($tz);
        } catch (Exception $e) {
            $tzObj = new DateTimeZone('EST');
        }
        $now      = new DateTime('now', $tzObj);
        $todayStr = $now->format('Y-m-d');

        $tot = $this->get_totals($todayStr, $todayStr, $tz, $o['token'], $o['zoneId'] ?? '');
        if (isset($tot['error'])) {
            return;
        }

        $money = isset($tot['money']) ? (float)$tot['money'] : 0.0;
        $label = '$' . number_format_i18n($money, 2);

        $wp_admin_bar->add_node([
            'id'    => 'clickadu_today_earnings',
            'title' => '<span class="clickadu-badge">' . esc_html($label) . '</span>',
            'href'  => admin_url('index.php'),
            'meta'  => [
                'title' => __("Clickadu Today's Earnings (Click to open Dashboard)", 'clickadu'),
                'class' => 'clickadu-today-earnings',
            ],
        ]);
    }

    public function toolbar_css() {
        if (!is_user_logged_in() || !current_user_can('manage_options') || !is_admin_bar_showing()) {
            return;
        }
        $o = $this->get_options();
        $color = $o['toolbar_color'] ?? 'green';
        $hex = ($color === 'yellow') ? '#FFCC00' : '#22c55e';
        ?>
        <style>
        #wp-admin-bar-clickadu_today_earnings > .ab-item,
        #wp-admin-bar-clickadu_today_earnings > .ab-item .clickadu-badge {
            color: <?php echo esc_attr($hex); ?> !important;
            font-weight: 700;
        }
        </style>
        <?php
    }

    /* ----------------------------
     *  API Helper (returns totals + CPM)
     * ---------------------------- */
    private function get_totals($dateFrom, $dateTo, $timezone, $token, $zoneId = '') {
        $params = [
            'token'    => $token,
            'dateFrom' => $dateFrom,
            'dateTo'   => $dateTo,
            'format'   => 'json',
            'timezone' => $timezone,
        ];
        if ($zoneId !== '') {
            $params['zoneId'] = $zoneId;
        }

        $cache_key = 'clickadu_tot_' . md5(wp_json_encode($params));
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $url = self::API_BASE . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        $resp = wp_remote_get($url, [
            'timeout' => 20,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($resp)) {
            $out = ['error' => $resp->get_error_message()];
            set_transient($cache_key, $out, 120); // cache errors briefly to avoid spamming failed requests
            return $out;
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);

        if ($code !== 200 || empty($body)) {
            $out = ['error' => sprintf(__('API error or empty response (HTTP %d).', 'clickadu-stats'), (int)$code)];
            set_transient($cache_key, $out, 120);
            return $out;
        }

        $json = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // XML fallback if the endpoint returned XML
            $xml = @simplexml_load_string($body);
            if ($xml && isset($xml->total)) {
                $impr  = (float)($xml->total->impressions ?? 0);
                $money = (float)($xml->total->money ?? 0);
                $cpm   = ($impr > 0) ? ($money / $impr) * 1000 : 0.0;
                $total = [
                    'impressions' => $impr,
                    'money'       => $money,
                    'cpm'         => $cpm,
                ];
                set_transient($cache_key, $total, 300);
                return $total;
            }
            $out = ['error' => __('Invalid JSON response from Clickadu API.', 'clickadu-stats')];
            set_transient($cache_key, $out, 120);
            return $out;
        }

        $total = $json['total'] ?? [];
        $impr  = isset($total['impressions']) ? (float)$total['impressions'] : 0.0;
        $money = isset($total['money']) ? (float)$total['money'] : 0.0;
        $cpm   = ($impr > 0) ? ($money / $impr) * 1000 : 0.0;

        $out = [
            'impressions' => $impr,
            'money'       => $money,
            'cpm'         => $cpm,
        ];

        set_transient($cache_key, $out, 300); // 5 minutes cache
        return $out;
    }
}

new Clickadu_Dashboard_Stats();

// Redirect to settings page after activation
register_activation_hook(__FILE__, function() {
    add_option('clickadu_dash_redirect', true);
});

add_action('admin_init', function() {
    if (get_option('clickadu_dash_redirect', false)) {
        delete_option('clickadu_dash_redirect');
        if (!isset($_GET['activate-multi'])) {
            wp_safe_redirect(admin_url('options-general.php?page=clickadu-dash-stats'));
            exit;
        }
    }
});