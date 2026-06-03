<?php
/**
 * Plugin Name: Stagecard
 * Description: A program schedule builder that includes on-brand customization options and automated page creation for events, speakers, and sponsors.
 * Version: 1.19.008
 * Update URI: https://github.com/okohring/stagecard
 * Author: Olivia Kohring
 * Text Domain: program-agenda
 */

if (!defined('ABSPATH')) { exit; }

final class Program_Agenda_Plugin {
    const VERSION = '1.19.008';
    const GITHUB_REPO = 'okohring/stagecard';
    const OPT_EVENT = 'pa_event_page_settings';
    const OPT_SPEAKER = 'pa_speaker_page_settings';
    const META_EVENT_SETTINGS = '_pa_event_page_settings';
    const META_SPEAKER_SETTINGS = '_pa_speaker_page_settings';
    const OPT_DELETE_DATA_ON_UNINSTALL = 'pa_delete_data_on_uninstall';

    private $cached_theme_colors = null;

    public function __construct() {
        add_action('init', [__CLASS__, 'register_post_types']);
        add_action('init', [$this, 'maybe_flush_rewrite_rules_for_version'], 20);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_filter('parent_file', [$this, 'admin_parent_file']);
        add_filter('submenu_file', [$this, 'admin_submenu_file'], 10, 2);
        add_filter('admin_title', [$this, 'admin_title'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'public_assets']);
        add_filter('body_class', [$this, 'public_body_class']);
        add_action('wp_head', [$this, 'hide_theme_entity_page_title'], 20);
        add_action('admin_post_pa_save_program', [$this, 'save_program']);
        add_action('admin_post_pa_save_program_advanced', [$this, 'save_program_advanced']);
        add_action('admin_post_pa_save_event', [$this, 'save_event']);
        add_action('admin_post_pa_save_speaker', [$this, 'save_speaker']);
        add_action('admin_post_pa_save_sponsor', [$this, 'save_sponsor']);
        add_action('admin_post_pa_save_settings', [$this, 'save_settings']);
        add_action('admin_post_pa_delete_item', [$this, 'delete_item']);
        add_action('admin_post_pa_duplicate_item', [$this, 'duplicate_item']);
        add_action('admin_post_pa_bulk_items', [$this, 'bulk_items']);
        add_action('admin_post_pa_mass_import', [$this, 'mass_import']);
        add_action('admin_post_pa_download_import_template', [$this, 'download_import_template']);
        add_shortcode('program_agenda', [$this, 'shortcode_program_agenda']);
        add_shortcode('program_sponsors', [$this, 'shortcode_program_sponsors']);
        add_shortcode('program_speakers', [$this, 'shortcode_program_speakers']);
        add_shortcode('program_pdf', [$this, 'shortcode_program_pdf']);
        add_action('template_redirect', [$this, 'maybe_render_program_pdf_page']);
        add_filter('the_content', [$this, 'replace_single_content']);
        add_action('admin_bar_menu', [$this, 'admin_bar_edit_link'], 80);
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_github_plugin_update']);
        add_filter('site_transient_update_plugins', [$this, 'check_github_plugin_update']);
        add_filter('plugins_api', [$this, 'github_plugin_info'], 20, 3);
        add_action('admin_init', [$this, 'maybe_clear_github_update_cache']);
    }

    public static function register_post_types() {
        register_post_type('pa_program', [
            'labels' => ['name' => 'Programs', 'singular_name' => 'Program'],
            'public' => false,
            'show_ui' => false,
            'supports' => ['title', 'author'],
            'capability_type' => 'post',
        ]);
        register_post_type('pa_event', [
            'labels' => ['name' => 'Events', 'singular_name' => 'Event'],
            'public' => true,
            'show_ui' => false,
            'has_archive' => false,
            'rewrite' => ['slug' => 'program-event', 'with_front' => false],
            'supports' => ['title', 'editor', 'thumbnail', 'author'],
            'capability_type' => 'post',
        ]);
        register_post_type('pa_speaker', [
            'labels' => ['name' => 'Speakers', 'singular_name' => 'Speaker'],
            'public' => true,
            'show_ui' => false,
            'has_archive' => false,
            'rewrite' => ['slug' => 'program-speaker', 'with_front' => false],
            'supports' => ['title', 'editor', 'thumbnail', 'author'],
            'capability_type' => 'post',
        ]);
        register_post_type('pa_sponsor', [
            'labels' => ['name' => 'Sponsors', 'singular_name' => 'Sponsor'],
            'public' => true,
            'show_ui' => false,
            'has_archive' => false,
            'rewrite' => ['slug' => 'program-sponsor', 'with_front' => false],
            'supports' => ['title', 'editor', 'thumbnail', 'author'],
            'capability_type' => 'post',
        ]);
    }


    public function maybe_flush_rewrite_rules_for_version() {
        $stored_version = get_option('pa_program_agenda_rewrite_version', '');
        if ($stored_version !== self::VERSION) {
            flush_rewrite_rules(false);
            update_option('pa_program_agenda_rewrite_version', self::VERSION, false);
        }
    }

    public function admin_menu() {
        add_menu_page('Programs', 'Programs', 'edit_posts', 'program-main', [$this, 'page_programs'], 'dashicons-calendar-alt', 26);
        add_submenu_page('program-main', 'Programs', 'Programs', 'edit_posts', 'program-main', [$this, 'page_programs']);
        add_submenu_page('program-main', 'Events', 'Events', 'edit_posts', 'program-events', [$this, 'page_events']);
        add_submenu_page('program-main', 'Speakers', 'Speakers', 'edit_posts', 'program-speakers', [$this, 'page_speakers']);
        add_submenu_page('program-main', 'Sponsors', 'Sponsors', 'edit_posts', 'program-sponsors', [$this, 'page_sponsors']);
        add_submenu_page('program-main', 'Mass Import', 'Mass Import', 'edit_posts', 'program-mass-import', [$this, 'page_mass_import']);
        add_submenu_page('program-main', 'Advanced Settings', 'Advanced Settings', 'edit_posts', 'program-advanced-settings', [$this, 'page_program_advanced_settings']);
        add_submenu_page('program-main', 'Admin Settings', 'Admin Settings', 'manage_options', 'program-admin-settings', [$this, 'page_settings']);
        add_submenu_page(null, 'Edit Program', 'Edit Program', 'edit_posts', 'program-edit-program', [$this, 'form_program']);
        add_submenu_page(null, 'Edit Event', 'Edit Event', 'edit_posts', 'program-edit-event', [$this, 'form_event']);
        add_submenu_page(null, 'Edit Speaker', 'Edit Speaker', 'edit_posts', 'program-edit-speaker', [$this, 'form_speaker']);
        add_submenu_page(null, 'Edit Sponsor', 'Edit Sponsor', 'edit_posts', 'program-edit-sponsor', [$this, 'form_sponsor']);
    }

    public function admin_title($admin_title, $title) {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (strpos($page, 'program-') === 0) {
            $section_title = $this->admin_section_title_for_page($page);
            return $section_title . ' ‹ ' . get_bloginfo('name') . ' — WordPress';
        }
        return $admin_title;
    }

    private function admin_section_title_for_page($page) {
        $map = [
            'program-main' => 'Programs',
            'program-events' => 'Events',
            'program-speakers' => 'Speakers',
            'program-sponsors' => 'Sponsors',
            'program-mass-import' => 'Mass Import',
            'program-advanced-settings' => 'Advanced Settings',
            'program-admin-settings' => 'Admin Settings',
            'program-edit-program' => 'Programs',
            'program-edit-event' => 'Events',
            'program-edit-speaker' => 'Speakers',
            'program-edit-sponsor' => 'Sponsors',
        ];
        return $map[$page] ?? 'Programs';
    }

    public function admin_parent_file($parent_file) {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (strpos($page, 'program-') === 0) {
            return 'program-main';
        }
        return $parent_file;
    }

    public function admin_submenu_file($submenu_file, $parent_file) {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $map = [
            'program-main' => 'program-main',
            'program-events' => 'program-events',
            'program-speakers' => 'program-speakers',
            'program-sponsors' => 'program-sponsors',
            'program-mass-import' => 'program-mass-import',
            'program-advanced-settings' => 'program-advanced-settings',
            'program-edit-program' => 'program-main',
            'program-edit-event' => 'program-events',
            'program-edit-speaker' => 'program-speakers',
            'program-edit-sponsor' => 'program-sponsors',
            'program-admin-settings' => 'program-admin-settings',
        ];
        return $map[$page] ?? $submenu_file;
    }

    public function admin_assets($hook) {
        if (strpos($hook, 'program') === false) { return; }
        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_style('pa-admin', plugin_dir_url(__FILE__) . 'assets/css/admin.css', [], self::VERSION);
        $theme_colors = $this->theme_palette_colors();
        wp_add_inline_style('pa-admin', $this->admin_theme_css($theme_colors));
        wp_enqueue_script('pa-admin', plugin_dir_url(__FILE__) . 'assets/js/admin.js', ['jquery','wp-color-picker','jquery-ui-sortable'], self::VERSION, true);
        wp_localize_script('pa-admin', 'paProgramAgenda', [
            'themeColors' => $theme_colors,
            'programCategories' => $this->program_categories_for_js(),
            'programDates' => $this->program_dates_for_js(),
            'programSponsorLevels' => $this->program_sponsor_levels_for_js(),
        ]);
    }


    private function admin_theme_css($colors) {
        $colors = array_values(array_filter((array)$colors, function($color) {
            return is_string($color) && preg_match('/^#([A-Fa-f0-9]{3}){1,2}$/', $color);
        }));
        $accent = $this->first_usable_admin_color($colors) ?: '#2271b1';
        $accent_two = $colors[1] ?? $accent;
        $accent_three = $colors[2] ?? '#f0f0f1';
        return sprintf(
            '.pa-wrap{--pa-theme-primary:%s;--pa-theme-secondary:%s;--pa-theme-tertiary:%s;}',
            esc_attr($accent),
            esc_attr($accent_two),
            esc_attr($accent_three)
        );
    }

    private function first_usable_admin_color($colors) {
        foreach ((array)$colors as $color) {
            $hex = ltrim($color, '#');
            if (strlen($hex) === 3) {
                $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
            }
            if (strlen($hex) !== 6) { continue; }
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            $luminance = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b);
            if ($luminance > 32 && $luminance < 235) { return '#' . $hex; }
        }
        return '';
    }

    private function theme_palette_colors() {
        if ($this->cached_theme_colors !== null) {
            return $this->cached_theme_colors;
        }

        $colors = [];

        // First preference: colors supplied by the active theme. This includes
        // Salient/Nectar options, block/theme.json palettes, classic editor palettes,
        // Customizer theme mods, and common theme option arrays.
        $colors = array_merge($colors, $this->active_theme_palette_colors());

        // Second preference: WordPress' own default palette only if the active theme
        // did not provide enough usable colors.
        if (count($colors) < 3 && function_exists('wp_get_global_settings')) {
            $palette = wp_get_global_settings(['color', 'palette']);
            if (!empty($palette['default']) && is_array($palette['default'])) {
                foreach ($palette['default'] as $item) {
                    if (!empty($item['color']) && is_string($item['color'])) {
                        $this->append_hex_color($colors, $item['color']);
                    }
                }
            }
        }

        if (count($colors) < 3) {
            foreach (['#000000', '#666666', '#ffffff'] as $fallback) {
                $this->append_hex_color($colors, $fallback);
            }
        }
        $this->cached_theme_colors = array_slice($colors, 0, 6);
        return $this->cached_theme_colors;
    }

    private function active_theme_palette_colors() {
        $colors = [];

        foreach ($this->salient_palette_colors() as $color) {
            $this->append_hex_color($colors, $color);
        }

        if (function_exists('wp_get_global_settings')) {
            $palette = wp_get_global_settings(['color', 'palette']);
            if (!empty($palette['theme']) && is_array($palette['theme'])) {
                foreach ($palette['theme'] as $item) {
                    if (!empty($item['color']) && is_string($item['color'])) {
                        $this->append_hex_color($colors, $item['color']);
                    }
                }
            }
        }

        if (function_exists('get_theme_support')) {
            $editor_palette = get_theme_support('editor-color-palette');
            if (is_array($editor_palette)) {
                $palette_items = $editor_palette[0] ?? $editor_palette;
                if (is_array($palette_items)) {
                    foreach ($palette_items as $item) {
                        if (!empty($item['color']) && is_string($item['color'])) {
                            $this->append_hex_color($colors, $item['color']);
                        }
                    }
                }
            }
        }

        foreach ($this->theme_mod_palette_colors() as $color) {
            $this->append_hex_color($colors, $color);
        }

        foreach ($this->theme_option_palette_colors() as $color) {
            $this->append_hex_color($colors, $color);
        }

        return array_slice($colors, 0, 6);
    }

    private function append_hex_color(&$colors, $color) {
        if (!is_string($color)) { return; }
        $color = trim($color);
        if (preg_match('/^#([A-Fa-f0-9]{3}){1,2}$/', $color)) {
            $normalized = sanitize_hex_color($color);
            if ($normalized && !in_array(strtolower($normalized), array_map('strtolower', $colors), true)) {
                $colors[] = $normalized;
            }
        }
    }

    private function theme_mod_palette_colors() {
        $colors = [];
        if (!function_exists('get_theme_mods')) { return $colors; }
        $mods = get_theme_mods();
        if (!is_array($mods)) { return $colors; }

        $preferred_keys = [
            'accent_color', 'primary_color', 'secondary_color', 'tertiary_color',
            'link_color', 'button_color', 'background_color', 'header_textcolor',
            'text_color', 'heading_color', 'main_color', 'brand_color',
        ];

        foreach ($preferred_keys as $key) {
            if (isset($mods[$key])) {
                $value = (string) $mods[$key];
                if ($key === 'background_color' || $key === 'header_textcolor') {
                    $value = '#' . ltrim($value, '#');
                }
                $this->append_hex_color($colors, $value);
            }
        }

        $this->scan_theme_color_values($mods, $colors, true);
        return array_slice($colors, 0, 6);
    }

    private function theme_option_palette_colors() {
        $colors = [];
        if (!function_exists('wp_get_theme')) { return $colors; }

        $theme = wp_get_theme();
        $keys = array_filter(array_unique([
            strtolower((string) $theme->get_stylesheet()),
            strtolower((string) $theme->get_template()),
            strtolower((string) $theme->get('TextDomain')),
        ]));

        $option_names = [];
        foreach ($keys as $key) {
            $option_names[] = $key;
            $option_names[] = $key . '_options';
            $option_names[] = $key . '_theme_options';
            $option_names[] = 'theme_mods_' . $key;
        }
        $option_names = array_unique($option_names);

        foreach ($option_names as $option_name) {
            $option_value = get_option($option_name);
            if (is_array($option_value)) {
                $this->scan_theme_color_values($option_value, $colors, false);
            }
            if (count($colors) >= 6) { break; }
        }

        return array_slice($colors, 0, 6);
    }

    private function scan_theme_color_values($value, &$colors, $scan_all = false, $path = '') {
        if (!is_array($value)) { return; }
        foreach ($value as $key => $child) {
            $key_string = strtolower((string) $key);
            $child_path = $path === '' ? $key_string : $path . '.' . $key_string;
            $looks_relevant = $scan_all || strpos($child_path, 'color') !== false || strpos($child_path, 'colour') !== false || strpos($child_path, 'palette') !== false || strpos($child_path, 'accent') !== false || strpos($child_path, 'brand') !== false;

            if (is_string($child) && $looks_relevant) {
                $candidate = $child;
                if (preg_match('/^[A-Fa-f0-9]{6}$/', $candidate)) {
                    $candidate = '#' . $candidate;
                }
                $this->append_hex_color($colors, $candidate);
            } elseif (is_array($child)) {
                $this->scan_theme_color_values($child, $colors, $scan_all, $child_path);
            }
            if (count($colors) >= 6) { return; }
        }
    }

    private function salient_palette_colors() {
        $colors = [];
        $is_salient = false;
        if (function_exists('wp_get_theme')) {
            $theme = wp_get_theme();
            $name = strtolower((string) $theme->get('Name'));
            $template = strtolower((string) $theme->get_template());
            $stylesheet = strtolower((string) $theme->get_stylesheet());
            $is_salient = strpos($name, 'salient') !== false || strpos($template, 'salient') !== false || strpos($stylesheet, 'salient') !== false;
        }

        $option_sets = [];
        if (function_exists('get_nectar_theme_options')) {
            $nectar_options = get_nectar_theme_options();
            if (is_array($nectar_options)) { $option_sets[] = $nectar_options; }
        }
        if (isset($GLOBALS['nectar_options']) && is_array($GLOBALS['nectar_options'])) {
            $option_sets[] = $GLOBALS['nectar_options'];
        }

        foreach (['salient', 'salient_redux', 'salient_options', 'salient_theme_options', 'nectar_options', 'redux_options'] as $option_name) {
            $option_value = get_option($option_name);
            if (is_array($option_value)) { $option_sets[] = $option_value; }
        }

        $preferred_keys = [
            'accent-color', 'accent_color', 'accent-color-2', 'accent_color_2',
            'extra-color-1', 'extra_color_1', 'extra-color-2', 'extra_color_2', 'extra-color-3', 'extra_color_3',
            'extra-color-gradient-1', 'extra_color_gradient_1', 'extra-color-gradient-2', 'extra_color_gradient_2',
        ];

        foreach ($option_sets as $set) {
            foreach ($preferred_keys as $key) {
                if (isset($set[$key])) { $this->append_hex_color($colors, $set[$key]); }
            }
        }

        foreach ($option_sets as $set) {
            $this->scan_salient_color_values($set, $colors, $is_salient);
        }

        return $colors;
    }

    private function scan_salient_color_values($value, &$colors, $is_salient, $path = '') {
        if (!is_array($value)) { return; }
        foreach ($value as $key => $child) {
            $key_string = strtolower((string) $key);
            $child_path = $path === '' ? $key_string : $path . '.' . $key_string;
            $looks_relevant = $is_salient || strpos($child_path, 'accent') !== false || strpos($child_path, 'extra-color') !== false || strpos($child_path, 'extra_color') !== false || strpos($child_path, 'nectar') !== false;
            if (is_string($child) && $looks_relevant) {
                $this->append_hex_color($colors, $child);
            } elseif (is_array($child)) {
                $this->scan_salient_color_values($child, $colors, $is_salient, $child_path);
            }
            if (count($colors) >= 6) { return; }
        }
    }

    private function program_categories_for_js() {
        $map = [];
        $programs = get_posts(['post_type'=>'pa_program','post_status'=>['publish','draft'],'numberposts'=>-1]);
        foreach ($programs as $program) {
            $cats = get_post_meta($program->ID, '_pa_categories', true);
            $names = [];
            if (is_array($cats)) {
                foreach ($cats as $cat) {
                    if (!empty($cat['name'])) { $names[] = $cat['name']; }
                }
            }
            $map[$program->ID] = array_values(array_unique($names));
        }
        return $map;
    }

    private function program_dates_for_js() {
        $map = [];
        $programs = get_posts(['post_type'=>'pa_program','post_status'=>['publish','draft'],'numberposts'=>-1]);
        foreach ($programs as $program) { $map[$program->ID] = $this->program_date_options($program->ID); }
        return $map;
    }

    private function program_sponsor_levels_for_js() {
        $map = [];
        $programs = get_posts(['post_type'=>'pa_program','post_status'=>['publish','draft'],'numberposts'=>-1]);
        foreach ($programs as $program) {
            $levels = get_post_meta($program->ID, '_pa_sponsor_levels', true);
            if (!is_array($levels)) { $levels = []; }
            $map[$program->ID] = array_values(array_filter(array_map('sanitize_text_field', $levels)));
        }
        return $map;
    }

    private function program_date_options($program_id) {
        $program_id = absint($program_id);
        $dates = [];
        $add = static function($date) use (&$dates) {
            $date = sanitize_text_field($date);
            if ($date && !isset($dates[$date])) {
                $ts = strtotime($date);
                $dates[$date] = $ts ? date_i18n('F j, Y', $ts) : $date;
            }
        };
        $add_range = static function($start, $end) use (&$add) {
            $start = sanitize_text_field($start);
            $end = sanitize_text_field($end);
            if (!$start && !$end) { return; }
            if (!$start || !$end) { $add($start ?: $end); return; }
            try {
                $start_dt = new DateTime($start);
                $end_dt = new DateTime($end);
                if ($end_dt < $start_dt) { $tmp = $start_dt; $start_dt = $end_dt; $end_dt = $tmp; }
                while ($start_dt <= $end_dt) {
                    $add($start_dt->format('Y-m-d'));
                    $start_dt->modify('+1 day');
                }
            } catch (Exception $e) {
                $add($start);
                $add($end);
            }
        };
        $add_range(get_post_meta($program_id, '_pa_program_start_date', true), get_post_meta($program_id, '_pa_program_end_date', true));
        $additional = get_post_meta($program_id, '_pa_program_additional_dates', true);
        if (is_array($additional)) {
            foreach ($additional as $range) {
                if (is_array($range)) { $add_range($range['start'] ?? '', $range['end'] ?? ''); }
            }
        }
        return $dates;
    }

    public function public_assets() {
        if (!is_singular(['pa_event', 'pa_speaker', 'pa_sponsor']) && !$this->page_has_pa_shortcode()) {
            return;
        }
        wp_enqueue_style('pa-montserrat', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap', [], null);
        wp_enqueue_style('pa-public', plugin_dir_url(__FILE__) . 'assets/css/public.css', [], self::VERSION);
        wp_add_inline_style('pa-public', $this->public_inline_css());
    }

    private function page_has_pa_shortcode() {
        if (!is_singular()) { return false; }
        $post = get_post();
        if (!$post || empty($post->post_content)) { return false; }
        return has_shortcode($post->post_content, 'program_agenda') || has_shortcode($post->post_content, 'program_sponsors') || has_shortcode($post->post_content, 'program_speakers') || has_shortcode($post->post_content, 'program_pdf');
    }

    private function public_inline_css() {
        return '
/* Shared speaker-card rules: these apply anywhere a speaker card appears. */
.pa-speaker-card-list{display:flex!important;flex-wrap:wrap;gap:12px;align-items:flex-start;}
.pa-speaker-card-list-agenda{display:flex!important;flex-wrap:wrap;gap:12px;align-items:flex-start;}
.pa-speaker-card{box-sizing:border-box!important;display:inline-grid!important;grid-template-columns:64px max-content!important;align-items:center!important;column-gap:12px!important;width:max-content!important;max-width:none!important;min-width:max-content!important;overflow:visible!important;}
.pa-speaker-card-image{box-sizing:border-box!important;display:flex!important;align-items:center!important;justify-content:center!important;width:64px!important;height:64px!important;min-width:64px!important;max-width:64px!important;min-height:64px!important;max-height:64px!important;aspect-ratio:1/1!important;overflow:hidden!important;flex:0 0 64px!important;line-height:0!important;}
.pa-speaker-card-thumb{width:100%!important;height:100%!important;object-fit:cover!important;object-position:center center!important;display:block!important;}
.pa-speaker-card-thumb.is-circle,.pa-speaker-card-placeholder.is-circle,.pa-speaker-card-image:has(.is-circle){border-radius:50%!important;}
.pa-speaker-card-thumb.is-square,.pa-speaker-card-placeholder.is-square{border-radius:0!important;}
.pa-speaker-card-placeholder{display:block!important;width:100%!important;height:100%!important;background:rgba(0,0,0,.08)!important;}
.pa-speaker-card-text{box-sizing:border-box!important;display:flex!important;flex-direction:column!important;justify-content:center!important;min-width:max-content!important;max-width:none!important;white-space:nowrap!important;padding-right:28px!important;overflow:visible!important;}
.pa-speaker-card-text h1,.pa-speaker-card-text h2,.pa-speaker-card-text h3,.pa-speaker-card-text h4,.pa-speaker-card-text h5,.pa-speaker-card-text h6,.pa-speaker-card-text p{display:block!important;margin-top:0!important;margin-bottom:2px!important;line-height:1.15!important;max-width:none!important;white-space:nowrap!important;overflow:visible!important;text-overflow:clip!important;}
.pa-speaker-card-text h3,.pa-speaker-card-text h3 a{white-space:nowrap!important;max-width:none!important;overflow:visible!important;text-overflow:clip!important;}
.pa-speaker-card-text a{display:inline!important;white-space:nowrap!important;max-width:none!important;overflow:visible!important;text-overflow:clip!important;}
.pa-speaker-card-text p:last-child,.pa-speaker-card-text h3:last-child{margin-bottom:0!important;}
.pa-speaker-hero-image{overflow:hidden;display:inline-flex;align-items:center;justify-content:center;flex:none;}
.pa-speaker-hero-image img,.pa-speaker-image{width:100%!important;height:100%!important;object-fit:cover!important;object-position:center center!important;display:block!important;}
';
    }

    public function public_body_class($classes) {
        if (is_singular('pa_event')) { $classes[] = 'pa-program-entity-page'; $classes[] = 'pa-program-event-page'; }
        if (is_singular('pa_speaker')) { $classes[] = 'pa-program-entity-page'; $classes[] = 'pa-program-speaker-page'; }
        if (is_singular('pa_sponsor')) { $classes[] = 'pa-program-entity-page'; $classes[] = 'pa-program-sponsor-page'; }
        return $classes;
    }

    public function hide_theme_entity_page_title() {
        if (!is_singular(['pa_event', 'pa_speaker', 'pa_sponsor'])) { return; }
        echo '<style id="pa-hide-theme-entity-page-title">body.pa-program-entity-page #page-header-wrap,body.pa-program-entity-page .page-header-bg,body.pa-program-entity-page .page-header-no-bg,body.pa-program-entity-page .heading-title,body.pa-program-entity-page .row .col.section-title,body.pa-program-entity-page .entry-header,body.pa-program-entity-page header.entry-header,body.pa-program-entity-page .entry-title,body.pa-program-entity-page h1.entry-title,body.pa-program-entity-page .post-title,body.pa-program-entity-page .post-meta,body.pa-program-entity-page .entry-meta,body.pa-program-entity-page .single-post-meta,body.pa-program-entity-page .posted-on,body.pa-program-entity-page .post-date,body.pa-program-entity-page .date,body.pa-program-entity-page time.entry-date,body.pa-program-entity-page .byline{display:none!important;}body.pa-program-entity-page .pa-theme-sponsor-page .entry-title,body.pa-program-entity-page .pa-theme-event-page .entry-title,body.pa-program-entity-page .pa-theme-speaker-page .entry-title{display:revert!important;}body.pa-program-entity-page .container-wrap{padding-top:0!important;}</style>' . "
";
    }

    private function nav($active = 'programs', $editing_title = '') {
        $items = [
            'programs' => ['Programs', admin_url('admin.php?page=program-main')],
            'events' => ['Events', admin_url('admin.php?page=program-events')],
            'speakers' => ['Speakers', admin_url('admin.php?page=program-speakers')],
            'sponsors' => ['Sponsors', admin_url('admin.php?page=program-sponsors')],
            'import' => ['Mass Import', admin_url('admin.php?page=program-mass-import')],
            'advanced' => ['Advanced Settings', admin_url('admin.php?page=program-advanced-settings')],
            'settings' => ['Admin Settings', admin_url('admin.php?page=program-admin-settings')],
        ];
        echo '<div class="pa-wrap"><h1 class="pa-admin-brand"><img class="pa-admin-brand-logo" src="' . esc_url(plugin_dir_url(__FILE__) . 'assets/img/stagecard-logo.svg?v=' . self::VERSION) . '" alt="Stagecard"><small>(v ' . esc_html(self::VERSION) . ')</small></h1><nav class="pa-tabs">';
        foreach ($items as $key => $item) {
            $is_active = $active === $key;
            $label = $item[0];
            if ($is_active && $editing_title !== '') { $label .= ': ' . $editing_title; }
            printf('<a class="%s" href="%s"%s>%s</a>', $is_active ? 'active current' : '', esc_url($item[1]), $is_active ? ' aria-current="page"' : '', esc_html($label));
        }
        echo '</nav>';
        if (!empty($_GET['deleted'])) { echo '<div class="notice notice-success is-dismissible"><p>Deleted successfully.</p></div>'; }
        if (!empty($_GET['bulk_updated'])) { echo '<div class="notice notice-success is-dismissible"><p>Bulk update complete.</p></div>'; }
        if (!empty($_GET['bulk_drafted'])) { echo '<div class="notice notice-success is-dismissible"><p>Selected items moved to Draft.</p></div>'; }
        if (!empty($_GET['bulk_deleted'])) { echo '<div class="notice notice-success is-dismissible"><p>Selected items deleted.</p></div>'; }
        if (!empty($_GET['bulk_error'])) { echo '<div class="notice notice-error is-dismissible"><p>Bulk update could not be completed. Select items, then choose at least one change: move to Draft, delete, assign a program, or assign a sponsor level.</p></div>'; }
        if (!empty($_GET['imported'])) { echo '<div class="notice notice-success is-dismissible"><p>Import complete. Created ' . intval($_GET['imported']) . ' item(s).' . (!empty($_GET['import_warnings']) ? ' Some rows were skipped or need review.' : '') . '</p></div>'; }
        if (!empty($_GET['import_error'])) { echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['import_error']))) . '</p></div>'; }
    }


    private function list_search($label, $placeholder) {
        echo '<div class="pa-list-toolbar"><label class="pa-list-search-label"><span>' . esc_html($label) . '</span><input type="search" class="pa-admin-list-search" data-pa-list-search placeholder="' . esc_attr($placeholder) . '"></label></div>';
    }

    private function normalize_search_terms($parts) {
        $parts = array_filter(array_map(static function($part) { return is_scalar($part) ? wp_strip_all_tags((string) $part) : ''; }, (array) $parts));
        return strtolower(trim(implode(' ', $parts)));
    }

    private function status_tabs($base_url, $post_type) {
        $current = isset($_GET['status']) ? sanitize_key($_GET['status']) : 'publish';
        $map = ['publish' => 'Live', 'draft' => 'Drafts'];
        echo '<div class="pa-status-tabs">';
        foreach ($map as $status => $label) {
            $count = wp_count_posts($post_type)->{$status} ?? 0;
            printf('<a class="%s" href="%s">%s (%d)</a>', $current === $status ? 'active' : '', esc_url(add_query_arg('status', $status, $base_url)), esc_html($label), intval($count));
        }
        echo '</div>';
    }

    private function query_items($post_type) {
        $status = isset($_GET['status']) ? sanitize_key($_GET['status']) : 'publish';
        if (!in_array($status, ['publish','draft'], true)) { $status = 'publish'; }
        return get_posts(['post_type' => $post_type, 'post_status' => $status, 'numberposts' => -1, 'orderby' => 'date', 'order' => 'DESC']);
    }

    private function sortable_th($label, $type = 'text', $class = '') {
        $classes = trim('pa-sortable-th ' . $class);
        return '<th class="' . esc_attr($classes) . '" scope="col"><button type="button" class="pa-sort-button" data-pa-sort-type="' . esc_attr($type) . '"><span>' . esc_html($label) . '</span><span class="pa-sort-indicator" aria-hidden="true">↕</span></button></th>';
    }

    private function sort_td($html, $value = '', $type = 'text', $class = '') {
        $sort_value = $type === 'number' ? (string) floatval($value) : strtolower(wp_strip_all_tags((string) $value));
        return '<td class="' . esc_attr($class) . '" data-pa-sort-value="' . esc_attr($sort_value) . '">' . $html . '</td>';
    }

    private function bulk_actions($post_type) {
        if (!in_array($post_type, ['pa_event','pa_sponsor'], true)) { return; }
        $programs = get_posts(['post_type'=>'pa_program','post_status'=>['publish','draft'],'numberposts'=>-1,'orderby'=>'title','order'=>'ASC']);
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="pa-bulk-form" data-pa-bulk-form="' . esc_attr($post_type) . '">';
        wp_nonce_field('pa_bulk_items_' . $post_type);
        echo '<input type="hidden" name="action" value="pa_bulk_items"><input type="hidden" name="post_type" value="' . esc_attr($post_type) . '">';
        echo '<div class="pa-bulk-toolbar"><label><span>Action</span><select name="bulk_action" class="pa-bulk-action-select"><option value="">No status/delete action</option><option value="draft">Move to Draft</option><option value="delete">Delete</option></select></label>';
        echo '<label class="pa-bulk-program-field"><span>Assign program</span><select name="bulk_program_id" class="pa-bulk-program-select"><option value="">Assign program</option>';
        foreach ($programs as $program) {
            $levels = get_post_meta($program->ID, '_pa_sponsor_levels', true);
            if (!is_array($levels)) { $levels = []; }
            $levels = array_values(array_filter(array_unique(array_map('sanitize_text_field', $levels))));
            echo '<option value="' . esc_attr($program->ID) . '" data-sponsor-levels="' . esc_attr(wp_json_encode($levels)) . '">' . esc_html($program->post_title) . '</option>';
        }
        echo '</select></label>';
        if ($post_type === 'pa_sponsor') {
            echo '<label class="pa-bulk-level-field" hidden><span>Assign sponsor level</span><select name="bulk_sponsor_level" class="pa-bulk-level-select"><option value="">Assign sponsor level</option></select></label>';
        }
        echo '<button type="submit" class="button button-primary pa-bulk-apply">Apply to selected</button><button type="button" class="button pa-bulk-select-visible">Select all visible</button><button type="button" class="button-link pa-bulk-clear">Clear selection</button><span class="pa-bulk-count">0 selected</span></div>';
    }

    private function close_bulk_actions($post_type) {
        if (in_array($post_type, ['pa_event','pa_sponsor'], true)) { echo '</form>'; }
    }

    private function sponsor_program_ids($sponsor_id) {
        $ids = get_post_meta($sponsor_id, '_pa_sponsor_program_ids', true);
        if (!is_array($ids)) { $ids = []; }
        $ids = array_values(array_filter(array_unique(array_map('absint', $ids))));
        $legacy = absint(get_post_meta($sponsor_id, '_pa_sponsor_program_id', true));
        if ($legacy && !in_array($legacy, $ids, true)) { array_unshift($ids, $legacy); }
        return $ids;
    }

    private function sponsor_program_titles($sponsor_id) {
        $titles = [];
        foreach ($this->sponsor_program_ids($sponsor_id) as $program_id) {
            $title = get_the_title($program_id);
            if ($title) { $titles[] = $title; }
        }
        return $titles;
    }


    private function speaker_event_ids($speaker_id) {
        $speaker_id = absint($speaker_id);
        if (!$speaker_id) { return []; }
        $events = get_posts([
            'post_type' => 'pa_event',
            'post_status' => ['publish','draft'],
            'numberposts' => -1,
            'meta_key' => '_pa_event_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
        ]);
        $ids = [];
        foreach ($events as $event) {
            $speaker_ids = get_post_meta($event->ID, '_pa_speaker_ids', true);
            if (!is_array($speaker_ids)) { $speaker_ids = []; }
            if (in_array($speaker_id, array_map('absint', $speaker_ids), true)) { $ids[] = absint($event->ID); }
        }
        return $ids;
    }

    private function speaker_event_picker_label($event_id) {
        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'pa_event') { return ''; }
        $parts = [$event->post_title];
        $when = $this->format_event_when($event_id);
        if ($when) { $parts[] = $when; }
        $program_id = absint(get_post_meta($event_id, '_pa_program_id', true));
        $program_title = $program_id ? get_the_title($program_id) : '';
        if ($program_title) { $parts[] = $program_title; }
        return implode(' — ', array_filter($parts));
    }

    private function sync_speaker_events($speaker_id, $selected_event_ids) {
        $speaker_id = absint($speaker_id);
        if (!$speaker_id) { return; }
        $selected_event_ids = array_values(array_filter(array_unique(array_map('absint', (array)$selected_event_ids))));
        $selected_event_ids = array_values(array_filter($selected_event_ids, static function($event_id) { return get_post_type($event_id) === 'pa_event'; }));
        $selected_lookup = array_fill_keys($selected_event_ids, true);

        $events = get_posts([
            'post_type' => 'pa_event',
            'post_status' => ['publish','draft'],
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
        foreach ($events as $event) {
            $event_id = absint($event->ID);
            $speaker_ids = get_post_meta($event_id, '_pa_speaker_ids', true);
            if (!is_array($speaker_ids)) { $speaker_ids = []; }
            $speaker_ids = array_values(array_filter(array_unique(array_map('absint', $speaker_ids))));
            $currently_attached = in_array($speaker_id, $speaker_ids, true);
            $should_attach = isset($selected_lookup[$event_id]);

            if ($should_attach && !$currently_attached) {
                $speaker_ids[] = $speaker_id;
                update_post_meta($event_id, '_pa_speaker_ids', $speaker_ids);
            } elseif (!$should_attach && $currently_attached) {
                $speaker_ids = array_values(array_filter($speaker_ids, static function($id) use ($speaker_id) { return absint($id) !== $speaker_id; }));
                update_post_meta($event_id, '_pa_speaker_ids', $speaker_ids);
            }
        }
    }

    /**
     * Sponsors can belong to multiple programs, so levels are resolved per program first.
     * The legacy general level meta remains as a fallback for older records/admin tables.
     */
    private function sponsor_levels_for_program($sponsor_id, $program_id = 0) {
        $program_id = absint($program_id);
        $program_levels = get_post_meta($sponsor_id, '_pa_sponsor_program_levels', true);
        if ($program_id && is_array($program_levels) && $program_levels) {
            $keys = [$program_id, (string) $program_id];
            foreach ($keys as $key) {
                if (isset($program_levels[$key]) && is_array($program_levels[$key])) {
                    return array_values(array_filter(array_unique(array_map('sanitize_text_field', $program_levels[$key]))));
                }
            }
            return [];
        }
        $levels = get_post_meta($sponsor_id, '_pa_sponsor_levels', true);
        if (!is_array($levels)) { $levels = []; }
        return array_values(array_filter(array_unique(array_map('sanitize_text_field', $levels))));
    }

    private function sponsor_all_levels($sponsor_id) {
        $all = get_post_meta($sponsor_id, '_pa_sponsor_levels', true);
        if (!is_array($all)) { $all = []; }
        $program_levels = get_post_meta($sponsor_id, '_pa_sponsor_program_levels', true);
        if (is_array($program_levels)) {
            foreach ($program_levels as $levels) {
                if (is_array($levels)) {
                    foreach ($levels as $level) { $all[] = $level; }
                }
            }
        }
        return array_values(array_filter(array_unique(array_map('sanitize_text_field', $all))));
    }

    public function page_programs() {
        $this->nav('programs');
        $this->list_search('Search programs', 'Search programs by title, date, shortcode, or event');
        echo '<a class="pa-add-new" href="' . esc_url(admin_url('admin.php?page=program-edit-program')) . '">Add new</a>';
        $this->status_tabs(admin_url('admin.php?page=program-main'), 'pa_program');
        $items = $this->query_items('pa_program');
        echo '<table class="pa-table pa-sortable-table"><thead><tr>' . $this->sortable_th('Title') . $this->sortable_th('Dates') . $this->sortable_th('Shortcode') . $this->sortable_th('Go to Live') . $this->sortable_th('Author') . $this->sortable_th('Date Created', 'date') . '<th></th></tr></thead><tbody>';
        foreach ($items as $p) {
            $dates = get_post_meta($p->ID, '_pa_program_dates', true);
            $author = get_the_author_meta('display_name', $p->post_author);
            $shortcode_id = $this->program_shortcode_id($p->ID);
            $shortcode = $shortcode_id ? '[program_agenda id="' . $shortcode_id . '"]' : '';
            $back_to_link = get_post_meta($p->ID, '_pa_back_to_link', true);
            $live_link = $back_to_link ? '<a href="' . esc_url($back_to_link) . '" target="_blank" rel="noopener">Go to Live</a>' : '&mdash;';
            $program_events = get_posts(['post_type'=>'pa_event','post_status'=>['publish','draft'],'numberposts'=>-1,'meta_key'=>'_pa_event_date','orderby'=>'meta_value','order'=>'ASC','meta_query'=>[['key'=>'_pa_program_id','value'=>$p->ID,'compare'=>'=']]]);
            $event_toggle = '';
            $event_detail_row = '';
            $event_titles_for_search = [];
            if ($program_events) {
                $event_count = count($program_events);
                $detail_id = 'pa-program-events-' . absint($p->ID);
                $event_toggle = '<button type="button" class="button-link pa-program-events-toggle" data-target="' . esc_attr($detail_id) . '" aria-expanded="false">View events (' . intval($event_count) . ')</button>';
                $event_list = '<div class="pa-program-event-sublist">';
                foreach ($program_events as $event) {
                    $event_titles_for_search[] = $event->post_title;
                    $when = $this->format_event_when($event->ID);
                    $event_list .= '<div class="pa-program-event-row"><a href="' . esc_url(admin_url('admin.php?page=program-edit-event&id=' . $event->ID)) . '">' . esc_html($event->post_title) . '</a>' . ($when ? '<span>' . esc_html($when) . '</span>' : '') . '</div>';
                }
                $event_list .= '</div>';
                $event_detail_row = '<tr id="' . esc_attr($detail_id) . '" class="pa-program-events-detail-row pa-is-hidden" data-pa-detail-for="program-' . absint($p->ID) . '" aria-hidden="true" style="display:none;"><td colspan="7">' . $event_list . '</td></tr>';
            }
            $search_terms = $this->normalize_search_terms(array_merge([$p->post_title, $dates, $shortcode, $author, get_the_date('', $p)], $event_titles_for_search));
            echo '<tr class="pa-program-main-row pa-searchable-row" data-pa-row-key="program-' . absint($p->ID) . '" data-pa-search="' . esc_attr($search_terms) . '"><td><a class="pa-program-title-link" href="' . esc_url(admin_url('admin.php?page=program-edit-program&id=' . $p->ID)) . '">' . esc_html($p->post_title) . '</a>' . ($event_toggle ? '<div class="pa-program-events-toggle-wrap">' . $event_toggle . '</div>' : '') . '</td><td>' . esc_html($dates) . '</td><td>' . ($shortcode ? '<code class="pa-list-shortcode">' . esc_html($shortcode) . '</code>' : '&mdash;') . '</td><td>' . $live_link . '</td><td>' . esc_html($author) . '</td><td>' . esc_html(get_the_date('', $p)) . '</td><td>' . $this->row_actions($p) . '</td></tr>' . $event_detail_row;
        }
        if ($items) { echo '<tr class="pa-list-search-empty" hidden><td colspan="7">No matching programs found.</td></tr>'; }
        if (!$items) { echo '<tr><td colspan="7">No programs found.</td></tr>'; }
        echo '</tbody></table>';
        echo '</div>';
    }

    public function page_events() {
        $this->nav('events');
        $this->list_search('Search events', 'Search events by title, program, category, location, or speaker');
        echo '<a class="pa-add-new" href="' . esc_url(admin_url('admin.php?page=program-edit-event')) . '">Add new</a>';
        $this->status_tabs(admin_url('admin.php?page=program-events'), 'pa_event');
        $this->bulk_actions('pa_event');
        $items = $this->query_items('pa_event');
        echo '<table class="pa-table pa-sortable-table"><thead><tr><th class="pa-select-col"><label class="screen-reader-text" for="pa-select-all-events">Select all events</label><input type="checkbox" id="pa-select-all-events" class="pa-bulk-check-all"></th>' . $this->sortable_th('Title') . $this->sortable_th('Dates', 'text') . $this->sortable_th('Category') . $this->sortable_th('Page') . $this->sortable_th('Author') . $this->sortable_th('Date Created', 'date') . '<th></th></tr></thead><tbody>';
        foreach ($items as $p) {
            $date = $this->format_event_when($p->ID);
            $raw_date = get_post_meta($p->ID, '_pa_event_date', true);
            $cat = get_post_meta($p->ID, '_pa_event_category', true);
            $author = get_the_author_meta('display_name', $p->post_author);
            $program_title = get_the_title(absint(get_post_meta($p->ID, '_pa_program_id', true)));
            $location = get_post_meta($p->ID, '_pa_event_location', true);
            $speaker_names = [];
            $speaker_ids = get_post_meta($p->ID, '_pa_speaker_ids', true);
            if (!is_array($speaker_ids)) { $speaker_ids = []; }
            foreach ((array) $speaker_ids as $speaker_id) { $speaker_names[] = get_the_title(absint($speaker_id)); }
            $sponsor_names = [];
            $sponsor_ids = get_post_meta($p->ID, '_pa_sponsor_ids', true);
            if (!is_array($sponsor_ids)) { $sponsor_ids = []; }
            foreach ((array) $sponsor_ids as $sponsor_id) { $sponsor_names[] = get_the_title(absint($sponsor_id)); }
            $search_terms = $this->normalize_search_terms(array_merge([$p->post_title, $date, $cat, $author, get_the_date('', $p), $program_title, $location], $speaker_names, $sponsor_names));
            echo '<tr class="pa-searchable-row" data-pa-search="' . esc_attr($search_terms) . '"><td class="pa-select-col"><input type="checkbox" class="pa-bulk-item-check" name="item_ids[]" value="' . esc_attr($p->ID) . '"></td>' .
                $this->sort_td('<a href="' . esc_url(admin_url('admin.php?page=program-edit-event&id=' . $p->ID)) . '">' . esc_html($p->post_title) . '</a>', $p->post_title) .
                $this->sort_td(esc_html($date), $raw_date ?: $date, 'date') .
                $this->sort_td(esc_html($cat), $cat) .
                $this->sort_td('<a href="' . esc_url(get_permalink($p)) . '" target="_blank" rel="noopener">View page</a>', 'view page') .
                $this->sort_td(esc_html($author), $author) .
                $this->sort_td(esc_html(get_the_date('', $p)), get_the_date('Y-m-d H:i:s', $p), 'date') .
                '<td>' . $this->row_actions($p) . '</td></tr>';
        }
        if ($items) { echo '<tr class="pa-list-search-empty" hidden><td colspan="8">No matching events found.</td></tr>'; }
        if (!$items) { echo '<tr><td colspan="8">No events found.</td></tr>'; }
        echo '</tbody></table>';
        $this->close_bulk_actions('pa_event');
        echo '</div>';
    }

    public function page_speakers() {
        $this->nav('speakers');
        $this->list_search('Search speakers', 'Search speakers by name, company, role, or credentials');
        echo '<a class="pa-add-new" href="' . esc_url(admin_url('admin.php?page=program-edit-speaker')) . '">Add new</a>';
        $this->status_tabs(admin_url('admin.php?page=program-speakers'), 'pa_speaker');
        $items = $this->query_items('pa_speaker');
        echo '<table class="pa-table pa-sortable-table"><thead><tr>' . $this->sortable_th('Name') . $this->sortable_th('Company') . $this->sortable_th('Program style') . $this->sortable_th('Page') . $this->sortable_th('Author') . $this->sortable_th('Date Created', 'date') . '<th></th></tr></thead><tbody>';
        foreach ($items as $p) {
            $company = get_post_meta($p->ID, '_pa_speaker_company', true);
            $author = get_the_author_meta('display_name', $p->post_author);
            $role = get_post_meta($p->ID, '_pa_speaker_role_title', true);
            $credentials = get_post_meta($p->ID, '_pa_speaker_credentials', true);
            $style_program_id = $this->speaker_primary_program_id($p->ID);
            $style_program_title = $style_program_id ? get_the_title($style_program_id) : '';
            $search_terms = $this->normalize_search_terms([$p->post_title, $company, $role, $credentials, $author, get_the_date('', $p), $style_program_title]);
            echo '<tr class="pa-searchable-row" data-pa-search="' . esc_attr($search_terms) . '"><td><a href="' . esc_url(admin_url('admin.php?page=program-edit-speaker&id=' . $p->ID)) . '">' . esc_html($p->post_title) . '</a></td><td>' . esc_html($company) . '</td><td>' . ($style_program_title ? esc_html($style_program_title) : '&mdash;') . '</td><td><a href="' . esc_url(get_permalink($p)) . '" target="_blank" rel="noopener">View page</a></td><td>' . esc_html($author) . '</td><td>' . esc_html(get_the_date('', $p)) . '</td><td>' . $this->row_actions($p) . '</td></tr>';
        }
        if ($items) { echo '<tr class="pa-list-search-empty" hidden><td colspan="7">No matching speakers found.</td></tr>'; }
        if (!$items) { echo '<tr><td colspan="7">No speakers found.</td></tr>'; }
        echo '</tbody></table>';
        echo '</div>';
    }

    public function page_sponsors() {
        $this->nav('sponsors');
        $this->list_search('Search sponsors', 'Search sponsors by company, program, level, or bio');
        echo '<a class="pa-add-new" href="' . esc_url(admin_url('admin.php?page=program-edit-sponsor')) . '">Add new</a>';
        $this->status_tabs(admin_url('admin.php?page=program-sponsors'), 'pa_sponsor');
        $this->bulk_actions('pa_sponsor');
        $items = $this->query_items('pa_sponsor');
        echo '<table class="pa-table pa-sortable-table"><thead><tr><th class="pa-select-col"><label class="screen-reader-text" for="pa-select-all-sponsors">Select all sponsors</label><input type="checkbox" id="pa-select-all-sponsors" class="pa-bulk-check-all"></th>' . $this->sortable_th('Company') . $this->sortable_th('Program') . $this->sortable_th('Levels') . $this->sortable_th('Page') . $this->sortable_th('Author') . $this->sortable_th('Date Created', 'date') . '<th></th></tr></thead><tbody>';
        foreach ($items as $p) {
            $program_titles = $this->sponsor_program_titles($p->ID);
            $program_title_display = $program_titles ? implode(', ', $program_titles) : '';
            $levels = $this->sponsor_all_levels($p->ID);
            $bio = wp_strip_all_tags($p->post_content);
            $website = get_post_meta($p->ID, '_pa_sponsor_website', true);
            $author = get_the_author_meta('display_name', $p->post_author);
            $search_terms = $this->normalize_search_terms([$p->post_title, $program_title_display, implode(' ', $levels), $bio, $website, $author, get_the_date('', $p)]);
            echo '<tr class="pa-searchable-row" data-pa-search="' . esc_attr($search_terms) . '"><td class="pa-select-col"><input type="checkbox" class="pa-bulk-item-check" name="item_ids[]" value="' . esc_attr($p->ID) . '"></td>' .
                $this->sort_td('<a href="' . esc_url(admin_url('admin.php?page=program-edit-sponsor&id=' . $p->ID)) . '">' . esc_html($p->post_title) . '</a>', $p->post_title) .
                $this->sort_td($program_title_display ? esc_html($program_title_display) : '&mdash;', $program_title_display) .
                $this->sort_td($levels ? esc_html(implode(', ', $levels)) : '&mdash;', implode(', ', $levels)) .
                $this->sort_td('<a href="' . esc_url(get_permalink($p)) . '" target="_blank" rel="noopener">View page</a>', 'view page') .
                $this->sort_td(esc_html($author), $author) .
                $this->sort_td(esc_html(get_the_date('', $p)), get_the_date('Y-m-d H:i:s', $p), 'date') .
                '<td>' . $this->row_actions($p) . '</td></tr>';
        }
        if ($items) { echo '<tr class="pa-list-search-empty" hidden><td colspan="8">No matching sponsors found.</td></tr>'; }
        if (!$items) { echo '<tr><td colspan="8">No sponsors found.</td></tr>'; }
        echo '</tbody></table>';
        $this->close_bulk_actions('pa_sponsor');
        echo '</div>';
    }

    public function form_sponsor() {
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $post = $id ? get_post($id) : null;
        $program_ids = $id ? $this->sponsor_program_ids($id) : [];
        $program_id = $program_ids ? absint($program_ids[0]) : 0;
        $program_levels = $id ? get_post_meta($id, '_pa_sponsor_program_levels', true) : [];
        if (!is_array($program_levels)) { $program_levels = []; }
        $levels = $id ? get_post_meta($id, '_pa_sponsor_levels', true) : [];
        if (!is_array($levels)) { $levels = []; }
        $programs = get_posts(['post_type'=>'pa_program','post_status'=>['publish','draft'],'numberposts'=>-1,'orderby'=>'title','order'=>'ASC']);
        $this->nav('sponsors', $post ? $post->post_title : '');
        if (!empty($_GET['saved'])) { echo '<div class="notice notice-success is-dismissible pa-save-notice"><p>Saved successfully!</p></div>'; }
        echo '<h2>' . ($id ? 'Edit Sponsor' : 'Add New Sponsor') . '</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="pa-form pa-comfortable-form pa-sponsor-form">';
        wp_nonce_field('pa_save_sponsor');
        echo '<input type="hidden" name="action" value="pa_save_sponsor"><input type="hidden" name="id" value="' . esc_attr($id) . '"><input type="hidden" name="pa_post_status" value="publish" class="pa-post-status">';
        echo '<label class="pa-field">Company Name <span>*</span><input required type="text" name="sponsor_company" value="' . esc_attr($post ? $post->post_title : '') . '"></label>';
        $logo_id = $id ? absint(get_post_meta($id, '_pa_sponsor_logo_id', true)) : 0;
        $website = $id ? get_post_meta($id, '_pa_sponsor_website', true) : '';
        echo '<div class="pa-sponsor-logo-website-row"><div class="pa-sponsor-logo-cell">';
        $this->image_field('sponsor_logo_id', $logo_id, 'Logo', 'Logo displays no taller than 150px and no wider than 250px on event pages.');
        echo '</div><label class="pa-field pa-sponsor-website-cell">Sponsor website <input type="url" name="sponsor_website" value="' . esc_attr($website) . '" placeholder="https://example.com"></label></div>';
        echo '<section class="pa-field pa-sponsor-program-picker-field"><h3 class="pa-field-heading">Programs</h3><p class="description">Searchable and multi-selectable. Select every Program this sponsor belongs to, then choose that sponsor&rsquo;s level for each Program.</p><div class="pa-sponsor-program-toolbar"><input type="search" class="pa-sponsor-program-search" placeholder="Search programs"><button type="button" class="button pa-select-all-sponsor-programs">Select all visible</button></div><div class="pa-sponsor-program-picker">';
        foreach ($programs as $program) {
            $program_search_terms = strtolower(trim($program->post_title . ' ' . wp_strip_all_tags($program->post_content)));
            echo '<label data-name="' . esc_attr($program_search_terms) . '"><input type="checkbox" class="pa-sponsor-program-check" name="sponsor_program_ids[]" value="' . esc_attr($program->ID) . '" ' . checked(in_array(absint($program->ID), array_map('intval', $program_ids), true), true, false) . '> ' . esc_html($program->post_title) . '</label>';
        }
        echo '</div><ul class="pa-selected-sponsor-programs" data-empty="No programs selected.">';
        foreach ($program_ids as $selected_program_id) {
            $selected_program = get_post($selected_program_id);
            if (!$selected_program || $selected_program->post_type !== 'pa_program') { continue; }
            $available_levels = get_post_meta($selected_program_id, '_pa_sponsor_levels', true);
            if (!is_array($available_levels)) { $available_levels = []; }
            $selected_levels = [];
            foreach ([$selected_program_id, (string) $selected_program_id] as $level_key) {
                if (isset($program_levels[$level_key]) && is_array($program_levels[$level_key])) { $selected_levels = $program_levels[$level_key]; break; }
            }
            if (!$selected_levels && $selected_program_id === $program_id) { $selected_levels = $levels; }
            echo '<li data-id="' . esc_attr($selected_program_id) . '"><div class="pa-sponsor-program-row-head"><strong>' . esc_html($selected_program->post_title) . '</strong><button type="button" class="button-link pa-remove-sponsor-program">Remove</button></div><div class="pa-sponsor-program-levels" data-program-id="' . esc_attr($selected_program_id) . '">';
            if ($available_levels) {
                echo '<span class="pa-sponsor-program-level-heading">Assign sponsor level</span>';
                foreach ($available_levels as $level) {
                    $level = sanitize_text_field($level);
                    if ($level === '') { continue; }
                    echo '<label><input type="checkbox" name="sponsor_program_levels[' . esc_attr($selected_program_id) . '][]" value="' . esc_attr($level) . '" ' . checked(in_array($level, $selected_levels, true), true, false) . '> ' . esc_html($level) . '</label>';
                }
            } else {
                echo '<p class="description">No sponsor levels exist for this Program yet.</p>';
            }
            echo '</div></li>';
        }
        echo '</ul></section>';
        echo '<div class="pa-editor-field"><label>Bio</label><p class="description">Recommended maximum: 150 words.</p>'; wp_editor($post ? $post->post_content : '', 'sponsor_bio', ['textarea_name'=>'sponsor_bio','media_buttons'=>false,'textarea_rows'=>6]); echo '</div>';
        echo $this->form_actions('Save Sponsor') . '</form></div>';
    }



    private function requested_post_status() {
        return (isset($_POST['pa_post_status']) && sanitize_key(wp_unslash($_POST['pa_post_status'])) === 'draft') ? 'draft' : 'publish';
    }

    private function form_actions($primary_label, $extra_class = '') {
        $classes = trim('pa-form-actions ' . $extra_class);
        return '<p class="' . esc_attr($classes) . '"><button class="button button-primary">' . esc_html($primary_label) . '</button> <a href="#" class="pa-save-draft-link">Save as draft</a></p>';
    }

    private function row_actions($post) {
        $confirm = $post->post_type === 'pa_program'
            ? 'Are you sure you want to permanently delete this Program? This may also affect related events, speakers, and sponsors. This cannot be undone.'
            : 'Are you sure you want to permanently delete this item? This cannot be undone.';
        $confirm_attr = esc_attr($confirm);
        $delete = wp_nonce_url(admin_url('admin-post.php?action=pa_delete_item&id=' . $post->ID), 'pa_delete_item_' . $post->ID);
        $actions = [];
        if (in_array($post->post_type, ['pa_event','pa_speaker','pa_sponsor'], true)) {
            $duplicate = wp_nonce_url(admin_url('admin-post.php?action=pa_duplicate_item&id=' . $post->ID), 'pa_duplicate_item_' . $post->ID);
            $actions[] = '<a href="' . esc_url($duplicate) . '">Duplicate</a>';
        }
        $actions[] = '<a class="pa-danger" href="' . esc_url($delete) . '" data-pa-delete-confirm="' . $confirm_attr . '">Delete</a>';
        return implode(' | ', $actions);
    }



    public function page_mass_import() {
        $this->nav('import');
        echo '<section class="pa-card pa-import-card"><h2>Mass Import</h2>';
        echo '<p>Upload Events, Speakers or Sponsors from a CSV/XLSX spreadsheet. Images can be added to the spreadsheet with public image URLS (https://example.com/speaker-jane-doe.jpg), or by uploading a ZIP file that contains the spreadsheet and an images folder. Use the file name from the images folder in the excel sheet cell (ex: speaker-jane-doe.jpg)</p>';
        echo '<p><strong>Example structure:</strong></p>';
        echo '<pre class="pa-import-zip-example">example-program-speakers.zip
> images folder &gt; speaker-jane-doe.jpg
> example-program-speakers.xlsx</pre>';
        echo '<div class="pa-import-template-links"><strong>Download templates:</strong> ';
        foreach (['events'=>'Events','speakers'=>'Speakers','sponsors'=>'Sponsors'] as $type => $label) {
            $url = wp_nonce_url(admin_url('admin-post.php?action=pa_download_import_template&type=' . $type), 'pa_download_import_template_' . $type);
            echo '<a class="pa-import-template-button" href="' . esc_url($url) . '">' . esc_html($label) . ' CSV</a> ';
        }
        echo '</div>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data" class="pa-form pa-import-form">';
        wp_nonce_field('pa_mass_import');
        echo '<input type="hidden" name="action" value="pa_mass_import">';
        echo '<div class="pa-import-settings-row">';
        echo '<label><span>Import type</span><select name="import_type" required><option value="events">Events</option><option value="speakers">Speakers</option><option value="sponsors">Sponsors</option></select></label>';
        echo '<label><span>Imported item status</span><select name="import_status"><option value="publish">Live</option><option value="draft">Draft</option></select></label>';
        echo '</div>';
        echo '<label><span>Upload file</span><input type="file" name="import_file" accept=".csv,.xlsx,.zip" required></label>';
        echo '<p class="description">Spreadsheet-only uploads should use image URLs. ZIP uploads may use image filenames that match files inside the ZIP <code>images</code> folder. Do not embed images directly inside spreadsheet cells.</p>';
        echo '<p><button type="submit" class="button button-primary">Import file</button></p>';
        echo '</form>';
    }

    public function page_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to manage these settings.', 'program-agenda'));
        }
        $this->nav('settings');
        if (isset($_GET['saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Saved successfully!</p></div>';
        }
        $enabled = get_option(self::OPT_DELETE_DATA_ON_UNINSTALL, '0') === '1';
        echo '<section class="pa-form-card pa-settings-card">';
        echo '<h2>Admin Settings</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('pa_save_settings');
        echo '<input type="hidden" name="action" value="pa_save_settings">';
        echo '<section class="pa-field pa-cleanup-setting">';
        echo '<h3>Uninstall cleanup</h3>';
        echo '<p class="description">By default, deleting the plugin keeps all Programs, Events, Speakers, and Sponsors so a temporary uninstall does not erase client content.</p>';
        echo '<label class="pa-checkbox-field"><input type="checkbox" name="delete_data_on_uninstall" value="1" ' . checked($enabled, true, false) . '> Delete Programs, Events, Speakers, and Sponsors created by this plugin when this plugin is deleted</label>';
        echo '<p class="description pa-danger-note">Only enable this for testing or when you intentionally want this plugin’s Programs, Events, Speakers, and Sponsors permanently removed during uninstall. Normal WordPress Pages, Posts, Media Library uploads, menus, users, theme settings, and WPBakery/Salient content are not deleted.</p>';
        echo '</section>';
        echo '<p><button class="button button-primary">Save Settings</button></p>';
        echo '</form></section></div>';
    }

    public function save_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to manage these settings.', 'program-agenda'));
        }
        check_admin_referer('pa_save_settings');
        update_option(self::OPT_DELETE_DATA_ON_UNINSTALL, isset($_POST['delete_data_on_uninstall']) ? '1' : '0');
        wp_safe_redirect(admin_url('admin.php?page=program-admin-settings&saved=1'));
        exit;
    }

    public function page_program_advanced_settings() {
        $programs = get_posts(['post_type'=>'pa_program','post_status'=>['publish','draft'],'numberposts'=>-1,'orderby'=>'title','order'=>'ASC']);
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $post = $id ? get_post($id) : null;
        if (!$post || $post->post_type !== 'pa_program') { $id = 0; $post = null; }
        $title = $post ? $post->post_title : '';

        $this->nav('advanced', $title);
        if (!empty($_GET['saved'])) { echo '<div class="notice notice-success is-dismissible pa-save-notice"><p>Saved successfully!</p></div>'; }
        echo '<h2>Advanced Settings</h2>';
        echo '<section class="pa-form-card pa-advanced-program-picker"><h3>Edit Program</h3><p class="description">Choose a program to edit its agenda, event page, and speaker page design settings directly.</p>';
        if (!$programs) {
            echo '<p>No programs found yet. Create a program first, then return here to edit its advanced settings.</p></section></div>';
            return;
        }
        echo '<label class="pa-field">Edit Program<select class="pa-advanced-program-jump" data-base-url="' . esc_url(admin_url('admin.php?page=program-advanced-settings&id=')) . '" onchange="if(this.value){window.location=this.getAttribute(&quot;data-base-url&quot;)+this.value;}"><option value="">Select a program</option>';
        foreach ($programs as $program) {
            echo '<option value="' . esc_attr($program->ID) . '" ' . selected($id, $program->ID, false) . '>' . esc_html($program->post_title) . '</option>';
        }
        echo '</select></label></section>';

        if (!$id) {
            echo '<p class="description">Select a program above to edit its advanced settings.</p></div>';
            return;
        }

        $agenda = get_post_meta($id, '_pa_agenda_settings', true);
        if (!is_array($agenda)) { $agenda = []; }
        $show_desc = get_post_meta($id, '_pa_show_event_descriptions', true);
        if (!$agenda && $show_desc) { $agenda['show_descriptions'] = $show_desc; }
        $speaker_card = get_post_meta($id, '_pa_speaker_card_settings', true);
        if (!is_array($speaker_card)) { $speaker_card = []; }
        $event_page_settings = get_post_meta($id, self::META_EVENT_SETTINGS, true);
        if (!is_array($event_page_settings)) { $event_page_settings = []; }
        $speaker_page_settings = get_post_meta($id, self::META_SPEAKER_SETTINGS, true);
        if (!is_array($speaker_page_settings)) { $speaker_page_settings = []; }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="pa-form pa-comfortable-form pa-program-form pa-program-advanced-only-form">';
        wp_nonce_field('pa_save_program_advanced');
        echo '<input type="hidden" name="action" value="pa_save_program_advanced"><input type="hidden" name="id" value="' . esc_attr($id) . '">';
        echo '<section class="pa-program-advanced-settings pa-program-advanced-settings-standalone"><div class="pa-program-advanced-inner">';
        $this->program_advanced_settings_module($agenda, $speaker_card, $event_page_settings, $speaker_page_settings);
        echo '</div></section>';
        echo '<p><button type="submit" class="button button-primary">Save Advanced Settings</button></p>';
        echo '</form></div>';
    }

    private function program_advanced_settings_module($agenda, $speaker_card, $event_page_settings, $speaker_page_settings) {
        if (!is_array($agenda)) { $agenda = []; }
        if (!is_array($speaker_card)) { $speaker_card = []; }
        if (!is_array($event_page_settings)) { $event_page_settings = []; }
        if (!is_array($speaker_page_settings)) { $speaker_page_settings = []; }
        echo '<div class="pa-program-preview-stage" data-pa-active-preview="agenda">';
        echo '<div class="pa-program-preview-panel is-active" data-pa-preview-panel="agenda">';
        $this->combined_program_preview($agenda, $speaker_card);
        echo '</div><div class="pa-program-preview-panel" data-pa-preview-panel="event" hidden aria-hidden="true">';
        $this->program_page_preview('event', $event_page_settings);
        echo '</div><div class="pa-program-preview-panel" data-pa-preview-panel="speaker" hidden aria-hidden="true">';
        $this->program_page_preview('speaker', $speaker_page_settings);
        echo '</div></div>';
        echo '<div class="pa-program-advanced-accordion pa-program-advanced-tabset" data-pa-active-section="agenda">';
        echo '<nav class="pa-program-advanced-tab-buttons" aria-label="Program advanced settings"><button type="button" class="pa-program-advanced-tab-button is-active" data-pa-preview-target="agenda" aria-selected="true"><span>Agenda Settings</span><small>Public agenda and speaker cards.</small></button><button type="button" class="pa-program-advanced-tab-button" data-pa-preview-target="event" aria-selected="false"><span>Event Page Settings</span><small>Individual Event page styling for this program.</small></button><button type="button" class="pa-program-advanced-tab-button" data-pa-preview-target="speaker" aria-selected="false"><span>Speaker Page Settings</span><small>Individual Speaker page styling for this program.</small></button></nav>';
        echo '<div class="pa-program-advanced-tab-panels">';
        echo '<section class="pa-program-advanced-subsection pa-program-advanced-accordion-item is-active" data-pa-preview-target="agenda"><div class="pa-program-advanced-subsection-inner">';
        echo '<div class="pa-program-advanced-panels">';
        $this->agenda_controls($agenda);
        $this->speaker_card_controls($speaker_card);
        echo '</div></div></section>';
        echo '<section class="pa-program-advanced-subsection pa-program-advanced-accordion-item" data-pa-preview-target="event"><div class="pa-program-advanced-subsection-inner" hidden>';
        $this->program_page_settings_controls('event', $event_page_settings, 'event_page_settings');
        echo '</div></section>';
        echo '<section class="pa-program-advanced-subsection pa-program-advanced-accordion-item" data-pa-preview-target="speaker"><div class="pa-program-advanced-subsection-inner" hidden>';
        $this->program_page_settings_controls('speaker', $speaker_page_settings, 'speaker_page_settings');
        echo '</div></section>';
        echo '</div></div><p class="pa-reset-advanced-wrap"><a href="#" class="pa-reset-all-advanced">Reset all advanced settings to default</a></p>';
    }

    public function form_program() {
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $post = $id ? get_post($id) : null;
        $title = $post ? $post->post_title : '';
        $dates = $id ? get_post_meta($id, '_pa_program_dates', true) : '';
        $start_date = $id ? get_post_meta($id, '_pa_program_start_date', true) : '';
        $end_date = $id ? get_post_meta($id, '_pa_program_end_date', true) : '';
        $back_to_link = $id ? get_post_meta($id, '_pa_back_to_link', true) : '';
        $additional_dates = $id ? get_post_meta($id, '_pa_program_additional_dates', true) : [];
        if (!is_array($additional_dates)) { $additional_dates = []; }
        $show_desc = $id ? get_post_meta($id, '_pa_show_event_descriptions', true) : 'show';
        $categories = $id ? get_post_meta($id, '_pa_categories', true) : [];
        $all_categories_same = $id ? get_post_meta($id, '_pa_categories_all_same', true) : '';
        if (!is_array($categories)) { $categories = []; }
        $speaker_categories = $id ? get_post_meta($id, '_pa_speaker_categories', true) : [];
        if (!is_array($speaker_categories)) { $speaker_categories = []; }
        $speaker_card = $id ? get_post_meta($id, '_pa_speaker_card_settings', true) : [];
        if (!is_array($speaker_card)) { $speaker_card = []; }
        $agenda = $id ? get_post_meta($id, '_pa_agenda_settings', true) : [];
        if (!is_array($agenda)) { $agenda = []; }
        if (!$agenda && $show_desc) { $agenda['show_descriptions'] = $show_desc; }
        $this->nav('programs', $id && $title ? $title : '');
        if (!empty($_GET['saved'])) { echo '<div class="notice notice-success is-dismissible pa-save-notice"><p>Saved successfully!</p></div>'; }
        echo '<h2>' . ($id ? 'Edit Program' : 'Add New Program') . '</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="pa-form pa-comfortable-form pa-program-form">';
        wp_nonce_field('pa_save_program');
        echo '<input type="hidden" name="action" value="pa_save_program"><input type="hidden" name="id" value="' . esc_attr($id) . '"><input type="hidden" name="pa_post_status" value="publish" class="pa-post-status">';
        $this->program_style_copy_control($id);
        $event_page_settings = $id ? get_post_meta($id, self::META_EVENT_SETTINGS, true) : [];
        $speaker_page_settings = $id ? get_post_meta($id, self::META_SPEAKER_SETTINGS, true) : [];
        echo '<input type="hidden" class="pa-copy-event-page-settings" name="program_event_page_settings_json" value="' . esc_attr(is_array($event_page_settings) ? wp_json_encode($event_page_settings) : '') . '">';
        echo '<input type="hidden" class="pa-copy-speaker-page-settings" name="program_speaker_page_settings_json" value="' . esc_attr(is_array($speaker_page_settings) ? wp_json_encode($speaker_page_settings) : '') . '">';
        echo '<label class="pa-field">Program title <span>*</span><input required type="text" name="program_title" value="' . esc_attr($title) . '"></label>';
        echo '<div class="pa-inline-fields pa-two-fields pa-program-date-row"><label class="pa-field">Program start date<input type="date" name="program_start_date" value="' . esc_attr($start_date) . '"></label>';
        echo '<label class="pa-field">Program end date<input type="date" name="program_end_date" value="' . esc_attr($end_date) . '"></label></div>';
        echo '<div class="pa-additional-dates-block"><a href="#" class="pa-additional-dates-toggle">Additional Event Dates</a><div id="pa-additional-dates" class="pa-additional-dates-list">';
        foreach ($additional_dates as $i => $range) { $this->additional_date_row($i, $range); }
        echo '</div><template id="pa-additional-date-template">'; $this->additional_date_row('__INDEX__', ['start'=>'','end'=>'']); echo '</template></div>';
        echo '<label class="pa-field">Back to link <span>*</span><input required type="url" name="back_to_link" value="' . esc_attr($back_to_link) . '" placeholder="https://example.com/program"><small>This is where the “Back to Program” link on individual Event and Speaker pages will send users.</small></label>';
        echo '<section class="pa-program-category-columns"><div class="pa-program-category-column pa-event-categories-column"><h3>Event Categories</h3><p class="description">Category colors and icons are intentional design settings. If no color is chosen, black is used.</p>';
        echo '<label class="pa-field pa-checkbox-field pa-all-categories-same-field"><input type="checkbox" name="categories_all_same" value="1" class="pa-all-categories-same" ' . checked($all_categories_same, '1', false) . '> All event categories have same settings</label>';
        echo '<div id="pa-categories">';
        if (!$categories) { $categories = [['name'=>'','color'=>'#000000','icon'=>'none']]; }
        foreach ($categories as $i => $cat) { $this->category_row($i, $cat); }
        echo '</div><button type="button" class="button pa-add-category">Add event category</button>';
        echo '<template id="pa-category-template">'; $this->category_row('__INDEX__', ['name'=>'','color'=>'#000000','icon'=>'none']); echo '</template></div>';
        echo '<div class="pa-program-category-column pa-speaker-categories-column"><h3>Speaker Categories</h3><p class="description">Optional labels such as Moderator, Host, Panelist, or Featured Speaker. These appear above selected speaker cards.</p><div id="pa-speaker-categories">';
        if (!$speaker_categories) { $speaker_categories = [['name'=>'']]; }
        foreach ($speaker_categories as $i => $speaker_category) { $this->speaker_category_row($i, $speaker_category); }
        echo '</div><button type="button" class="button pa-add-speaker-category">Add speaker category</button>';
        echo '<template id="pa-speaker-category-template">'; $this->speaker_category_row('__INDEX__', ['name'=>'']); echo '</template></div></section>';
        $sponsor_levels = $id ? get_post_meta($id, '_pa_sponsor_levels', true) : [];
        if (!is_array($sponsor_levels)) { $sponsor_levels = []; }
        echo '<section class="pa-sponsor-levels-section"><h3>Sponsor Levels</h3><p class="description">Add sponsor levels for this Program. Sponsors can be assigned to one or more levels. Drag, or use the arrows, to reorder how levels appear on sponsor showcase pages.</p><div id="pa-sponsor-levels">';
        if (!$sponsor_levels) { $sponsor_levels = ['']; }
        foreach ($sponsor_levels as $i => $level) { $this->sponsor_level_row($i, $level); }
        echo '</div><button type="button" class="button pa-add-sponsor-level">Add sponsor level</button><template id="pa-sponsor-level-template">'; $this->sponsor_level_row('__INDEX__', ''); echo '</template></section>';
        echo '<details class="pa-program-advanced-settings"><summary><span>Advanced Settings</span><small>Adjust how the public agenda, speaker cards, and individual Event/Speaker pages appear for this program.</small><small>Leave fields blank to inherit the active WordPress theme. Border width and radius default to 0.</small></summary><div class="pa-program-advanced-inner">';
        $this->program_advanced_settings_module($agenda, $speaker_card, $event_page_settings, $speaker_page_settings);
        echo '</div></details>';
        if ($id) {
            echo '<div class="pa-shortcode-box"><h3>Agenda Shortcode</h3><p>Place this shortcode on any page where the schedule should appear.</p><input readonly value="' . esc_attr('[program_agenda id="' . $this->program_shortcode_id($id) . '"]') . '" onclick="this.select();"></div>';
            echo '<div class="pa-shortcode-box"><h3>Sponsor Showcase Shortcode</h3><p>Place this shortcode on the public Sponsors showcase page.</p><input readonly value="' . esc_attr('[program_sponsors id="' . $this->program_shortcode_id($id) . '"]') . '" onclick="this.select();"></div>';
            echo '<div class="pa-shortcode-box"><h3>Speaker Directory Shortcode</h3><p>Place this shortcode on a public Speakers page to show everyone speaking at this Program.</p><input readonly value="' . esc_attr('[program_speakers id="' . $this->program_shortcode_id($id) . '"]') . '" onclick="this.select();"></div>';
            echo '<div class="pa-shortcode-box"><h3>Program PDF Shortcode</h3><p>Place this shortcode wherever users should download or print the latest Program agenda.</p><input readonly value="' . esc_attr('[program_pdf id="' . $this->program_shortcode_id($id) . '"]') . '" onclick="this.select();"></div>';
        } else {
            echo '<div class="pa-shortcode-box"><h3>Shortcodes</h3><p>The agenda, sponsor showcase, speaker directory, and Program PDF shortcodes will appear here after the program is saved.</p></div>';
        }
        echo $this->form_actions('Save Program') . '</form></div>';
    }

    private function program_style_copy_control($current_id = 0) {
        $programs = get_posts(['post_type'=>'pa_program','post_status'=>['publish','draft'],'numberposts'=>-1,'orderby'=>'title','order'=>'ASC','exclude'=>[$current_id]]);
        if (!$programs) { return; }
        echo '<section class="pa-copy-styles-box"><h3>Copy styles from previous program</h3><p class="description">Copies category colors/icons, agenda settings, speaker card settings, and program-specific Event/Speaker page settings. Program title, dates, and content stay unchanged.</p>';
        echo '<div class="pa-copy-styles-row"><select class="pa-copy-program-source"><option value="">Select a program</option>';
        foreach ($programs as $program) {
            $cats = get_post_meta($program->ID, '_pa_categories', true);
            if (!is_array($cats)) { $cats = []; }
            $card = get_post_meta($program->ID, '_pa_speaker_card_settings', true);
            if (!is_array($card)) { $card = []; }
            $agenda = get_post_meta($program->ID, '_pa_agenda_settings', true);
            if (!is_array($agenda)) { $agenda = []; }
            $event_page = get_post_meta($program->ID, self::META_EVENT_SETTINGS, true);
            if (!is_array($event_page)) { $event_page = []; }
            $speaker_page = get_post_meta($program->ID, self::META_SPEAKER_SETTINGS, true);
            if (!is_array($speaker_page)) { $speaker_page = []; }
            $styles = ['categories' => $cats, 'speaker_card' => $card, 'agenda' => $agenda, 'event_page' => $event_page, 'speaker_page' => $speaker_page];
            echo '<option value="' . esc_attr($program->ID) . '" data-styles="' . esc_attr(wp_json_encode($styles)) . '">' . esc_html($program->post_title) . '</option>';
        }
        echo '</select> <button type="button" class="button pa-copy-program-styles">Copy all styles</button></div></section>';
    }

    private function additional_date_row($i, $range) {
        $start = is_array($range) ? ($range['start'] ?? '') : '';
        $end = is_array($range) ? ($range['end'] ?? '') : '';
        echo '<div class="pa-additional-date-row pa-inline-fields pa-two-fields">';
        echo '<label class="pa-field">Additional start date<input type="date" name="additional_dates[' . esc_attr($i) . '][start]" value="' . esc_attr($start) . '"></label>';
        echo '<label class="pa-field">Additional end date<input type="date" name="additional_dates[' . esc_attr($i) . '][end]" value="' . esc_attr($end) . '"><a href="#" class="pa-remove-additional-date">Remove date range</a></label>';
        echo '</div>';
    }

    private function category_row($i, $cat) {
        $icons = ['none'=>'None','heart'=>'Heart','circle'=>'Circle','triangle'=>'Triangle','square'=>'Square','star'=>'Star'];
        $selected_icon = $cat['icon'] ?? 'none';
        echo '<div class="pa-category-row">';
        echo '<input type="text" name="categories[' . esc_attr($i) . '][name]" placeholder="Category name" value="' . esc_attr($cat['name'] ?? '') . '">';
        echo $this->color_control('categories[' . esc_attr($i) . '][color]', $cat['color'] ?? '#000000', '', '', 'Category color');
        echo '<span class="pa-category-icon-preview" aria-label="Icon preview">' . esc_html($this->icon_char($selected_icon)) . '</span>';
        echo '<select class="pa-category-icon-select" name="categories[' . esc_attr($i) . '][icon]">';
        foreach ($icons as $key => $label) { echo '<option value="' . esc_attr($key) . '" ' . selected($selected_icon, $key, false) . '>' . esc_html($label) . '</option>'; }
        echo '</select>';
        echo '<a href="#" class="pa-remove-row pa-remove-category-link">Remove category</a>';
        echo '<div class="pa-category-remove-warning" hidden><p>This will remove this category from all events that use it.</p><label><input type="checkbox" class="pa-hide-category-warning"> Don&rsquo;t show this warning again</label><button type="button" class="button button-link-delete pa-confirm-remove-category">Remove</button></div>';
        echo '</div>';
    }

    private function speaker_category_row($i, $category) {
        $name = is_array($category) ? ($category['name'] ?? '') : (string)$category;
        echo '<div class="pa-speaker-category-row"><input type="text" name="speaker_categories[' . esc_attr($i) . '][name]" placeholder="Speaker category" value="' . esc_attr($name) . '"><a href="#" class="pa-remove-row pa-remove-speaker-category-link">Remove category</a></div>';
    }

    private function normalize_speaker_categories($categories) {
        $normalized = [];
        foreach ((array)$categories as $category) {
            $name = is_array($category) ? ($category['name'] ?? '') : $category;
            $name = sanitize_text_field($name);
            if ($name !== '' && !in_array($name, $normalized, true)) { $normalized[] = $name; }
        }
        return $normalized;
    }

    private function speaker_categories_for_program($program_id = 0) {
        $program_id = absint($program_id);
        if ($program_id) { return $this->normalize_speaker_categories(get_post_meta($program_id, '_pa_speaker_categories', true)); }
        $categories = [];
        $programs = get_posts(['post_type'=>'pa_program','post_status'=>['publish','draft'],'numberposts'=>-1,'orderby'=>'title','order'=>'ASC']);
        foreach ($programs as $program) {
            foreach ($this->normalize_speaker_categories(get_post_meta($program->ID, '_pa_speaker_categories', true)) as $category) {
                if (!in_array($category, $categories, true)) { $categories[] = $category; }
            }
        }
        return $categories;
    }

    private function speaker_category_options_html($categories, $selected = '') {
        $selected = sanitize_text_field($selected);
        ob_start();
        echo '<option value="">Default speaker</option>';
        foreach ($this->normalize_speaker_categories($categories) as $category) {
            echo '<option value="' . esc_attr($category) . '" ' . selected($selected, $category, false) . '>' . esc_html($category) . '</option>';
        }
        return ob_get_clean();
    }

    private function selected_speaker_admin_row($speaker_id, $event_speaker_categories = [], $speaker_categories = []) {
        $speaker_id = absint($speaker_id);
        $sp = get_post($speaker_id);
        if (!$sp || $sp->post_type !== 'pa_speaker') { return ''; }
        $selected_category = '';
        foreach ([$speaker_id, (string)$speaker_id] as $key) {
            if (isset($event_speaker_categories[$key])) { $selected_category = sanitize_text_field($event_speaker_categories[$key]); break; }
        }
        return '<li data-id="' . esc_attr($speaker_id) . '"><span class="pa-selected-speaker-name">' . esc_html($sp->post_title) . '</span><span class="pa-selected-speaker-category-wrap"><label><span class="screen-reader-text">Speaker category</span><select class="pa-selected-speaker-category" name="speaker_categories[' . esc_attr($speaker_id) . ']">' . $this->speaker_category_options_html($speaker_categories, $selected_category) . '</select></label></span><span class="pa-selected-speaker-actions"><button type="button" class="button-link pa-move-speaker-up" aria-label="Move up" title="Move up"><span aria-hidden="true">▲</span><span class="screen-reader-text">Move up</span></button> <button type="button" class="button-link pa-move-speaker-down" aria-label="Move down" title="Move down"><span aria-hidden="true">▼</span><span class="screen-reader-text">Move down</span></button> <button type="button" class="button-link pa-remove-speaker">Remove</button></span></li>';
    }

    private function sponsor_level_row($i, $level) {
        echo '<div class="pa-sponsor-level-row">';
        echo '<span class="pa-sponsor-level-handle" aria-hidden="true">☰</span>';
        echo '<input type="text" name="sponsor_levels[' . esc_attr($i) . ']" placeholder="Sponsor level" value="' . esc_attr($level) . '">';
        echo '<span class="pa-sponsor-level-actions"><button type="button" class="button-link pa-move-sponsor-level-up" aria-label="Move up" title="Move up"><span aria-hidden="true">▲</span><span class="screen-reader-text">Move up</span></button> <button type="button" class="button-link pa-move-sponsor-level-down" aria-label="Move down" title="Move down"><span aria-hidden="true">▼</span><span class="screen-reader-text">Move down</span></button> <a href="#" class="pa-remove-sponsor-level">Remove level</a></span>';
        echo '</div>';
    }

    private function program_border_controls($group, $s, $label, $live_class = '') {
        $live_class = trim($live_class);
        $lock_radius = !empty($s['lock_radius']);
        $lock_width = !empty($s['lock_width']);
        $radius_fallback = isset($s['border_radius']) && $s['border_radius'] !== '' ? absint($s['border_radius']) : 0;
        $width_fallback = isset($s['border_width']) && $s['border_width'] !== '' ? absint($s['border_width']) : 0;
        echo '<details class="pa-program-border-control pa-border-control pa-collapsible-border" data-border-key="' . esc_attr($group) . '">';
        echo '<summary><span>' . esc_html($label) . '</span><small>Corner radius, border width, and sides</small></summary>';
        echo '<div class="pa-border-section"><div class="pa-border-section-title"><strong>Corner radius</strong><label><input class="pa-lock-radius ' . esc_attr($live_class) . '" type="checkbox" name="' . esc_attr($group) . '[lock_radius]" value="1" ' . checked($lock_radius, true, false) . '> Same for every corner</label></div>';
        echo '<div class="pa-border-grid pa-radius-fields">';
        foreach (['tl'=>'Top left','tr'=>'Top right','br'=>'Bottom right','bl'=>'Bottom left'] as $k=>$lab) {
            $val = $s['radius_' . $k] ?? $radius_fallback;
            echo '<label>' . esc_html($lab) . '<input class="pa-radius-input ' . esc_attr($live_class) . '" type="number" min="0" name="' . esc_attr($group) . '[radius_' . esc_attr($k) . ']" value="' . esc_attr($val) . '" placeholder="0"></label>';
        }
        echo '</div></div>';
        echo '<div class="pa-border-section"><div class="pa-border-section-title"><strong>Border width</strong><label><input class="pa-lock-width ' . esc_attr($live_class) . '" type="checkbox" name="' . esc_attr($group) . '[lock_width]" value="1" ' . checked($lock_width, true, false) . '> Same for every side</label></div>';
        echo '<div class="pa-border-grid pa-width-fields">';
        foreach (['top'=>'Top','right'=>'Right','bottom'=>'Bottom','left'=>'Left'] as $k=>$lab) {
            $val = $s['width_' . $k] ?? $width_fallback;
            echo '<label>' . esc_html($lab) . '<input class="pa-width-input ' . esc_attr($live_class) . '" type="number" min="0" name="' . esc_attr($group) . '[width_' . esc_attr($k) . ']" value="' . esc_attr($val) . '" placeholder="0"></label>';
        }
        echo '</div></div>';
        echo '<div class="pa-border-section pa-border-color-section">';
        echo $this->color_control($group . '[border_color]', $s['border_color'] ?? '', '', 'Border color', $label . ' border color');
        echo '</div></details>';
    }

    private function sanitize_tab_border_options($input) {
        $input = is_array($input) ? $input : [];
        $out = [
            'lock_radius' => !empty($input['lock_radius']) ? '1' : '',
            'lock_width' => !empty($input['lock_width']) ? '1' : '',
        ];
        foreach (['tl','tr','br','bl'] as $corner) {
            $key = 'radius_' . $corner;
            $out[$key] = isset($input[$key]) && $input[$key] !== '' ? absint($input[$key]) : 999;
        }
        foreach (['top','right','bottom','left'] as $side) {
            $key = 'width_' . $side;
            $out[$key] = isset($input[$key]) && $input[$key] !== '' ? absint($input[$key]) : 1;
        }
        if (!empty($out['lock_radius'])) {
            $shared_radius = $out['radius_tl'];
            foreach (['tr','br','bl'] as $corner) { $out['radius_' . $corner] = $shared_radius; }
        }
        if (!empty($out['lock_width'])) {
            $shared_width = $out['width_top'];
            foreach (['right','bottom','left'] as $side) { $out['width_' . $side] = $shared_width; }
        }
        return $out;
    }

    private function sanitize_program_border_options($raw) {
        $raw = (array)$raw;
        $out = [
            'lock_radius' => !empty($raw['lock_radius']) ? 1 : 0,
            'lock_width' => !empty($raw['lock_width']) ? 1 : 0,
        ];
        $radius_fallback = isset($raw['border_radius']) && $raw['border_radius'] !== '' ? absint($raw['border_radius']) : 0;
        $width_fallback = isset($raw['border_width']) && $raw['border_width'] !== '' ? absint($raw['border_width']) : 0;
        $radius_values = [];
        foreach (['tl','tr','br','bl'] as $k) { $radius_values[$k] = isset($raw['radius_' . $k]) && $raw['radius_' . $k] !== '' ? absint($raw['radius_' . $k]) : $radius_fallback; }
        if ($out['lock_radius']) { $first = reset($radius_values); foreach ($radius_values as $k=>$v) { $radius_values[$k] = $first; } }
        foreach ($radius_values as $k=>$v) { $out['radius_' . $k] = $v; }
        $width_values = [];
        foreach (['top','right','bottom','left'] as $k) { $width_values[$k] = isset($raw['width_' . $k]) && $raw['width_' . $k] !== '' ? absint($raw['width_' . $k]) : $width_fallback; }
        if ($out['lock_width']) { $first = reset($width_values); foreach ($width_values as $k=>$v) { $width_values[$k] = $first; } }
        foreach ($width_values as $k=>$v) { $out['width_' . $k] = $v; }
        return $out;
    }

    private function program_border_style($settings, $important = false) {
        if (!is_array($settings)) { return ''; }
        $bang = $important ? ' !important' : '';
        $style = 'border-style:solid' . $bang . ';';
        $has = false;
        foreach (['top','right','bottom','left'] as $side) {
            $v = isset($settings['width_' . $side]) ? $settings['width_' . $side] : ($settings['border_width'] ?? null);
            if ($v !== null && $v !== '') { $style .= 'border-' . $side . '-width:' . absint($v) . 'px' . $bang . ';'; $has = true; }
        }
        foreach (['tl'=>'top-left','tr'=>'top-right','br'=>'bottom-right','bl'=>'bottom-left'] as $k=>$corner) {
            $v = isset($settings['radius_' . $k]) ? $settings['radius_' . $k] : ($settings['border_radius'] ?? null);
            if ($v !== null && $v !== '') { $style .= 'border-' . $corner . '-radius:' . absint($v) . 'px' . $bang . ';'; $has = true; }
        }
        return $has ? $style : '';
    }


    /**
     * Keep stale saved test values (for example medium/large) on the safe full-card path.
     */
    private function normalize_agenda_card_size($value) {
        $value = sanitize_key((string)$value);
        return $value === 'thin' ? 'thin' : 'full';
    }

    private function agenda_controls($s) {
        $show_desc = $s['show_descriptions'] ?? 'hide';
        $display_mode = ($s['display_mode'] ?? 'tabs') === 'tabs' ? 'tabs' : 'stacked';
        $date_display = $s['date_display'] ?? 'numeric';
        if (!in_array($date_display, ['numeric','abbrev'], true)) { $date_display = 'numeric'; }
        $card_size = $this->normalize_agenda_card_size($s['card_size'] ?? 'full');

        echo '<section class="pa-program-design-panel pa-agenda-section pa-event-card-section pa-advanced-tab-panel active" data-pa-program-panel="agenda"><h4>Event Card Settings</h4><p class="description">Controls how event cards appear on the public agenda.</p>';

        echo '<div class="pa-agenda-options-row pa-agenda-color-row">';
        echo $this->color_control('agenda[background]', $s['background'] ?? '', '', 'Background color', 'Agenda background color');
        echo $this->color_control('agenda[accent_bar_color]', $s['accent_bar_color'] ?? '', '', 'Accent bar color', 'Event card accent bar color');
        echo $this->color_control('agenda[title_color]', $s['title_color'] ?? ($s['color'] ?? ''), '', 'Title text color', 'Event title text color');
        echo $this->color_control('agenda[location_color]', $s['location_color'] ?? '', '', 'Event information text color', 'Event information text color');
        echo '</div>';

        echo '<div class="pa-agenda-options-row pa-agenda-border-row">';
        $this->program_border_controls('agenda', $s, 'Border', 'pa-agenda-live-field');
        echo '</div>';

        echo '<details class="pa-nested-advanced-settings"><summary><span>Advanced Settings</span><small>Display options, tabs, date format, and card size</small></summary>';
        echo '<div class="pa-agenda-options-row pa-agenda-behavior-row">';
        echo '<label class="pa-field">Event descriptions <span>*</span><select class="pa-agenda-live-field" required name="agenda[show_descriptions]"><option value="show" ' . selected($show_desc, 'show', false) . '>Show</option><option value="hide" ' . selected($show_desc, 'hide', false) . '>Hide</option></select></label>';
        echo '<label class="pa-field">Agenda display <select class="pa-agenda-live-field" name="agenda[display_mode]"><option value="stacked" ' . selected($display_mode, 'stacked', false) . '>Stacked</option><option value="tabs" ' . selected($display_mode, 'tabs', false) . '>Tabs by day</option></select></label>';
        echo '<input type="hidden" class="pa-agenda-live-field" name="agenda[speaker_layout]" value="inline">';
        echo '<div class="pa-agenda-tab-color-row">';
        echo $this->color_control('agenda[tab_background_color]', $s['tab_background_color'] ?? '', '', 'Tab color', 'Agenda tab color');
        echo $this->color_control('agenda[tab_border_color]', $s['tab_border_color'] ?? '', '', 'Tab border color', 'Agenda tab border color');
        echo '</div>';
        $tab_border = isset($s['tab_border']) && is_array($s['tab_border']) ? $s['tab_border'] : [];
        echo '<details class="pa-control-group pa-border-control pa-collapsible-border pa-tab-border-control" open>';
        echo '<summary>Tab border <small>Radius, width, and color</small></summary>';
        echo '<div class="pa-border-section"><div class="pa-border-section-title"><strong>Corner radius</strong><label><input class="pa-lock-radius" type="checkbox" name="agenda[tab_border][lock_radius]" value="1" ' . checked(!empty($tab_border['lock_radius']), true, false) . '> Same for every corner</label></div>';
        echo '<div class="pa-border-grid pa-radius-fields">';
        foreach (['tl'=>'Top left','tr'=>'Top right','br'=>'Bottom right','bl'=>'Bottom left'] as $k=>$lab) { echo '<label>' . esc_html($lab) . '<input class="pa-radius-input pa-agenda-live-field" type="number" min="0" name="agenda[tab_border][radius_' . esc_attr($k) . ']" value="' . esc_attr($tab_border['radius_'.$k] ?? 999) . '" placeholder="999"></label>'; }
        echo '</div></div>';
        echo '<div class="pa-border-section"><div class="pa-border-section-title"><strong>Border width</strong><label><input class="pa-lock-width" type="checkbox" name="agenda[tab_border][lock_width]" value="1" ' . checked(!empty($tab_border['lock_width']), true, false) . '> Same for every side</label></div>';
        echo '<div class="pa-border-grid pa-width-fields">';
        foreach (['top'=>'Top','right'=>'Right','bottom'=>'Bottom','left'=>'Left'] as $k=>$lab) { echo '<label>' . esc_html($lab) . '<input class="pa-width-input pa-agenda-live-field" type="number" min="0" name="agenda[tab_border][width_' . esc_attr($k) . ']" value="' . esc_attr($tab_border['width_'.$k] ?? 1) . '" placeholder="1"></label>'; }
        echo '</div></div>';
        echo '</details>';
        echo '<label class="pa-field">Date display <select class="pa-agenda-live-field" name="agenda[date_display]"><option value="numeric" ' . selected($date_display, 'numeric', false) . '>8/20</option><option value="abbrev" ' . selected($date_display, 'abbrev', false) . '>Aug. 20</option></select></label>';
        echo '<label class="pa-field">Card size <select class="pa-agenda-live-field" name="agenda[card_size]"><option value="full" ' . selected($card_size, 'full', false) . '>Full: compact speaker cards</option><option value="thin" ' . selected($card_size, 'thin', false) . '>Thin: title/meta only</option></select></label>';
        echo '</div></details>';
        echo '</section>';
    }



    private function speaker_card_controls($s) {
        $show_thumb = isset($s['show_thumbnail']) ? $s['show_thumbnail'] : '1';
        $shape = $s['thumbnail_shape'] ?? 'theme';
        echo '<section class="pa-program-design-panel pa-speaker-card-section pa-advanced-tab-panel" data-pa-program-panel="speaker-card"><h4>Speaker Card Settings</h4><p class="description">These cards appear on agenda event listings and individual event pages.</p>';

        echo '<div class="pa-card-options-row pa-card-color-row">';
        echo $this->color_control('speaker_card[background]', $s['background'] ?? '', '', 'Background color', 'Speaker card background color');
        echo $this->color_control('speaker_card[color]', $s['color'] ?? '', '', 'Text color', 'Speaker card text color');
        echo '</div>';

        echo '<div class="pa-card-options-row pa-card-border-row">';
        $this->program_border_controls('speaker_card', $s, 'Border', 'pa-speaker-card-live-field');
        echo '</div>';

        echo '<div class="pa-card-thumbnail-control">';
        echo '<div class="pa-field-heading">Thumbnail</div>';
        echo '<div class="pa-card-thumbnail-inline">';
        echo '<label class="pa-field pa-checkbox-field"><input class="pa-speaker-card-live-field" type="checkbox" name="speaker_card[show_thumbnail]" value="1" ' . checked($show_thumb, '1', false) . '> Show/hide</label>';
        echo '<label class="pa-field pa-thumbnail-shape-field"><select class="pa-speaker-card-live-field" name="speaker_card[thumbnail_shape]"><option value="theme" ' . selected($shape, 'theme', false) . '>Theme/default</option><option value="square" ' . selected($shape, 'square', false) . '>Square</option><option value="circle" ' . selected($shape, 'circle', false) . '>Circle</option></select></label>';
        echo '</div>';
        echo '</div>';
        echo '</section>';
    }


    private function combined_program_preview($agenda, $speaker_card) {
        echo '<section class="pa-combined-preview-section"><h3>Preview</h3>';
        echo '<div class="pa-agenda-tabs-preview pa-agenda-day-tabs" hidden><div class="pa-agenda-day-tab-list"><button type="button" class="pa-agenda-day-tab active">8/20</button><button type="button" class="pa-agenda-day-tab">8/21</button></div></div>';
        echo '<article class="pa-event-card pa-event-card-preview pa-event-card--has-speakers pa-event-card--speakers-inline pa-event-card--size-full">';
        echo '<div class="pa-event-card__datebar"><span class="pa-event-card__date pa-event-card-preview-date">8/20</span><span class="pa-event-card__time">00:00</span></div>';
        echo '<div class="pa-event-card__body"><div class="pa-event-card__summary"><h4 class="pa-event-card__title"><span class="pa-preview-line pa-preview-line-title"></span></h4><p class="pa-event-card__meta"><span class="pa-event-card__category"><span class="pa-event-card__category-icon">★</span><span class="pa-preview-line pa-preview-line-short"></span></span><span class="pa-event-card__meta-dot" aria-hidden="true">•</span><span class="pa-preview-line pa-preview-line-medium"></span></p><div class="pa-event-card__description pa-event-card-preview-description"><span class="pa-preview-line pa-preview-line-content"></span><span class="pa-preview-line pa-preview-line-content"></span></div></div>';
        echo '<div class="pa-event-card__speakers"><div class="pa-speaker-card-list pa-speaker-card-list-agenda"><article class="pa-speaker-card pa-speaker-card-preview"><span class="pa-speaker-card-image"><span class="pa-speaker-card-thumb pa-speaker-card-preview-thumb" aria-hidden="true"></span></span><div class="pa-speaker-card-text pa-speaker-card-preview-text"><span class="pa-preview-line pa-preview-line-speaker-name"></span><span class="pa-preview-line pa-preview-line-speaker-role"></span><span class="pa-preview-line pa-preview-line-speaker-company"></span></div></article></div></div></div></article>';
        echo '</section>';
    }


    private function program_page_preview($type, $s) {
        if (!is_array($s)) { $s = []; }
        $is_speaker = $type === 'speaker';
        echo '<section class="pa-preview-card pa-program-page-preview-card"><div class="pa-preview-title"><h3>Preview</h3></div>';
        echo '<div class="pa-live-preview" data-program-page-preview="' . esc_attr($type) . '">';
        if ($is_speaker) {
            echo '<div class="pa-preview-header pa-preview-speaker-header" style="' . esc_attr($this->inline_header_style($s)) . '"><span class="pa-preview-image" style="' . esc_attr($this->preview_image_style($s)) . '"></span><div class="pa-preview-speaker-text"><span class="pa-preview-line pa-preview-line-heading"></span><span class="pa-preview-line pa-preview-line-subheading"></span><span class="pa-preview-line pa-preview-line-subheading"></span></div><nav class="pa-preview-speaker-icons" aria-label="Preview speaker links"><span class="pa-preview-icon" aria-hidden="true">↗</span><span class="pa-preview-icon" aria-hidden="true">◎</span></nav></div>';
        } else {
            echo '<div class="pa-preview-header" style="' . esc_attr($this->inline_header_style($s)) . '"><span class="pa-preview-line pa-preview-line-heading"></span><span class="pa-preview-line pa-preview-line-subheading"></span></div>';
        }
        echo '<div class="pa-preview-content" style="' . esc_attr($this->inline_content_style($s)) . '"><span class="pa-preview-line pa-preview-line-content"></span><span class="pa-preview-line pa-preview-line-content"></span><span class="pa-preview-line pa-preview-line-content"></span></div></div></section>';
    }

    private function program_page_settings_controls($type, $s, $root) {
        if (!is_array($s)) { $s = []; }
        $label = $type === 'speaker' ? 'Speaker Page Settings' : 'Event Page Settings';
        echo '<section class="pa-program-page-settings-panel" data-pa-page-settings="' . esc_attr($type) . '">';

        echo '<div class="pa-page-settings-columns">';
        echo '<div class="pa-page-settings-section pa-page-settings-header-section"><h5>Header</h5><div class="pa-color-setting-row">';
        echo $this->color_control($root . '[header_bg]', $s['header_bg'] ?? '', '', 'Background color', $label . ' header background color');
        echo $this->color_control($root . '[header_color]', $s['header_color'] ?? '', '', 'Text color', $label . ' header text color');
        echo '</div>';
        $this->border_group_named($root, 'header_border', 'Border', $s);
        echo '</div>';

        echo '<div class="pa-page-settings-section pa-page-settings-content-section"><h5>Content</h5><div class="pa-color-setting-row">';
        echo $this->color_control($root . '[content_bg]', $s['content_bg'] ?? '', '', 'Background color', $label . ' content background color');
        echo $this->color_control($root . '[content_color]', $s['content_color'] ?? '', '', 'Text color', $label . ' content text color');
        echo '</div>';
        $this->border_group_named($root, 'content_border', 'Border', $s);
        echo '</div>';
        echo '</div>';

        if ($type === 'event') {
        }

        if ($type === 'speaker') {
            echo '<div class="pa-page-settings-section pa-page-settings-image-section"><label class="pa-settings-section-heading">Speaker Page Image Settings</label><div class="pa-image-options-row">';
            echo '<label class="pa-field">Image shape<select name="' . esc_attr($root) . '[image_shape]"><option value="" ' . selected($s['image_shape'] ?? '', '', false) . '>Theme/default</option><option value="square" ' . selected($s['image_shape'] ?? '', 'square', false) . '>Square</option><option value="circle" ' . selected($s['image_shape'] ?? '', 'circle', false) . '>Circle</option></select></label>';
            echo '<label class="pa-field">Image border width<input type="number" min="0" name="' . esc_attr($root) . '[image_border_width]" value="' . esc_attr($s['image_border_width'] ?? 0) . '" placeholder="0"></label>';
            echo $this->color_control($root . '[image_border_color]', $s['image_border_color'] ?? '', '', 'Image border color', 'Speaker image border color');
            echo '</div></div>';

            echo '<div class="pa-page-settings-section pa-page-settings-image-section pa-speaker-directory-image-settings"><label class="pa-settings-section-heading">Speaker Directory Image Settings</label><div class="pa-image-options-row">';
            echo '<label class="pa-field">Image shape<select name="' . esc_attr($root) . '[directory_image_shape]"><option value="" ' . selected($s['directory_image_shape'] ?? '', '', false) . '>Theme/default</option><option value="square" ' . selected($s['directory_image_shape'] ?? '', 'square', false) . '>Square</option><option value="circle" ' . selected($s['directory_image_shape'] ?? '', 'circle', false) . '>Circle</option></select></label>';
            echo '<label class="pa-field">Image border width<input type="number" min="0" name="' . esc_attr($root) . '[directory_image_border_width]" value="' . esc_attr($s['directory_image_border_width'] ?? 0) . '" placeholder="0"></label>';
            echo $this->color_control($root . '[directory_image_border_color]', $s['directory_image_border_color'] ?? '', '', 'Image border color', 'Speaker directory image border color');
            echo '</div></div>';
        }
        echo '</section>';
    }

    private function border_group_named($root, $key, $label, $s) {
        $v = $s[$key] ?? [];
        $prefix = esc_attr($key);
        $root_attr = esc_attr($root);
        echo '<details class="pa-control-group pa-border-control pa-collapsible-border" data-border-key="' . $root_attr . '-' . $prefix . '">';
        echo '<summary>' . esc_html($label) . ' <small>Radius, width, and color</small></summary>';
        echo '<div class="pa-border-section"><div class="pa-border-section-title"><strong>Corner radius</strong><label><input class="pa-lock-radius" type="checkbox" name="' . $root_attr . '[' . $prefix . '][lock_radius]" value="1" ' . checked(!empty($v['lock_radius']), true, false) . '> Same for every corner</label></div>';
        echo '<div class="pa-border-grid pa-radius-fields">';
        foreach (['tl'=>'Top left','tr'=>'Top right','br'=>'Bottom right','bl'=>'Bottom left'] as $k=>$lab) { echo '<label>' . esc_html($lab) . '<input class="pa-radius-input" type="number" min="0" name="' . $root_attr . '[' . $prefix . '][radius_' . esc_attr($k) . ']" value="' . esc_attr($v['radius_'.$k] ?? 0) . '" placeholder="0"></label>'; }
        echo '</div></div>';
        echo '<div class="pa-border-section"><div class="pa-border-section-title"><strong>Border width</strong><label><input class="pa-lock-width" type="checkbox" name="' . $root_attr . '[' . $prefix . '][lock_width]" value="1" ' . checked(!empty($v['lock_width']), true, false) . '> Same for every side</label></div>';
        echo '<div class="pa-border-grid pa-width-fields">';
        foreach (['top'=>'Top','right'=>'Right','bottom'=>'Bottom','left'=>'Left'] as $k=>$lab) { echo '<label>' . esc_html($lab) . '<input class="pa-width-input" type="number" min="0" name="' . $root_attr . '[' . $prefix . '][width_' . esc_attr($k) . ']" value="' . esc_attr($v['width_'.$k] ?? 0) . '" placeholder="0"></label>'; }
        echo '</div></div>';
        echo '<div class="pa-border-section pa-border-color-section">' . $this->color_control($root . '[' . $key . '][color]', $v['color'] ?? '', '', '', $label . ' border color') . '</div>';
        echo '</details>';
    }

    public function form_event() {
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $post = $id ? get_post($id) : null;
        $program_id = $id ? absint(get_post_meta($id, '_pa_program_id', true)) : 0;
        $speaker_ids = $id ? get_post_meta($id, '_pa_speaker_ids', true) : [];
        if (!is_array($speaker_ids)) { $speaker_ids = []; }
        $event_speaker_categories = $id ? get_post_meta($id, '_pa_event_speaker_categories', true) : [];
        if (!is_array($event_speaker_categories)) { $event_speaker_categories = []; }
        $speaker_categories = $this->speaker_categories_for_program($program_id);
        $programs = get_posts(['post_type'=>'pa_program','post_status'=>['publish','draft'],'numberposts'=>-1,'orderby'=>'title','order'=>'ASC']);
        $speakers = get_posts(['post_type'=>'pa_speaker','post_status'=>['publish','draft'],'numberposts'=>-1,'orderby'=>'title','order'=>'ASC']);
        $sponsors = get_posts(['post_type'=>'pa_sponsor','post_status'=>['publish','draft'],'numberposts'=>-1,'orderby'=>'title','order'=>'ASC']);
        $sponsor_ids = $id ? get_post_meta($id, '_pa_sponsor_ids', true) : [];
        if (!is_array($sponsor_ids)) { $sponsor_ids = []; }
        if ($id && empty($sponsor_ids)) {
            $legacy_logo_ids = get_post_meta($id, '_pa_event_sponsor_logo_ids', true);
            // Legacy direct sponsor logos are intentionally not carried forward into Sponsor entities.
        }
        $category_options = [];
        foreach ($programs as $pr) {
            $cats = get_post_meta($pr->ID, '_pa_categories', true);
            if (is_array($cats)) {
                foreach ($cats as $cat) {
                    if (!empty($cat['name'])) { $category_options[] = $cat['name']; }
                }
            }
        }
        $category_options = array_values(array_unique($category_options));
        $stored_date = $id ? get_post_meta($id, '_pa_event_date', true) : '';
        $stored_time = $id ? get_post_meta($id, '_pa_event_time', true) : '';
        $stored_end_time = $id ? get_post_meta($id, '_pa_event_end_time', true) : '';
        if (!$stored_time && $stored_date && strpos($stored_date, ':') !== false) { $stored_time = date('H:i', strtotime($stored_date)); }
        $date_value = $stored_date ? date('Y-m-d', strtotime($stored_date)) : '';
        $image_id = $id ? absint(get_post_meta($id, '_pa_event_image_id', true)) : 0;
        $invite_only = $id ? get_post_meta($id, '_pa_event_invite_only', true) : '';
        $this->nav('events', $post ? $post->post_title : '');
        if (!empty($_GET['saved'])) { echo '<div class="notice notice-success is-dismissible pa-save-notice"><p>Saved successfully!</p></div>'; }
        echo '<h2>' . ($id ? 'Edit Event' : 'Add New Event') . '</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="pa-form pa-comfortable-form pa-event-form">';
        wp_nonce_field('pa_save_event');
        echo '<input type="hidden" name="action" value="pa_save_event"><input type="hidden" name="id" value="' . esc_attr($id) . '"><input type="hidden" name="pa_post_status" value="publish" class="pa-post-status">';
        echo '<div class="pa-event-layout">';
        echo '<div class="pa-event-section pa-event-section-1">';

        echo '<label class="pa-field pa-event-title-field pa-event-span-full"><span class="pa-field-heading">Event Title <span>*</span></span><input required type="text" name="event_title" value="' . esc_attr($post ? $post->post_title : '') . '"></label>';

        echo '<label class="pa-field pa-event-half"><span class="pa-field-heading">Program</span><select name="program_id" class="pa-program-category-source pa-program-date-source"><option value="">No program selected</option>'; foreach ($programs as $pr) { echo '<option value="' . esc_attr($pr->ID) . '" ' . selected($program_id, $pr->ID, false) . '>' . esc_html($pr->post_title) . '</option>'; } echo '</select></label>';

        echo '<div class="pa-field pa-event-half pa-event-category-field"><label class="pa-field-heading">Category</label><small>Choose an existing Program category below, or type a new one.</small><input type="text" name="event_category" class="pa-event-category-input" value="' . esc_attr($id ? get_post_meta($id, '_pa_event_category', true) : '') . '" placeholder="Type a category or choose a pill below">';
        echo '<div class="pa-category-pill-picker" data-pa-category-pill-picker aria-label="Program categories"><p class="description">Available categories</p><div class="pa-category-pills"></div></div></div>';

        echo '</div><div class="pa-event-section pa-event-section-2">';

        echo '<div class="pa-field pa-event-date-field pa-event-span-full"><label class="pa-field-heading">Date <span>*</span></label><small>Dates are pulled from the selected Program. Choose “Add another date” to enter a different date.</small><div class="pa-event-date-controls"><select class="pa-event-program-date-select"><option value="">Select a date</option><option value="__custom__">Add another date</option></select><input required type="date" name="event_date" value="' . esc_attr($date_value) . '"></div></div>';
        echo '<label class="pa-field pa-event-time-field"><span class="pa-field-heading">Start Time <span>*</span></span><input required type="time" name="event_time" value="' . esc_attr($stored_time) . '"></label>';
        echo '<label class="pa-field pa-event-time-field"><span class="pa-field-heading">End Time</span><input type="time" name="event_end_time" value="' . esc_attr($stored_end_time) . '"></label>';

        echo '<label class="pa-field pa-event-half"><span class="pa-field-heading">Location <span>*</span></span><input required type="text" name="event_location" value="' . esc_attr($id ? get_post_meta($id, '_pa_event_location', true) : '') . '"></label>';
        echo '<label class="pa-field pa-event-half"><span class="pa-field-heading">Location Link</span><input type="url" name="event_location_link" value="' . esc_attr($id ? get_post_meta($id, '_pa_event_location_link', true) : '') . '"></label>';

        echo '</div><div class="pa-event-section pa-event-section-3">';

        echo '<div class="pa-event-span-full">';
        $this->image_field('event_image_id', $image_id, 'Event Header Photo', 'Recommended 1400x250px; larger images will crop to this frame. Will appear on event page only.');
        echo '</div>';

        echo '<div class="pa-editor-field pa-event-description-field pa-event-span-full"><label class="pa-field-heading">Event Description <span>*</span></label>';
        wp_editor($post ? $post->post_content : '', 'event_description', ['textarea_name'=>'event_description','media_buttons'=>false,'textarea_rows'=>8]);
        echo '</div>';

        echo '<div class="pa-field pa-event-half pa-event-checkbox-block"><span class="pa-field-heading">Invite only</span><label class="pa-checkbox-control"><input type="checkbox" name="event_invite_only" value="1" class="pa-invite-only-toggle" ' . checked($invite_only, '1', false) . '> Enable invite-only message</label></div>';
        echo '<div class="pa-field pa-event-half pa-event-checkbox-block"><span class="pa-field-heading">Add to Calendar</span><label class="pa-checkbox-control"><input type="checkbox" name="event_show_add_to_calendar" value="1" ' . checked($id ? get_post_meta($id, '_pa_event_show_add_to_calendar', true) : '', '1', false) . '> Show Add to Calendar link</label></div>';

        echo '<div class="pa-editor-field pa-invite-warning-editor pa-event-span-full" ' . ($invite_only === '1' ? '' : 'hidden') . '><label class="pa-field-heading">Invite-only custom message</label><p class="description">Message shown on the individual event page below the invite-only notice.</p>';
        wp_editor($id ? get_post_meta($id, '_pa_event_invite_warning', true) : '', 'event_invite_warning', ['textarea_name'=>'event_invite_warning','media_buttons'=>false,'textarea_rows'=>4]);
        echo '</div>';

        echo '</div><div class="pa-event-section pa-event-section-4">';

        echo '<section class="pa-field pa-speakers-field pa-event-half"><h3 class="pa-field-heading">Speakers</h3><p class="description">Searchable and multi-selectable. Selected speakers can be reordered below.</p><div class="pa-speaker-toolbar"><input type="search" class="pa-speaker-search" placeholder="Search speakers by name, company, role, or credentials"><button type="button" class="button pa-select-all-speakers">Select all visible</button></div><div class="pa-speaker-picker">';
        foreach ($speakers as $sp) {
            $speaker_company = get_post_meta($sp->ID, '_pa_speaker_company', true);
            $speaker_role = get_post_meta($sp->ID, '_pa_speaker_role_title', true);
            $speaker_credentials = get_post_meta($sp->ID, '_pa_speaker_credentials', true);
            $speaker_search_terms = strtolower(trim($sp->post_title . ' ' . $speaker_company . ' ' . $speaker_role . ' ' . $speaker_credentials));
            echo '<label data-name="' . esc_attr($speaker_search_terms) . '"><input type="checkbox" class="pa-speaker-check" value="' . esc_attr($sp->ID) . '" ' . checked(in_array($sp->ID, array_map('intval', $speaker_ids), true), true, false) . '> ' . esc_html($sp->post_title) . '</label>';
        }
        echo '</div><ul class="pa-selected-speakers pa-selected-speakers-with-categories" data-empty="No speakers selected.">';
        foreach ($speaker_ids as $sid) { echo $this->selected_speaker_admin_row($sid, $event_speaker_categories, $speaker_categories); }
        echo '</ul><template id="pa-speaker-category-select-template"><span class="pa-selected-speaker-category-wrap"><label><span class="screen-reader-text">Speaker category</span><select class="pa-selected-speaker-category" name="speaker_categories[__SPEAKER_ID__]">' . $this->speaker_category_options_html($speaker_categories) . '</select></label></span></template><input type="hidden" name="speaker_order" class="pa-speaker-order" value="' . esc_attr(implode(',', array_map('intval', $speaker_ids))) . '"></section>';

        echo '<section class="pa-field pa-sponsors-field pa-event-half"><h3 class="pa-field-heading">Sponsors</h3><p class="description">Searchable and multi-selectable. Choose from sponsors created in the Sponsors tab.</p><div class="pa-sponsor-toolbar"><input type="search" class="pa-sponsor-search" placeholder="Search sponsors by company, program, level, or bio"><button type="button" class="button pa-select-all-sponsors">Select all visible</button></div><div class="pa-sponsor-picker">';
        foreach ($sponsors as $sponsor) {
            $sponsor_program_id = absint(get_post_meta($sponsor->ID, '_pa_sponsor_program_id', true));
            $sponsor_program_title = $sponsor_program_id ? get_the_title($sponsor_program_id) : '';
            $sponsor_levels = $this->sponsor_levels_for_program($sponsor->ID, $program_id);
            $sponsor_search_terms = strtolower(trim($sponsor->post_title . ' ' . $sponsor_program_title . ' ' . implode(' ', $sponsor_levels) . ' ' . wp_strip_all_tags($sponsor->post_content)));
            echo '<label data-name="' . esc_attr($sponsor_search_terms) . '"><input type="checkbox" class="pa-sponsor-check" value="' . esc_attr($sponsor->ID) . '" ' . checked(in_array($sponsor->ID, array_map('intval', $sponsor_ids), true), true, false) . '> ' . esc_html($sponsor->post_title) . '</label>';
        }
        echo '</div><ul class="pa-selected-sponsors" data-empty="No sponsors selected.">';
        foreach ($sponsor_ids as $sid) { $sponsor = get_post($sid); if ($sponsor) { echo '<li data-id="' . esc_attr($sid) . '"><span class="pa-selected-sponsor-name">' . esc_html($sponsor->post_title) . '</span><span class="pa-selected-sponsor-actions"><button type="button" class="button-link pa-move-sponsor-up" aria-label="Move up" title="Move up"><span aria-hidden="true">▲</span><span class="screen-reader-text">Move up</span></button> <button type="button" class="button-link pa-move-sponsor-down" aria-label="Move down" title="Move down"><span aria-hidden="true">▼</span><span class="screen-reader-text">Move down</span></button> <button type="button" class="button-link pa-remove-sponsor">Remove</button></span></li>'; } }
        echo '</ul><input type="hidden" name="sponsor_order" class="pa-sponsor-order" value="' . esc_attr(implode(',', array_map('intval', $sponsor_ids))) . '"></section>';

        echo '</div>';
        echo $this->form_actions($id ? 'Update Event' : 'Save Event', 'pa-event-actions');
        echo '</div></form></div>';
    }

    public function form_speaker() {
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $post = $id ? get_post($id) : null;
        $this->nav('speakers', $post ? $post->post_title : '');
        if (!empty($_GET['saved'])) { echo '<div class="notice notice-success is-dismissible pa-save-notice"><p>Saved successfully!</p></div>'; }
        echo '<h2>' . ($id ? 'Edit Speaker' : 'Add New Speaker') . '</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="pa-form pa-comfortable-form pa-speaker-form">';
        wp_nonce_field('pa_save_speaker');
        echo '<input type="hidden" name="action" value="pa_save_speaker"><input type="hidden" name="id" value="' . esc_attr($id) . '"><input type="hidden" name="pa_post_status" value="publish" class="pa-post-status">';
        $img = $id ? absint(get_post_meta($id, '_pa_speaker_image_id', true)) : 0;
        $this->image_field('speaker_image_id', $img, 'Speaker image <span>*</span>', '500x500px recommended');
        echo '<div class="pa-inline-fields pa-three-fields"><label class="pa-field">First Name <span>*</span><input required type="text" name="first_name" value="' . esc_attr($id ? get_post_meta($id, '_pa_first_name', true) : '') . '"></label>';
        echo '<label class="pa-field">Last Name <span>*</span><input required type="text" name="last_name" value="' . esc_attr($id ? get_post_meta($id, '_pa_last_name', true) : '') . '"></label>';
        echo '<label class="pa-field">Credentials<input type="text" name="credentials" value="' . esc_attr($id ? get_post_meta($id, '_pa_speaker_credentials', true) : '') . '"></label></div>';
        echo '<label class="pa-field">Company <span>*</span><input required type="text" name="company" value="' . esc_attr($id ? get_post_meta($id, '_pa_speaker_company', true) : '') . '"></label>';
        echo '<label class="pa-field">Role Title<input type="text" name="role_title" value="' . esc_attr($id ? get_post_meta($id, '_pa_speaker_role_title', true) : '') . '"></label>';
        echo '<div class="pa-inline-fields pa-two-fields"><label class="pa-field">LinkedIn<input type="url" name="linkedin" value="' . esc_attr($id ? get_post_meta($id, '_pa_speaker_linkedin', true) : '') . '"></label>';
        echo '<label class="pa-field">Website<input type="url" name="website" value="' . esc_attr($id ? get_post_meta($id, '_pa_speaker_website', true) : '') . '"></label></div>';
        $selected_event_ids = $id ? $this->speaker_event_ids($id) : [];
        $events_for_speaker = get_posts(['post_type'=>'pa_event','post_status'=>['publish','draft'],'numberposts'=>-1,'meta_key'=>'_pa_event_date','orderby'=>'meta_value','order'=>'ASC']);
        echo '<section class="pa-field pa-speaker-event-picker-field"><h3 class="pa-field-heading">Events</h3><p class="description">Searchable and multi-selectable. Selected events are automatically sorted by event date.</p><div class="pa-speaker-event-toolbar"><input type="search" class="pa-speaker-event-search" placeholder="Search events by title, program, date, category, or location"><button type="button" class="button pa-select-all-speaker-events">Select all visible</button></div><div class="pa-speaker-event-picker">';
        foreach ($events_for_speaker as $event_for_speaker) {
            $event_program_id = absint(get_post_meta($event_for_speaker->ID, '_pa_program_id', true));
            $event_program_title = $event_program_id ? get_the_title($event_program_id) : '';
            $event_when = $this->format_event_when($event_for_speaker->ID);
            $event_category = get_post_meta($event_for_speaker->ID, '_pa_event_category', true);
            $event_location = get_post_meta($event_for_speaker->ID, '_pa_event_location', true);
            $event_label = $this->speaker_event_picker_label($event_for_speaker->ID);
            $event_search_terms = strtolower(trim($event_for_speaker->post_title . ' ' . $event_program_title . ' ' . $event_when . ' ' . $event_category . ' ' . $event_location));
            echo '<label data-name="' . esc_attr($event_search_terms) . '"><input type="checkbox" class="pa-speaker-event-check" name="speaker_event_ids[]" value="' . esc_attr($event_for_speaker->ID) . '" ' . checked(in_array(absint($event_for_speaker->ID), array_map('intval', $selected_event_ids), true), true, false) . '> ' . esc_html($event_label) . '</label>';
        }
        echo '</div><ul class="pa-selected-speaker-events" data-empty="No events selected.">';
        foreach ($selected_event_ids as $selected_event_id) {
            $selected_event_label = $this->speaker_event_picker_label($selected_event_id);
            if ($selected_event_label) { echo '<li data-id="' . esc_attr($selected_event_id) . '"><span class="pa-selected-speaker-event-name">' . esc_html($selected_event_label) . '</span><button type="button" class="button-link pa-remove-speaker-event">Remove</button></li>'; }
        }
        echo '</ul></section>';
        $style_program_id = $id ? absint(get_post_meta($id, '_pa_speaker_style_program_id', true)) : 0;
        if (!$style_program_id && $id) { $style_program_id = $this->speaker_primary_program_id($id); }
        $programs_for_style = get_posts(['post_type'=>'pa_program','post_status'=>['publish','draft'],'numberposts'=>-1,'orderby'=>'date','order'=>'DESC']);
        echo '<label class="pa-field">Program style<select name="speaker_style_program_id"><option value="">Default: most recent attached program</option>';
        foreach ($programs_for_style as $program_for_style) { echo '<option value="' . esc_attr($program_for_style->ID) . '" ' . selected($style_program_id, $program_for_style->ID, false) . '>' . esc_html($program_for_style->post_title) . '</option>'; }
        echo '</select><small>Controls which Program styling is used for this speaker page when the speaker appears in more than one program.</small></label>';
        echo '<div class="pa-editor-field"><label>Bio <span>*</span></label>'; wp_editor($post ? $post->post_content : '', 'speaker_bio', ['textarea_name'=>'speaker_bio','media_buttons'=>false,'textarea_rows'=>8]); echo '</div>';
        echo $this->form_actions('Save Speaker') . '</form></div>';
    }

    private function image_field($name, $image_id, $label, $description = '') {
        echo '<div class="pa-image-field pa-field"><label>' . wp_kses_post($label) . '</label>';
        if ($description) { echo '<p class="description pa-image-recommendation"><em>' . esc_html($description) . '</em></p>'; }
        echo '<div class="pa-image-preview">';
        if ($image_id) { echo wp_get_attachment_image($image_id, 'thumbnail'); }
        echo '</div><input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($image_id) . '"><button type="button" class="button pa-upload-image">Choose image</button> <button type="button" class="button pa-remove-image">Remove</button></div>';
    }

    private function color_control($name, $value = '', $preview_key = '', $label = '', $aria_label = 'Color') {
        $value = $value ? sanitize_hex_color($value) : '';
        $theme_colors = $this->theme_palette_colors();
        ob_start();
        echo '<div class="pa-color-control' . ($label ? ' pa-field' : '') . '">';
        if ($label) { echo '<span class="pa-color-label">' . esc_html($label) . '</span>'; }
        echo '<input class="pa-color-value pa-preview-input" ' . ($preview_key ? 'data-preview="' . esc_attr($preview_key) . '"' : '') . ' type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">';
        echo '<div class="pa-color-palette" role="group" aria-label="' . esc_attr($aria_label) . '">';
        foreach ($theme_colors as $index => $color) {
            $active = strtolower($value) === strtolower($color) ? ' active' : '';
            echo '<button type="button" class="pa-color-swatch' . esc_attr($active) . '" data-color="' . esc_attr($color) . '" style="--pa-swatch-color:' . esc_attr($color) . '" title="Theme color ' . esc_attr($index + 1) . '"><span>Theme color ' . esc_html($index + 1) . '</span></button>';
        }
        echo '<button type="button" class="button pa-more-colors">More colors</button>';
        echo '<button type="button" class="button-link pa-clear-color">Reset</button>';
        echo '</div>';
        echo '<div class="pa-color-popover" hidden><div class="pa-color-popover-inner"><button type="button" class="button-link pa-close-color" aria-label="Close color picker">×</button><label>Custom color <input class="pa-native-color" type="color" value="' . esc_attr($value ?: '#000000') . '"></label><input class="pa-hex-color" type="text" value="' . esc_attr($value) . '" placeholder="#000000"></div></div>';
        echo '</div>';
        return ob_get_clean();
    }

    private function preview_image_style($s) {
        $css = '';
        if (($s['image_shape'] ?? '') === 'circle') { $css .= 'border-radius:50%;'; }
        elseif (($s['image_shape'] ?? '') === 'square') { $css .= 'border-radius:0;'; }
        if (isset($s['image_border_width']) && $s['image_border_width'] !== '') { $css .= 'border-style:solid;border-width:' . absint($s['image_border_width']) . 'px;'; }
        if (!empty($s['image_border_color'])) { $css .= 'border-color:' . esc_attr($s['image_border_color']) . ';'; }
        return $css;
    }

    private function can_edit_pa_post($post_type, $id = 0) {
        $id = absint($id);
        if ($id) {
            $post = get_post($id);
            return $post && $post->post_type === $post_type && current_user_can('edit_post', $id);
        }
        return current_user_can('edit_posts');
    }

    private function require_edit_pa_post($post_type, $id = 0) {
        if (!$this->can_edit_pa_post($post_type, $id)) {
            wp_die(esc_html__('You are not allowed to edit this item.', 'program-agenda'));
        }
    }

    private function ensure_saved_post_id($result) {
        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }
        $post_id = absint($result);
        if (!$post_id) {
            wp_die(esc_html__('The item could not be saved.', 'program-agenda'));
        }
        return $post_id;
    }

    private function save_program_advanced_meta($program_id) {
        $agenda_in = (array)($_POST['agenda'] ?? []);
        $agenda = [
            'show_descriptions' => sanitize_key($agenda_in['show_descriptions'] ?? 'hide') === 'show' ? 'show' : 'hide',
            'display_mode' => sanitize_key($agenda_in['display_mode'] ?? 'tabs') === 'stacked' ? 'stacked' : 'tabs',
            'speaker_layout' => 'inline',
            'date_display' => in_array(sanitize_key($agenda_in['date_display'] ?? 'numeric'), ['numeric','abbrev'], true) ? sanitize_key($agenda_in['date_display'] ?? 'numeric') : 'numeric',
            'card_size' => $this->normalize_agenda_card_size($agenda_in['card_size'] ?? 'full'),
            'background' => sanitize_hex_color($agenda_in['background'] ?? '') ?: '',
            'accent_bar_color' => sanitize_hex_color($agenda_in['accent_bar_color'] ?? '') ?: '',
            'title_color' => sanitize_hex_color($agenda_in['title_color'] ?? ($agenda_in['color'] ?? '')) ?: '',
            'location_color' => sanitize_hex_color($agenda_in['location_color'] ?? '') ?: '',
            'tab_background_color' => sanitize_hex_color($agenda_in['tab_background_color'] ?? '') ?: '',
            'tab_border_color' => sanitize_hex_color($agenda_in['tab_border_color'] ?? '') ?: '',
            'tab_border' => $this->sanitize_tab_border_options($agenda_in['tab_border'] ?? []),
            'border_color' => sanitize_hex_color($agenda_in['border_color'] ?? '') ?: '',
        ];
        $agenda = array_merge($agenda, $this->sanitize_program_border_options($agenda_in));
        update_post_meta($program_id, '_pa_agenda_settings', $agenda);
        update_post_meta($program_id, '_pa_show_event_descriptions', $agenda['show_descriptions']);

        $card_in = (array)($_POST['speaker_card'] ?? []);
        $card = [
            'show_thumbnail' => !empty($card_in['show_thumbnail']) ? '1' : '0',
            'thumbnail_shape' => sanitize_key($card_in['thumbnail_shape'] ?? 'theme'),
            'background' => sanitize_hex_color($card_in['background'] ?? '') ?: '',
            'color' => sanitize_hex_color($card_in['color'] ?? '') ?: '',
            'border_color' => sanitize_hex_color($card_in['border_color'] ?? '') ?: '',
        ];
        $card = array_merge($card, $this->sanitize_program_border_options($card_in));
        update_post_meta($program_id, '_pa_speaker_card_settings', $card);

        if (isset($_POST['event_page_settings'])) {
            update_post_meta($program_id, self::META_EVENT_SETTINGS, $this->sanitize_settings($_POST['event_page_settings']));
        }
        if (isset($_POST['speaker_page_settings'])) {
            update_post_meta($program_id, self::META_SPEAKER_SETTINGS, $this->sanitize_settings($_POST['speaker_page_settings']));
        }
    }

    public function save_program_advanced() {
        check_admin_referer('pa_save_program_advanced');
        $id = absint($_POST['id'] ?? 0);
        $this->require_edit_pa_post('pa_program', $id);
        $this->save_program_advanced_meta($id);
        wp_safe_redirect(admin_url('admin.php?page=program-advanced-settings&id=' . $id . '&saved=1'));
        exit;
    }

    public function save_program() {
        check_admin_referer('pa_save_program');
        $id = absint($_POST['id'] ?? 0);
        $this->require_edit_pa_post('pa_program', $id);
        $title = sanitize_text_field($_POST['program_title'] ?? '');
        $status = $this->requested_post_status();
        $postarr = ['post_type'=>'pa_program','post_title'=>$title,'post_name'=>sanitize_title($title),'post_status'=>$status];
        if ($id) { $postarr['ID'] = $id; $new_id = wp_update_post($postarr, true); } else { $new_id = wp_insert_post($postarr, true); }
        $new_id = $this->ensure_saved_post_id($new_id);
        $program_start = sanitize_text_field($_POST['program_start_date'] ?? '');
        $program_end = sanitize_text_field($_POST['program_end_date'] ?? '');
        update_post_meta($new_id, '_pa_program_start_date', $program_start);
        update_post_meta($new_id, '_pa_program_end_date', $program_end);
        $additional_dates = [];
        foreach ((array)($_POST['additional_dates'] ?? []) as $range) {
            $range_start = sanitize_text_field($range['start'] ?? '');
            $range_end = sanitize_text_field($range['end'] ?? '');
            if ($range_start === '' && $range_end === '') { continue; }
            $additional_dates[] = ['start' => $range_start, 'end' => $range_end];
        }
        update_post_meta($new_id, '_pa_program_additional_dates', $additional_dates);
        update_post_meta($new_id, '_pa_program_dates', $this->program_dates_label($program_start, $program_end, $additional_dates));
        update_post_meta($new_id, '_pa_back_to_link', esc_url_raw($_POST['back_to_link'] ?? ''));
        $agenda_in = (array)($_POST['agenda'] ?? []);
        $agenda = [
            'show_descriptions' => sanitize_key($agenda_in['show_descriptions'] ?? 'hide') === 'show' ? 'show' : 'hide',
            'display_mode' => sanitize_key($agenda_in['display_mode'] ?? 'tabs') === 'stacked' ? 'stacked' : 'tabs',
            'speaker_layout' => 'inline',
            'date_display' => in_array(sanitize_key($agenda_in['date_display'] ?? 'numeric'), ['numeric','abbrev'], true) ? sanitize_key($agenda_in['date_display'] ?? 'numeric') : 'numeric',
            'card_size' => $this->normalize_agenda_card_size($agenda_in['card_size'] ?? 'full'),
            'background' => sanitize_hex_color($agenda_in['background'] ?? '') ?: '',
            'accent_bar_color' => sanitize_hex_color($agenda_in['accent_bar_color'] ?? '') ?: '',
            'title_color' => sanitize_hex_color($agenda_in['title_color'] ?? ($agenda_in['color'] ?? '')) ?: '',
            'location_color' => sanitize_hex_color($agenda_in['location_color'] ?? '') ?: '',
            'tab_background_color' => sanitize_hex_color($agenda_in['tab_background_color'] ?? '') ?: '',
            'tab_border_color' => sanitize_hex_color($agenda_in['tab_border_color'] ?? '') ?: '',
            'tab_border' => $this->sanitize_tab_border_options($agenda_in['tab_border'] ?? []),
            'border_color' => sanitize_hex_color($agenda_in['border_color'] ?? '') ?: '',
        ];
        $agenda = array_merge($agenda, $this->sanitize_program_border_options($agenda_in));
        update_post_meta($new_id, '_pa_agenda_settings', $agenda);
        update_post_meta($new_id, '_pa_show_event_descriptions', $agenda['show_descriptions']);
        $categories_all_same = !empty($_POST['categories_all_same']) ? '1' : '0';
        update_post_meta($new_id, '_pa_categories_all_same', $categories_all_same);
        $cats = [];
        foreach ((array)($_POST['categories'] ?? []) as $cat) {
            $name = sanitize_text_field($cat['name'] ?? '');
            if ($name === '') { continue; }
            $cats[] = ['name'=>$name, 'color'=>sanitize_hex_color($cat['color'] ?? '') ?: '#000000', 'icon'=>sanitize_key($cat['icon'] ?? 'none')];
        }
        if ($categories_all_same === '1' && $cats) {
            $shared_color = $cats[0]['color'] ?: '#000000';
            $shared_icon = $cats[0]['icon'] ?: 'none';
            foreach ($cats as &$cat) { $cat['color'] = $shared_color; $cat['icon'] = $shared_icon; }
            unset($cat);
        }
        update_post_meta($new_id, '_pa_categories', $cats);
        $speaker_categories = [];
        foreach ((array)($_POST['speaker_categories'] ?? []) as $speaker_category) {
            $speaker_category_name = sanitize_text_field($speaker_category['name'] ?? '');
            if ($speaker_category_name !== '' && !in_array($speaker_category_name, array_column($speaker_categories, 'name'), true)) {
                $speaker_categories[] = ['name' => $speaker_category_name];
            }
        }
        update_post_meta($new_id, '_pa_speaker_categories', $speaker_categories);
        $sponsor_levels = [];
        foreach ((array)($_POST['sponsor_levels'] ?? []) as $level) {
            $level = sanitize_text_field($level);
            if ($level !== '' && !in_array($level, $sponsor_levels, true)) { $sponsor_levels[] = $level; }
        }
        update_post_meta($new_id, '_pa_sponsor_levels', $sponsor_levels);
        $card_in = (array)($_POST['speaker_card'] ?? []);
        $card = [
            'show_thumbnail' => !empty($card_in['show_thumbnail']) ? '1' : '0',
            'thumbnail_shape' => sanitize_key($card_in['thumbnail_shape'] ?? 'theme'),
            'background' => sanitize_hex_color($card_in['background'] ?? '') ?: '',
            'color' => sanitize_hex_color($card_in['color'] ?? '') ?: '',
            'border_color' => sanitize_hex_color($card_in['border_color'] ?? '') ?: '',
        ];
        $card = array_merge($card, $this->sanitize_program_border_options($card_in));
        update_post_meta($new_id, '_pa_speaker_card_settings', $card);
        if (isset($_POST['event_page_settings'])) {
            update_post_meta($new_id, self::META_EVENT_SETTINGS, $this->sanitize_settings($_POST['event_page_settings']));
        } elseif (isset($_POST['program_event_page_settings_json']) && $_POST['program_event_page_settings_json'] !== '') {
            $event_page_settings = json_decode(wp_unslash($_POST['program_event_page_settings_json']), true);
            if (is_array($event_page_settings)) { update_post_meta($new_id, self::META_EVENT_SETTINGS, $this->sanitize_settings($event_page_settings)); }
        }
        if (isset($_POST['speaker_page_settings'])) {
            update_post_meta($new_id, self::META_SPEAKER_SETTINGS, $this->sanitize_settings($_POST['speaker_page_settings']));
        } elseif (isset($_POST['program_speaker_page_settings_json']) && $_POST['program_speaker_page_settings_json'] !== '') {
            $speaker_page_settings = json_decode(wp_unslash($_POST['program_speaker_page_settings_json']), true);
            if (is_array($speaker_page_settings)) { update_post_meta($new_id, self::META_SPEAKER_SETTINGS, $this->sanitize_settings($speaker_page_settings)); }
        }
        wp_safe_redirect(admin_url('admin.php?page=program-edit-program&id=' . $new_id . '&saved=1')); exit;
    }

    public function save_event() {
        check_admin_referer('pa_save_event');
        $id = absint($_POST['id'] ?? 0);
        $this->require_edit_pa_post('pa_event', $id);
        $postarr = ['post_type'=>'pa_event','post_title'=>sanitize_text_field($_POST['event_title'] ?? ''),'post_content'=>wp_kses_post($_POST['event_description'] ?? ''),'post_status'=>$this->requested_post_status()];
        if ($id) { $postarr['ID'] = $id; $new_id = wp_update_post($postarr, true); } else { $new_id = wp_insert_post($postarr, true); }
        $new_id = $this->ensure_saved_post_id($new_id);
        foreach (['program_id'=>'_pa_program_id','event_category'=>'_pa_event_category','event_date'=>'_pa_event_date','event_time'=>'_pa_event_time','event_end_time'=>'_pa_event_end_time','event_location'=>'_pa_event_location','event_location_link'=>'_pa_event_location_link','event_image_id'=>'_pa_event_image_id'] as $field=>$meta) {
            $value = $_POST[$field] ?? '';
            $value = in_array($field, ['program_id','event_image_id'], true) ? absint($value) : ($field === 'event_location_link' ? esc_url_raw($value) : sanitize_text_field($value));
            update_post_meta($new_id, $meta, $value);
        }
        $sponsor_ids = array_values(array_filter(array_map('absint', explode(',', sanitize_text_field($_POST['sponsor_order'] ?? '')))));
        update_post_meta($new_id, '_pa_sponsor_ids', $sponsor_ids);
        delete_post_meta($new_id, '_pa_event_sponsor_logo_ids');
        delete_post_meta($new_id, '_pa_event_sponsor_logo_id');
        update_post_meta($new_id, '_pa_event_show_add_to_calendar', !empty($_POST['event_show_add_to_calendar']) ? '1' : '0');
        update_post_meta($new_id, '_pa_event_invite_only', !empty($_POST['event_invite_only']) ? '1' : '0');
        update_post_meta($new_id, '_pa_event_invite_warning', wp_kses_post($_POST['event_invite_warning'] ?? ''));
        $program_id = absint($_POST['program_id'] ?? 0);
        $category_name = sanitize_text_field($_POST['event_category'] ?? '');
        if ($program_id && $category_name !== '') {
            $cats = get_post_meta($program_id, '_pa_categories', true);
            if (!is_array($cats)) { $cats = []; }
            $exists = false;
            foreach ($cats as $cat) {
                if (isset($cat['name']) && strtolower($cat['name']) === strtolower($category_name)) { $exists = true; break; }
            }
            if (!$exists) {
                $all_same = get_post_meta($program_id, '_pa_categories_all_same', true) === '1';
                $base = $all_same && !empty($cats[0]) && is_array($cats[0]) ? $cats[0] : ['color'=>'#000000', 'icon'=>'none'];
                $cats[] = ['name'=>$category_name, 'color'=>sanitize_hex_color($base['color'] ?? '') ?: '#000000', 'icon'=>sanitize_key($base['icon'] ?? 'none')];
                update_post_meta($program_id, '_pa_categories', $cats);
            }
        }
        $order = array_filter(array_map('absint', explode(',', sanitize_text_field($_POST['speaker_order'] ?? ''))));
        update_post_meta($new_id, '_pa_speaker_ids', $order);
        $raw_speaker_categories = (array)($_POST['speaker_categories'] ?? []);
        $event_speaker_categories = [];
        foreach ($order as $speaker_id) {
            $speaker_id = absint($speaker_id);
            $speaker_category = '';
            foreach ([$speaker_id, (string)$speaker_id] as $speaker_category_key) {
                if (isset($raw_speaker_categories[$speaker_category_key])) { $speaker_category = sanitize_text_field($raw_speaker_categories[$speaker_category_key]); break; }
            }
            if ($speaker_category !== '') { $event_speaker_categories[(string)$speaker_id] = $speaker_category; }
        }
        update_post_meta($new_id, '_pa_event_speaker_categories', $event_speaker_categories);
        wp_safe_redirect(admin_url('admin.php?page=program-edit-event&id=' . $new_id . '&saved=1')); exit;
    }

    public function save_speaker() {
        check_admin_referer('pa_save_speaker');
        $id = absint($_POST['id'] ?? 0);
        $this->require_edit_pa_post('pa_speaker', $id);
        $first = sanitize_text_field($_POST['first_name'] ?? ''); $last = sanitize_text_field($_POST['last_name'] ?? '');
        $title = trim($first . ' ' . $last);
        $postarr = ['post_type'=>'pa_speaker','post_title'=>$title,'post_content'=>wp_kses_post($_POST['speaker_bio'] ?? ''),'post_status'=>$this->requested_post_status()];
        if ($id) { $postarr['ID'] = $id; $new_id = wp_update_post($postarr, true); } else { $new_id = wp_insert_post($postarr, true); }
        $new_id = $this->ensure_saved_post_id($new_id);
        $fields = ['speaker_image_id'=>'_pa_speaker_image_id','first_name'=>'_pa_first_name','last_name'=>'_pa_last_name','credentials'=>'_pa_speaker_credentials','role_title'=>'_pa_speaker_role_title','company'=>'_pa_speaker_company','linkedin'=>'_pa_speaker_linkedin','website'=>'_pa_speaker_website'];
        foreach ($fields as $field=>$meta) {
            $value = $_POST[$field] ?? '';
            $value = $field === 'speaker_image_id' ? absint($value) : (in_array($field, ['linkedin','website'], true) ? esc_url_raw($value) : sanitize_text_field($value));
            update_post_meta($new_id, $meta, $value);
        }
        update_post_meta($new_id, '_pa_speaker_style_program_id', absint($_POST['speaker_style_program_id'] ?? 0));
        $selected_event_ids = array_values(array_filter(array_unique(array_map('absint', (array)($_POST['speaker_event_ids'] ?? [])))));
        $this->sync_speaker_events($new_id, $selected_event_ids);
        wp_safe_redirect(admin_url('admin.php?page=program-edit-speaker&id=' . $new_id . '&saved=1')); exit;
    }

    public function save_sponsor() {
        check_admin_referer('pa_save_sponsor');
        $id = absint($_POST['id'] ?? 0);
        $this->require_edit_pa_post('pa_sponsor', $id);
        $title = sanitize_text_field($_POST['sponsor_company'] ?? '');
        $postarr = ['post_type'=>'pa_sponsor','post_title'=>$title,'post_content'=>wp_kses_post($_POST['sponsor_bio'] ?? ''),'post_status'=>$this->requested_post_status()];
        if ($id) { $postarr['ID'] = $id; $new_id = wp_update_post($postarr, true); } else { $new_id = wp_insert_post($postarr, true); }
        $new_id = $this->ensure_saved_post_id($new_id);
        update_post_meta($new_id, '_pa_sponsor_logo_id', absint($_POST['sponsor_logo_id'] ?? 0));
        update_post_meta($new_id, '_pa_sponsor_website', esc_url_raw($_POST['sponsor_website'] ?? ''));
        $program_ids = array_values(array_filter(array_unique(array_map('absint', (array)($_POST['sponsor_program_ids'] ?? [])))));
        $program_ids = array_values(array_filter($program_ids, static function($program_id) { return get_post_type($program_id) === 'pa_program'; }));
        $program_id = $program_ids ? absint($program_ids[0]) : 0;
        update_post_meta($new_id, '_pa_sponsor_program_id', $program_id);
        update_post_meta($new_id, '_pa_sponsor_program_ids', $program_ids);

        $raw_program_levels = (array)($_POST['sponsor_program_levels'] ?? []);
        $program_levels = [];
        $all_levels = [];
        foreach ($program_ids as $selected_program_id) {
            $selected_levels = [];
            foreach ((array)($raw_program_levels[$selected_program_id] ?? $raw_program_levels[(string) $selected_program_id] ?? []) as $level) {
                $level = sanitize_text_field($level);
                if ($level !== '' && !in_array($level, $selected_levels, true)) { $selected_levels[] = $level; }
                if ($level !== '' && !in_array($level, $all_levels, true)) { $all_levels[] = $level; }
            }
            $program_levels[(string) $selected_program_id] = $selected_levels;
        }
        update_post_meta($new_id, '_pa_sponsor_program_levels', $program_levels);
        update_post_meta($new_id, '_pa_sponsor_levels', $all_levels);
        wp_safe_redirect(admin_url('admin.php?page=program-edit-sponsor&id=' . $new_id . '&saved=1')); exit;
    }

    public function download_import_template() {
        $type = sanitize_key($_GET['type'] ?? 'events');
        if (!in_array($type, ['events','speakers','sponsors'], true)) { $type = 'events'; }
        check_admin_referer('pa_download_import_template_' . $type);
        if (!current_user_can('edit_posts')) { wp_die('Permission denied.'); }
        $templates = [
            'events' => ['program','event_title','date','start_time','end_time','location','location_link','category','description','speakers','sponsors','header_image'],
            'speakers' => ['program','first_name','last_name','speaker_name','role_title','company','credentials','bio','headshot_image','website','linkedin'],
            'sponsors' => ['programs','company_name','sponsor_levels','bio','logo_image','sponsor_website'],
        ];
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="program-agenda-' . $type . '-template.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $templates[$type]);
        if ($type === 'sponsors') { fputcsv($out, ['DHKC Test 2026 | Another Program','Example Sponsor','DHKC Test 2026: Gold | Another Program: Diamond','Short sponsor bio.','example-logo.png','https://example.com']); }
        elseif ($type === 'speakers') { fputcsv($out, ['DHKC Test 2026','Jane','Smith','Jane Smith','Director','Example Co.','MPH','Short speaker bio.','jane-smith.jpg','https://example.com','https://linkedin.com/in/example']); }
        else { fputcsv($out, ['DHKC Test 2026','Opening Session','2026-06-01','9:00 AM','10:00 AM','Main Hall','https://example.com/location','Keynote','Short event description.','Jane Smith, John Doe','Example Sponsor, Another Sponsor','event-header.jpg']); }
        fclose($out);
        exit;
    }

    public function mass_import() {
        check_admin_referer('pa_mass_import');
        if (!current_user_can('edit_posts')) { wp_die('Permission denied.'); }
        $type = sanitize_key($_POST['import_type'] ?? 'events');
        if (!in_array($type, ['events','speakers','sponsors'], true)) { $type = 'events'; }
        $status = sanitize_key($_POST['import_status'] ?? 'publish');
        if (!in_array($status, ['publish','draft'], true)) { $status = 'publish'; }
        if (empty($_FILES['import_file']['tmp_name']) || !empty($_FILES['import_file']['error'])) {
            wp_safe_redirect(add_query_arg('import_error', rawurlencode('Upload failed. Choose a CSV, XLSX, or ZIP file.'), admin_url('admin.php?page=program-mass-import'))); exit;
        }
        $result = $this->read_import_upload($_FILES['import_file']);
        if (is_wp_error($result)) {
            wp_safe_redirect(add_query_arg('import_error', rawurlencode($result->get_error_message()), admin_url('admin.php?page=program-mass-import'))); exit;
        }
        $created = 0; $warnings = 0; $content_rows = 0;
        foreach ($result['rows'] as $row) {
            if (!$this->row_has_content($row)) { continue; }
            $content_rows++;
            $ok = false;
            if ($type === 'events') { $ok = $this->import_event_row($row, $status, $result['images']); }
            elseif ($type === 'speakers') { $ok = $this->import_speaker_row($row, $status, $result['images']); }
            else { $ok = $this->import_sponsor_row($row, $status, $result['images']); }
            if ($ok) { $created++; } else { $warnings++; }
        }
        if (!empty($result['temp_dir'])) {
            $this->cleanup_import_temp_dir($result['temp_dir']);
        }
        if ($created < 1) {
            wp_safe_redirect(add_query_arg('import_error', rawurlencode('No files imported. Check import type.'), admin_url('admin.php?page=program-mass-import'))); exit;
        }
        wp_safe_redirect(add_query_arg(['imported'=>$created, 'import_warnings'=>$warnings], admin_url('admin.php?page=program-mass-import'))); exit;
    }

    private function row_has_content($row) {
        foreach ((array)$row as $value) { if (trim((string)$value) !== '') { return true; } }
        return false;
    }

    private function read_import_upload($file) {
        $name = sanitize_file_name($file['name'] ?? '');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $tmp = $file['tmp_name'];
        $images = [];
        if ($ext === 'zip') {
            if (!class_exists('ZipArchive')) { return new WP_Error('pa_zip_missing', 'This server does not support ZIP imports. Upload CSV/XLSX instead.'); }
            $dir = trailingslashit(get_temp_dir()) . 'pa-import-' . wp_generate_password(8, false) . '/';
            wp_mkdir_p($dir);
            $zip = new ZipArchive();
            if ($zip->open($tmp) !== true) {
                $this->cleanup_import_temp_dir($dir);
                return new WP_Error('pa_zip_open', 'Could not open the ZIP file.');
            }
            $sheet_path = '';
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $entry = $stat['name'] ?? '';
                if (!$entry || strpos($entry, '__MACOSX/') === 0) { continue; }
                $base = basename($entry);
                $entry_ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
                if (!$sheet_path && in_array($entry_ext, ['csv','xlsx'], true)) { $sheet_path = $dir . $base; copy('zip://' . $tmp . '#' . $entry, $sheet_path); }
                if (preg_match('#(^|/)images/[^/]+\.(jpe?g|png|gif|webp|svg)$#i', $entry)) { $target = $dir . $base; copy('zip://' . $tmp . '#' . $entry, $target); $images[strtolower($base)] = $target; }
            }
            $zip->close();
            if (!$sheet_path) {
                $this->cleanup_import_temp_dir($dir);
                return new WP_Error('pa_zip_sheet_missing', 'ZIP imports need one CSV or XLSX spreadsheet.');
            }
            $rows = strtolower(pathinfo($sheet_path, PATHINFO_EXTENSION)) === 'xlsx' ? $this->parse_xlsx_rows($sheet_path) : $this->parse_csv_rows($sheet_path);
            if (is_wp_error($rows)) {
                $this->cleanup_import_temp_dir($dir);
                return $rows;
            }
            return ['rows'=>$rows, 'images'=>$images, 'temp_dir'=>$dir];
        }
        if ($ext === 'xlsx') { $rows = $this->parse_xlsx_rows($tmp); }
        elseif ($ext === 'csv') { $rows = $this->parse_csv_rows($tmp); }
        else { return new WP_Error('pa_bad_import_type', 'Use a CSV, XLSX, or ZIP file.'); }
        return is_wp_error($rows) ? $rows : ['rows'=>$rows, 'images'=>$images, 'temp_dir'=>''];
    }

    private function cleanup_import_temp_dir($dir) {
        $dir = trailingslashit((string)$dir);
        if ($dir === '/' || !is_dir($dir) || strpos($dir, trailingslashit(get_temp_dir()) . 'pa-import-') !== 0) { return; }
        $files = glob($dir . '*');
        if (is_array($files)) {
            foreach ($files as $file) {
                if (is_file($file)) { @unlink($file); }
            }
        }
        @rmdir($dir);
    }

    private function parse_csv_rows($path) {
        $handle = fopen($path, 'r');
        if (!$handle) { return new WP_Error('pa_csv_open', 'Could not read the CSV file.'); }
        $header = fgetcsv($handle);
        if (!$header) { fclose($handle); return new WP_Error('pa_csv_header', 'The spreadsheet needs a header row.'); }
        $header = array_map([$this, 'normalize_import_key'], $header);
        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            $row = [];
            foreach ($header as $i => $key) { if ($key !== '') { $row[$key] = isset($data[$i]) ? trim((string)$data[$i]) : ''; } }
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }

    private function parse_xlsx_rows($path) {
        if (!class_exists('ZipArchive')) { return new WP_Error('pa_xlsx_missing', 'This server does not support XLSX imports. Save the template as CSV instead.'); }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) { return new WP_Error('pa_xlsx_open', 'Could not open the XLSX file.'); }
        $shared = [];
        $shared_xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($shared_xml) {
            $sx = @simplexml_load_string($shared_xml);
            if ($sx) { foreach ($sx->si as $si) { $shared[] = (string)$si->t; } }
        }
        $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if (!$sheet_xml) { return new WP_Error('pa_xlsx_sheet', 'Could not find the first worksheet in the XLSX file.'); }
        $xml = @simplexml_load_string($sheet_xml);
        if (!$xml) { return new WP_Error('pa_xlsx_parse', 'Could not read the XLSX worksheet.'); }
        $table = [];
        foreach ($xml->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $ref = (string)$c['r'];
                preg_match('/[A-Z]+/', $ref, $m);
                $col = $this->xlsx_col_index($m[0] ?? 'A');
                $type = (string)$c['t'];
                $value = (string)$c->v;
                if ($type === 's') { $value = $shared[(int)$value] ?? ''; }
                elseif ($type === 'inlineStr') { $value = (string)$c->is->t; }
                $cells[$col] = trim($value);
            }
            if ($cells) { $table[] = $cells; }
        }
        if (!$table) { return []; }
        $header_cells = array_shift($table);
        $max = max(array_keys($header_cells));
        $header = [];
        for ($i=0; $i <= $max; $i++) { $header[$i] = $this->normalize_import_key($header_cells[$i] ?? ''); }
        $rows = [];
        foreach ($table as $cells) {
            $row = [];
            foreach ($header as $i => $key) { if ($key !== '') { $row[$key] = $cells[$i] ?? ''; } }
            $rows[] = $row;
        }
        return $rows;
    }

    private function xlsx_col_index($letters) {
        $n = 0;
        foreach (str_split($letters) as $ch) { $n = $n * 26 + (ord($ch) - 64); }
        return $n - 1;
    }

    private function normalize_import_key($key) {
        return trim(preg_replace('/[^a-z0-9]+/', '_', strtolower((string)$key)), '_');
    }

    private function split_import_list($value) {
        return array_values(array_filter(array_map('trim', preg_split('/\s*[|,;]\s*/', (string)$value))));
    }

    private function find_program_id_by_import_value($value) {
        global $wpdb;
        $value = trim((string)$value);
        if ($value === '') { return 0; }
        if (ctype_digit($value) && get_post_type((int)$value) === 'pa_program') { return (int)$value; }
        $title_matches = get_posts([
            'post_type' => 'pa_program',
            'post_status' => ['publish','draft'],
            'title' => $value,
            'numberposts' => 1,
            'fields' => 'ids',
        ]);
        if ($title_matches) { return absint($title_matches[0]); }
        $slug_matches = get_posts(['post_type'=>'pa_program','post_status'=>['publish','draft'],'name'=>sanitize_title($value),'numberposts'=>1,'fields'=>'ids']);
        if ($slug_matches) { return absint($slug_matches[0]); }
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'pa_program' AND post_status IN ('publish','draft') AND LOWER(post_title) = LOWER(%s) ORDER BY post_status = 'publish' DESC, ID DESC LIMIT 1",
            $value
        ));
    }

    private function find_pa_post_by_title($title, $post_type) {
        return $this->find_pa_post_by_import_value($title, $post_type);
    }

    private function find_pa_post_by_import_value($value, $post_type) {
        global $wpdb;
        $value = trim(wp_strip_all_tags((string)$value));
        if ($value === '' || !in_array($post_type, ['pa_speaker','pa_sponsor','pa_event','pa_program'], true)) { return 0; }
        if (ctype_digit($value) && get_post_type((int)$value) === $post_type) { return (int)$value; }

        $title_matches = get_posts([
            'post_type' => $post_type,
            'post_status' => ['publish','draft'],
            'title' => $value,
            'numberposts' => 1,
            'fields' => 'ids',
        ]);
        if ($title_matches) { return absint($title_matches[0]); }

        $slug_matches = get_posts([
            'post_type' => $post_type,
            'post_status' => ['publish','draft'],
            'name' => sanitize_title($value),
            'numberposts' => 1,
            'fields' => 'ids',
        ]);
        if ($slug_matches) { return absint($slug_matches[0]); }

        $id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish','draft') AND LOWER(post_title) = LOWER(%s) ORDER BY post_status = 'publish' DESC, ID DESC LIMIT 1",
            $post_type,
            $value
        ));
        if ($id) { return $id; }

        if ($post_type === 'pa_speaker') {
            $parts = preg_split('/\s+/', $value);
            $first = $parts ? array_shift($parts) : '';
            $last = $parts ? implode(' ', $parts) : '';
            if ($first !== '') {
                $meta_query = [['key'=>'_pa_first_name','value'=>$first,'compare'=>'LIKE']];
                if ($last !== '') { $meta_query[] = ['key'=>'_pa_last_name','value'=>$last,'compare'=>'LIKE']; }
                $matches = get_posts([
                    'post_type'=>'pa_speaker',
                    'post_status'=>['publish','draft'],
                    'numberposts'=>1,
                    'fields'=>'ids',
                    'meta_query'=>$meta_query,
                ]);
                if ($matches) { return absint($matches[0]); }
            }
        }

        return 0;
    }

    private function normalize_import_time($value) {
        $value = trim((string)$value);
        if ($value === '') { return ''; }

        // Excel stores times as fractions of a day: 0.375 = 09:00.
        if (is_numeric($value)) {
            $float = (float) $value;
            if ($float >= 0 && $float < 1) {
                $total_minutes = (int) round($float * 24 * 60);
                $hours = floor($total_minutes / 60) % 24;
                $minutes = $total_minutes % 60;
                return sprintf('%02d:%02d', $hours, $minutes);
            }
            // Excel date+time serial. Keep only the time portion.
            if ($float >= 1) {
                $fraction = $float - floor($float);
                if ($fraction > 0) {
                    $total_minutes = (int) round($fraction * 24 * 60);
                    $hours = floor($total_minutes / 60) % 24;
                    $minutes = $total_minutes % 60;
                    return sprintf('%02d:%02d', $hours, $minutes);
                }
            }
        }

        $value = preg_replace('/\s+/', ' ', $value);
        if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', $value, $m)) {
            return sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
        }
        $ts = strtotime($value);
        return $ts ? date('H:i', $ts) : '';
    }

    /**
     * Import images from a public URL or from the ZIP package's top-level images/ folder.
     */
    private function import_image_to_media($value, $images = []) {
        $value = trim((string)$value);
        if ($value === '') { return 0; }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        if (preg_match('#^https?://#i', $value)) {
            $id = media_sideload_image(esc_url_raw($value), 0, null, 'id');
            return is_wp_error($id) ? 0 : absint($id);
        }
        $path = $images[strtolower(basename($value))] ?? '';
        if ($path && file_exists($path)) {
            $copy = wp_tempnam(basename($path));
            if (!$copy || !copy($path, $copy)) { return 0; }
            $file_array = ['name'=>basename($path), 'tmp_name'=>$copy];
            $id = media_handle_sideload($file_array, 0);
            if (is_wp_error($id)) { @unlink($copy); return 0; }
            return absint($id);
        }
        return 0;
    }

    private function import_speaker_row($row, $status, $images = []) {
        $name = sanitize_text_field($row['speaker_name'] ?? '');
        $first = sanitize_text_field($row['first_name'] ?? '');
        $last = sanitize_text_field($row['last_name'] ?? '');
        if (!$name) { $name = trim($first . ' ' . $last); }
        if (!$name) { return false; }
        if (!$first && !$last) { $parts = preg_split('/\s+/', $name); $first = array_shift($parts); $last = implode(' ', $parts); }
        $id = wp_insert_post(['post_type'=>'pa_speaker','post_title'=>$name,'post_content'=>wp_kses_post($row['bio'] ?? ''),'post_status'=>$status], true);
        if (is_wp_error($id)) { return false; }
        update_post_meta($id, '_pa_first_name', $first);
        update_post_meta($id, '_pa_last_name', $last);
        update_post_meta($id, '_pa_speaker_role_title', sanitize_text_field($row['role_title'] ?? $row['role'] ?? ''));
        update_post_meta($id, '_pa_speaker_company', sanitize_text_field($row['company'] ?? ''));
        update_post_meta($id, '_pa_speaker_credentials', sanitize_text_field($row['credentials'] ?? ''));
        update_post_meta($id, '_pa_speaker_website', esc_url_raw($row['website'] ?? ''));
        update_post_meta($id, '_pa_speaker_linkedin', esc_url_raw($row['linkedin'] ?? ''));
        $program_id = $this->find_program_id_by_import_value($row['program'] ?? '');
        if ($program_id) { update_post_meta($id, '_pa_speaker_style_program_id', $program_id); }
        $image_id = $this->import_image_to_media($row['headshot_image'] ?? $row['image'] ?? '', $images);
        if ($image_id) { update_post_meta($id, '_pa_speaker_image_id', $image_id); }
        return true;
    }

    private function import_sponsor_row($row, $status, $images = []) {
        $company = sanitize_text_field($row['company_name'] ?? $row['company'] ?? '');
        if (!$company) { return false; }
        $id = wp_insert_post(['post_type'=>'pa_sponsor','post_title'=>$company,'post_content'=>wp_kses_post($row['bio'] ?? ''),'post_status'=>$status], true);
        if (is_wp_error($id)) { return false; }
        update_post_meta($id, '_pa_sponsor_website', esc_url_raw($row['sponsor_website'] ?? $row['website'] ?? ''));
        $logo_id = $this->import_image_to_media($row['logo_image'] ?? $row['logo'] ?? '', $images);
        if ($logo_id) { update_post_meta($id, '_pa_sponsor_logo_id', $logo_id); }
        $program_ids = [];
        foreach ($this->split_import_list($row['programs'] ?? $row['program'] ?? '') as $program_value) {
            $program_id = $this->find_program_id_by_import_value($program_value);
            if ($program_id) { $program_ids[] = $program_id; }
        }
        $program_ids = array_values(array_unique($program_ids));
        update_post_meta($id, '_pa_sponsor_program_ids', $program_ids);
        update_post_meta($id, '_pa_sponsor_program_id', $program_ids ? $program_ids[0] : 0);
        $level_map = [];
        $all_levels = [];
        $raw_levels = (string)($row['sponsor_levels'] ?? $row['levels'] ?? '');
        foreach ($this->split_import_list($raw_levels) as $chunk) {
            $program_name = ''; $level = $chunk;
            if (strpos($chunk, ':') !== false) { [$program_name, $level] = array_map('trim', explode(':', $chunk, 2)); }
            $level = sanitize_text_field($level);
            if ($level === '') { continue; }
            if ($program_name) { $pid = $this->find_program_id_by_import_value($program_name); }
            else { $pid = $program_ids[0] ?? 0; }
            if ($pid) { $level_map[(string)$pid][] = $level; }
            if (!in_array($level, $all_levels, true)) { $all_levels[] = $level; }
        }
        update_post_meta($id, '_pa_sponsor_program_levels', $level_map);
        update_post_meta($id, '_pa_sponsor_levels', $all_levels);
        return true;
    }

    private function import_event_row($row, $status, $images = []) {
        $title = sanitize_text_field($row['event_title'] ?? $row['title'] ?? '');
        if (!$title) { return false; }
        $program_id = $this->find_program_id_by_import_value($row['program'] ?? '');
        if (!$program_id) { return false; }
        $id = wp_insert_post(['post_type'=>'pa_event','post_title'=>$title,'post_content'=>wp_kses_post($row['description'] ?? $row['event_description'] ?? ''),'post_status'=>$status], true);
        if (is_wp_error($id)) { return false; }
        update_post_meta($id, '_pa_program_id', $program_id);
        update_post_meta($id, '_pa_event_category', sanitize_text_field($row['category'] ?? ''));
        update_post_meta($id, '_pa_event_date', sanitize_text_field($row['date'] ?? $row['event_date'] ?? ''));
        update_post_meta($id, '_pa_event_time', $this->normalize_import_time($row['start_time'] ?? $row['event_time'] ?? $row['time'] ?? ''));
        update_post_meta($id, '_pa_event_end_time', $this->normalize_import_time($row['end_time'] ?? $row['event_end_time'] ?? ''));
        update_post_meta($id, '_pa_event_location', sanitize_text_field($row['location'] ?? ''));
        update_post_meta($id, '_pa_event_location_link', esc_url_raw($row['location_link'] ?? ''));
        $header_image_id = $this->import_image_to_media($row['header_image'] ?? $row['event_header_image'] ?? $row['event_image'] ?? '', $images);
        if ($header_image_id) { update_post_meta($id, '_pa_event_image_id', $header_image_id); }
        $speaker_ids = [];
        foreach ($this->split_import_list($row['speakers'] ?? $row['speaker_names'] ?? $row['speaker'] ?? '') as $speaker_name) { $sid = $this->find_pa_post_by_import_value($speaker_name, 'pa_speaker'); if ($sid) { $speaker_ids[] = $sid; } }
        update_post_meta($id, '_pa_speaker_ids', array_values(array_unique($speaker_ids)));
        $sponsor_ids = [];
        foreach ($this->split_import_list($row['sponsors'] ?? $row['sponsor_names'] ?? $row['sponsor'] ?? '') as $sponsor_name) { $sid = $this->find_pa_post_by_import_value($sponsor_name, 'pa_sponsor'); if ($sid) { $sponsor_ids[] = $sid; } }
        update_post_meta($id, '_pa_sponsor_ids', array_values(array_unique($sponsor_ids)));
        update_post_meta($id, '_pa_event_invite_only', !empty($row['invite_only']) && in_array(strtolower($row['invite_only']), ['1','yes','true'], true) ? '1' : '0');
        return true;
    }


    private function sanitize_settings($raw) {
        $s = [];
        foreach (['header_bg','content_bg','header_color','content_color','image_border_color','directory_image_border_color'] as $k) { $s[$k] = sanitize_hex_color($raw[$k] ?? '') ?: ''; }
        $s['image_shape'] = in_array(($raw['image_shape'] ?? ''), ['','square','circle'], true) ? $raw['image_shape'] : '';
        $s['image_border_width'] = isset($raw['image_border_width']) && $raw['image_border_width'] !== '' ? absint($raw['image_border_width']) : 0;
        $s['directory_image_shape'] = in_array(($raw['directory_image_shape'] ?? ''), ['','square','circle'], true) ? $raw['directory_image_shape'] : '';
        $s['directory_image_border_width'] = isset($raw['directory_image_border_width']) && $raw['directory_image_border_width'] !== '' ? absint($raw['directory_image_border_width']) : 0;
        foreach (['header_border','content_border'] as $key) {
            $v = (array)($raw[$key] ?? []);
            $lock_radius = !empty($v['lock_radius']) ? 1 : 0;
            $lock_width = !empty($v['lock_width']) ? 1 : 0;
            $s[$key] = [
                'lock_radius' => $lock_radius,
                'lock_width' => $lock_width,
                'color' => sanitize_hex_color($v['color'] ?? '') ?: '',
            ];

            $radius_values = [];
            foreach (['tl','tr','br','bl'] as $c) {
                $raw_value = $v['radius_'.$c] ?? '';
                $radius_values[$c] = ($raw_value !== '') ? absint($raw_value) : 0;
            }
            if ($lock_radius) {
                $locked_radius = 0;
                foreach (['tl','tr','br','bl'] as $c) {
                    if (isset($radius_values[$c])) { $locked_radius = $radius_values[$c]; break; }
                }
                foreach (['tl','tr','br','bl'] as $c) { $radius_values[$c] = $locked_radius; }
            }
            foreach (['tl','tr','br','bl'] as $c) { $s[$key]['radius_'.$c] = $radius_values[$c]; }

            $width_values = [];
            foreach (['top','right','bottom','left'] as $c) {
                $raw_value = $v['width_'.$c] ?? '';
                $width_values[$c] = ($raw_value !== '') ? absint($raw_value) : 0;
            }
            if ($lock_width) {
                $locked_width = 0;
                foreach (['top','right','bottom','left'] as $c) {
                    if (isset($width_values[$c])) { $locked_width = $width_values[$c]; break; }
                }
                foreach (['top','right','bottom','left'] as $c) { $width_values[$c] = $locked_width; }
            }
            foreach (['top','right','bottom','left'] as $c) { $s[$key]['width_'.$c] = $width_values[$c]; }
        }
        return $s;
    }

    public function duplicate_item() {
        $id = absint($_GET['id'] ?? 0);
        check_admin_referer('pa_duplicate_item_' . $id);
        $post = get_post($id);
        if (!$post || !in_array($post->post_type, ['pa_event','pa_speaker','pa_sponsor'], true) || !current_user_can('edit_post', $id)) {
            wp_die(esc_html__('You are not allowed to duplicate this item.', 'program-agenda'));
        }
        $new_id = wp_insert_post([
            'post_type' => $post->post_type,
            'post_title' => $post->post_title . ' Copy',
            'post_content' => $post->post_content,
            'post_status' => 'draft',
            'post_author' => get_current_user_id(),
        ], true);
        $new_id = $this->ensure_saved_post_id($new_id);
        $meta = get_post_meta($id);
        foreach ($meta as $key => $values) {
            foreach ((array)$values as $value) { add_post_meta($new_id, $key, maybe_unserialize($value)); }
        }
        $page = $post->post_type === 'pa_event' ? 'program-edit-event' : ($post->post_type === 'pa_speaker' ? 'program-edit-speaker' : 'program-edit-sponsor');
        wp_safe_redirect(admin_url('admin.php?page=' . $page . '&id=' . $new_id . '&duplicated=1')); exit;
    }

    public function bulk_items() {
        $post_type = sanitize_key($_POST['post_type'] ?? '');
        if (!in_array($post_type, ['pa_event','pa_sponsor'], true)) {
            wp_die(esc_html__('Unsupported bulk action.', 'program-agenda'));
        }
        check_admin_referer('pa_bulk_items_' . $post_type);
        $ids = array_values(array_filter(array_map('absint', (array)($_POST['item_ids'] ?? []))));
        $bulk_action = sanitize_key($_POST['bulk_action'] ?? '');
        $program_id = absint($_POST['bulk_program_id'] ?? 0);
        $level = sanitize_text_field($_POST['bulk_sponsor_level'] ?? '');
        $redirect_page = $post_type === 'pa_event' ? 'program-events' : 'program-sponsors';
        $redirect = admin_url('admin.php?page=' . $redirect_page);

        if (!$ids || ($bulk_action && !in_array($bulk_action, ['draft','delete'], true))) {
            wp_safe_redirect(add_query_arg('bulk_error', '1', $redirect)); exit;
        }
        if (!$bulk_action && !$program_id) {
            wp_safe_redirect(add_query_arg('bulk_error', '1', $redirect)); exit;
        }
        if ($level && (!$program_id || $post_type !== 'pa_sponsor')) {
            wp_safe_redirect(add_query_arg('bulk_error', 'program', $redirect)); exit;
        }
        if ($program_id && get_post_type($program_id) !== 'pa_program') {
            wp_safe_redirect(add_query_arg('bulk_error', 'program', $redirect)); exit;
        }

        if ($bulk_action === 'delete') {
            foreach ($ids as $id) {
                $post = get_post($id);
                if ($post && $post->post_type === $post_type && current_user_can('delete_post', $id)) {
                    wp_delete_post($id, true);
                }
            }
            wp_safe_redirect(add_query_arg('bulk_deleted', count($ids), $redirect)); exit;
        }

        $updated = 0;
        foreach ($ids as $id) {
            $post = get_post($id);
            if (!$post || $post->post_type !== $post_type || !current_user_can('edit_post', $id)) { continue; }

            if ($bulk_action === 'draft') {
                $result = wp_update_post(['ID' => $id, 'post_status' => 'draft'], true);
                if (is_wp_error($result)) { continue; }
            }

            if ($program_id) {
                if ($post_type === 'pa_event') {
                    update_post_meta($id, '_pa_program_id', $program_id);
                } else {
                    $program_ids = $this->sponsor_program_ids($id);
                    if (!in_array($program_id, $program_ids, true)) { $program_ids[] = $program_id; }
                    update_post_meta($id, '_pa_sponsor_program_ids', array_values(array_unique(array_map('absint', $program_ids))));
                    if (!absint(get_post_meta($id, '_pa_sponsor_program_id', true))) { update_post_meta($id, '_pa_sponsor_program_id', $program_id); }
                    if ($level !== '') {
                        $program_levels = get_post_meta($id, '_pa_sponsor_program_levels', true);
                        if (!is_array($program_levels)) { $program_levels = []; }
                        $program_key = (string) $program_id;
                        $current = isset($program_levels[$program_key]) && is_array($program_levels[$program_key]) ? $program_levels[$program_key] : [];
                        if (!$current && isset($program_levels[$program_id]) && is_array($program_levels[$program_id])) { $current = $program_levels[$program_id]; }
                        $current[] = $level;
                        $program_levels[$program_key] = array_values(array_filter(array_unique(array_map('sanitize_text_field', $current))));
                        if (isset($program_levels[$program_id]) && $program_id !== $program_key) { unset($program_levels[$program_id]); }
                        update_post_meta($id, '_pa_sponsor_program_levels', $program_levels);

                        $legacy_levels = get_post_meta($id, '_pa_sponsor_levels', true);
                        if (!is_array($legacy_levels)) { $legacy_levels = []; }
                        $legacy_levels[] = $level;
                        update_post_meta($id, '_pa_sponsor_levels', array_values(array_filter(array_unique(array_map('sanitize_text_field', $legacy_levels)))));
                    }
                }
            }
            $updated++;
        }

        if ($bulk_action === 'draft') {
            wp_safe_redirect(add_query_arg('bulk_drafted', $updated, $redirect)); exit;
        }
        wp_safe_redirect(add_query_arg('bulk_updated', $updated, $redirect)); exit;
    }

    public function delete_item() {
        $id = absint($_GET['id'] ?? 0);
        check_admin_referer('pa_delete_item_' . $id);
        $post = get_post($id);
        if (!$post || !in_array($post->post_type, ['pa_program','pa_event','pa_speaker','pa_sponsor'], true) || !current_user_can('delete_post', $id)) {
            wp_die(esc_html__('You are not allowed to delete this item.', 'program-agenda'));
        }
        wp_delete_post($id, true);
        wp_safe_redirect(add_query_arg('deleted', '1', wp_get_referer() ?: admin_url('admin.php?page=program-main'))); exit;
    }

    private function program_shortcode_id($program_id) {
        $post = get_post($program_id);
        if (!$post || $post->post_type !== 'pa_program') { return ''; }
        $slug = $post->post_name ?: sanitize_title($post->post_title);
        return $slug ?: (string) absint($program_id);
    }

    private function resolve_program_shortcode_id($value) {
        $value = trim((string)$value);
        if ($value === '') { return 0; }
        if (ctype_digit($value)) { return absint($value); }

        $slug = sanitize_title($value);
        $program = get_page_by_path($slug, OBJECT, 'pa_program');
        if ($program && $program->post_type === 'pa_program') { return absint($program->ID); }

        $matches = get_posts([
            'post_type' => 'pa_program',
            'post_status' => ['publish','draft'],
            'title' => $value,
            'numberposts' => 1,
            'fields' => 'ids',
        ]);
        return !empty($matches[0]) ? absint($matches[0]) : 0;
    }

    private function format_program_date_part($date, $format = 'full') {
        if (!$date) { return ''; }
        $ts = strtotime($date);
        if (!$ts) { return $date; }
        if ($format === 'numeric') { return date_i18n('n/j', $ts); }
        if ($format === 'abbrev') { return date_i18n('M. j', $ts); }
        return date_i18n('F j', $ts);
    }

    private function program_dates_label($start, $end, $additional = []) {
        $parts = [];
        if ($start || $end) {
            $parts[] = trim($this->format_program_date_part($start, 'full') . (($start && $end) ? ' – ' : '') . $this->format_program_date_part($end, 'full'));
        }
        foreach ((array)$additional as $range) {
            $a = is_array($range) ? ($range['start'] ?? '') : '';
            $b = is_array($range) ? ($range['end'] ?? '') : '';
            if ($a || $b) { $parts[] = trim($this->format_program_date_part($a, 'full') . (($a && $b) ? ' – ' : '') . $this->format_program_date_part($b, 'full')); }
        }
        return implode(', ', array_filter($parts));
    }

    private function format_agenda_date($date, $display = 'numeric') {
        if (!$date) { return ''; }
        $ts = strtotime($date);
        if (!$ts) { return $date; }
        if ($display === 'abbrev' || $display === 'full') { return date_i18n('M. j', $ts); }
        return date_i18n('n/j', $ts);
    }

    private function agenda_day_key($date) {
        $date = is_string($date) ? trim($date) : '';
        if ($date === '') { return 'unscheduled'; }

        $ts = strtotime($date);
        if ($ts) { return date_i18n('Y-m-d', $ts); }

        return sanitize_title($date);
    }

    private function agenda_event_sort_value($event) {
        $date = get_post_meta($event->ID, '_pa_event_date', true);
        $time = get_post_meta($event->ID, '_pa_event_start_time', true);
        if (!$time) { $time = get_post_meta($event->ID, '_pa_event_time', true); }
        $raw = trim(($date ?: '') . ' ' . ($time ?: ''));
        $ts = $raw ? strtotime($raw) : false;
        if ($ts) { return $ts; }
        $date_ts = $date ? strtotime($date) : false;
        return $date_ts ?: PHP_INT_MAX;
    }

    public function shortcode_program_agenda($atts) {
        $atts = shortcode_atts(['id'=>''], $atts, 'program_agenda');
        $program_id = $this->resolve_program_shortcode_id($atts['id']);
        $program = get_post($program_id);
        if (!$program || $program->post_type !== 'pa_program') { return ''; }
        $events = get_posts(['post_type'=>'pa_event','post_status'=>'publish','numberposts'=>-1,'meta_key'=>'_pa_event_date','orderby'=>'meta_value','order'=>'ASC','meta_query'=>[['key'=>'_pa_program_id','value'=>$program_id,'compare'=>'=']]]);
        $agenda = get_post_meta($program_id, '_pa_agenda_settings', true); if (!is_array($agenda)) { $agenda = []; }
        $show_desc = ($agenda['show_descriptions'] ?? get_post_meta($program_id, '_pa_show_event_descriptions', true) ?: 'hide') !== 'hide';
        $display_mode = ($agenda['display_mode'] ?? 'tabs') === 'stacked' ? 'stacked' : 'tabs';
        $date_display = $agenda['date_display'] ?? 'numeric';
        if (!in_array($date_display, ['numeric','abbrev'], true)) { $date_display = 'numeric'; }
        $card_size = $this->normalize_agenda_card_size($agenda['card_size'] ?? 'full');
        $agenda_style = $this->agenda_item_style($agenda);
        $cats = get_post_meta($program_id, '_pa_categories', true); if (!is_array($cats)) { $cats = []; }
        $cat_map = []; foreach ($cats as $c) { $cat_map[$c['name']] = $c; }
        ob_start();
        $schedule_style = '';
        $tab_style = '';
        $tab_bg_color = $agenda['tab_background_color'] ?? ($agenda['background'] ?? '');
        if (!empty($tab_bg_color)) { $tab_style .= '--pa-agenda-tab-bg:' . esc_attr($tab_bg_color) . ';'; }
        if (!empty($agenda['tab_border_color'])) { $tab_style .= '--pa-agenda-tab-border-color:' . esc_attr($agenda['tab_border_color']) . ';'; }
        $tab_border = isset($agenda['tab_border']) && is_array($agenda['tab_border']) ? $agenda['tab_border'] : [];
        if (!$tab_border && isset($agenda['tab_border_width'])) {
            $legacy_width = absint($agenda['tab_border_width']);
            $legacy_radius = isset($agenda['tab_border_radius']) ? absint($agenda['tab_border_radius']) : 999;
            $tab_border = [
                'width_top' => $legacy_width,
                'width_right' => $legacy_width,
                'width_bottom' => $legacy_width,
                'width_left' => $legacy_width,
                'radius_tl' => $legacy_radius,
                'radius_tr' => $legacy_radius,
                'radius_br' => $legacy_radius,
                'radius_bl' => $legacy_radius,
            ];
        }
        $tab_style .= '--pa-agenda-tab-border-width-top:' . absint($tab_border['width_top'] ?? 1) . 'px;';
        $tab_style .= '--pa-agenda-tab-border-width-right:' . absint($tab_border['width_right'] ?? 1) . 'px;';
        $tab_style .= '--pa-agenda-tab-border-width-bottom:' . absint($tab_border['width_bottom'] ?? 1) . 'px;';
        $tab_style .= '--pa-agenda-tab-border-width-left:' . absint($tab_border['width_left'] ?? 1) . 'px;';
        $tab_style .= '--pa-agenda-tab-radius-tl:' . absint($tab_border['radius_tl'] ?? 999) . 'px;';
        $tab_style .= '--pa-agenda-tab-radius-tr:' . absint($tab_border['radius_tr'] ?? 999) . 'px;';
        $tab_style .= '--pa-agenda-tab-radius-br:' . absint($tab_border['radius_br'] ?? 999) . 'px;';
        $tab_style .= '--pa-agenda-tab-radius-bl:' . absint($tab_border['radius_bl'] ?? 999) . 'px;';
        $tab_text_color = $agenda['title_color'] ?? ($agenda['color'] ?? '');
        if (!empty($tab_text_color)) { $tab_style .= '--pa-agenda-tab-color:' . esc_attr($tab_text_color) . ';'; }

        $tab_has_any_border_width = (absint($tab_border['width_top'] ?? 1) || absint($tab_border['width_right'] ?? 1) || absint($tab_border['width_bottom'] ?? 1) || absint($tab_border['width_left'] ?? 1));
        $tab_button_style = $tab_style;
        if (!$tab_has_any_border_width) {
            $tab_button_style .= 'border:0!important;border-width:0!important;border-style:none!important;';
        } else {
            $tab_button_style .= 'border-style:solid!important;';
            $tab_button_style .= 'border-color:' . esc_attr($agenda['tab_border_color'] ?: 'transparent') . '!important;';
            $tab_button_style .= 'border-top-width:' . absint($tab_border['width_top'] ?? 1) . 'px!important;';
            $tab_button_style .= 'border-right-width:' . absint($tab_border['width_right'] ?? 1) . 'px!important;';
            $tab_button_style .= 'border-bottom-width:' . absint($tab_border['width_bottom'] ?? 1) . 'px!important;';
            $tab_button_style .= 'border-left-width:' . absint($tab_border['width_left'] ?? 1) . 'px!important;';
        }
        $tab_button_style .= 'border-top-left-radius:' . absint($tab_border['radius_tl'] ?? 999) . 'px!important;';
        $tab_button_style .= 'border-top-right-radius:' . absint($tab_border['radius_tr'] ?? 999) . 'px!important;';
        $tab_button_style .= 'border-bottom-right-radius:' . absint($tab_border['radius_br'] ?? 999) . 'px!important;';
        $tab_button_style .= 'border-bottom-left-radius:' . absint($tab_border['radius_bl'] ?? 999) . 'px!important;';
        $tab_button_style .= 'box-shadow:none!important;outline:0!important;';

        echo '<section class="pa-schedule" style="' . esc_attr($schedule_style) . '">';
        echo '<div class="pa-agenda-content">';
        $render_agenda_event = function($event) use ($program_id) {
            echo $this->agenda_event_card($event, $program_id);
        };

        if ($events && $display_mode === 'tabs') {
            usort($events, function($a, $b) {
                $cmp = $this->agenda_event_sort_value($a) <=> $this->agenda_event_sort_value($b);
                if ($cmp !== 0) { return $cmp; }
                return strcasecmp($a->post_title, $b->post_title);
            });
            $groups = [];
            foreach ($events as $event) {
                $event_date_raw = get_post_meta($event->ID, '_pa_event_date', true);
                $key = $this->agenda_day_key($event_date_raw);
                if (!isset($groups[$key])) { $groups[$key] = []; }
                $groups[$key][] = $event;
            }
            $uid = 'pa-day-tabs-' . absint($program_id);
            echo '<div class="pa-agenda-day-tabs' . ($tab_has_any_border_width ? '' : ' pa-agenda-tabs-no-border') . '" style="' . esc_attr($tab_style) . '" data-pa-day-tabs="' . esc_attr($uid) . '"><div class="pa-agenda-day-tab-list" role="tablist">';
            $i = 0;
            foreach ($groups as $date_key => $day_events) {
                $label = $date_key === 'unscheduled' ? 'Unscheduled' : $this->format_agenda_date($date_key, $date_display);
                echo '<button type="button" class="pa-agenda-day-tab' . ($i === 0 ? ' active' : '') . '" style="' . esc_attr($tab_button_style) . '" role="tab" aria-selected="' . ($i === 0 ? 'true' : 'false') . '" data-pa-day-target="' . esc_attr($uid . '-' . $i) . '">' . esc_html($label) . '</button>';
                $i++;
            }
            echo '</div><div class="pa-agenda-day-panels">';
            $i = 0;
            foreach ($groups as $date_key => $day_events) {
                echo '<div class="pa-agenda-day-panel' . ($i === 0 ? ' active' : '') . '" id="' . esc_attr($uid . '-' . $i) . '"><div class="pa-event-list">';
                foreach ($day_events as $event) { $render_agenda_event($event); }
                echo '</div></div>';
                $i++;
            }
            echo '</div></div>';
            echo '<script>(function(){var root=document.querySelector("[data-pa-day-tabs=\"' . esc_js($uid) . '\"]");if(!root||root.dataset.paTabsReady)return;root.dataset.paTabsReady="1";root.addEventListener("click",function(e){var btn=e.target.closest?e.target.closest(".pa-agenda-day-tab"):null;if(!btn||!root.contains(btn))return;var target=btn.getAttribute("data-pa-day-target");root.querySelectorAll(".pa-agenda-day-tab").forEach(function(b){b.classList.remove("active");b.setAttribute("aria-selected","false")});root.querySelectorAll(".pa-agenda-day-panel").forEach(function(p){p.classList.toggle("active",p.id===target)});btn.classList.add("active");btn.setAttribute("aria-selected","true");});})();</script>';
        } else {
            echo '<div class="pa-event-list">';
            foreach ($events as $event) { $render_agenda_event($event); }
            echo '</div>';
        }
        if (!$events) { echo '<p>No events have been added yet.</p>'; }
        echo '</div>';
        echo '</section>';
        return ob_get_clean();
    }


    public function shortcode_program_speakers($atts) {
        $atts = shortcode_atts(['id'=>''], $atts, 'program_speakers');
        $program_id = $this->resolve_program_shortcode_id($atts['id']);
        $program = get_post($program_id);
        if (!$program || $program->post_type !== 'pa_program') { return ''; }

        $events = get_posts([
            'post_type'=>'pa_event',
            'post_status'=>'publish',
            'numberposts'=>-1,
            'meta_key'=>'_pa_event_date',
            'orderby'=>'meta_value',
            'order'=>'ASC',
            'meta_query'=>[['key'=>'_pa_program_id','value'=>$program_id,'compare'=>'=']],
        ]);

        $speaker_ids = [];
        foreach ($events as $event) {
            $ids = get_post_meta($event->ID, '_pa_speaker_ids', true);
            if (!is_array($ids)) { continue; }
            foreach ($ids as $speaker_id) {
                $speaker_id = absint($speaker_id);
                if ($speaker_id && get_post_type($speaker_id) === 'pa_speaker' && !in_array($speaker_id, $speaker_ids, true)) {
                    $speaker_ids[] = $speaker_id;
                }
            }
        }

        if (!$speaker_ids) { return '<p class="pa-program-speakers-empty">No speakers have been added yet.</p>'; }

        $speakers = get_posts([
            'post_type'=>'pa_speaker',
            'post_status'=>'publish',
            'numberposts'=>-1,
            'post__in'=>$speaker_ids,
            'orderby'=>'title',
            'order'=>'ASC',
        ]);

        usort($speakers, function($a, $b) {
            $a_last = trim((string)get_post_meta($a->ID, '_pa_last_name', true));
            $b_last = trim((string)get_post_meta($b->ID, '_pa_last_name', true));
            if ($a_last === '') {
                $a_parts = preg_split('/\s+/', trim($a->post_title));
                $a_last = $a_parts ? end($a_parts) : $a->post_title;
            }
            if ($b_last === '') {
                $b_parts = preg_split('/\s+/', trim($b->post_title));
                $b_last = $b_parts ? end($b_parts) : $b->post_title;
            }
            $last_compare = strcasecmp($a_last, $b_last);
            if ($last_compare !== 0) { return $last_compare; }
            return strcasecmp($a->post_title, $b->post_title);
        });

        $speaker_page_settings = $this->speaker_page_settings_for_program($program_id);
        if (!is_array($speaker_page_settings)) { $speaker_page_settings = []; }
        $directory_image_shape = $speaker_page_settings['directory_image_shape'] ?? '';
        $directory_image_radius = $directory_image_shape === 'circle' ? '50%' : ($directory_image_shape === 'square' ? '0' : '');
        $directory_image_border_width = isset($speaker_page_settings['directory_image_border_width']) && $speaker_page_settings['directory_image_border_width'] !== '' ? absint($speaker_page_settings['directory_image_border_width']) : 0;
        $directory_image_border_color = !empty($speaker_page_settings['directory_image_border_color']) ? sanitize_hex_color($speaker_page_settings['directory_image_border_color']) : '';
       $directory_image_frame_style = 'box-sizing:border-box!important;position:relative!important;overflow:hidden!important;line-height:0!important;border:0!important;';
        $directory_image_inner_style = 'box-sizing:border-box!important;display:block!important;width:100%!important;height:100%!important;max-width:100%!important;max-height:100%!important;object-fit:cover!important;object-position:center center!important;border:0!important;margin:0!important;padding:0!important;';

    if ($directory_image_radius !== '') {
        $directory_image_frame_style .= 'border-radius:' . esc_attr($directory_image_radius) . '!important;';
        $directory_image_inner_style .= 'border-radius:' . esc_attr($directory_image_radius) . '!important;';
}

    if ($directory_image_border_width > 0) {
    $directory_image_frame_style .= 'padding:' . $directory_image_border_width . 'px!important;background:' . esc_attr($directory_image_border_color ?: 'currentColor') . '!important;';
}   else {
    $directory_image_frame_style .= 'padding:0!important;background:transparent!important;';
}

        ob_start();
        echo '<section class="pa-program-speakers" aria-label="Program speakers">';
        echo '<div class="pa-program-speaker-grid">';
        foreach ($speakers as $speaker) {
            $image_id = absint(get_post_meta($speaker->ID, '_pa_speaker_image_id', true));
            $role = get_post_meta($speaker->ID, '_pa_speaker_role_title', true);
            $company = get_post_meta($speaker->ID, '_pa_speaker_company', true);
            $permalink = get_permalink($speaker);
            echo '<article class="pa-program-speaker-card">';
            echo '<a class="pa-program-speaker-image-link" style="' . esc_attr($directory_image_frame_style) . '" href="' . esc_url($permalink) . '" aria-label="' . esc_attr($speaker->post_title) . '">';
            if ($image_id) {
                echo wp_get_attachment_image($image_id, 'medium', false, ['class'=>'pa-program-speaker-image', 'alt'=>esc_attr($speaker->post_title), 'style'=>$directory_image_inner_style]);
            } else {
                echo '<span class="pa-program-speaker-image pa-program-speaker-placeholder" style="' . esc_attr($directory_image_inner_style) . '" aria-hidden="true"></span>';
            }
            echo '</a>';
            echo '<h3 class="pa-program-speaker-name"><a href="' . esc_url($permalink) . '">' . esc_html($speaker->post_title) . '</a></h3>';
            if ($role) { echo '<p class="pa-program-speaker-role">' . esc_html($role) . '</p>'; }
            if ($company) { echo '<p class="pa-program-speaker-company">' . esc_html($company) . '</p>'; }
            echo '</article>';
        }
        echo '</div></section>';
        return ob_get_clean();
    }

    public function shortcode_program_pdf($atts) {
        $atts = shortcode_atts(['id'=>'', 'label'=>'Download as PDF'], $atts, 'program_pdf');
        $program_id = $this->resolve_program_shortcode_id($atts['id']);
        $program = get_post($program_id);
        if (!$program || $program->post_type !== 'pa_program') { return ''; }
        $url = add_query_arg('pa_program_pdf', $program_id, home_url('/'));
        $label = trim(wp_strip_all_tags((string)($atts['label'] ?? '')));
        if ($label === '') { $label = 'Download as PDF'; }
        return '<p class="pa-program-pdf-download"><a class="pa-program-pdf-link" href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($label) . '</a></p>';
    }

    public function maybe_render_program_pdf_page() {
        if (empty($_GET['pa_program_pdf'])) { return; }
        $program_id = absint($_GET['pa_program_pdf']);
        $program = get_post($program_id);
        if (!$program || $program->post_type !== 'pa_program') {
            status_header(404);
            wp_die(esc_html__('Program not found.', 'program-agenda'));
        }

        $events = get_posts([
            'post_type' => 'pa_event',
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_query' => [['key'=>'_pa_program_id','value'=>$program_id,'compare'=>'=']],
        ]);
        usort($events, function($a, $b) {
            $cmp = $this->agenda_event_sort_value($a) <=> $this->agenda_event_sort_value($b);
            if ($cmp !== 0) { return $cmp; }
            return strcasecmp($a->post_title, $b->post_title);
        });

        $program_url = get_post_meta($program_id, '_pa_back_to_link', true);
        if (!$program_url) { $program_url = home_url('/'); }
        $generated = date_i18n(get_option('date_format', 'F j, Y'));
        $logo_url = '';
        $logo_id = function_exists('get_theme_mod') ? absint(get_theme_mod('custom_logo')) : 0;
        if ($logo_id) { $logo_url = wp_get_attachment_image_url($logo_id, 'full'); }
        $site_name = get_bloginfo('name');

        nocache_headers();
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
        echo '<!doctype html><html ' . get_language_attributes() . '><head><meta charset="' . esc_attr(get_bloginfo('charset')) . '"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . esc_html($program->post_title) . ' Program PDF</title>';
        echo '<style>
            :root{color-scheme:light;}
            body{margin:0;background:#f6f7f7;color:#1d2327;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;line-height:1.45;}
            .pa-program-print-page{max-width:900px;margin:0 auto;padding:48px 36px;background:#fff;min-height:100vh;box-sizing:border-box;}
            .pa-program-print-actions{position:sticky;top:0;z-index:2;margin:-48px -36px 32px;padding:14px 36px;background:#f6f7f7;border-bottom:1px solid #dcdcde;text-align:right;}
            .pa-program-print-actions button{appearance:none;border:1px solid #1d2327;background:#1d2327;color:#fff;border-radius:4px;padding:8px 14px;cursor:pointer;}
            .pa-program-print-logo{max-width:220px;max-height:90px;width:auto;height:auto;object-fit:contain;margin:0 0 28px;display:block;}
            .pa-program-print-site-name{margin:0 0 22px;font-size:14px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#646970;}
            .pa-program-print-title{margin:0 0 10px;font-size:34px;line-height:1.08;}
            .pa-program-print-note{margin:0 0 34px;color:#50575e;font-size:14px;border-bottom:1px solid #dcdcde;padding-bottom:22px;}
            .pa-program-print-note a{color:inherit;}
            .pa-program-print-day{margin:34px 0 14px;font-size:20px;line-height:1.2;border-bottom:2px solid #1d2327;padding-bottom:8px;}
            .pa-program-print-event{break-inside:avoid;page-break-inside:avoid;margin:0 0 22px;padding:0 0 22px;border-bottom:1px solid #dcdcde;}
            .pa-program-print-event:last-child{border-bottom:0;}
            .pa-program-print-time{margin:0 0 4px;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#646970;}
            .pa-program-print-event-title{margin:0 0 8px;font-size:18px;line-height:1.2;}
            .pa-program-print-meta{margin:0 0 10px;font-size:13px;color:#50575e;}
            .pa-program-print-description{margin:10px 0 0;font-size:14px;}
            .pa-program-print-description > :first-child{margin-top:0;}
            .pa-program-print-description > :last-child{margin-bottom:0;}
            .pa-program-print-speakers{margin:12px 0 0;}
            .pa-program-print-speakers h3{margin:0 0 6px;font-size:13px;text-transform:uppercase;letter-spacing:.04em;}
            .pa-program-print-speakers ul{margin:0;padding-left:18px;}
            .pa-program-print-speakers li{margin:0 0 5px;}
            .pa-program-print-speaker-detail{color:#50575e;font-size:13px;}
            .pa-program-print-empty{margin:24px 0;color:#646970;}
            @media print{body{background:#fff}.pa-program-print-page{max-width:none;padding:0;background:#fff}.pa-program-print-actions{display:none}.pa-program-print-note{font-size:11px}.pa-program-print-title{font-size:28px}.pa-program-print-day{break-after:avoid;page-break-after:avoid}.pa-program-print-event{break-inside:avoid;page-break-inside:avoid}}
        </style>';
        echo '</head><body><main class="pa-program-print-page">';
        echo '<div class="pa-program-print-actions"><button type="button" onclick="window.print()">Print / Save as PDF</button></div>';
        if ($logo_url) { echo '<img class="pa-program-print-logo" src="' . esc_url($logo_url) . '" alt="' . esc_attr($site_name) . '">'; }
        else { echo '<p class="pa-program-print-site-name">' . esc_html($site_name) . '</p>'; }
        echo '<h1 class="pa-program-print-title">' . esc_html($program->post_title) . '</h1>';
        echo '<p class="pa-program-print-note">Generated on ' . esc_html($generated) . ' and subject to change. Visit <a href="' . esc_url($program_url) . '">' . esc_html($program_url) . '</a> for the latest agenda.</p>';

        if (!$events) {
            echo '<p class="pa-program-print-empty">No events have been added yet.</p>';
        } else {
            $current_day = '';
            foreach ($events as $event) {
                $date = get_post_meta($event->ID, '_pa_event_date', true);
                $day_key = $date ? date_i18n('Y-m-d', strtotime($date)) : 'unscheduled';
                if ($day_key !== $current_day) {
                    $current_day = $day_key;
                    $day_label = $date && strtotime($date) ? date_i18n('F j, Y', strtotime($date)) : 'Unscheduled';
                    echo '<h2 class="pa-program-print-day">' . esc_html($day_label) . '</h2>';
                }
                $start = get_post_meta($event->ID, '_pa_event_time', true);
                $end = get_post_meta($event->ID, '_pa_event_end_time', true);
                $time = trim($start . ($end ? ' – ' . $end : ''));
                $location = get_post_meta($event->ID, '_pa_event_location', true);
                $category = get_post_meta($event->ID, '_pa_event_category', true);
                echo '<article class="pa-program-print-event">';
                if ($time) { echo '<p class="pa-program-print-time">' . esc_html($time) . '</p>'; }
                echo '<h3 class="pa-program-print-event-title">' . esc_html($event->post_title) . '</h3>';
                $meta = array_filter([$category, $location]);
                if ($meta) { echo '<p class="pa-program-print-meta">' . esc_html(implode(' • ', $meta)) . '</p>'; }
                if ($event->post_content) { echo '<div class="pa-program-print-description">' . wp_kses_post(wpautop($event->post_content)) . '</div>'; }
                $speaker_ids = get_post_meta($event->ID, '_pa_speaker_ids', true);
                if (!is_array($speaker_ids)) { $speaker_ids = []; }
                if ($speaker_ids) {
                    echo '<section class="pa-program-print-speakers"><h3>Speakers</h3><ul>';
                    foreach ($speaker_ids as $speaker_id) {
                        $speaker = get_post(absint($speaker_id));
                        if (!$speaker || $speaker->post_type !== 'pa_speaker') { continue; }
                        $credentials = get_post_meta($speaker->ID, '_pa_speaker_credentials', true);
                        $role = get_post_meta($speaker->ID, '_pa_speaker_role_title', true);
                        $company = get_post_meta($speaker->ID, '_pa_speaker_company', true);
                        $name = $speaker->post_title . ($credentials ? ', ' . $credentials : '');
                        $detail = implode(' • ', array_filter([$role, $company]));
                        echo '<li><strong>' . esc_html($name) . '</strong>' . ($detail ? '<br><span class="pa-program-print-speaker-detail">' . esc_html($detail) . '</span>' : '') . '</li>';
                    }
                    echo '</ul></section>';
                }
                echo '</article>';
            }
        }
        echo '</main><script>window.addEventListener("load",function(){setTimeout(function(){window.print();},250);});</script></body></html>';
        exit;
    }

    public function shortcode_program_sponsors($atts) {
        $atts = shortcode_atts(['id'=>''], $atts, 'program_sponsors');
        $program_id = $this->resolve_program_shortcode_id($atts['id']);
        $program = get_post($program_id);
        if (!$program || $program->post_type !== 'pa_program') { return ''; }

        $levels = get_post_meta($program_id, '_pa_sponsor_levels', true);
        if (!is_array($levels)) { $levels = []; }
        $levels = array_values(array_filter(array_map('trim', array_map('wp_strip_all_tags', $levels))));

        $all_sponsors = get_posts([
            'post_type' => 'pa_sponsor',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        $sponsors = array_values(array_filter($all_sponsors, function($sponsor) use ($program_id) {
            return in_array(absint($program_id), $this->sponsor_program_ids($sponsor->ID), true);
        }));

        $grouped = [];
        foreach ($levels as $level) { $grouped[$level] = []; }
        $unleveled = [];
        foreach ($sponsors as $sponsor) {
            $sponsor_levels = $this->sponsor_levels_for_program($sponsor->ID, $program_id);
            $matched = false;
            foreach ($levels as $level) {
                if (in_array($level, $sponsor_levels, true)) {
                    $grouped[$level][] = $sponsor;
                    $matched = true;
                }
            }
            if (!$matched) { $unleveled[] = $sponsor; }
        }
        if ($unleveled) { $grouped['Sponsors'] = $unleveled; }

        ob_start();
        echo '<section class="pa-sponsor-showcase" aria-label="Sponsors">';
        $printed = 0;
        foreach ($grouped as $level => $level_sponsors) {
            if (!$level_sponsors) { continue; }
            echo '<section class="pa-sponsor-level-group"><h3>' . esc_html($level) . '</h3><div class="pa-sponsor-logo-grid">';
            foreach ($level_sponsors as $sponsor) { echo $this->sponsor_showcase_logo($sponsor); }
            echo '</div></section>';
            $printed++;
        }
        if ($printed === 0) { echo '<p>No sponsors have been added yet.</p>'; }
        echo '</section>';
        return ob_get_clean();
    }

    private function sponsor_showcase_logo($sponsor) {
        $logo_id = absint(get_post_meta($sponsor->ID, '_pa_sponsor_logo_id', true));
        $url = get_permalink($sponsor);
        ob_start();
        echo '<a class="pa-sponsor-showcase-logo" href="' . esc_url($url) . '">';
        if ($logo_id) {
            echo wp_get_attachment_image($logo_id, 'full', false, ['class'=>'pa-sponsor-showcase-logo-img', 'alt'=>esc_attr($sponsor->post_title)]);
        } else {
            echo '<span class="pa-sponsor-showcase-name">' . esc_html($sponsor->post_title) . '</span>';
        }
        echo '</a>';
        return ob_get_clean();
    }

    private function single_sponsor($post) {
        ob_start();
        $program_ids = $this->sponsor_program_ids($post->ID);
        $program_id = $program_ids ? absint($program_ids[0]) : absint(get_post_meta($post->ID, '_pa_sponsor_program_id', true));
        $s = $this->speaker_page_settings_for_program($program_id);
        $logo_id = absint(get_post_meta($post->ID, '_pa_sponsor_logo_id', true));
        $website = get_post_meta($post->ID, '_pa_sponsor_website', true);
        $sponsor_page_id = 'pa-single-sponsor-' . absint($post->ID);
        $header_color = sanitize_hex_color($s['header_color'] ?? '');
        $content_color = sanitize_hex_color($s['content_color'] ?? '');
        $scoped_color_css = '';
        if ($header_color) {
            $scoped_color_css .= '#' . $sponsor_page_id . ' .pa-single-header,#' . $sponsor_page_id . ' .pa-single-header *{color:' . $header_color . ' !important;}';
        }
        if ($content_color) {
            $scoped_color_css .= '#' . $sponsor_page_id . ' .pa-single-content,#' . $sponsor_page_id . ' .pa-single-content *{color:' . $content_color . ' !important;}';
        }
        if ($scoped_color_css) { echo '<style>' . esc_html($scoped_color_css) . '</style>'; }

        echo '<article id="' . esc_attr($sponsor_page_id) . '" class="pa-single pa-single-sponsor">';
        echo $this->back_to_program_link($program_id);
        echo '<header class="pa-single-header pa-speaker-hero pa-sponsor-hero ' . esc_attr($this->area_class($s, 'header')) . '" style="' . esc_attr($this->inline_header_style($s)) . '">';
        if ($logo_id) {
            $logo_meta = wp_get_attachment_metadata($logo_id);
            $logo_width = is_array($logo_meta) ? absint($logo_meta['width'] ?? 0) : 0;
            $logo_height = is_array($logo_meta) ? absint($logo_meta['height'] ?? 0) : 0;
            $logo_shape_class = ($logo_width && $logo_height && abs($logo_width - $logo_height) <= 12) ? ' pa-sponsor-hero-logo-square' : ' pa-sponsor-hero-logo-wide';
            echo '<div class="pa-sponsor-hero-logo' . esc_attr($logo_shape_class) . '"><a href="' . esc_url(get_permalink($post)) . '" aria-label="' . esc_attr($post->post_title) . '">' . wp_get_attachment_image($logo_id, 'full', false, ['class'=>'pa-sponsor-hero-logo-img', 'alt'=>esc_attr($post->post_title)]) . '</a></div>';
        }
        echo '<div class="pa-speaker-hero-text pa-sponsor-hero-text"><h5 class="pa-speaker-page-label pa-sponsor-page-label">Sponsor</h5><h3 class="pa-speaker-name pa-sponsor-name">' . esc_html($post->post_title) . '</h3>';
        if ($website) { echo '<h6 class="pa-speaker-company pa-sponsor-website"><a href="' . esc_url($website) . '" target="_blank" rel="noopener">Learn more</a></h6>'; }
        echo '</div></header>';
        echo '<div class="pa-single-content ' . esc_attr($this->area_class($s, 'content')) . '" style="' . esc_attr($this->inline_content_style($s)) . '">';
        echo '<div class="pa-single-text">' . wp_kses_post(wpautop($post->post_content)) . '</div>';
        echo '</div>';
        echo '</article>';
        return ob_get_clean();
    }
    public function admin_bar_edit_link($wp_admin_bar) {
        if (!is_singular(['pa_event','pa_speaker','pa_sponsor']) || !is_admin_bar_showing()) { return; }
        $post = get_queried_object();
        if (!$post || empty($post->ID) || !current_user_can('edit_post', $post->ID)) { return; }
        $page = $post->post_type === 'pa_event' ? 'program-edit-event' : ($post->post_type === 'pa_speaker' ? 'program-edit-speaker' : 'program-edit-sponsor');
        $label = $post->post_type === 'pa_event' ? 'Edit Event' : ($post->post_type === 'pa_speaker' ? 'Edit Speaker' : 'Edit Sponsor');
        $wp_admin_bar->add_node([
            'id' => 'pa-edit-entity',
            'title' => $label,
            'href' => admin_url('admin.php?page=' . $page . '&id=' . absint($post->ID)),
        ]);

        if (!in_array($post->post_type, ['pa_event','pa_speaker'], true)) { return; }
        $program_id = $post->post_type === 'pa_event'
            ? absint(get_post_meta($post->ID, '_pa_program_id', true))
            : absint($this->speaker_primary_program_id($post->ID));
        if (!$program_id || get_post_type($program_id) !== 'pa_program' || !current_user_can('edit_post', $program_id)) { return; }
        $wp_admin_bar->add_node([
            'id' => 'pa-edit-program',
            'title' => 'Edit Program',
            'href' => admin_url('admin.php?page=program-advanced-settings&id=' . absint($program_id)),
        ]);
    }

    private function icon_char($icon) { return ['heart'=>'♥','circle'=>'●','triangle'=>'▲','square'=>'■','star'=>'★','none'=>''][$icon] ?? ''; }
    private function format_event_time_range($event_id) {
        $time = get_post_meta($event_id, '_pa_event_time', true);
        if (!$time) { return ''; }
        $start_ts = strtotime($time);
        if (!$start_ts) { return ''; }
        $time_text = date_i18n(get_option('time_format'), $start_ts);
        $end_time = get_post_meta($event_id, '_pa_event_end_time', true);
        if ($end_time) {
            $end_ts = strtotime($end_time);
            if ($end_ts && $end_ts > $start_ts) {
                $time_text .= ' – ' . date_i18n(get_option('time_format'), $end_ts);
            }
        }
        return $time_text;
    }

    private function format_event_start_time($event_id) {
        $time = get_post_meta($event_id, '_pa_event_time', true);
        if (!$time) { return ''; }
        $start_ts = strtotime($time);
        return $start_ts ? date_i18n(get_option('time_format'), $start_ts) : '';
    }

    private function format_event_when($event_id) {
        $date = get_post_meta($event_id, '_pa_event_date', true);
        if (!$date) { return ''; }
        $date_text = date_i18n(get_option('date_format'), strtotime($date));
        $time_text = $this->format_event_time_range($event_id);
        return $time_text ? trim($time_text . ' • ' . $date_text) : $date_text;
    }

    public function replace_single_content($content) {
        if (!is_singular(['pa_event','pa_speaker','pa_sponsor']) || !in_the_loop() || !is_main_query()) { return $content; }
        global $post;
        if ($post->post_type === 'pa_event') { return $this->single_event($post); }
        if ($post->post_type === 'pa_speaker') { return $this->single_speaker($post); }
        return $this->single_sponsor($post);
    }

    private function event_page_settings_for_program($program_id) {
        $program_id = absint($program_id);
        if ($program_id && metadata_exists('post', $program_id, self::META_EVENT_SETTINGS)) {
            $settings = get_post_meta($program_id, self::META_EVENT_SETTINGS, true);
            if (is_array($settings)) { return $settings; }
        }
        $settings = get_option(self::OPT_EVENT, []);
        return is_array($settings) ? $settings : [];
    }

    private function speaker_page_settings_for_program($program_id) {
        $program_id = absint($program_id);
        if ($program_id && metadata_exists('post', $program_id, self::META_SPEAKER_SETTINGS)) {
            $settings = get_post_meta($program_id, self::META_SPEAKER_SETTINGS, true);
            if (is_array($settings)) { return $settings; }
        }
        $settings = get_option(self::OPT_SPEAKER, []);
        return is_array($settings) ? $settings : [];
    }

    private function event_calendar_datetimes($event_id) {
        $event_id = absint($event_id);
        $date = get_post_meta($event_id, '_pa_event_date', true);
        if (!$date) { return [null, null]; }
        $start_time = get_post_meta($event_id, '_pa_event_time', true) ?: '09:00';
        $end_time = get_post_meta($event_id, '_pa_event_end_time', true);
        if (!$end_time) { $end_time = date('H:i', strtotime($start_time . ' +1 hour')); }
        try {
            $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(get_option('timezone_string') ?: 'UTC');
            $start = new DateTime($date . ' ' . $start_time, $timezone);
            $end = new DateTime($date . ' ' . $end_time, $timezone);
            if ($end <= $start) { $end = clone $start; $end->modify('+1 hour'); }
            return [$start, $end];
        } catch (Exception $e) {
            return [null, null];
        }
    }

    private function event_google_calendar_url($event_id) {
        $event_id = absint($event_id);
        [$start, $end] = $this->event_calendar_datetimes($event_id);
        $args = [
            'action' => 'TEMPLATE',
            'text' => get_the_title($event_id),
            'details' => wp_strip_all_tags(get_post_field('post_content', $event_id)),
            'location' => get_post_meta($event_id, '_pa_event_location', true),
        ];
        if ($start && $end) {
            $start_utc = clone $start;
            $end_utc = clone $end;
            $start_utc->setTimezone(new DateTimeZone('UTC'));
            $end_utc->setTimezone(new DateTimeZone('UTC'));
            $args['dates'] = $start_utc->format('Ymd\THis\Z') . '/' . $end_utc->format('Ymd\THis\Z');
        }
        return add_query_arg($args, 'https://calendar.google.com/calendar/render');
    }

    private function event_outlook_calendar_url($event_id) {
        $event_id = absint($event_id);
        [$start, $end] = $this->event_calendar_datetimes($event_id);
        $args = [
            'subject' => get_the_title($event_id),
            'body' => wp_strip_all_tags(get_post_field('post_content', $event_id)),
            'location' => get_post_meta($event_id, '_pa_event_location', true),
        ];
        if ($start && $end) {
            $args['startdt'] = $start->format(DateTime::ATOM);
            $args['enddt'] = $end->format(DateTime::ATOM);
        }
        return add_query_arg($args, 'https://outlook.live.com/calendar/0/deeplink/compose');
    }

    private function calendar_icon_svg() {
        return '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path fill="currentColor" d="M7 2h2v2h6V2h2v2h3.25v17.25H3.75V4H7V2Zm11.25 8.25H5.75v9h12.5v-9ZM5.75 8.25h12.5V6H5.75v2.25Z"/></svg>';
    }

    private function google_calendar_icon_svg() {
        return '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true" role="img"><path fill="currentColor" d="M21.6 12.23c0-.75-.07-1.47-.2-2.16H12v4.09h5.38a4.6 4.6 0 0 1-2 3.02v2.51h3.24c1.9-1.75 2.98-4.32 2.98-7.46Z"/><path fill="currentColor" d="M12 22c2.7 0 4.97-.9 6.62-2.31l-3.24-2.51c-.9.6-2.04.95-3.38.95-2.6 0-4.8-1.76-5.58-4.12H3.08v2.59A10 10 0 0 0 12 22Z"/><path fill="currentColor" d="M6.42 14.01A6.02 6.02 0 0 1 6.1 12c0-.7.12-1.38.32-2.01V7.4H3.08A10 10 0 0 0 2 12c0 1.61.39 3.13 1.08 4.6l3.34-2.59Z"/><path fill="currentColor" d="M12 5.87c1.47 0 2.79.51 3.83 1.5l2.87-2.87C16.96 2.88 14.7 2 12 2a10 10 0 0 0-8.92 5.4l3.34 2.59C7.2 7.63 9.4 5.87 12 5.87Z"/></svg>';
    }

    private function outlook_calendar_icon_svg() {
        return '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path fill="currentColor" d="M3 5.5 13 3v18L3 18.5v-13Zm12 1H21v11h-6v-2h4V8.5h-4v-2ZM8.35 9.25c-1.63 0-2.8 1.25-2.8 3.05 0 1.82 1.17 3.06 2.8 3.06s2.8-1.24 2.8-3.06c0-1.8-1.17-3.05-2.8-3.05Zm0 1.55c.62 0 1.05.58 1.05 1.5 0 .94-.43 1.52-1.05 1.52s-1.05-.58-1.05-1.52c0-.92.43-1.5 1.05-1.5Z"/></svg>';
    }

    private function single_event($post) {
        ob_start();
        $img = absint(get_post_meta($post->ID, '_pa_event_image_id', true));
        $date = $this->format_event_when($post->ID);
        $loc = get_post_meta($post->ID, '_pa_event_location', true);
        $link = get_post_meta($post->ID, '_pa_event_location_link', true);
        $cat = get_post_meta($post->ID, '_pa_event_category', true);
        $program_id = absint(get_post_meta($post->ID, '_pa_program_id', true));
        $cats = get_post_meta($program_id, '_pa_categories', true); if (!is_array($cats)) { $cats = []; }
        $cat_map = []; foreach ($cats as $category_item) { if (!empty($category_item['name'])) { $cat_map[$category_item['name']] = $category_item; } }
        $event_category = $cat_map[$cat] ?? ['color'=>'#000000','icon'=>'none'];
        $event_category_icon = $this->icon_char($event_category['icon'] ?? 'none');
        $s = $this->event_page_settings_for_program($program_id);
        $invite_only = get_post_meta($post->ID, '_pa_event_invite_only', true) === '1';
        $invite_warning = get_post_meta($post->ID, '_pa_event_invite_warning', true);
        $event_page_id = 'pa-single-event-' . absint($post->ID);
        $header_color = sanitize_hex_color($s['header_color'] ?? '');
        $content_color = sanitize_hex_color($s['content_color'] ?? '');
        $scoped_color_css = '';
        if ($header_color) {
            $scoped_color_css .= '#' . $event_page_id . ' .pa-single-header,#' . $event_page_id . ' .pa-single-header *{color:' . $header_color . ' !important;}';
            $scoped_color_css .= '#' . $event_page_id . ' .pa-event-single-category{color:' . $header_color . ' !important;}';
        }
        if ($content_color) {
            $scoped_color_css .= '#' . $event_page_id . ' .pa-single-content,#' . $event_page_id . ' .pa-single-content *{color:' . $content_color . ' !important;}';
        }
        if ($scoped_color_css) { echo '<style>' . esc_html($scoped_color_css) . '</style>'; }

        echo '<article id="' . esc_attr($event_page_id) . '" class="pa-single pa-single-event">';
        echo $this->back_to_program_link($program_id);
        if ($img) { echo '<div class="pa-event-header-photo" style="' . esc_attr($this->border_style_from_settings($s, 'header')) . '">' . wp_get_attachment_image($img, 'full', false, ['class'=>'pa-event-header-image']) . '</div>'; }
        echo '<header class="pa-single-header pa-event-hero ' . esc_attr($this->area_class($s, 'header')) . '" style="' . esc_attr($this->inline_header_style($s, (bool)$img)) . '">';
        echo '<div class="pa-event-hero-inner"><h3>' . esc_html($post->post_title) . '</h3>';
        echo '<div class="pa-event-meta-block"><span class="pa-event-accent-bar" aria-hidden="true"></span><div class="pa-event-meta-stack">';
        $program_title = $program_id ? get_the_title($program_id) : '';
        if ($cat || $program_title) { echo '<h5 class="pa-event-single-category">' . ($cat && $event_category_icon !== '' ? '<span class="pa-event-single-category-icon" aria-hidden="true">' . esc_html($event_category_icon) . '</span>' : '') . ($cat ? '<span>' . esc_html($cat) . '</span>' : '') . ($cat && $program_title ? '<span class="pa-event-single-dot">•</span>' : '') . ($program_title ? '<span>' . esc_html($program_title) . '</span>' : '') . '</h5>'; }
        if ($date) { echo '<h5 class="pa-event-single-meta"><span class="pa-calendar-icon" aria-hidden="true">' . $this->calendar_icon_svg() . '</span><span>' . esc_html($date) . '</span></h5>'; }
        if ($loc) { echo '<h5 class="pa-event-single-location"><span class="pa-location-pin" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M12 2.25c-3.31 0-6 2.69-6 6 0 4.41 6 13.5 6 13.5s6-9.09 6-13.5c0-3.31-2.69-6-6-6zm0 8.25a2.25 2.25 0 1 1 0-4.5 2.25 2.25 0 0 1 0 4.5z"/></svg></span> ' . ($link ? '<a href="' . esc_url($link) . '" target="_blank" rel="noopener noreferrer">' . esc_html($loc) . '</a>' : esc_html($loc)) . '</h5>'; }
        if (get_post_meta($post->ID, '_pa_event_show_add_to_calendar', true) === '1') { echo '<div class="pa-event-single-calendar"><h5 class="pa-event-single-calendar-heading"><span>Add to Calendar</span><span class="pa-calendar-options"><a class="pa-calendar-option" href="' . esc_url($this->event_outlook_calendar_url($post->ID)) . '" target="_blank" rel="noopener noreferrer" aria-label="Add to Outlook Calendar" title="Outlook"><span class="pa-calendar-logo">' . $this->outlook_calendar_icon_svg() . '</span></a><a class="pa-calendar-option" href="' . esc_url($this->event_google_calendar_url($post->ID)) . '" target="_blank" rel="noopener noreferrer" aria-label="Add to Google Calendar" title="Google"><span class="pa-calendar-logo">' . $this->google_calendar_icon_svg() . '</span></a></span></h5></div>'; }
        echo '</div></div>';
        $invite_warning_text = trim(wp_strip_all_tags($invite_warning));
        if ($invite_only) {
            echo '<div class="pa-event-invite-warning"><div class="pa-event-invite-warning-default"><span class="pa-event-invite-warning-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M3.75 6.75h16.5v10.5H3.75V6.75Zm1.5 1.4v7.7h13.5v-7.7L12 12.9 5.25 8.15Zm12.38.1H6.37L12 12.1l5.63-3.85Z" fill="currentColor"/></svg></span><span>This event is invite only.</span></div>' . ($invite_warning_text !== '' ? '<div class="pa-event-invite-warning-text">' . wp_kses_post(wpautop($invite_warning)) . '</div>' : '') . '</div>';
        }
        echo '</div></header>';
        echo '<div class="pa-single-content ' . esc_attr($this->area_class($s, 'content')) . '" style="' . esc_attr($this->inline_content_style($s)) . '">';
        echo '<div class="pa-single-text">' . wp_kses_post(wpautop($post->post_content)) . '</div>';
        $speaker_ids = get_post_meta($post->ID, '_pa_speaker_ids', true);
        if (is_array($speaker_ids) && $speaker_ids) {
            echo $this->single_event_speaker_sections($speaker_ids, $program_id, $post->ID);
        }
        echo '</div>';
        $sponsor_ids = get_post_meta($post->ID, '_pa_sponsor_ids', true);
        if (!is_array($sponsor_ids)) { $sponsor_ids = []; }
        $sponsor_ids = array_values(array_filter(array_map('absint', $sponsor_ids)));
        if ($sponsor_ids) {
            echo '<div class="pa-event-sponsor-logos pa-event-sponsor-logos-below"><h4>Sponsored by</h4><div class="pa-event-sponsor-logo-list">';
            foreach ($sponsor_ids as $sponsor_id) {
                $sponsor = get_post($sponsor_id);
                if (!$sponsor || $sponsor->post_type !== 'pa_sponsor') { continue; }
                $logo_id = absint(get_post_meta($sponsor_id, '_pa_sponsor_logo_id', true));
                $sponsor_url = get_permalink($sponsor);
                echo '<a class="pa-event-sponsor-logo" href="' . esc_url($sponsor_url) . '" aria-label="' . esc_attr($sponsor->post_title) . '">';
                if ($logo_id) { echo wp_get_attachment_image($logo_id, 'full', false, ['class'=>'pa-event-sponsor-logo-img', 'alt'=>esc_attr($sponsor->post_title)]); }
                else { echo '<span class="pa-event-sponsor-name">' . esc_html($sponsor->post_title) . '</span>'; }
                echo '</a>';
            }
            echo '</div></div>';
        }
        echo '</article>'; return ob_get_clean();
    }

    private function single_event_speaker_sections($speaker_ids, $program_id, $event_id) {
        $speaker_ids = array_values(array_filter(array_map('absint', (array)$speaker_ids)));
        if (!$speaker_ids) { return ''; }

        $event_speaker_categories = get_post_meta(absint($event_id), '_pa_event_speaker_categories', true);
        if (!is_array($event_speaker_categories)) { $event_speaker_categories = []; }

        $category_groups = [];
        $default_ids = [];
        foreach ($speaker_ids as $speaker_id) {
            $speaker = get_post($speaker_id);
            if (!$speaker || $speaker->post_type !== 'pa_speaker') { continue; }
            $category = '';
            foreach ([$speaker_id, (string)$speaker_id] as $key) {
                if (isset($event_speaker_categories[$key])) { $category = trim(sanitize_text_field($event_speaker_categories[$key])); break; }
            }
            if ($category !== '') {
                if (!isset($category_groups[$category])) { $category_groups[$category] = []; }
                $category_groups[$category][] = $speaker_id;
            } else {
                $default_ids[] = $speaker_id;
            }
        }

        if (!$category_groups) {
            return '<div class="pa-event-single-speakers"><h4>Speakers</h4>' . $this->speaker_cards($speaker_ids, $program_id, 'event-page-default', 0) . '</div>';
        }

        ob_start();
        echo '<div class="pa-single-event-speaker-sections' . (!$default_ids ? ' pa-single-event-speaker-sections--only-categorized' : '') . '">';
        echo '<div class="pa-single-event-speaker-section-list pa-single-event-speaker-section-list--categorized">';
        foreach ($category_groups as $category => $ids) {
            echo '<section class="pa-single-event-speaker-section pa-single-event-speaker-section--categorized"><h4 class="pa-single-event-speaker-section-heading">' . esc_html($category) . '</h4>' . $this->speaker_cards($ids, $program_id, 'event-page-category', $event_id) . '</section>';
        }
        echo '</div>';
        if ($default_ids) {
            echo '<section class="pa-single-event-speaker-section pa-single-event-speaker-section--default"><h4 class="pa-single-event-speaker-section-heading">Speakers</h4>' . $this->speaker_cards($default_ids, $program_id, 'event-page-default', 0) . '</section>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    private function single_speaker($post) {
        ob_start();
        $primary_program_id = $this->speaker_primary_program_id($post->ID);
        $s = $this->speaker_page_settings_for_program($primary_program_id);
        $credentials = get_post_meta($post->ID, '_pa_speaker_credentials', true);
        $role_title = get_post_meta($post->ID, '_pa_speaker_role_title', true);
        $company = get_post_meta($post->ID, '_pa_speaker_company', true);
        $li = get_post_meta($post->ID, '_pa_speaker_linkedin', true);
        $web = get_post_meta($post->ID, '_pa_speaker_website', true);
        $img = absint(get_post_meta($post->ID, '_pa_speaker_image_id', true));
        $shape = $s['image_shape'] ?? '';
        $image_radius = $shape === 'circle' ? '50%' : ($shape === 'square' ? '0' : '0');
        $image_border_width = isset($s['image_border_width']) && $s['image_border_width'] !== '' ? absint($s['image_border_width']) : 0;
        $image_border_color = !empty($s['image_border_color']) ? $s['image_border_color'] : 'currentColor';
        $img_frame_style = 'width:150px;height:150px;box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center;overflow:hidden;flex:none;';
        $img_frame_style .= 'border-radius:' . esc_attr($image_radius) . ';';
        if ($image_border_width > 0) {
            $img_frame_style .= 'padding:' . $image_border_width . 'px;background-color:' . esc_attr($image_border_color) . ';';
        }
        $img_style = 'width:100%;height:100%;object-fit:cover;display:block;border:0;border-radius:' . esc_attr($image_radius) . ';';
        $speaker_page_id = 'pa-single-speaker-' . absint($post->ID);
        $header_color = sanitize_hex_color($s['header_color'] ?? '');
        $header_bg = sanitize_hex_color($s['header_bg'] ?? '');
        $content_color = sanitize_hex_color($s['content_color'] ?? '');
        $scoped_color_css = '';
        if ($header_color) {
            $scoped_color_css .= '#' . $speaker_page_id . ' .pa-single-header,#' . $speaker_page_id . ' .pa-single-header *{color:' . $header_color . ' !important;}';
            $scoped_color_css .= '#' . $speaker_page_id . ' .pa-speaker-icon-link,#' . $speaker_page_id . ' .pa-speaker-icon-link svg{color:' . $header_color . ' !important;fill:currentColor !important;stroke:currentColor !important;}';
        }
        if ($content_color) {
            $scoped_color_css .= '#' . $speaker_page_id . ' .pa-single-content,#' . $speaker_page_id . ' .pa-single-content *{color:' . $content_color . ' !important;}';
        }
        if ($scoped_color_css) { echo '<style>' . esc_html($scoped_color_css) . '</style>'; }

        echo '<article id="' . esc_attr($speaker_page_id) . '" class="pa-single pa-single-speaker">';
        echo $this->back_to_program_link($primary_program_id);
        echo '<header class="pa-single-header pa-speaker-hero ' . esc_attr($this->area_class($s, 'header')) . '" style="' . esc_attr($this->inline_header_style($s)) . '">';
        if ($img) { echo '<div class="pa-speaker-hero-image" style="' . esc_attr($img_frame_style) . '">' . wp_get_attachment_image($img, 'medium', false, ['class'=>'pa-speaker-image','style'=>$img_style]) . '</div>'; }
        echo '<div class="pa-speaker-hero-text"><h5 class="pa-speaker-page-label">Speaker</h5><h3 class="pa-speaker-name">' . esc_html($post->post_title);
        if ($credentials) { echo ' <span class="pa-speaker-credentials">' . esc_html($credentials) . '</span>'; }
        echo '</h3>';
        $speaker_meta_parts = array_filter([$role_title, $company]);
        if ($speaker_meta_parts) {
        echo '<h4 class="pa-speaker-role-company">' . esc_html(implode(' • ', $speaker_meta_parts)) . '</h4>';
   }
        echo '</div>';
        if ($li || $web) {
            echo '<nav class="pa-speaker-header-links" aria-label="Speaker links">';
            if ($li) { echo '<a class="pa-speaker-icon-link pa-speaker-icon-linkedin" href="' . esc_url($li) . '" aria-label="LinkedIn" target="_blank" rel="noopener"><svg aria-hidden="true" viewBox="0 0 24 24" focusable="false"><path fill="currentColor" d="M20.45 20.45h-3.56v-5.57c0-1.33-.03-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.34V9h3.42v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.32 7.43a2.06 2.06 0 1 1 0-4.12 2.06 2.06 0 0 1 0 4.12zM7.1 20.45H3.54V9H7.1v11.45z"/></svg></a>'; }
            if ($web) { echo '<a class="pa-speaker-icon-link pa-speaker-icon-website" href="' . esc_url($web) . '" aria-label="Website" target="_blank" rel="noopener"><svg aria-hidden="true" viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path d="M3 12h18M12 3c2.4 2.5 3.6 5.5 3.6 9s-1.2 6.5-3.6 9M12 3C9.6 5.5 8.4 8.5 8.4 12s1.2 6.5 3.6 9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></a>'; }
            echo '</nav>';
        }
        echo '</header><div class="pa-single-content ' . esc_attr($this->area_class($s, 'content')) . '" style="' . esc_attr($this->inline_content_style($s)) . '">';
        echo '<div class="pa-single-text">' . wp_kses_post(wpautop($post->post_content)) . '</div>';
        echo '</div>';
        echo $this->speaker_upcoming_event_cards($post->ID);
        echo '</article>'; return ob_get_clean();
    }

    private function back_to_program_link($program_id) {
        $program_id = absint($program_id);
        if (!$program_id) { return ''; }
        $url = get_post_meta($program_id, '_pa_back_to_link', true);
        if (!$url) { return ''; }
        return '<p class="pa-back-to-program"><a href="' . esc_url($url) . '">&larr; Back to Program</a></p>';
    }

    private function speaker_primary_program_id($speaker_id) {
        $explicit_program_id = absint(get_post_meta($speaker_id, '_pa_speaker_style_program_id', true));
        if ($explicit_program_id) { return $explicit_program_id; }
        $events = $this->speaker_events($speaker_id, true);
        if (!$events) { $events = $this->speaker_events($speaker_id, false); }
        if (!$events) { return 0; }
        return absint(get_post_meta($events[0]->ID, '_pa_program_id', true));
    }

    private function speaker_events($speaker_id, $upcoming_only = true) {
        $speaker_id = absint($speaker_id);
        if (!$speaker_id) { return []; }

        // Speaker IDs may be saved as integers in serialized post meta, so searching
        // only for the quoted string version can miss valid event relationships.
        $meta_query = [
            'relation' => 'AND',
            [
                'relation' => 'OR',
                [
                    'key' => '_pa_speaker_ids',
                    'value' => '"' . $speaker_id . '"',
                    'compare' => 'LIKE',
                ],
                [
                    'key' => '_pa_speaker_ids',
                    'value' => 'i:' . $speaker_id . ';',
                    'compare' => 'LIKE',
                ],
            ],
        ];

        if ($upcoming_only) {
            $meta_query[] = [
                'key' => '_pa_event_date',
                'value' => current_time('Y-m-d'),
                'compare' => '>=',
                'type' => 'DATE',
            ];
        }

        return get_posts([
            'post_type' => 'pa_event',
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_key' => '_pa_event_date',
            'orderby' => 'meta_value',
            'order' => $upcoming_only ? 'ASC' : 'DESC',
            'meta_query' => $meta_query,
        ]);
    }

    private function speaker_upcoming_event_cards($speaker_id) {
        $events = $this->speaker_events($speaker_id, true);
        if (!$events) { return ''; }
        ob_start();
        echo '<section class="pa-speaker-upcoming-events"><h5>Upcoming Events</h5><div class="pa-event-list">';
        foreach ($events as $event) {
            $program_id = absint(get_post_meta($event->ID, '_pa_program_id', true));
            echo $this->speaker_event_card($event, $program_id);
        }
        echo '</div></section>';
        return ob_get_clean();
    }

    private function speaker_event_card($event, $program_id) {
        return $this->agenda_event_card($event, $program_id);
    }

    private function agenda_event_card($event, $program_id) {
        $program_id = absint($program_id);
        $agenda = $program_id ? get_post_meta($program_id, '_pa_agenda_settings', true) : [];
        if (!is_array($agenda)) { $agenda = []; }

        $speaker_layout = 'inline';
        $date_display = $agenda['date_display'] ?? 'numeric';
        if (!in_array($date_display, ['numeric','abbrev'], true)) { $date_display = 'abbrev'; }
        $card_size = $this->normalize_agenda_card_size($agenda['card_size'] ?? 'full');
        $agenda_style = $this->agenda_item_style($agenda);
        $show_desc = ($agenda['show_descriptions'] ?? get_post_meta($program_id, '_pa_show_event_descriptions', true) ?: 'hide') !== 'hide';

        $cats = $program_id ? get_post_meta($program_id, '_pa_categories', true) : [];
        if (!is_array($cats)) { $cats = []; }
        $cat_map = [];
        foreach ($cats as $c) { if (!empty($c['name'])) { $cat_map[$c['name']] = $c; } }

        $cat = get_post_meta($event->ID, '_pa_event_category', true);
        $c = $cat_map[$cat] ?? ['color'=>'#000000','icon'=>'none'];
        $cat_color = $c['color'] ?: '#000000';
        $event_date_raw = get_post_meta($event->ID, '_pa_event_date', true);
        $date_text = $event_date_raw ? $this->format_agenda_date($event_date_raw, $date_display) : '';
        $time_text = $this->format_event_start_time($event->ID);
        $loc = get_post_meta($event->ID, '_pa_event_location', true);
        $link = get_post_meta($event->ID, '_pa_event_location_link', true);
        $invite_only = get_post_meta($event->ID, '_pa_event_invite_only', true) === '1';
        $speaker_ids = get_post_meta($event->ID, '_pa_speaker_ids', true);
        if (!is_array($speaker_ids)) { $speaker_ids = []; }
        $item_style = trim($agenda_style . ';--pa-agenda-accent-color:' . $cat_color . ';');


        $card_classes = [
            'pa-event-card',
            'pa-event-card--' . ($speaker_ids ? 'has-speakers' : 'no-speakers'),
            'pa-event-card--speakers-' . $speaker_layout,
            'pa-event-card--size-' . $card_size,
        ];

        $invite_icon_html = $invite_only ? '<span class="pa-event-card__invite-wrap pa-event-card__datebar-invite-wrap"><span class="pa-event-card__invite-icon" aria-label="Invite only" tabindex="0"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3.75 6.75h16.5v10.5H3.75V6.75Zm1.5 1.4v7.7h13.5v-7.7L12 12.9 5.25 8.15Zm12.38.1H6.37L12 12.1l5.63-3.85Z" fill="currentColor"/></svg></span><span class="pa-invite-tooltip" role="tooltip"><span class="pa-invite-tooltip-inner"><span>Invite only</span></span></span></span>' : '';

        ob_start();
        echo '<article class="' . esc_attr(implode(' ', $card_classes)) . '" style="' . esc_attr($item_style) . '">';
        echo '<div class="pa-event-card__datebar"><span class="pa-event-card__date">' . esc_html($date_text ?: 'Date') . '</span><span class="pa-event-card__time">' . esc_html($time_text ?: 'Time') . '</span>' . $invite_icon_html . '</div>';
        echo '<div class="pa-event-card__body"><div class="pa-event-card__summary"><h4 class="pa-event-card__title"><a href="' . esc_url(get_permalink($event)) . '">' . esc_html($event->post_title) . '</a></h4>';

        if ($cat || $loc) {
            echo '<p class="pa-event-card__meta">';
            if ($cat) { echo '<span class="pa-event-card__category"><span class="pa-event-card__category-icon" style="color:' . esc_attr($cat_color) . '">' . esc_html($this->icon_char($c['icon'] ?? 'none')) . '</span><span class="pa-event-card__category-text">' . esc_html($cat) . '</span></span>'; }
            if ($cat && $loc) { echo '<span class="pa-event-card__meta-dot" aria-hidden="true">•</span>'; }
            if ($loc) { echo '<span class="pa-event-card__location">' . ($link ? '<a href="' . esc_url($link) . '" target="_blank" rel="noopener noreferrer">' . esc_html($loc) . '</a>' : esc_html($loc)) . '</span>'; }
            echo '</p>';
        }
        if ($card_size !== 'thin' && $show_desc && $event->post_content) { echo '<div class="pa-event-card__description">' . wp_kses_post(wpautop($event->post_content)) . '</div>'; }
        echo '</div>';
        if ($card_size !== 'thin' && $speaker_ids) { echo '<div class="pa-event-card__speakers">' . $this->speaker_cards($speaker_ids, $program_id, 'agenda', $event->ID) . '</div>'; }
        echo '</div></article>';
        return ob_get_clean();
    }

    private function inline_header_style($s, $omit_border = false) { return $this->style_from_settings($s, 'header', $omit_border); }
    private function inline_content_style($s) { return $this->style_from_settings($s, 'content'); }
    private function area_class($s, $area) { return !empty($s[$area . '_color']) ? 'pa-has-custom-font-color' : ''; }
    private function style_from_settings($s, $area, $omit_border = false) {
        $css = '';
        if (!empty($s[$area . '_bg'])) {
            $css .= 'background-color:' . esc_attr($s[$area . '_bg']) . ';';
            $css .= '--pa-single-' . esc_attr($area) . '-bg:' . esc_attr($s[$area . '_bg']) . ';';
        }
        if (!empty($s[$area . '_color'])) {
            $css .= 'color:' . esc_attr($s[$area . '_color']) . ';';
            $css .= '--pa-single-' . esc_attr($area) . '-color:' . esc_attr($s[$area . '_color']) . ';';
        }
        if (!$omit_border) { $css .= $this->border_style_from_settings($s, $area); }
        return $css;
    }

    private function border_style_from_settings($s, $area) {
        $css = '';
        $b = $s[$area . '_border'] ?? [];
        $has_width = false;
        foreach (['top','right','bottom','left'] as $side) { if (isset($b['width_'.$side]) && $b['width_'.$side] !== '') { $has_width = true; } }
        if ($has_width || !empty($b['color'])) $css .= 'border-style:solid;';
        if (!empty($b['color'])) $css .= 'border-color:' . esc_attr($b['color']) . ';';
        foreach (['top','right','bottom','left'] as $side) { if (isset($b['width_'.$side]) && $b['width_'.$side] !== '') $css .= 'border-' . $side . '-width:' . absint($b['width_'.$side]) . 'px;'; }
        $rmap = ['tl'=>'top-left','tr'=>'top-right','br'=>'bottom-right','bl'=>'bottom-left']; foreach ($rmap as $k=>$name) { if (isset($b['radius_'.$k]) && $b['radius_'.$k] !== '') $css .= 'border-' . $name . '-radius:' . absint($b['radius_'.$k]) . 'px;'; }
        return $css;
    }

    private function agenda_item_style($settings) {
        if (!is_array($settings)) { $settings = []; }
        $style = '';
        if (!empty($settings['background'])) { $style .= 'background-color:' . esc_attr($settings['background']) . ';--pa-agenda-card-bg:' . esc_attr($settings['background']) . ';'; }
        if (!empty($settings['accent_bar_color'])) { $style .= '--pa-agenda-bar-color:' . esc_attr($settings['accent_bar_color']) . ';'; }
        $title_color = $settings['title_color'] ?? ($settings['color'] ?? '');
        $location_color = $settings['location_color'] ?? '';
        if (!empty($title_color)) { $style .= '--pa-agenda-title-color:' . esc_attr($title_color) . ';--pa-agenda-scroll-color:' . esc_attr($title_color) . ';'; }
        if (!empty($location_color)) { $style .= '--pa-agenda-location-color:' . esc_attr($location_color) . ';'; if (empty($title_color)) { $style .= '--pa-agenda-scroll-color:' . esc_attr($location_color) . ';'; } }
        $category_color = !empty($location_color) ? $location_color : $title_color;
        if (!empty($category_color)) { $style .= '--pa-agenda-category-color:' . esc_attr($category_color) . ';'; }
        $style .= $this->program_border_style($settings, true);
        if (!empty($settings['border_color'])) { $style .= 'border-color:' . esc_attr($settings['border_color']) . ' !important;'; }
        if (strpos($style, 'radius') !== false) { $style .= 'overflow:hidden;'; }
        return $style;
    }



    public function maybe_clear_github_update_cache() {
        if (!is_admin() || !current_user_can('update_plugins')) { return; }
        if (isset($_GET['force-check']) || isset($_GET['pa_stagecard_clear_update_cache'])) {
            delete_site_transient('pa_stagecard_github_release');
            delete_site_transient('update_plugins');
            if (function_exists('wp_clean_plugins_cache')) { wp_clean_plugins_cache(true); }
        }
    }

    /**
     * Checks the public GitHub Releases feed for newer Stagecard ZIP releases.
     *
     * Release setup notes:
     * - Create tags like v1.15.137.
     * - Attach the built WordPress plugin ZIP to the release assets.
     * - The updater intentionally ignores GitHub's automatic source-code ZIPs.
     */
    public function check_github_plugin_update($transient) {
        if (empty($transient) || !is_object($transient)) { return $transient; }

        $plugin_file = plugin_basename(__FILE__);
        if (empty($transient->checked)) { $transient->checked = []; }
        if (empty($transient->checked[$plugin_file])) { $transient->checked[$plugin_file] = self::VERSION; }
        if (empty($transient->response) || !is_array($transient->response)) { $transient->response = []; }
        if (empty($transient->no_update) || !is_array($transient->no_update)) { $transient->no_update = []; }

        $release = $this->github_latest_release();
        if (!$release || empty($release['version']) || empty($release['download_url'])) { return $transient; }
        if (!version_compare($release['version'], self::VERSION, '>')) {
            $transient->no_update[$plugin_file] = (object) [
                'id' => self::GITHUB_REPO,
                'slug' => dirname($plugin_file),
                'plugin' => $plugin_file,
                'new_version' => self::VERSION,
                'url' => $release['html_url'],
                'package' => '',
            ];
            unset($transient->response[$plugin_file]);
            return $transient;
        }

        unset($transient->no_update[$plugin_file]);
        $transient->response[$plugin_file] = (object) [
            'id' => self::GITHUB_REPO,
            'slug' => dirname($plugin_file),
            'plugin' => $plugin_file,
            'new_version' => $release['version'],
            'url' => $release['html_url'],
            'package' => $release['download_url'],
            'tested' => $release['tested'],
            'requires' => $release['requires'],
            'requires_php' => $release['requires_php'],
        ];

        return $transient;
    }

    public function github_plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== dirname(plugin_basename(__FILE__))) {
            return $result;
        }

        $release = $this->github_latest_release(false);
        if (!$release) { return $result; }

        return (object) [
            'name' => 'Stagecard',
            'slug' => dirname(plugin_basename(__FILE__)),
            'version' => $release['version'],
            'author' => '<a href="https://oliviakohring.com/">Olivia Kohring</a>',
            'homepage' => $release['html_url'],
            'download_link' => $release['download_url'],
            'requires' => $release['requires'],
            'tested' => $release['tested'],
            'requires_php' => $release['requires_php'],
            'last_updated' => $release['published_at'],
            'sections' => [
                'description' => 'Stagecard is a WordPress program manager that includes unique pages for events, speakers, and sponsors. Using simple shortcode plugins, you can easily generate an on-brand agenda featuring customizable event cards and speaker profiles. <br><br> This plugin has been created with ChatGPT.',
                'changelog' => $release['body'] ? wp_kses_post(wpautop($release['body'])) : 'See the GitHub release notes for this version.',
            ],
        ];
    }

    private function github_latest_release($use_cache = true) {
        $cache_key = 'pa_stagecard_github_release';
        if ($use_cache) {
            $cached = get_site_transient($cache_key);
            if (is_array($cached)) { return $cached; }
        }

        $response = wp_remote_get('https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest', [
            'timeout' => 12,
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'Stagecard/' . self::VERSION . '; ' . home_url('/'),
            ],
        ]);

        if (is_wp_error($response)) { return false; }
        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) { return false; }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data) || empty($data['tag_name'])) { return false; }

        $version = ltrim((string) $data['tag_name'], 'vV');
        $download_url = '';
        if (!empty($data['assets']) && is_array($data['assets'])) {
            foreach ($data['assets'] as $asset) {
                $name = isset($asset['name']) ? strtolower((string) $asset['name']) : '';
                if ($name && substr($name, -4) === '.zip' && !empty($asset['browser_download_url'])) {
                    $download_url = esc_url_raw($asset['browser_download_url']);
                    break;
                }
            }
        }

        // Fallback for Stagecard's release-asset naming convention, in case GitHub's asset list is delayed or filtered.
        if (!$download_url && $version) {
            $download_url = esc_url_raw('https://github.com/' . self::GITHUB_REPO . '/releases/download/' . rawurlencode((string) $data['tag_name']) . '/program-agenda-v' . str_replace('.', '-', $version) . '.zip');
        }

        if (!$download_url) { return false; }

        $release = [
            'version' => $version,
            'html_url' => !empty($data['html_url']) ? esc_url_raw($data['html_url']) : 'https://github.com/' . self::GITHUB_REPO,
            'download_url' => $download_url,
            'published_at' => !empty($data['published_at']) ? sanitize_text_field($data['published_at']) : '',
            'body' => !empty($data['body']) ? wp_kses_post($data['body']) : '',
            'requires' => '5.8',
            'tested' => '6.8',
            'requires_php' => '7.4',
        ];

        set_site_transient($cache_key, $release, 5 * MINUTE_IN_SECONDS);
        return $release;
    }

    private function speaker_cards($speaker_ids, $program_id = 0, $context = '', $event_id = 0) {
        $settings = $program_id ? get_post_meta($program_id, '_pa_speaker_card_settings', true) : [];
        if (!is_array($settings)) { $settings = []; }
        $show_thumb = ($settings['show_thumbnail'] ?? '1') !== '0';
        $style = '';
        $card_color = !empty($settings['color']) ? sanitize_hex_color($settings['color']) : '';
        $card_bg = !empty($settings['background']) ? sanitize_hex_color($settings['background']) : '';
        if ($card_bg) { $style .= 'background-color:' . esc_attr($card_bg) . ';'; }
        if ($card_color) { $style .= 'color:' . esc_attr($card_color) . ';--pa-speaker-card-text-color:' . esc_attr($card_color) . ';'; }
        $text_style = $card_color ? 'color:' . esc_attr($card_color) . ' !important;' : '';
        $style .= $this->program_border_style($settings);
        if (!empty($settings['border_color'])) { $style .= 'border-color:' . esc_attr($settings['border_color']) . ';'; }
        $img_class = 'pa-speaker-card-thumb';
        if (($settings['thumbnail_shape'] ?? '') === 'circle') { $img_class .= ' is-circle'; }
        if (($settings['thumbnail_shape'] ?? '') === 'square') { $img_class .= ' is-square'; }
        $event_speaker_categories = $event_id ? get_post_meta(absint($event_id), '_pa_event_speaker_categories', true) : [];
        if (!is_array($event_speaker_categories)) { $event_speaker_categories = []; }
        $default_cards = [];
        $categorized_cards = [];
        foreach ((array)$speaker_ids as $sid) {
            $sid = absint($sid);
            $sp = get_post($sid);
            if (!$sp || $sp->post_type !== 'pa_speaker') { continue; }
            $category = '';
            foreach ([$sid, (string)$sid] as $key) { if (isset($event_speaker_categories[$key])) { $category = sanitize_text_field($event_speaker_categories[$key]); break; } }
            $role = get_post_meta($sp->ID, '_pa_speaker_role_title', true);
            if (!$role) { $role = get_post_meta($sp->ID, '_pa_speaker_credentials', true); }
            $company = get_post_meta($sp->ID, '_pa_speaker_company', true);
            $img = absint(get_post_meta($sp->ID, '_pa_speaker_image_id', true));
            ob_start();
            echo '<div class="pa-speaker-card-unit' . ($category ? ' pa-speaker-card-unit--categorized' : '') . '">';
            if ($category) { echo '<span class="pa-speaker-card-category-label">' . esc_html($category) . '</span>'; }
            echo '<article class="pa-speaker-card' . ($category ? ' pa-speaker-card--categorized' : '') . '" style="' . esc_attr($style) . '">';
            if ($show_thumb) {
                echo '<a class="pa-speaker-card-image" href="' . esc_url(get_permalink($sp)) . '">';
                if ($img) { echo wp_get_attachment_image($img, 'medium', false, ['class'=>$img_class]); }
                else { echo '<span class="' . esc_attr($img_class) . ' pa-speaker-card-placeholder" aria-hidden="true"></span>'; }
                echo '</a>';
            }
            echo '<div class="pa-speaker-card-text"><h3><a style="' . esc_attr($text_style) . '" href="' . esc_url(get_permalink($sp)) . '">' . esc_html($sp->post_title) . '</a></h3>';
            if ($role) { echo '<p class="pa-speaker-card-role" style="' . esc_attr($text_style) . '">' . esc_html($role) . '</p>'; }
            if ($company) { echo '<p class="pa-speaker-card-company" style="' . esc_attr($text_style) . '">' . esc_html($company) . '</p>'; }
            echo '</div></article></div>';
            $card_html = ob_get_clean();
            if ($category) { $categorized_cards[] = $card_html; } else { $default_cards[] = $card_html; }
        }
        $list_classes = 'pa-speaker-card-list ' . ($context === 'agenda' ? 'pa-speaker-card-list-agenda' : '');
        if ($categorized_cards) { $list_classes .= ' pa-speaker-card-list-has-categories'; }
        if ($categorized_cards && $default_cards) { $list_classes .= ' pa-speaker-card-list--categorized-split'; }
        ob_start();
        echo '<div class="' . esc_attr(trim($list_classes)) . '">';
        echo implode('', array_merge($categorized_cards, $default_cards));
        echo '</div>';
        return ob_get_clean();
    }
}

new Program_Agenda_Plugin();
register_activation_hook(__FILE__, function(){ Program_Agenda_Plugin::register_post_types(); flush_rewrite_rules(); });
register_deactivation_hook(__FILE__, function(){ flush_rewrite_rules(); });
