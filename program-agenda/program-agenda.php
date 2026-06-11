<?php
/**
 * Plugin Name: Stagecard
 * Description: A program schedule builder that includes on-brand customization options and automated page creation for events, speakers, and sponsors.
 * Version: 1.23.006
 * Update URI: https://github.com/okohring/stagecard
 * Author: Olivia Kohring
 * Text Domain: program-agenda
 */

if (!defined('ABSPATH')) { exit; }

final class Program_Agenda_Plugin {
    const VERSION = '1.23.006';
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

        return $colors;
    }

    private function salient_palette_colors() {
        $colors = [];
        $option_names = ['salient_redux', 'nectar_redux', 'redux_options'];
        $keys = ['accent-color','extra-color-1','extra-color-2','extra-color-3','color-accent-color','color-extra-color-1','color-extra-color-2','color-extra-color-3'];
        foreach ($option_names as $option_name) {
            $options = get_option($option_name, []);
            if (!is_array($options)) { continue; }
            foreach ($keys as $key) {
                if (!empty($options[$key])) { $this->append_hex_color($colors, $options[$key]); }
            }
        }
        return $colors;
    }

    private function theme_mod_palette_colors() {
        $colors = [];
        $mods = function_exists('get_theme_mods') ? get_theme_mods() : [];
        if (!is_array($mods)) { return $colors; }
        foreach ($mods as $key => $value) {
            if (is_string($value) && preg_match('/color|accent|palette/i', (string)$key)) {
                $this->append_hex_color($colors, $value);
            } elseif (is_array($value) && preg_match('/color|accent|palette/i', (string)$key)) {
                array_walk_recursive($value, function($item) use (&$colors) {
                    if (is_string($item)) { $this->append_hex_color($colors, $item); }
                });
            }
        }
        return $colors;
    }

    private function theme_option_palette_colors() {
        $colors = [];
        global $wpdb;
        if (!$wpdb) { return $colors; }
        $rows = $wpdb->get_results("SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE '%theme%' OR option_name LIKE '%salient%' OR option_name LIKE '%nectar%' LIMIT 40");
        foreach ((array)$rows as $row) {
            $value = maybe_unserialize($row->option_value ?? '');
            $this->append_colors_from_value($colors, $value);
            if (count($colors) >= 6) { break; }
        }
        return $colors;
    }

    private function append_colors_from_value(&$colors, $value) {
        if (is_string($value)) {
            if (strlen($value) > 8000) { return; }
            if (preg_match_all('/#(?:[A-Fa-f0-9]{3}){1,2}\b/', $value, $matches)) {
                foreach ($matches[0] as $color) { $this->append_hex_color($colors, $color); }
            }
        } elseif (is_array($value)) {
            foreach ($value as $key => $item) {
                if (is_string($key) && preg_match('/color|accent|palette/i', $key)) {
                    $this->append_colors_from_value($colors, $item);
                } elseif (is_array($item)) {
                    $this->append_colors_from_value($colors, $item);
                }
                if (count($colors) >= 6) { break; }
            }
        }
    }

    private function append_hex_color(&$colors, $color) {
        if (!is_string($color)) { return; }
        $color = trim($color);
        if (!preg_match('/^#(?:[A-Fa-f0-9]{3}){1,2}$/', $color)) { return; }
        $color = strtoupper($color);
        if (!in_array($color, $colors, true)) { $colors[] = $color; }
    }

    public function public_assets() {
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
        if (!in_array($post_type, ['pa_event','pa_speaker','pa_sponsor'], true)) { return; }
        $programs = get_posts(['post_type'=>'pa_program','post_status'=>['publish','draft'],'numberposts'=>-1,'orderby'=>'title','order'=>'ASC']);
        $program_label = $post_type === 'pa_speaker' ? 'Assign program style' : 'Assign program';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="pa-bulk-form" data-pa-bulk-form="' . esc_attr($post_type) . '">';
        wp_nonce_field('pa_bulk_items_' . $post_type);
        echo '<input type="hidden" name="action" value="pa_bulk_items"><input type="hidden" name="post_type" value="' . esc_attr($post_type) . '">';
        echo '<div class="pa-bulk-toolbar"><label><span>Action</span><select name="bulk_action" class="pa-bulk-action-select"><option value="">No status/delete action</option><option value="draft">Move to Draft</option><option value="delete">Delete</option></select></label>';
        echo '<label class="pa-bulk-program-field"><span>' . esc_html($program_label) . '</span><select name="bulk_program_id" class="pa-bulk-program-select"><option value="">' . esc_html($program_label) . '</option>';
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
        if (in_array($post_type, ['pa_event','pa_speaker','pa_sponsor'], true)) { echo '</form>'; }
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
        $this->bulk_actions('pa_speaker');
        $items = $this->query_items('pa_speaker');
        echo '<table class="pa-table pa-sortable-table"><thead><tr><th class="pa-select-col"><label class="screen-reader-text" for="pa-select-all-speakers">Select all speakers</label><input type="checkbox" id="pa-select-all-speakers" class="pa-bulk-check-all"></th>' . $this->sortable_th('Name') . $this->sortable_th('Company') . $this->sortable_th('Program style') . $this->sortable_th('Page') . $this->sortable_th('Author') . $this->sortable_th('Date Created', 'date') . '<th></th></tr></thead><tbody>';
        foreach ($items as $p) {
            $company = get_post_meta($p->ID, '_pa_speaker_company', true);
            $author = get_the_author_meta('display_name', $p->post_author);
            $role = get_post_meta($p->ID, '_pa_speaker_role_title', true);
            $credentials = get_post_meta($p->ID, '_pa_speaker_credentials', true);
            $style_program_id = $this->speaker_primary_program_id($p->ID);
            $style_program_title = $style_program_id ? get_the_title($style_program_id) : '';
            $search_terms = $this->normalize_search_terms([$p->post_title, $company, $role, $credentials, $author, get_the_date('', $p), $style_program_title]);
            echo '<tr class="pa-searchable-row" data-pa-search="' . esc_attr($search_terms) . '"><td class="pa-select-col"><input type="checkbox" class="pa-bulk-item-check" name="item_ids[]" value="' . esc_attr($p->ID) . '"></td><td><a href="' . esc_url(admin_url('admin.php?page=program-edit-speaker&id=' . $p->ID)) . '">' . esc_html($p->post_title) . '</a></td><td>' . esc_html($company) . '</td><td>' . ($style_program_title ? esc_html($style_program_title) : '&mdash;') . '</td><td><a href="' . esc_url(get_permalink($p)) . '" target="_blank" rel="noopener">View page</a></td><td>' . esc_html($author) . '</td><td>' . esc_html(get_the_date('', $p)) . '</td><td>' . $this->row_actions($p) . '</td></tr>';
        }
        if ($items) { echo '<tr class="pa-list-search-empty" hidden><td colspan="8">No matching speakers found.</td></tr>'; }
        if (!$items) { echo '<tr><td colspan="8">No speakers found.</td></tr>'; }
        echo '</tbody></table>';
        $this->close_bulk_actions('pa_speaker');
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
        echo '<label class="pa-field pa-sponsor-slug-field"><span class="pa-field-heading">Page URL Slug</span><input type="text" name="sponsor_slug" value="' . esc_attr($post ? $post->post_name : '') . '" placeholder="auto-generate-from-company-name"><small>Used in the public sponsor page URL. Leave blank to generate from the company name.</small></label>';
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
        $speaker_card = get_post_meta($id, '_pa_speaker_card_settings', true);
        $event_settings = $this->settings_for_program($id, 'event');
        $speaker_settings = $this->settings_for_program($id, 'speaker');
        $categories = get_post_meta($id, '_pa_categories', true);
        $all_same = get_post_meta($id, '_pa_categories_all_same', true) === '1';
        if (!is_array($agenda)) { $agenda = []; }
        if (!is_array($speaker_card)) { $speaker_card = []; }
        if (!is_array($categories)) { $categories = []; }

        echo '<section class="pa-form-card pa-advanced-settings-card"><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="pa-form pa-advanced-settings-form">';
        wp_nonce_field('pa_save_program_advanced');
        echo '<input type="hidden" name="action" value="pa_save_program_advanced"><input type="hidden" name="program_id" value="' . esc_attr($id) . '">';

        echo '<div class="pa-advanced-grid">';
        echo '<section class="pa-advanced-panel"><h3>Agenda Display</h3>';
        echo '<label class="pa-field">Event card style<select name="agenda[card_size]"><option value="full" ' . selected($agenda['card_size'] ?? 'full', 'full', false) . '>Full cards with speakers</option><option value="thin" ' . selected($agenda['card_size'] ?? '', 'thin', false) . '>Thin cards without speakers</option></select></label>';
        echo '<label class="pa-field">Event descriptions<select name="agenda[show_descriptions]"><option value="show" ' . selected($agenda['show_descriptions'] ?? '', 'show', false) . '>Show on agenda</option><option value="hide" ' . selected($agenda['show_descriptions'] ?? 'hide', 'hide', false) . '>Hide on agenda</option></select></label>';
        echo '<label class="pa-field">Agenda date display<select name="agenda[date_display]"><option value="numeric" ' . selected($agenda['date_display'] ?? 'numeric', 'numeric', false) . '>Numeric day</option><option value="abbrev" ' . selected($agenda['date_display'] ?? '', 'abbrev', false) . '>Abbreviated month + day</option></select></label>';
        echo '<label class="pa-field">Date navigation<select name="agenda[display_mode]"><option value="tabs" ' . selected($agenda['display_mode'] ?? 'tabs', 'tabs', false) . '>Tabs</option><option value="stacked" ' . selected($agenda['display_mode'] ?? '', 'stacked', false) . '>Stacked by day</option></select></label>';
        echo '</section>';

        echo '<section class="pa-advanced-panel"><h3>Agenda Colors</h3><div class="pa-color-setting-row">';
        echo $this->color_control('agenda[background]', $agenda['background'] ?? '', '', 'Background color', 'Agenda background color');
        echo $this->color_control('agenda[accent_bar_color]', $agenda['accent_bar_color'] ?? '', '', 'Accent bar color', 'Event card accent bar color');
        echo $this->color_control('agenda[title_color]', $agenda['title_color'] ?? '', '', 'Event title color', 'Event title color');
        echo $this->color_control('agenda[location_color]', $agenda['location_color'] ?? '', '', 'Event details color', 'Event details color');
        echo '</div>';
        echo '<div class="pa-color-setting-row">';
        echo $this->color_control('agenda[tab_background_color]', $agenda['tab_background_color'] ?? '', '', 'Tab background color', 'Date tab background color');
        echo $this->color_control('agenda[tab_border_color]', $agenda['tab_border_color'] ?? '', '', 'Tab border color', 'Date tab border color');
        echo $this->color_control('agenda[border_color]', $agenda['border_color'] ?? '', '', 'Card border color', 'Event card border color');
        echo '</div>';
        $this->border_group_named('agenda', 'tab_border', 'Date tab border', $agenda);
        $this->border_group_named('agenda', 'border', 'Event card border', $agenda);
        echo '</section>';

        echo '<section class="pa-advanced-panel"><h3>Speaker Cards</h3>';
        echo '<label class="pa-checkbox-field"><input type="checkbox" name="speaker_card[show_thumbnail]" value="1" ' . checked(!empty($speaker_card['show_thumbnail']), true, false) . '> Show speaker thumbnails on agenda cards</label>';
        echo '<label class="pa-field">Thumbnail shape<select name="speaker_card[thumbnail_shape]"><option value="theme" ' . selected($speaker_card['thumbnail_shape'] ?? 'theme', 'theme', false) . '>Theme/default</option><option value="circle" ' . selected($speaker_card['thumbnail_shape'] ?? '', 'circle', false) . '>Circle</option><option value="square" ' . selected($speaker_card['thumbnail_shape'] ?? '', 'square', false) . '>Square</option></select></label>';
        echo '<div class="pa-color-setting-row">';
        echo $this->color_control('speaker_card[background]', $speaker_card['background'] ?? '', '', 'Background color', 'Speaker card background color');
        echo $this->color_control('speaker_card[color]', $speaker_card['color'] ?? '', '', 'Text color', 'Speaker card text color');
        echo $this->color_control('speaker_card[border_color]', $speaker_card['border_color'] ?? '', '', 'Border color', 'Speaker card border color');
        echo '</div>';
        $this->border_group_named('speaker_card', 'border', 'Speaker card border', $speaker_card);
        echo '</section>';

        echo '<section class="pa-advanced-panel pa-advanced-panel-wide"><h3>Categories</h3>';
        echo '<label class="pa-checkbox-field"><input type="checkbox" name="categories_all_same" value="1" ' . checked($all_same, true, false) . '> Use one shared color and icon for all categories</label>';
        $this->category_repeater($categories);
        echo '</section>';

        echo '<section class="pa-advanced-panel pa-advanced-panel-wide"><h3>Event Page Settings</h3>';
        $this->program_page_settings_controls('event', $event_settings, 'event_page_settings');
        echo '</section>';

        echo '<section class="pa-advanced-panel pa-advanced-panel-wide"><h3>Speaker Page Settings</h3>';
        $this->program_page_settings_controls('speaker', $speaker_settings, 'speaker_page_settings');
        echo '</section>';
        echo '</div>';

        echo '<p><button class="button button-primary">Save Advanced Settings</button></p>';
        echo '</form></section></div>';
    }

    public function save_program_advanced() {
        check_admin_referer('pa_save_program_advanced');
        $program_id = absint($_POST['program_id'] ?? 0);
        $this->require_edit_pa_post('pa_program', $program_id);

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

        $categories_all_same = !empty($_POST['categories_all_same']) ? '1' : '0';
        update_post_meta($program_id, '_pa_categories_all_same', $categories_all_same);
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
        update_post_meta($program_id, '_pa_categories', $cats);

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

        update_post_meta($program_id, self::META_EVENT_SETTINGS, $this->sanitize_settings($_POST['event_page_settings'] ?? []));
        update_post_meta($program_id, self::META_SPEAKER_SETTINGS, $this->sanitize_settings($_POST['speaker_page_settings'] ?? []));

        wp_safe_redirect(admin_url('admin.php?page=program-advanced-settings&id=' . $program_id . '&saved=1'));
        exit;
    }

    private function normalize_agenda_card_size($value) {
        $value = sanitize_key($value);
        return in_array($value, ['thin','full'], true) ? $value : 'full';
    }

    private function sanitize_tab_border_options($input) {
        if (!is_array($input)) { $input = []; }
        $out = [];
        $out['color'] = sanitize_hex_color($input['color'] ?? '') ?: '';
        $out['lock_radius'] = !empty($input['lock_radius']) ? '1' : '0';
        $out['lock_width'] = !empty($input['lock_width']) ? '1' : '0';
        foreach (['tl','tr','br','bl'] as $key) {
            $out['radius_' . $key] = max(0, intval($input['radius_' . $key] ?? 0));
        }
        foreach (['top','right','bottom','left'] as $key) {
            $out['width_' . $key] = max(0, intval($input['width_' . $key] ?? 0));
        }
        return $out;
    }

    private function sanitize_program_border_options($input) {
        if (!is_array($input)) { $input = []; }
        $border = isset($input['border']) && is_array($input['border']) ? $input['border'] : [];
        $out = [];
        $out['border_width'] = max(0, intval($border['width_top'] ?? ($input['border_width'] ?? 0)));
        $out['border_radius'] = max(0, intval($border['radius_tl'] ?? ($input['border_radius'] ?? 0)));
        $out['border'] = [
            'color' => sanitize_hex_color($border['color'] ?? ($input['border_color'] ?? '')) ?: '',
            'lock_radius' => !empty($border['lock_radius']) ? '1' : '0',
            'lock_width' => !empty($border['lock_width']) ? '1' : '0',
        ];
        foreach (['tl','tr','br','bl'] as $key) {
            $out['border']['radius_' . $key] = max(0, intval($border['radius_' . $key] ?? $out['border_radius']));
        }
        foreach (['top','right','bottom','left'] as $key) {
            $out['border']['width_' . $key] = max(0, intval($border['width_' . $key] ?? $out['border_width']));
        }
        return $out;
    }

    private function category_repeater($categories) {
        $categories = is_array($categories) && $categories ? $categories : [['name'=>'','color'=>'#000000','icon'=>'none']];
        echo '<div class="pa-category-repeater">';
        foreach ($categories as $i=>$cat) {
            $name = $cat['name'] ?? ''; $color = $cat['color'] ?? '#000000'; $icon = $cat['icon'] ?? 'none';
            echo '<div class="pa-category-row">';
            echo '<label>Name<input type="text" name="categories['.$i.'][name]" value="' . esc_attr($name) . '"></label>';
            echo '<label>Color<input type="text" class="pa-color" name="categories['.$i.'][color]" value="' . esc_attr($color) . '"></label>';
            echo '<label>Icon<select name="categories['.$i.'][icon]">';
            foreach ($this->category_icon_options() as $key=>$label) { echo '<option value="'.esc_attr($key).'" '.selected($icon,$key,false).'>'.esc_html($label).'</option>'; }
            echo '</select></label><button type="button" class="button pa-remove-category">Remove</button></div>';
        }
        echo '</div><button type="button" class="button pa-add-category">Add category</button>';
    }

    private function program_speaker_categories($program_id) {
        $categories = get_post_meta($program_id, '_pa_speaker_categories', true);
        if (!is_array($categories)) { $categories = []; }
        return array_values(array_filter(array_map(static function($category) {
            if (is_array($category)) { return sanitize_text_field($category['name'] ?? ''); }
            return sanitize_text_field($category);
        }, $categories)));
    }

    private function speaker_categories_for_program($program_id) {
        return $program_id ? $this->program_speaker_categories($program_id) : [];
    }

    private function program_categories_for_js() {
        $programs = get_posts(['post_type'=>'pa_program','post_status'=>['publish','draft'],'numberposts'=>-1]);
        $data = [];
        foreach ($programs as $program) {
            $cats = get_post_meta($program->ID, '_pa_categories', true);
            if (!is_array($cats)) { $cats = []; }
            $data[$program->ID] = array_values(array_filter(array_map(static function($cat) {
                return is_array($cat) ? sanitize_text_field($cat['name'] ?? '') : sanitize_text_field($cat);
            }, $cats)));
        }
        return $data;
    }

    private function program_dates_for_js() {
        $programs = get_posts(['post_type'=>'pa_program','post_status'=>['publish','draft'],'numberposts'=>-1]);
        $data = [];
        foreach ($programs as $program) {
            $dates = $this->program_date_options($program->ID);
            $data[$program->ID] = $dates;
        }
        return $data;
    }

    private function program_sponsor_levels_for_js() {
        $programs = get_posts(['post_type'=>'pa_program','post_status'=>['publish','draft'],'numberposts'=>-1]);
        $data = [];
        foreach ($programs as $program) {
            $levels = get_post_meta($program->ID, '_pa_sponsor_levels', true);
            if (!is_array($levels)) { $levels = []; }
            $data[$program->ID] = array_values(array_filter(array_unique(array_map('sanitize_text_field', $levels))));
        }
        return $data;
    }

    private function program_date_options($program_id) {
        $dates = [];
        $start = get_post_meta($program_id, '_pa_program_start_date', true);
        $end = get_post_meta($program_id, '_pa_program_end_date', true);
        $this->append_date_range_options($dates, $start, $end);
        $additional = get_post_meta($program_id, '_pa_program_additional_dates', true);
        if (is_array($additional)) {
            foreach ($additional as $range) {
                $this->append_date_range_options($dates, $range['start'] ?? '', $range['end'] ?? '');
            }
        }
        $unique = [];
        foreach ($dates as $date) { $unique[$date] = $date; }
        return array_values($unique);
    }

    private function append_date_range_options(&$dates, $start, $end) {
        $start_ts = $start ? strtotime($start) : false;
        $end_ts = $end ? strtotime($end) : $start_ts;
        if (!$start_ts) { return; }
        if (!$end_ts || $end_ts < $start_ts) { $end_ts = $start_ts; }
        for ($ts = $start_ts; $ts <= $end_ts; $ts = strtotime('+1 day', $ts)) {
            $dates[] = date('Y-m-d', $ts);
        }
    }

    private function category_icon_options() {
        return ['none'=>'None','star'=>'Star','circle'=>'Circle','square'=>'Square','diamond'=>'Diamond','heart'=>'Heart','bolt'=>'Bolt','check'=>'Check','plus'=>'Plus','flag'=>'Flag'];
    }

    private function color_control($name, $value, $fallback = '', $label = '', $aria_label = '') {
        $fallback = $fallback !== '' ? $fallback : '#000000';
        $safe_value = sanitize_hex_color($value) ?: '';
        $swatch_value = $safe_value ?: $fallback;
        $field_id = 'pa-color-' . sanitize_key(str_replace(['[',']'], '-', $name));
        $label_text = $label !== '' ? $label : 'Color';
        $aria_label = $aria_label !== '' ? $aria_label : $label_text;
        return '<label class="pa-color-control" for="' . esc_attr($field_id) . '"><span class="pa-color-control-label">' . esc_html($label_text) . '</span><span class="pa-color-input-wrap"><input id="' . esc_attr($field_id) . '" type="text" class="pa-color" name="' . esc_attr($name) . '" value="' . esc_attr($safe_value) . '" placeholder="' . esc_attr($fallback) . '" aria-label="' . esc_attr($aria_label) . '"><span class="pa-color-swatch" style="background-color:' . esc_attr($swatch_value) . '"></span></span></label>';
    }

    private function program_page_preview($type, $s) {
        $is_speaker = $type === 'speaker';
        echo '<section class="pa-program-page-preview pa-program-page-preview-' . esc_attr($type) . '"><div class="pa-preview-label">Live preview</div>';
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
        foreach (['top'=>'Top','right'=>'Right','bottom'=>'Bottom','left'] as $k=>$lab) { echo '<label>' . esc_html($lab) . '<input class="pa-width-input" type="number" min="0" name="' . $root_attr . '[' . $prefix . '][width_' . esc_attr($k) . ']" value="' . esc_attr($v['width_'.$k] ?? 0) . '" placeholder="0"></label>'; }
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
        echo '<label class="pa-field pa-event-slug-field pa-event-span-full"><span class="pa-field-heading">Page URL Slug</span><input type="text" name="event_slug" value="' . esc_attr($post ? $post->post_name : '') . '" placeholder="auto-generate-from-title"><small>Used in the public event page URL. Leave blank to generate from the title.</small></label>';

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

        echo '<div class="pa-field pa-event-half pa-event-checkbox-block"><span class="pa-field-heading">Invite only</span><label class="pa-checkbox-control"><input type="checkbox" name="event_invite_only" value="1" ' . checked($invite_only, '1', false) . '> <span>Show invite-only warning on event page</span></label></div>';
        echo '<label class="pa-field pa-event-half"><span class="pa-field-heading">Invite-only message</span><textarea name="event_invite_warning" rows="3" placeholder="Invite only. Please contact the event team for access.">' . esc_textarea($id ? get_post_meta($id, '_pa_event_invite_warning', true) : '') . '</textarea></label>';
        echo '<div class="pa-field pa-event-half pa-event-checkbox-block"><span class="pa-field-heading">Add to Calendar</span><label class="pa-checkbox-control"><input type="checkbox" name="event_show_add_to_calendar" value="1" ' . checked($id ? get_post_meta($id, '_pa_event_show_add_to_calendar', true) : '', '1', false) . '> <span>Show add-to-calendar button on event page</span></label></div>';

        echo '</div><div class="pa-event-section pa-event-section-4">';

        echo '<section class="pa-field pa-speaker-picker-field pa-event-span-full"><h3 class="pa-field-heading">Speakers</h3><div class="pa-speaker-picker-toolbar"><input type="search" class="pa-speaker-picker-search" placeholder="Search speakers"><button type="button" class="button pa-select-all-speakers">Select all visible</button></div><div class="pa-speaker-picker">';
        foreach ($speakers as $speaker) {
            $role = get_post_meta($speaker->ID, '_pa_speaker_role_title', true);
            $company = get_post_meta($speaker->ID, '_pa_speaker_company', true);
            $search_terms = strtolower(trim($speaker->post_title . ' ' . $role . ' ' . $company));
            echo '<label data-name="' . esc_attr($search_terms) . '"><input type="checkbox" class="pa-speaker-check" value="' . esc_attr($speaker->ID) . '" ' . checked(in_array($speaker->ID, array_map('intval', $speaker_ids)), true, false) . '> ' . esc_html($speaker->post_title) . '</label>';
        }
        echo '</div><ul class="pa-selected-speakers" data-empty="No speakers selected.">';
        foreach ($speaker_ids as $sid) {
            $sp = get_post($sid);
            if ($sp) {
                $speaker_category = '';
                foreach ([$sid, (string)$sid] as $speaker_category_key) {
                    if (isset($event_speaker_categories[$speaker_category_key])) { $speaker_category = $event_speaker_categories[$speaker_category_key]; break; }
                }
                echo '<li data-id="' . esc_attr($sid) . '"><span class="pa-drag-handle">↕</span><strong>' . esc_html($sp->post_title) . '</strong><button type="button" class="button-link pa-remove-speaker">Remove</button><div class="pa-speaker-event-category"><label>Speaker category<select name="speaker_categories[' . esc_attr($sid) . ']"><option value="">None</option>';
                foreach ($speaker_categories as $speaker_category_option) {
                    echo '<option value="' . esc_attr($speaker_category_option) . '" ' . selected($speaker_category, $speaker_category_option, false) . '>' . esc_html($speaker_category_option) . '</option>';
                }
                echo '</select></label></div></li>';
            }
        }
        echo '</ul><input type="hidden" name="speaker_order" class="pa-speaker-order" value="' . esc_attr(implode(',', array_map('intval', $speaker_ids))) . '"></section>';

        echo '</div><div class="pa-event-section pa-event-section-5">';
        echo '<section class="pa-field pa-sponsor-picker-field pa-event-span-full"><h3 class="pa-field-heading">Sponsors</h3><p class="description">Search and select sponsors to display on this event page.</p><div class="pa-sponsor-picker-toolbar"><input type="search" class="pa-sponsor-picker-search" placeholder="Search sponsors"><button type="button" class="button pa-select-all-sponsors">Select all visible</button></div><div class="pa-sponsor-picker">';
        foreach ($sponsors as $sponsor) {
            $levels = $this->sponsor_all_levels($sponsor->ID);
            $search_terms = strtolower(trim($sponsor->post_title . ' ' . implode(' ', $levels)));
            echo '<label data-name="' . esc_attr($search_terms) . '"><input type="checkbox" class="pa-sponsor-check" value="' . esc_attr($sponsor->ID) . '" ' . checked(in_array($sponsor->ID, array_map('intval', $sponsor_ids)), true, false) . '> ' . esc_html($sponsor->post_title) . '</label>';
        }
        echo '</div><ul class="pa-selected-sponsors" data-empty="No sponsors selected.">';
        foreach ($sponsor_ids as $sponsor_id) {
            $sponsor = get_post($sponsor_id);
            if ($sponsor && $sponsor->post_type === 'pa_sponsor') {
                echo '<li data-id="' . esc_attr($sponsor_id) . '"><span class="pa-drag-handle">↕</span><strong>' . esc_html($sponsor->post_title) . '</strong><button type="button" class="button-link pa-remove-sponsor">Remove</button></li>';
            }
        }
        echo '</ul><input type="hidden" name="sponsor_order" class="pa-sponsor-order" value="' . esc_attr(implode(',', array_map('intval', $sponsor_ids))) . '"></section>';
        echo '</div></div>';
        echo $this->form_actions('Save Event') . '</form></div>';
    }

    public function form_speaker() {
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $post = $id ? get_post($id) : null;
        $style_program_id = $id ? $this->speaker_primary_program_id($id) : 0;
        $programs = get_posts(['post_type'=>'pa_program','post_status'=>['publish','draft'],'numberposts'=>-1,'orderby'=>'title','order'=>'ASC']);
        $speaker_event_ids = $id ? $this->speaker_event_ids($id) : [];
        $all_events = get_posts(['post_type'=>'pa_event','post_status'=>['publish','draft'],'numberposts'=>-1,'meta_key'=>'_pa_event_date','orderby'=>'meta_value','order'=>'ASC']);
        $this->nav('speakers', $post ? $post->post_title : '');
        if (!empty($_GET['saved'])) { echo '<div class="notice notice-success is-dismissible pa-save-notice"><p>Saved successfully!</p></div>'; }
        echo '<h2>' . ($id ? 'Edit Speaker' : 'Add New Speaker') . '</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="pa-form pa-comfortable-form pa-speaker-form">';
        wp_nonce_field('pa_save_speaker');
        echo '<input type="hidden" name="action" value="pa_save_speaker"><input type="hidden" name="id" value="' . esc_attr($id) . '"><input type="hidden" name="pa_post_status" value="publish" class="pa-post-status">';
        echo '<div class="pa-speaker-main-fields">';
        echo '<div class="pa-speaker-name-row"><label class="pa-field">First Name <span>*</span><input required type="text" name="first_name" value="' . esc_attr($id ? get_post_meta($id, '_pa_first_name', true) : '') . '"></label>';
        echo '<label class="pa-field">Last Name <span>*</span><input required type="text" name="last_name" value="' . esc_attr($id ? get_post_meta($id, '_pa_last_name', true) : '') . '"></label></div>';
        echo '<label class="pa-field pa-speaker-slug-field"><span class="pa-field-heading">Page URL Slug</span><input type="text" name="speaker_slug" value="' . esc_attr($post ? $post->post_name : '') . '" placeholder="auto-generate-from-name"><small>Used in the public speaker page URL. Leave blank to generate from the speaker name.</small></label>';
        echo '<div class="pa-speaker-meta-row"><label class="pa-field">Credentials<input type="text" name="credentials" value="' . esc_attr($id ? get_post_meta($id, '_pa_speaker_credentials', true) : '') . '"></label>';
        echo '<label class="pa-field">Role / Title<input type="text" name="role_title" value="' . esc_attr($id ? get_post_meta($id, '_pa_speaker_role_title', true) : '') . '"></label>';
        echo '<label class="pa-field">Company<input type="text" name="company" value="' . esc_attr($id ? get_post_meta($id, '_pa_speaker_company', true) : '') . '"></label></div>';
        echo '<label class="pa-field">Program Style<select name="speaker_style_program_id"><option value="">Use default page style</option>';
        foreach ($programs as $program) { echo '<option value="' . esc_attr($program->ID) . '" ' . selected($style_program_id, $program->ID, false) . '>' . esc_html($program->post_title) . '</option>'; }
        echo '</select><small>Choose which Program\'s Speaker Page and Directory image styling should control this speaker.</small></label>';
        echo '</div>';
        $this->image_field('speaker_image_id', $id ? absint(get_post_meta($id, '_pa_speaker_image_id', true)) : 0, 'Headshot', 'Used on the speaker page, speaker cards, and directory.');
        echo '<div class="pa-editor-field"><label>Bio</label>'; wp_editor($post ? $post->post_content : '', 'speaker_bio', ['textarea_name'=>'speaker_bio','media_buttons'=>false,'textarea_rows'=>8]); echo '</div>';
        echo '<section class="pa-field pa-speaker-events-field"><h3 class="pa-field-heading">Events</h3><p class="description">Select every event this speaker appears on. This updates the event speaker lists automatically.</p><div class="pa-speaker-event-picker">';
        foreach ($all_events as $event) {
            $label = $this->speaker_event_picker_label($event->ID);
            $search_terms = strtolower(trim($label));
            echo '<label data-name="' . esc_attr($search_terms) . '"><input type="checkbox" name="speaker_event_ids[]" value="' . esc_attr($event->ID) . '" ' . checked(in_array(absint($event->ID), array_map('intval', $speaker_event_ids), true), true, false) . '> ' . esc_html($label) . '</label>';
        }
        echo '</div></section>';
        echo '<div class="pa-social-fields"><label class="pa-field">Website<input type="url" name="website" value="' . esc_attr($id ? get_post_meta($id, '_pa_speaker_website', true) : '') . '"></label>';
        echo '<label class="pa-field">LinkedIn<input type="url" name="linkedin" value="' . esc_attr($id ? get_post_meta($id, '_pa_speaker_linkedin', true) : '') . '"></label></div>';
        echo $this->form_actions('Save Speaker') . '</form></div>';
    }

    private function image_field($name, $id, $label, $help = '') {
        $url = $id ? wp_get_attachment_image_url($id, 'thumbnail') : '';
        echo '<div class="pa-image-field"><label>' . esc_html($label) . '</label><input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($id) . '" class="pa-image-id"><div class="pa-image-preview">' . ($url ? '<img src="'.esc_url($url).'" alt="">' : '') . '</div><button type="button" class="button pa-pick-image">Choose image</button><button type="button" class="button pa-remove-image">Remove</button>' . ($help ? '<p class="description">' . esc_html($help) . '</p>' : '') . '</div>';
    }

    public function form_program() {
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $post = $id ? get_post($id) : null;
        $agenda = $id ? get_post_meta($id, '_pa_agenda_settings', true) : [];
        $speaker_card = $id ? get_post_meta($id, '_pa_speaker_card_settings', true) : [];
        if (!is_array($agenda)) { $agenda = []; }
        if (!is_array($speaker_card)) { $speaker_card = []; }
        $cats = $id ? get_post_meta($id, '_pa_categories', true) : [];
        if (!is_array($cats)) { $cats = []; }
        $program_start = $id ? get_post_meta($id, '_pa_program_start_date', true) : '';
        $program_end = $id ? get_post_meta($id, '_pa_program_end_date', true) : '';
        $additional_dates = $id ? get_post_meta($id, '_pa_program_additional_dates', true) : [];
        if (!is_array($additional_dates)) { $additional_dates = []; }
        $all_same = $id ? get_post_meta($id, '_pa_categories_all_same', true) : '';
        $event_settings = $this->settings_for_program($id, 'event');
        $speaker_settings = $this->settings_for_program($id, 'speaker');
        $speaker_categories = $id ? get_post_meta($id, '_pa_speaker_categories', true) : [];
        if (!is_array($speaker_categories)) { $speaker_categories = []; }
        $sponsor_levels = $id ? get_post_meta($id, '_pa_sponsor_levels', true) : [];
        if (!is_array($sponsor_levels)) { $sponsor_levels = []; }
        $primary_sponsor_level = $id ? get_post_meta($id, '_pa_primary_sponsor_level', true) : '';
        if ($primary_sponsor_level !== '' && !in_array($primary_sponsor_level, $sponsor_levels, true)) { $primary_sponsor_level = ''; }
        $this->nav('programs', $post ? $post->post_title : '');
        if (!empty($_GET['saved'])) { echo '<div class="notice notice-success is-dismissible pa-save-notice"><p>Saved successfully!</p></div>'; }
        echo '<h2>' . ($id ? 'Edit Program' : 'Add New Program') . '</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="pa-form pa-comfortable-form pa-program-form">';
        wp_nonce_field('pa_save_program');
        echo '<input type="hidden" name="action" value="pa_save_program"><input type="hidden" name="id" value="' . esc_attr($id) . '"><input type="hidden" name="pa_post_status" value="publish" class="pa-post-status">';
        echo '<label class="pa-field">Program Title <span>*</span><input required type="text" name="program_title" value="' . esc_attr($post ? $post->post_title : '') . '"></label>';
        echo '<div class="pa-program-date-grid">';
        echo '<label class="pa-field">Start Date <input type="date" name="program_start_date" value="' . esc_attr($program_start) . '"></label>';
        echo '<label class="pa-field">End Date <input type="date" name="program_end_date" value="' . esc_attr($program_end) . '"></label>';
        echo '</div>';
        echo '<div class="pa-additional-dates"><h3>Additional Date Ranges</h3><p class="description">Use this if the same Program has sessions on non-contiguous dates.</p><div class="pa-additional-date-list">';
        foreach ($additional_dates as $i => $range) {
            echo '<div class="pa-additional-date-row"><label>Start <input type="date" name="additional_dates[' . intval($i) . '][start]" value="' . esc_attr($range['start'] ?? '') . '"></label><label>End <input type="date" name="additional_dates[' . intval($i) . '][end]" value="' . esc_attr($range['end'] ?? '') . '"></label><button type="button" class="button pa-remove-additional-date">Remove</button></div>';
        }
        echo '</div><button type="button" class="button pa-add-additional-date">Add another date</button></div>';
        echo '<label class="pa-field">Back to Program Link <input type="url" name="back_to_link" value="' . esc_attr($id ? get_post_meta($id, '_pa_back_to_link', true) : '') . '" placeholder="https://example.com/full-program-page"></label>';
        echo '<div class="pa-settings-grid">';
        echo '<div><h3>Agenda Settings</h3><label>Show descriptions<select name="agenda[show_descriptions]"><option value="show" '.selected($agenda['show_descriptions'] ?? '', 'show', false).'>Show</option><option value="hide" '.selected($agenda['show_descriptions'] ?? '', 'hide', false).'>Hide</option></select></label><label>Date display<select name="agenda[date_display]"><option value="numeric" '.selected($agenda['date_display'] ?? 'numeric', 'numeric', false).'>Numeric day</option><option value="abbrev" '.selected($agenda['date_display'] ?? '', 'abbrev', false).'>Abbreviated month + day</option></select></label></div>';
        echo '<div><h3>Card Style</h3><label>Event card style<select name="agenda[card_size]"><option value="full" '.selected($agenda['card_size'] ?? 'full', 'full', false).'>Full cards with speakers</option><option value="thin" '.selected($agenda['card_size'] ?? '', 'thin', false).'>Thin cards without speakers</option></select></label></div>';
        echo '</div>';
        echo '<section class="pa-card pa-categories"><h3>Categories</h3><label><input type="checkbox" name="categories_all_same" value="1" '.checked($all_same, '1', false).'> All categories same color and icon</label>';
        $this->category_repeater($cats);
        echo '</section>';
        echo '<section class="pa-card"><h3>Speaker Categories</h3><p class="description">Optional labels used when assigning speakers to events, such as Panelist, Moderator, or Keynote.</p><div class="pa-speaker-category-repeater">';
        if (!$speaker_categories) { $speaker_categories = [['name'=>'']]; }
        foreach ($speaker_categories as $i => $speaker_category) {
            $speaker_category_name = is_array($speaker_category) ? ($speaker_category['name'] ?? '') : $speaker_category;
            echo '<div class="pa-speaker-category-row"><label>Category name<input type="text" name="speaker_categories[' . intval($i) . '][name]" value="' . esc_attr($speaker_category_name) . '"></label><button type="button" class="button pa-remove-speaker-category">Remove</button></div>';
        }
        echo '</div><button type="button" class="button pa-add-speaker-category">Add speaker category</button></section>';
        echo '<section class="pa-card"><h3>Sponsor Levels</h3><p class="description">Create sponsor levels, such as Platinum, Gold, or Supporting Sponsor. Sponsors can be assigned to one or more levels.</p><div class="pa-sponsor-level-repeater">';
        if (!$sponsor_levels) { $sponsor_levels = ['']; }
        foreach ($sponsor_levels as $i => $level) {
            echo '<div class="pa-sponsor-level-row"><label>Level name<input type="text" name="sponsor_levels[' . intval($i) . ']" value="' . esc_attr($level) . '"></label><button type="button" class="button pa-remove-sponsor-level">Remove</button></div>';
        }
        echo '</div><button type="button" class="button pa-add-sponsor-level">Add sponsor level</button>';
        echo '<label class="pa-field pa-primary-sponsor-level-field">Primary sponsor level<select name="primary_sponsor_level"><option value="">None</option>';
        foreach ($sponsor_levels as $level) {
            $level = sanitize_text_field($level);
            if ($level === '') { continue; }
            echo '<option value="' . esc_attr($level) . '" ' . selected($primary_sponsor_level, $level, false) . '>' . esc_html($level) . '</option>';
        }
        echo '</select><small>Primary sponsors display larger on the sponsor showcase.</small></label>';
        echo '</section>';
        echo '<section class="pa-card"><h3>Event Page Settings</h3><input type="hidden" name="program_event_page_settings_json" value="' . esc_attr(wp_json_encode($event_settings)) . '">'; $this->program_page_settings_controls('event', $event_settings, 'event_page_settings'); echo '</section>';
        echo '<section class="pa-card"><h3>Speaker Page Settings</h3><input type="hidden" name="program_speaker_page_settings_json" value="' . esc_attr(wp_json_encode($speaker_settings)) . '">'; $this->program_page_settings_controls('speaker', $speaker_settings, 'speaker_page_settings'); echo '</section>';
        echo $this->form_actions('Save Program') . '</form></div>';
    }

    public function save_program() {
        check_admin_referer('pa_save_program');
        $id = absint($_POST['id'] ?? 0);
        $this->require_edit_pa_post('pa_program', $id);
        $title = sanitize_text_field($_POST['program_title'] ?? '');
        $postarr = ['post_type'=>'pa_program','post_title'=>$title,'post_status'=>$this->requested_post_status()];
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
        $primary_sponsor_level = sanitize_text_field($_POST['primary_sponsor_level'] ?? '');

if ($primary_sponsor_level !== '' && !in_array($primary_sponsor_level, $sponsor_levels, true)) {
    $primary_sponsor_level = '';
}

update_post_meta($new_id, '_pa_primary_sponsor_level', $primary_sponsor_level);
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
        $event_title = sanitize_text_field($_POST['event_title'] ?? '');
        $event_slug = sanitize_title($_POST['event_slug'] ?? '');
        $postarr = ['post_type'=>'pa_event','post_title'=>$event_title,'post_content'=>wp_kses_post($_POST['event_description'] ?? ''),'post_status'=>$this->requested_post_status(),'post_name'=>($event_slug ?: sanitize_title($event_title))];
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
        $speaker_slug = sanitize_title($_POST['speaker_slug'] ?? '');
        $postarr = ['post_type'=>'pa_speaker','post_title'=>$title,'post_content'=>wp_kses_post($_POST['speaker_bio'] ?? ''),'post_status'=>$this->requested_post_status(),'post_name'=>($speaker_slug ?: sanitize_title($title))];
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
        $sponsor_slug = sanitize_title($_POST['sponsor_slug'] ?? '');
        $postarr = ['post_type'=>'pa_sponsor','post_title'=>$title,'post_content'=>wp_kses_post($_POST['sponsor_bio'] ?? ''),'post_status'=>$this->requested_post_status(),'post_name'=>($sponsor_slug ?: sanitize_title($title))];
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
        if (is_wp_error($rows)) { return $rows; }
        return ['rows'=>$rows, 'images'=>$images, 'temp_dir'=>''];
    }

    private function parse_csv_rows($path) {
        $handle = fopen($path, 'r');
        if (!$handle) { return new WP_Error('pa_csv_open', 'Could not read the CSV file.'); }
        $header = fgetcsv($handle);
        if (!$header) { fclose($handle); return new WP_Error('pa_csv_header', 'CSV file is missing a header row.'); }
        $header = array_map([$this, 'normalize_import_key'], $header);
        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            $row = [];
            foreach ($header as $i => $key) { $row[$key] = $data[$i] ?? ''; }
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }

    private function parse_xlsx_rows($path) {
        if (!class_exists('ZipArchive')) { return new WP_Error('pa_xlsx_missing_zip', 'This server does not support XLSX imports. Upload CSV instead.'); }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) { return new WP_Error('pa_xlsx_open', 'Could not open XLSX file.'); }
        $shared = [];
        $shared_xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($shared_xml) {
            $xml = simplexml_load_string($shared_xml);
            if ($xml) {
                foreach ($xml->si as $si) {
                    $text = '';
                    if (isset($si->t)) { $text = (string)$si->t; }
                    elseif (isset($si->r)) { foreach ($si->r as $r) { $text .= (string)$r->t; } }
                    $shared[] = $text;
                }
            }
        }
        $sheet_name = 'xl/worksheets/sheet1.xml';
        $sheet_xml = $zip->getFromName($sheet_name);
        if (!$sheet_xml) {
            for ($i=1; $i<=10; $i++) {
                $candidate = 'xl/worksheets/sheet'.$i.'.xml';
                $sheet_xml = $zip->getFromName($candidate);
                if ($sheet_xml) { break; }
            }
        }
        if (!$sheet_xml) { $zip->close(); return new WP_Error('pa_xlsx_sheet', 'XLSX file has no readable worksheet.'); }
        $xml = simplexml_load_string($sheet_xml);
        $rows = [];
        foreach ($xml->sheetData->row as $row_node) {
            $row = [];
            foreach ($row_node->c as $cell) {
                $ref = (string)$cell['r'];
                $col = preg_replace('/\d+/', '', $ref);
                $idx = $this->xlsx_col_index($col);
                $type = (string)$cell['t'];
                $value = isset($cell->v) ? (string)$cell->v : '';
                if ($type === 's' && $value !== '' && isset($shared[(int)$value])) { $value = $shared[(int)$value]; }
                elseif ($type === 'inlineStr' && isset($cell->is->t)) { $value = (string)$cell->is->t; }
                $row[$idx] = $value;
            }
            if ($row) {
                ksort($row);
                $max = max(array_keys($row));
                $rows[] = array_replace(array_fill(0, $max + 1, ''), $row);
            }
        }
        $zip->close();
        if (!$rows) { return new WP_Error('pa_xlsx_empty', 'XLSX file has no rows.'); }
        $header = array_map([$this, 'normalize_import_key'], array_shift($rows));
        $assoc = [];
        foreach ($rows as $data) {
            $row = [];
            foreach ($header as $i => $key) { $row[$key] = $data[$i] ?? ''; }
            $assoc[] = $row;
        }
        return $assoc;
    }

    private function xlsx_col_index($letters) {
        $letters = strtoupper($letters);
        $n = 0;
        for ($i=0; $i<strlen($letters); $i++) { $n = $n * 26 + (ord($letters[$i]) - 64); }
        return $n - 1;
    }

    private function normalize_import_key($key) {
        $key = strtolower(trim((string)$key));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key);
        return trim($key, '_');
    }

    private function import_event_row($row, $status, $images) {
        $program_id = $this->find_program_id($row['program'] ?? '');
        if (!$program_id) { return false; }
        $title = sanitize_text_field($row['event_title'] ?? $row['title'] ?? '');
        if ($title === '') { return false; }
        $date = $this->normalize_import_date($row['date'] ?? $row['event_date'] ?? '');
        $start_time = $this->normalize_import_time($row['start_time'] ?? $row['time'] ?? '');
        $end_time = $this->normalize_import_time($row['end_time'] ?? '');
        $location = sanitize_text_field($row['location'] ?? '');
        $description = wp_kses_post($row['description'] ?? '');
        if (!$date || !$start_time || !$location || $description === '') { return false; }
        $category = sanitize_text_field($row['category'] ?? '');
        $event_id = wp_insert_post(['post_type'=>'pa_event','post_status'=>$status,'post_title'=>$title,'post_content'=>$description], true);
        if (is_wp_error($event_id) || !$event_id) { return false; }
        update_post_meta($event_id, '_pa_program_id', $program_id);
        update_post_meta($event_id, '_pa_event_category', $category);
        update_post_meta($event_id, '_pa_event_date', $date);
        update_post_meta($event_id, '_pa_event_time', $start_time);
        update_post_meta($event_id, '_pa_event_end_time', $end_time);
        update_post_meta($event_id, '_pa_event_location', $location);
        update_post_meta($event_id, '_pa_event_location_link', esc_url_raw($row['location_link'] ?? ''));
        if (!empty($row['header_image'])) { update_post_meta($event_id, '_pa_event_image_id', $this->import_image_value($row['header_image'], $images)); }
        $speaker_ids = $this->find_speaker_ids($row['speakers'] ?? '');
        update_post_meta($event_id, '_pa_speaker_ids', $speaker_ids);
        $sponsor_ids = $this->find_sponsor_ids($row['sponsors'] ?? '');
        update_post_meta($event_id, '_pa_sponsor_ids', $sponsor_ids);
        update_post_meta($event_id, '_pa_event_show_add_to_calendar', !empty($row['show_add_to_calendar']) ? '1' : '0');
        if ($category !== '') { $this->ensure_program_category($program_id, $category); }
        return true;
    }

    private function import_speaker_row($row, $status, $images) {
        $first = sanitize_text_field($row['first_name'] ?? '');
        $last = sanitize_text_field($row['last_name'] ?? '');
        $name = sanitize_text_field($row['speaker_name'] ?? $row['name'] ?? trim($first . ' ' . $last));
        if ($name === '') { return false; }
        if ($first === '' || $last === '') {
            $parts = preg_split('/\s+/', $name);
            if ($first === '') { $first = array_shift($parts); }
            if ($last === '') { $last = implode(' ', $parts); }
        }
        $speaker_id = wp_insert_post(['post_type'=>'pa_speaker','post_status'=>$status,'post_title'=>$name,'post_content'=>wp_kses_post($row['bio'] ?? '')], true);
        if (is_wp_error($speaker_id) || !$speaker_id) { return false; }
        update_post_meta($speaker_id, '_pa_first_name', $first);
        update_post_meta($speaker_id, '_pa_last_name', $last);
        update_post_meta($speaker_id, '_pa_speaker_role_title', sanitize_text_field($row['role_title'] ?? $row['title'] ?? ''));
        update_post_meta($speaker_id, '_pa_speaker_company', sanitize_text_field($row['company'] ?? ''));
        update_post_meta($speaker_id, '_pa_speaker_credentials', sanitize_text_field($row['credentials'] ?? ''));
        update_post_meta($speaker_id, '_pa_speaker_website', esc_url_raw($row['website'] ?? ''));
        update_post_meta($speaker_id, '_pa_speaker_linkedin', esc_url_raw($row['linkedin'] ?? ''));
        $program_id = $this->find_program_id($row['program'] ?? '');
        if ($program_id) { update_post_meta($speaker_id, '_pa_speaker_style_program_id', $program_id); }
        if (!empty($row['headshot_image'])) { update_post_meta($speaker_id, '_pa_speaker_image_id', $this->import_image_value($row['headshot_image'], $images)); }
        return true;
    }

    private function import_sponsor_row($row, $status, $images) {
        $company = sanitize_text_field($row['company_name'] ?? $row['company'] ?? '');
        if ($company === '') { return false; }
        $sponsor_id = wp_insert_post(['post_type'=>'pa_sponsor','post_status'=>$status,'post_title'=>$company,'post_content'=>wp_kses_post($row['bio'] ?? '')], true);
        if (is_wp_error($sponsor_id) || !$sponsor_id) { return false; }
        $program_assignments = $this->parse_sponsor_program_assignments($row['programs'] ?? $row['program'] ?? '', $row['sponsor_levels'] ?? $row['level'] ?? '');
        $program_ids = array_keys($program_assignments);
        update_post_meta($sponsor_id, '_pa_sponsor_program_ids', array_values(array_map('absint', $program_ids)));
        update_post_meta($sponsor_id, '_pa_sponsor_program_id', $program_ids ? absint($program_ids[0]) : 0);
        update_post_meta($sponsor_id, '_pa_sponsor_program_levels', $program_assignments);
        $all_levels = [];
        foreach ($program_assignments as $levels) { foreach ((array)$levels as $level) { $all_levels[] = $level; } }
        update_post_meta($sponsor_id, '_pa_sponsor_levels', array_values(array_filter(array_unique(array_map('sanitize_text_field', $all_levels)))));
        update_post_meta($sponsor_id, '_pa_sponsor_website', esc_url_raw($row['sponsor_website'] ?? $row['website'] ?? ''));
        if (!empty($row['logo_image'])) { update_post_meta($sponsor_id, '_pa_sponsor_logo_id', $this->import_image_value($row['logo_image'], $images)); }
        return true;
    }

    private function parse_sponsor_program_assignments($programs_value, $levels_value) {
        $assignments = [];
        $program_chunks = array_filter(array_map('trim', preg_split('/[|;]+/', (string)$programs_value)));
        $level_chunks = array_filter(array_map('trim', preg_split('/[|;]+/', (string)$levels_value)));
        foreach ($program_chunks as $index => $chunk) {
            $program_name = $chunk;
            $levels = [];
            if (strpos($chunk, ':') !== false) {
                [$program_name, $level_part] = array_map('trim', explode(':', $chunk, 2));
                $levels = array_filter(array_map('trim', explode(',', $level_part)));
            } elseif (isset($level_chunks[$index])) {
                $level_part = $level_chunks[$index];
                if (strpos($level_part, ':') !== false) { [, $level_part] = array_map('trim', explode(':', $level_part, 2)); }
                $levels = array_filter(array_map('trim', explode(',', $level_part)));
            }
            $program_id = $this->find_program_id($program_name);
            if (!$program_id) { continue; }
            $assignments[(string) $program_id] = array_values(array_filter(array_unique(array_map('sanitize_text_field', $levels))));
        }
        return $assignments;
    }

    private function find_program_id($name) {
        $name = trim((string)$name);
        if ($name === '') { return 0; }
        $posts = get_posts(['post_type'=>'pa_program','post_status'=>['publish','draft'],'title'=>$name,'numberposts'=>1]);
        if ($posts) { return $posts[0]->ID; }
        $posts = get_posts(['post_type'=>'pa_program','post_status'=>['publish','draft'],'numberposts'=>-1]);
        foreach ($posts as $post) { if (strtolower($post->post_title) === strtolower($name)) { return $post->ID; } }
        return 0;
    }

    private function find_speaker_ids($value) {
        $names = array_filter(array_map('trim', explode(',', (string)$value)));
        $ids = [];
        foreach ($names as $name) { $id = $this->find_post_id_by_title('pa_speaker', $name); if ($id) { $ids[] = $id; } }
        return $ids;
    }

    private function find_sponsor_ids($value) {
        $names = array_filter(array_map('trim', explode(',', (string)$value)));
        $ids = [];
        foreach ($names as $name) { $id = $this->find_post_id_by_title('pa_sponsor', $name); if ($id) { $ids[] = $id; } }
        return $ids;
    }

    private function find_post_id_by_title($post_type, $title) {
        $title = trim((string)$title);
        if ($title === '') { return 0; }
        $posts = get_posts(['post_type'=>$post_type,'post_status'=>['publish','draft'],'title'=>$title,'numberposts'=>1]);
        if ($posts) { return $posts[0]->ID; }
        $posts = get_posts(['post_type'=>$post_type,'post_status'=>['publish','draft'],'numberposts'=>-1]);
        foreach ($posts as $post) { if (strtolower($post->post_title) === strtolower($title)) { return $post->ID; } }
        return 0;
    }

    private function ensure_program_category($program_id, $category_name) {
        $category_name = sanitize_text_field($category_name);
        if ($program_id <= 0 || $category_name === '') { return; }
        $cats = get_post_meta($program_id, '_pa_categories', true);
        if (!is_array($cats)) { $cats = []; }
        foreach ($cats as $cat) { if (isset($cat['name']) && strtolower($cat['name']) === strtolower($category_name)) { return; } }
        $all_same = get_post_meta($program_id, '_pa_categories_all_same', true) === '1';
        $base = $all_same && !empty($cats[0]) && is_array($cats[0]) ? $cats[0] : ['color'=>'#000000', 'icon'=>'none'];
        $cats[] = ['name'=>$category_name, 'color'=>sanitize_hex_color($base['color'] ?? '') ?: '#000000', 'icon'=>sanitize_key($base['icon'] ?? 'none')];
        update_post_meta($program_id, '_pa_categories', $cats);
    }

    private function normalize_import_date($value) {
        $value = trim((string)$value);
        if ($value === '') { return ''; }
        if (is_numeric($value)) {
            $unix = ((float)$value - 25569) * 86400;
            return gmdate('Y-m-d', (int)$unix);
        }
        $ts = strtotime($value);
        return $ts ? date('Y-m-d', $ts) : '';
    }

    private function normalize_import_time($value) {
        $value = trim((string)$value);
        if ($value === '') { return ''; }
        if (is_numeric($value) && (float)$value < 1) {
            $seconds = round((float)$value * 86400);
            return gmdate('H:i', $seconds);
        }
        $ts = strtotime($value);
        return $ts ? date('H:i', $ts) : '';
    }

    private function import_image_value($value, $images) {
        $value = trim((string)$value);
        if ($value === '') { return 0; }
        $lower = strtolower(basename($value));
        if (isset($images[$lower])) { return $this->sideload_image($images[$lower]); }
        if (filter_var($value, FILTER_VALIDATE_URL)) { return $this->sideload_image($value); }
        return 0;
    }

    private function sideload_image($source) {
        if (!function_exists('media_handle_sideload')) { require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/image.php'; }
        if (filter_var($source, FILTER_VALIDATE_URL)) {
            $tmp = download_url($source);
            if (is_wp_error($tmp)) { return 0; }
            $name = basename(parse_url($source, PHP_URL_PATH));
        } else {
            $tmp = $source;
            $name = basename($source);
        }
        $file = ['name'=>$name, 'tmp_name'=>$tmp];
        $id = media_handle_sideload($file, 0);
        if (is_wp_error($id)) { if (file_exists($tmp)) { @unlink($tmp); } return 0; }
        return $id;
    }

    private function cleanup_import_temp_dir($dir) {
        if (!$dir || !is_dir($dir)) { return; }
        $files = scandir($dir);
        if ($files) { foreach ($files as $file) { if ($file !== '.' && $file !== '..') { @unlink($dir . '/' . $file); } } }
        @rmdir($dir);
    }

    public function duplicate_item() {
        $id = absint($_GET['id'] ?? 0);
        check_admin_referer('pa_duplicate_item_' . $id);
        $post = get_post($id);
        if (!$post || !in_array($post->post_type, ['pa_event','pa_speaker','pa_sponsor'], true) || !current_user_can('edit_post', $id)) {
            wp_die(esc_html__('You are not allowed to duplicate this item.', 'program-agenda'));
        }
        $copy_id = wp_insert_post([
            'post_type' => $post->post_type,
            'post_status' => 'draft',
            'post_title' => $post->post_title . ' Copy',
            'post_content' => $post->post_content,
            'post_author' => get_current_user_id(),
        ], true);
        $copy_id = $this->ensure_saved_post_id($copy_id);
        $meta = get_post_meta($id);
        foreach ($meta as $key=>$values) {
            if (strpos($key, '_pa_') !== 0) { continue; }
            foreach ($values as $value) { add_post_meta($copy_id, $key, maybe_unserialize($value)); }
        }
        $page = $post->post_type === 'pa_event' ? 'program-edit-event' : ($post->post_type === 'pa_speaker' ? 'program-edit-speaker' : 'program-edit-sponsor');
        wp_safe_redirect(admin_url('admin.php?page=' . $page . '&id=' . $copy_id . '&saved=1')); exit;
    }

    private function require_edit_pa_post($post_type, $id = 0) {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('You are not allowed to edit this content.', 'program-agenda'));
        }
        if ($id) {
            $post = get_post($id);
            if (!$post || $post->post_type !== $post_type || !current_user_can('edit_post', $id)) {
                wp_die(esc_html__('You are not allowed to edit this item.', 'program-agenda'));
            }
        }
    }

    private function ensure_saved_post_id($result) {
        if (is_wp_error($result) || !$result) {
            wp_die(esc_html__('Could not save. Please try again.', 'program-agenda'));
        }
        return (int)$result;
    }

    private function settings_for_program($program_id, $type) {
        $meta = $type === 'speaker' ? self::META_SPEAKER_SETTINGS : self::META_EVENT_SETTINGS;
        $saved = $program_id ? get_post_meta($program_id, $meta, true) : [];
        if (!is_array($saved) || !$saved) { $saved = get_option($type === 'speaker' ? self::OPT_SPEAKER : self::OPT_EVENT, []); }
        return is_array($saved) ? $saved : [];
    }

    private function sanitize_settings($in) {
        $in = is_array($in) ? $in : [];
        $out = [];
        foreach (['header_bg','header_color','content_bg','content_color','image_border_color','directory_image_border_color'] as $k) { $out[$k] = sanitize_hex_color($in[$k] ?? '') ?: ''; }
        foreach (['header_border','content_border'] as $border_key) {
            $out[$border_key] = $this->sanitize_border_group($in[$border_key] ?? []);
        }
        $out['image_shape'] = in_array(($in['image_shape'] ?? ''), ['','square','circle'], true) ? $in['image_shape'] : '';
        $out['image_border_width'] = max(0, intval($in['image_border_width'] ?? 0));
        $out['directory_image_shape'] = in_array(($in['directory_image_shape'] ?? ''), ['','square','circle'], true) ? $in['directory_image_shape'] : '';
        $out['directory_image_border_width'] = max(0, intval($in['directory_image_border_width'] ?? 0));
        return $out;
    }

    private function sanitize_border_group($input) {
        if (!is_array($input)) { $input = []; }
        $out = [];
        $out['color'] = sanitize_hex_color($input['color'] ?? '') ?: '';
        $out['lock_radius'] = !empty($input['lock_radius']) ? '1' : '0';
        $out['lock_width'] = !empty($input['lock_width']) ? '1' : '0';
        foreach (['tl','tr','br','bl'] as $key) {
            $out['radius_' . $key] = max(0, intval($input['radius_' . $key] ?? 0));
        }
        foreach (['top','right','bottom','left'] as $key) {
            $out['width_' . $key] = max(0, intval($input['width_' . $key] ?? 0));
        }
        return $out;
    }

    private function inline_header_style($s) {
        $style = '';
        if (!empty($s['header_bg'])) { $style .= 'background-color:' . esc_attr($s['header_bg']) . ';'; }
        if (!empty($s['header_color'])) { $style .= 'color:' . esc_attr($s['header_color']) . ';'; }
        if (!empty($s['header_border'])) { $style .= $this->border_style_from_group($s['header_border'], 'border'); }
        return $style;
    }

    private function inline_content_style($s) {
        $style = '';
        if (!empty($s['content_bg'])) { $style .= 'background-color:' . esc_attr($s['content_bg']) . ';'; }
        if (!empty($s['content_color'])) { $style .= 'color:' . esc_attr($s['content_color']) . ';'; }
        if (!empty($s['content_border'])) { $style .= $this->border_style_from_group($s['content_border'], 'border'); }
        return $style;
    }

    private function preview_image_style($s) {
        $style = '';
        $shape = $s['image_shape'] ?? '';
        if ($shape === 'circle') { $style .= 'border-radius:999px;'; }
        elseif ($shape === 'square') { $style .= 'border-radius:0;'; }
        if (!empty($s['image_border_width'])) { $style .= 'border-width:' . intval($s['image_border_width']) . 'px;border-style:solid;'; }
        if (!empty($s['image_border_color'])) { $style .= 'border-color:' . esc_attr($s['image_border_color']) . ';'; }
        return $style;
    }

    private function border_style_from_group($border, $prop = 'border') {
        if (!is_array($border)) { return ''; }
        $color = sanitize_hex_color($border['color'] ?? '') ?: '';
        $parts = '';
        $widths = [];
        foreach (['top','right','bottom','left'] as $side) { $widths[$side] = max(0, intval($border['width_' . $side] ?? 0)); }
        $radii = [];
        foreach (['tl','tr','br','bl'] as $corner) { $radii[$corner] = max(0, intval($border['radius_' . $corner] ?? 0)); }
        if (array_sum($widths) > 0) {
            foreach ($widths as $side=>$width) { $parts .= 'border-' . $side . '-width:' . $width . 'px;border-' . $side . '-style:solid;'; }
            if ($color) { $parts .= 'border-color:' . esc_attr($color) . ';'; }
        }
        if (array_sum($radii) > 0) {
            $parts .= 'border-radius:' . $radii['tl'] . 'px ' . $radii['tr'] . 'px ' . $radii['br'] . 'px ' . $radii['bl'] . 'px;';
        }
        return $parts;
    }

    private function style_attr_from_settings($s, $part) {
        if (!is_array($s)) { return ''; }
        $style = $part === 'header' ? $this->inline_header_style($s) : $this->inline_content_style($s);
        return $style ? ' style="' . esc_attr($style) . '"' : '';
    }

    private function speaker_image_style_attr($s, $context = 'single') {
        if (!is_array($s)) { $s = []; }
        $shape_key = $context === 'directory' ? 'directory_image_shape' : 'image_shape';
        $width_key = $context === 'directory' ? 'directory_image_border_width' : 'image_border_width';
        $color_key = $context === 'directory' ? 'directory_image_border_color' : 'image_border_color';
        $style = '';
        $shape = $s[$shape_key] ?? '';
        if ($shape === 'circle') { $style .= 'border-radius:999px;'; }
        elseif ($shape === 'square') { $style .= 'border-radius:0;'; }
        $width = max(0, intval($s[$width_key] ?? 0));
        if ($width > 0) { $style .= 'border-width:' . $width . 'px;border-style:solid;'; }
        $color = sanitize_hex_color($s[$color_key] ?? '') ?: '';
        if ($color) { $style .= 'border-color:' . esc_attr($color) . ';'; }
        return $style ? ' style="' . esc_attr($style) . '"' : '';
    }

    public function shortcode_program_agenda($atts) {
        $atts = shortcode_atts(['id'=>0], $atts, 'program_agenda');
        $program_id = $this->resolve_program_shortcode_id($atts['id']);
        if (!$program_id) { return '<p>Program not found.</p>'; }
        $settings = get_post_meta($program_id, '_pa_agenda_settings', true);
        if (!is_array($settings)) { $settings = []; }
        $card_size = $this->normalize_agenda_card_size($settings['card_size'] ?? 'full');
        $show = get_post_meta($program_id, '_pa_show_event_descriptions', true) === 'show';
        $date_display = in_array(($settings['date_display'] ?? 'numeric'), ['numeric','abbrev'], true) ? $settings['date_display'] : 'numeric';
        $display_mode = ($settings['display_mode'] ?? 'tabs') === 'stacked' ? 'stacked' : 'tabs';
        $events = get_posts(['post_type'=>'pa_event','post_status'=>'publish','numberposts'=>-1,'meta_key'=>'_pa_event_date','orderby'=>'meta_value','order'=>'ASC','meta_query'=>[['key'=>'_pa_program_id','value'=>$program_id,'compare'=>'=']]]);
        $by_date = [];
        foreach ($events as $event) {
            $date = get_post_meta($event->ID, '_pa_event_date', true) ?: '';
            $key = $date ? date('Y-m-d', strtotime($date)) : 'unscheduled';
            if (!isset($by_date[$key])) { $by_date[$key] = []; }
            $by_date[$key][] = $event;
        }
        ksort($by_date);
        $style = '';
        if (!empty($settings['background'])) { $style .= '--pa-agenda-bg:' . esc_attr($settings['background']) . ';'; }
        if (!empty($settings['accent_bar_color'])) { $style .= '--pa-agenda-bar-color:' . esc_attr($settings['accent_bar_color']) . ';'; }
        if (!empty($settings['title_color'])) { $style .= '--pa-agenda-title-color:' . esc_attr($settings['title_color']) . ';'; }
        if (!empty($settings['location_color'])) { $style .= '--pa-agenda-location-color:' . esc_attr($settings['location_color']) . ';'; }
        if (!empty($settings['tab_background_color'])) { $style .= '--pa-agenda-tab-bg:' . esc_attr($settings['tab_background_color']) . ';'; }
        if (!empty($settings['tab_border_color'])) { $style .= '--pa-agenda-tab-border-color:' . esc_attr($settings['tab_border_color']) . ';'; }
        if (!empty($settings['border_color'])) { $style .= '--pa-agenda-card-border:' . esc_attr($settings['border_color']) . ';'; }
        $style .= $this->css_vars_for_border($settings, 'border', 'pa-agenda-card');
        $style .= $this->css_vars_for_border($settings, 'tab_border', 'pa-agenda-tab');
        ob_start();
        echo '<div class="pa-schedule pa-card-size-' . esc_attr($card_size) . ' pa-date-display-' . esc_attr($date_display) . ' pa-display-' . esc_attr($display_mode) . '" style="' . esc_attr($style) . '">';
        if (!$events) { echo '<p>No events found.</p></div>'; return ob_get_clean(); }
        $tabs_id = 'pa-tabs-' . $program_id . '-' . wp_rand(1000, 9999);
        if ($display_mode === 'tabs') {
            echo '<div class="pa-date-tabs" role="tablist">';
            $i=0;
            foreach ($by_date as $date=>$items) {
                $label = $date === 'unscheduled' ? 'Unscheduled' : date_i18n('M j', strtotime($date));
                echo '<button type="button" class="pa-date-tab' . ($i===0?' active':'') . '" role="tab" aria-selected="' . ($i===0?'true':'false') . '" aria-controls="' . esc_attr($tabs_id . '-' . $i) . '">' . esc_html($label) . '</button>';
                $i++;
            }
            echo '</div>';
        }
        $i=0;
        foreach ($by_date as $date=>$items) {
            $panel_label = $date === 'unscheduled' ? 'Unscheduled' : date_i18n('F j, Y', strtotime($date));
            echo '<section id="' . esc_attr($tabs_id . '-' . $i) . '" class="pa-date-panel' . ($i===0?' active':'') . '"' . ($display_mode === 'tabs' && $i!==0 ? ' hidden' : '') . '>';
            if ($display_mode === 'stacked') { echo '<h3 class="pa-stacked-date-heading">' . esc_html($panel_label) . '</h3>'; }
            foreach ($items as $event) { echo $this->event_card($event->ID, $show, $settings, $program_id); }
            echo '</section>';
            $i++;
        }
        echo '</div>';
        return ob_get_clean();
    }

    private function css_vars_for_border($settings, $key, $prefix) {
        $border = isset($settings[$key]) && is_array($settings[$key]) ? $settings[$key] : [];
        $css = '';
        if (!empty($border['color'])) { $css .= '--' . $prefix . '-border-color:' . esc_attr($border['color']) . ';'; }
        foreach (['tl','tr','br','bl'] as $corner) {
            if (isset($border['radius_' . $corner])) { $css .= '--' . $prefix . '-radius-' . $corner . ':' . max(0, intval($border['radius_' . $corner])) . 'px;'; }
        }
        foreach (['top','right','bottom','left'] as $side) {
            if (isset($border['width_' . $side])) { $css .= '--' . $prefix . '-border-' . $side . ':' . max(0, intval($border['width_' . $side])) . 'px;'; }
        }
        return $css;
    }

    private function event_card($event_id, $show_description, $settings = [], $program_id = 0) {
        $event = get_post($event_id);
        if (!$event) { return ''; }
        $cat = get_post_meta($event_id, '_pa_event_category', true);
        $cats = $program_id ? get_post_meta($program_id, '_pa_categories', true) : [];
        $cat_data = ['color'=>'var(--pa-agenda-bar-color,#1d2327)','icon'=>'none'];
        if (is_array($cats)) {
            foreach ($cats as $c) { if (($c['name'] ?? '') === $cat) { $cat_data = $c; break; } }
        }
        $date = get_post_meta($event_id, '_pa_event_date', true);
        $time = get_post_meta($event_id, '_pa_event_time', true);
        $end = get_post_meta($event_id, '_pa_event_end_time', true);
        $loc = get_post_meta($event_id, '_pa_event_location', true);
        $loc_link = get_post_meta($event_id, '_pa_event_location_link', true);
        $date_display = in_array(($settings['date_display'] ?? 'numeric'), ['numeric','abbrev'], true) ? $settings['date_display'] : 'numeric';
        $card_size = $this->normalize_agenda_card_size($settings['card_size'] ?? 'full');
        $datebar = $this->event_card_datebar($date, $cat_data, $date_display);
        $time_text = $this->format_time_range($time, $end);
        $meta_bits = [];
        if ($time_text) { $meta_bits[] = $time_text; }
        if ($loc) {
            $loc_html = $loc_link ? '<a href="' . esc_url($loc_link) . '" target="_blank" rel="noopener">' . esc_html($loc) . '</a>' : esc_html($loc);
            $meta_bits[] = $loc_html;
        }
        $style = '';
        if (!empty($cat_data['color'])) { $style .= '--pa-event-category-color:' . esc_attr($cat_data['color']) . ';'; }
        $out = '<article class="pa-event-card pa-event-card-' . esc_attr($card_size) . '" style="' . esc_attr($style) . '">';
        $out .= $datebar;
        $out .= '<div class="pa-event-card__body"><h3 class="pa-event-card__title"><a href="' . esc_url(get_permalink($event_id)) . '">' . esc_html(get_the_title($event_id)) . '</a></h3>';
        if ($meta_bits) { $out .= '<p class="pa-event-card__meta">' . implode(' <span aria-hidden="true">•</span> ', $meta_bits) . '</p>'; }
        if ($show_description && $event->post_content) { $out .= '<div class="pa-event-card__desc">' . wp_kses_post(wpautop($event->post_content)) . '</div>'; }
        if ($card_size !== 'thin') {
            $speaker_ids = get_post_meta($event_id, '_pa_speaker_ids', true);
            if (is_array($speaker_ids) && $speaker_ids) { $out .= $this->speaker_card_list($speaker_ids, $program_id, 'agenda'); }
        }
        $out .= '</div></article>';
        return $out;
    }

    private function event_card_datebar($date, $cat_data, $date_display = 'numeric') {
        $date_ts = $date ? strtotime($date) : false;
        if ($date_ts && $date_display === 'abbrev') {
            $month = date_i18n('M', $date_ts);
            $day = date_i18n('j', $date_ts);
            return '<div class="pa-event-card__datebar"><span class="pa-date-month">' . esc_html($month) . '</span><span class="pa-date-day">' . esc_html($day) . '</span></div>';
        }
        if ($date_ts) {
            $day = date_i18n('j', $date_ts);
            return '<div class="pa-event-card__datebar"><span class="pa-date-day">' . esc_html($day) . '</span></div>';
        }
        return '<div class="pa-event-card__datebar"><span class="pa-date-day">–</span></div>';
    }

    private function speaker_card_list($ids, $program_id = 0, $context = 'default') {
        $style = '';
        if ($program_id) {
            $settings = get_post_meta($program_id, '_pa_speaker_card_settings', true);
            if (is_array($settings)) {
                if (!empty($settings['background'])) { $style .= '--pa-speaker-card-bg:' . esc_attr($settings['background']) . ';'; }
                if (!empty($settings['color'])) { $style .= '--pa-speaker-card-color:' . esc_attr($settings['color']) . ';'; }
                if (!empty($settings['border_color'])) { $style .= '--pa-speaker-card-border-color:' . esc_attr($settings['border_color']) . ';'; }
                $style .= $this->css_vars_for_border($settings, 'border', 'pa-speaker-card');
            }
        }
        $class = $context === 'agenda' ? 'pa-speaker-card-list pa-speaker-card-list-agenda' : 'pa-speaker-card-list';
        $out = '<div class="' . esc_attr($class) . '" style="' . esc_attr($style) . '">';
        foreach ($ids as $id) { $out .= $this->speaker_card($id, $program_id); }
        $out .= '</div>';
        return $out;
    }

    private function speaker_card($speaker_id, $program_id = 0) {
        $post = get_post($speaker_id); if (!$post) { return ''; }
        $settings = $program_id ? get_post_meta($program_id, '_pa_speaker_card_settings', true) : [];
        if (!is_array($settings)) { $settings = []; }
        $show_thumb = !empty($settings['show_thumbnail']);
        $shape = $settings['thumbnail_shape'] ?? 'theme';
        $image_id = absint(get_post_meta($speaker_id, '_pa_speaker_image_id', true));
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';
        $role = get_post_meta($speaker_id, '_pa_speaker_role_title', true);
        $company = get_post_meta($speaker_id, '_pa_speaker_company', true);
        $creds = get_post_meta($speaker_id, '_pa_speaker_credentials', true);
        $classes = 'pa-speaker-card';
        if (!$show_thumb) { $classes .= ' no-thumb'; }
        $out = '<a class="' . esc_attr($classes) . '" href="' . esc_url(get_permalink($speaker_id)) . '">';
        if ($show_thumb) {
            $img_class = 'pa-speaker-card-thumb';
            if ($shape === 'circle') { $img_class .= ' is-circle'; }
            if ($shape === 'square') { $img_class .= ' is-square'; }
            $out .= '<span class="pa-speaker-card-image">';
            $out .= $image_url ? '<img class="' . esc_attr($img_class) . '" src="' . esc_url($image_url) . '" alt="' . esc_attr($post->post_title) . '">' : '<span class="pa-speaker-card-placeholder ' . esc_attr($shape === 'circle' ? 'is-circle' : ($shape === 'square' ? 'is-square' : '')) . '"></span>';
            $out .= '</span>';
        }
        $out .= '<span class="pa-speaker-card-text"><h3>' . esc_html($post->post_title . ($creds ? ', ' . $creds : '')) . '</h3>';
        if ($role) { $out .= '<p>' . esc_html($role) . '</p>'; }
        if ($company) { $out .= '<p>' . esc_html($company) . '</p>'; }
        $out .= '</span></a>';
        return $out;
    }

    public function shortcode_program_sponsors($atts) {
        $atts = shortcode_atts(['id'=>0], $atts, 'program_sponsors');
        $program_id = $this->resolve_program_shortcode_id($atts['id']);
        if (!$program_id) { return '<p>Program not found.</p>'; }
        $primary_level = get_post_meta($program_id, '_pa_primary_sponsor_level', true);
        $sponsors = get_posts([
            'post_type'=>'pa_sponsor',
            'post_status'=>'publish',
            'numberposts'=>-1,
            'orderby'=>'title',
            'order'=>'ASC',
            'meta_query'=>[
                [
                    'key'=>'_pa_sponsor_program_ids',
                    'value'=>'i:' . $program_id . ';',
                    'compare'=>'LIKE',
                ],
            ],
        ]);
        $grouped = [];
        foreach ($sponsors as $sponsor) {
            $levels = $this->sponsor_levels_for_program($sponsor->ID, $program_id);
            if (!$levels) { $levels = ['Sponsors']; }
            foreach ($levels as $level) {
                if (!isset($grouped[$level])) { $grouped[$level] = []; }
                $grouped[$level][] = $sponsor;
            }
        }
        if (!$grouped) { return '<p>No sponsors found.</p>'; }
        $ordered = [];
        if ($primary_level !== '' && isset($grouped[$primary_level])) {
            $ordered[$primary_level] = $grouped[$primary_level];
            unset($grouped[$primary_level]);
        }
        foreach ($grouped as $level => $items) { $ordered[$level] = $items; }
        ob_start();
        echo '<div class="pa-program-sponsors">';
        foreach ($ordered as $level=>$items) {
            $is_primary = ($primary_level !== '' && $level === $primary_level);
            echo '<section class="pa-sponsor-level ' . ($is_primary ? 'pa-sponsor-level-primary' : '') . '"><h2>' . esc_html($level) . '</h2><div class="pa-sponsor-grid">';
            foreach ($items as $sponsor) {
                $logo_id = absint(get_post_meta($sponsor->ID, '_pa_sponsor_logo_id', true));
                $logo = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';
                $website = get_post_meta($sponsor->ID, '_pa_sponsor_website', true);
                $inner = $logo ? '<img src="' . esc_url($logo) . '" alt="' . esc_attr($sponsor->post_title) . '">' : '<span>' . esc_html($sponsor->post_title) . '</span>';
                $href = $website ?: get_permalink($sponsor->ID);
                echo '<a class="pa-sponsor-logo-card" href="' . esc_url($href) . '" target="_blank" rel="noopener">' . $inner . '</a>';
            }
            echo '</div></section>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    public function shortcode_program_speakers($atts) {
        $atts = shortcode_atts(['id'=>0], $atts, 'program_speakers');
        $program_id = $this->resolve_program_shortcode_id($atts['id']);
        if (!$program_id) { return '<p>Program not found.</p>'; }
        $events = get_posts([
            'post_type'=>'pa_event','post_status'=>'publish','numberposts'=>-1,
            'meta_query'=>[['key'=>'_pa_program_id','value'=>$program_id,'compare'=>'=']],
        ]);
        $speaker_ids = [];
        $speaker_categories = [];
        foreach ($events as $event) {
            $ids = get_post_meta($event->ID, '_pa_speaker_ids', true);
            if (is_array($ids)) {
                $event_speaker_categories = get_post_meta($event->ID, '_pa_event_speaker_categories', true);
                if (!is_array($event_speaker_categories)) { $event_speaker_categories = []; }
                foreach ($ids as $id) {
                    $id = absint($id);
                    if ($id) { $speaker_ids[$id] = true; }
                    if ($id) {
                        foreach ([$id, (string)$id] as $category_key) {
                            if (!empty($event_speaker_categories[$category_key])) {
                                $speaker_categories[$id] = sanitize_text_field($event_speaker_categories[$category_key]);
                                break;
                            }
                        }
                    }
                }
            }
        }
        $speaker_ids = array_keys($speaker_ids);
        if (!$speaker_ids) { return '<p>No speakers found.</p>'; }
        $speakers = get_posts(['post_type'=>'pa_speaker','post_status'=>'publish','numberposts'=>-1,'post__in'=>$speaker_ids,'orderby'=>'title','order'=>'ASC']);
        $settings = $this->settings_for_program($program_id, 'speaker');
        ob_start();
        echo '<div class="pa-program-speakers">';
        echo '<div class="pa-program-speakers-grid">';
        foreach ($speakers as $speaker) {
            $image_id = absint(get_post_meta($speaker->ID, '_pa_speaker_image_id', true));
            $image = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';
            $role = get_post_meta($speaker->ID, '_pa_speaker_role_title', true);
            $company = get_post_meta($speaker->ID, '_pa_speaker_company', true);
            $credentials = get_post_meta($speaker->ID, '_pa_speaker_credentials', true);
            $category = $speaker_categories[$speaker->ID] ?? '';
            echo '<article class="pa-program-speaker-card">';
            echo '<a class="pa-program-speaker-photo" href="' . esc_url(get_permalink($speaker->ID)) . '"' . $this->speaker_image_style_attr($settings, 'directory') . '>';
            if ($image) { echo '<img src="' . esc_url($image) . '" alt="' . esc_attr($speaker->post_title) . '">'; } else { echo '<span class="pa-speaker-placeholder"></span>'; }
            echo '</a>';
            echo '<h3 class="pa-program-speaker-name"><a href="' . esc_url(get_permalink($speaker->ID)) . '">' . esc_html($speaker->post_title . ($credentials ? ', ' . $credentials : '')) . '</a></h3>';
            if ($category) { echo '<p class="pa-program-speaker-category">' . esc_html($category) . '</p>'; }
            if ($role) { echo '<p class="pa-program-speaker-meta">' . esc_html($role) . '</p>'; }
            if ($company) { echo '<p class="pa-program-speaker-meta">' . esc_html($company) . '</p>'; }
            echo '</article>';
        }
        echo '</div></div>';
        return ob_get_clean();
    }

    public function shortcode_program_pdf($atts) {
        $atts = shortcode_atts(['id'=>0], $atts, 'program_pdf');
        $program_id = $this->resolve_program_shortcode_id($atts['id']);
        if (!$program_id) { return '<p>Program not found.</p>'; }
        $url = add_query_arg(['program_pdf'=>$program_id], home_url('/'));
        return '<a class="pa-program-pdf-button" href="' . esc_url($url) . '" target="_blank" rel="noopener">Download Program PDF</a>';
    }

    public function maybe_render_program_pdf_page() {
        $program_id = isset($_GET['program_pdf']) ? absint($_GET['program_pdf']) : 0;
        if (!$program_id || get_post_type($program_id) !== 'pa_program') { return; }
        $html = '<!doctype html><html><head><meta charset="utf-8"><title>' . esc_html(get_the_title($program_id)) . ' Program PDF</title><style>body{font-family:Arial,sans-serif;margin:24px;color:#111}h1{font-size:28px;margin:0 0 16px}.event{border-bottom:1px solid #ddd;padding:14px 0}.meta{color:#555;font-size:13px}.speakers{font-size:13px;color:#333}</style></head><body onload="window.print()">';
        $html .= '<h1>' . esc_html(get_the_title($program_id)) . '</h1>';
        $events = get_posts(['post_type'=>'pa_event','post_status'=>'publish','numberposts'=>-1,'meta_key'=>'_pa_event_date','orderby'=>'meta_value','order'=>'ASC','meta_query'=>[['key'=>'_pa_program_id','value'=>$program_id,'compare'=>'=']]]);
        foreach ($events as $event) {
            $html .= '<div class="event"><h2>' . esc_html($event->post_title) . '</h2>';
            $html .= '<div class="meta">' . esc_html($this->format_event_when($event->ID)) . ' — ' . esc_html(get_post_meta($event->ID, '_pa_event_location', true)) . '</div>';
            $html .= wpautop(wp_kses_post($event->post_content));
            $speaker_ids = get_post_meta($event->ID, '_pa_speaker_ids', true);
            if (is_array($speaker_ids) && $speaker_ids) {
                $names = array_map('get_the_title', array_map('absint', $speaker_ids));
                $html .= '<div class="speakers"><strong>Speakers:</strong> ' . esc_html(implode(', ', array_filter($names))) . '</div>';
            }
            $html .= '</div>';
        }
        $html .= '</body></html>';
        echo $html;
        exit;
    }

    private function speaker_primary_program_id($speaker_id) {
        $program_id = absint(get_post_meta($speaker_id, '_pa_speaker_style_program_id', true));
        if ($program_id) { return $program_id; }
        $events = $this->speaker_event_ids($speaker_id);
        if ($events) {
            $event_program = absint(get_post_meta($events[0], '_pa_program_id', true));
            if ($event_program) { return $event_program; }
        }
        return 0;
    }

    public function replace_single_content($content) {
        if (is_singular('pa_event')) { return $this->single_event_content(get_the_ID()); }
        if (is_singular('pa_speaker')) { return $this->single_speaker_content(get_the_ID()); }
        if (is_singular('pa_sponsor')) { return $this->single_sponsor_content(get_the_ID()); }
        return $content;
    }

    private function single_event_content($id) {
        $program_id = absint(get_post_meta($id, '_pa_program_id', true));
        $settings = $this->settings_for_program($program_id, 'event');
        $image_id = absint(get_post_meta($id, '_pa_event_image_id', true));
        $image = $image_id ? wp_get_attachment_image_url($image_id, 'large') : '';
        $invite = get_post_meta($id, '_pa_event_invite_only', true) === '1';
        $warning = get_post_meta($id, '_pa_event_invite_warning', true);
        $show_calendar = get_post_meta($id, '_pa_event_show_add_to_calendar', true) === '1';
        $date = get_post_meta($id, '_pa_event_date', true);
        $time = get_post_meta($id, '_pa_event_time', true);
        $end = get_post_meta($id, '_pa_event_end_time', true);
        $loc = get_post_meta($id, '_pa_event_location', true);
        $loc_link = get_post_meta($id, '_pa_event_location_link', true);
        ob_start();
        echo '<article class="pa-theme-event-page pa-single-event">';
        if ($image) { echo '<div class="pa-event-hero-image"><img src="' . esc_url($image) . '" alt=""></div>'; }
        echo '<header class="pa-event-header"' . $this->style_attr_from_settings($settings, 'header') . '><h1>' . esc_html(get_the_title($id)) . '</h1><p>' . esc_html($this->format_event_when($id)) . '</p></header>';
        echo '<section class="pa-event-content"' . $this->style_attr_from_settings($settings, 'content') . '>';
        if ($invite) { echo '<div class="pa-invite-warning" aria-label="Invite only">' . ($warning ? wp_kses_post(wpautop($warning)) : '<p>Invite only.</p>') . '</div>'; }
        if ($show_calendar) { echo $this->calendar_button($id, $date, $time, $end, $loc); }
        echo wp_kses_post(wpautop(get_post_field('post_content', $id)));
        $speaker_ids = get_post_meta($id, '_pa_speaker_ids', true);
        if (is_array($speaker_ids) && $speaker_ids) { echo '<h2>Speakers</h2>' . $this->speaker_card_list($speaker_ids, $program_id); }
        $sponsor_ids = get_post_meta($id, '_pa_sponsor_ids', true);
        if (!is_array($sponsor_ids)) { $sponsor_ids = []; }
        if ($sponsor_ids) {
            echo '<section class="pa-event-sponsors"><h2>Sponsors</h2><div class="pa-event-sponsor-logos">';
            foreach ($sponsor_ids as $sponsor_id) {
                $sponsor = get_post(absint($sponsor_id));
                if (!$sponsor || $sponsor->post_type !== 'pa_sponsor') { continue; }
                $logo_id = absint(get_post_meta($sponsor->ID, '_pa_sponsor_logo_id', true));
                $logo = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';
                $website = get_post_meta($sponsor->ID, '_pa_sponsor_website', true);
                $href = $website ?: get_permalink($sponsor->ID);
                $inner = $logo ? '<img src="' . esc_url($logo) . '" alt="' . esc_attr($sponsor->post_title) . '">' : '<span>' . esc_html($sponsor->post_title) . '</span>';
                echo '<a class="pa-event-sponsor-logo" href="' . esc_url($href) . '" target="_blank" rel="noopener">' . $inner . '</a>';
            }
            echo '</div></section>';
        }
        echo '</section>';
        $back = $program_id ? get_post_meta($program_id, '_pa_back_to_link', true) : '';
        if ($back) { echo '<p class="pa-back-link"><a href="' . esc_url($back) . '">Back to Program</a></p>'; }
        echo '</article>';
        return ob_get_clean();
    }

    private function calendar_button($id, $date, $time, $end, $loc) {
        if (!$date || !$time) { return ''; }
        $start_ts = strtotime($date . ' ' . $time);
        $end_ts = $end ? strtotime($date . ' ' . $end) : strtotime('+1 hour', $start_ts);
        if (!$start_ts || !$end_ts) { return ''; }
        $title = get_the_title($id);
        $details = wp_strip_all_tags(get_post_field('post_content', $id));
        $dates = gmdate('Ymd\THis\Z', $start_ts) . '/' . gmdate('Ymd\THis\Z', $end_ts);
        $google = add_query_arg(['action'=>'TEMPLATE','text'=>$title,'dates'=>$dates,'details'=>$details,'location'=>$loc], 'https://calendar.google.com/calendar/render');
        return '<p><a class="pa-calendar-button" href="' . esc_url($google) . '" target="_blank" rel="noopener">Add to Calendar</a></p>';
    }

    private function single_speaker_content($id) {
        $program_id = $this->speaker_primary_program_id($id);
        $settings = $this->settings_for_program($program_id, 'speaker');
        $image_id = absint(get_post_meta($id, '_pa_speaker_image_id', true));
        $image = $image_id ? wp_get_attachment_image_url($image_id, 'medium_large') : '';
        $role = get_post_meta($id, '_pa_speaker_role_title', true);
        $company = get_post_meta($id, '_pa_speaker_company', true);
        $creds = get_post_meta($id, '_pa_speaker_credentials', true);
        $linkedin = get_post_meta($id, '_pa_speaker_linkedin', true);
        $website = get_post_meta($id, '_pa_speaker_website', true);
        ob_start();
        echo '<article class="pa-theme-speaker-page pa-single-speaker">';
        echo '<header class="pa-speaker-header"' . $this->style_attr_from_settings($settings, 'header') . '>';
        if ($image) { echo '<div class="pa-speaker-hero-image"' . $this->speaker_image_style_attr($settings, 'single') . '><img src="' . esc_url($image) . '" alt="' . esc_attr(get_the_title($id)) . '"></div>'; }
        echo '<div><h1>' . esc_html(get_the_title($id) . ($creds ? ', ' . $creds : '')) . '</h1>';
        if ($role) { echo '<p>' . esc_html($role) . '</p>'; }
        if ($company) { echo '<p>' . esc_html($company) . '</p>'; }
        echo '<div class="pa-speaker-links">';
        if ($linkedin) { echo '<a href="' . esc_url($linkedin) . '" target="_blank" rel="noopener">LinkedIn</a>'; }
        if ($website) { echo '<a href="' . esc_url($website) . '" target="_blank" rel="noopener">Website</a>'; }
        echo '</div></div></header>';
        echo '<section class="pa-speaker-content"' . $this->style_attr_from_settings($settings, 'content') . '>' . wp_kses_post(wpautop(get_post_field('post_content', $id))) . '</section>';
        echo '</article>';
        return ob_get_clean();
    }

    private function single_sponsor_content($id) {
        $program_id = absint(get_post_meta($id, '_pa_sponsor_program_id', true));
        $settings = $this->settings_for_program($program_id, 'event');
        $logo_id = absint(get_post_meta($id, '_pa_sponsor_logo_id', true));
        $logo = $logo_id ? wp_get_attachment_image_url($logo_id, 'large') : '';
        $website = get_post_meta($id, '_pa_sponsor_website', true);
        ob_start();
        echo '<article class="pa-theme-sponsor-page pa-single-sponsor">';
        echo '<header class="pa-sponsor-header"' . $this->style_attr_from_settings($settings, 'header') . '>';
        if ($logo) { echo '<div class="pa-sponsor-hero-logo"><img src="' . esc_url($logo) . '" alt="' . esc_attr(get_the_title($id)) . '"></div>'; }
        echo '<div><h1>' . esc_html(get_the_title($id)) . '</h1>';
        if ($website) { echo '<p><a href="' . esc_url($website) . '" target="_blank" rel="noopener">Visit website</a></p>'; }
        echo '</div></header>';
        echo '<section class="pa-sponsor-content"' . $this->style_attr_from_settings($settings, 'content') . '>' . wp_kses_post(wpautop(get_post_field('post_content', $id))) . '</section>';
        echo '</article>';
        return ob_get_clean();
    }

    private function format_event_when($id) {
        $date = get_post_meta($id, '_pa_event_date', true);
        $time = get_post_meta($id, '_pa_event_time', true);
        $end = get_post_meta($id, '_pa_event_end_time', true);
        $parts = [];
        if ($date) { $parts[] = date_i18n('F j, Y', strtotime($date)); }
        $range = $this->format_time_range($time, $end);
        if ($range) { $parts[] = $range; }
        return implode(' • ', $parts);
    }

    private function format_time_range($start, $end = '') {
        if (!$start) { return ''; }
        $start_text = date_i18n('g:i A', strtotime($start));
        if ($end) { return $start_text . ' – ' . date_i18n('g:i A', strtotime($end)); }
        return $start_text;
    }

    public function admin_bar_edit_link($wp_admin_bar) {
        if (!is_singular(['pa_event','pa_speaker','pa_sponsor']) || !current_user_can('edit_posts')) { return; }
        $id = get_the_ID();
        $type = get_post_type($id);
        $page = $type === 'pa_event' ? 'program-edit-event' : ($type === 'pa_speaker' ? 'program-edit-speaker' : 'program-edit-sponsor');
        $wp_admin_bar->add_node(['id'=>'pa-edit-entity','title'=>'Edit in Stagecard','href'=>admin_url('admin.php?page=' . $page . '&id=' . $id)]);
    }

    public function check_github_plugin_update($transient) {
        if (!is_object($transient)) { $transient = new stdClass(); }
        if (!isset($transient->checked) || !is_array($transient->checked)) { return $transient; }
        $plugin_file = plugin_basename(__FILE__);
        if (!isset($transient->checked[$plugin_file])) { return $transient; }
        $release = $this->latest_github_release();
        if (!$release || empty($release['tag_name'])) { return $transient; }
        $latest_version = ltrim((string)$release['tag_name'], 'v');
        $current_version = $transient->checked[$plugin_file];
        if (version_compare($latest_version, $current_version, '<=')) { return $transient; }
        $asset = $this->github_release_asset($release);
        $transient->response[$plugin_file] = (object)[
            'slug' => dirname($plugin_file),
            'plugin' => $plugin_file,
            'new_version' => $latest_version,
            'url' => 'https://github.com/' . self::GITHUB_REPO,
            'package' => $asset ?: $release['zipball_url'],
        ];
        return $transient;
    }

    public function github_plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== basename(dirname(__FILE__))) { return $result; }
        $release = $this->latest_github_release();
        if (!$release) { return $result; }
        $asset = $this->github_release_asset($release);
        return (object)[
            'name' => 'Stagecard',
            'slug' => basename(dirname(__FILE__)),
            'version' => ltrim((string)$release['tag_name'], 'v'),
            'author' => '<a href="https://github.com/okohring">Olivia Kohring</a>',
            'homepage' => 'https://github.com/' . self::GITHUB_REPO,
            'download_link' => $asset ?: $release['zipball_url'],
            'sections' => ['description' => 'A program schedule builder for branded agendas.'],
        ];
    }

    public function maybe_clear_github_update_cache() {
        if (!is_admin() || !current_user_can('update_plugins')) { return; }
        if (isset($_GET['pa_clear_update_cache'])) {
            delete_site_transient('update_plugins');
            delete_transient('pa_latest_github_release');
            wp_safe_redirect(remove_query_arg('pa_clear_update_cache'));
            exit;
        }
    }

    private function latest_github_release() {
        $cached = get_transient('pa_latest_github_release');
        if (is_array($cached)) { return $cached; }
        $response = wp_remote_get('https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest', ['timeout'=>10,'headers'=>['Accept'=>'application/vnd.github+json']]);
        if (is_wp_error($response)) { return null; }
        if ((int)wp_remote_retrieve_response_code($response) !== 200) { return null; }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) { return null; }
        set_transient('pa_latest_github_release', $data, 15 * MINUTE_IN_SECONDS);
        return $data;
    }

    private function github_release_asset($release) {
        if (empty($release['assets']) || !is_array($release['assets'])) { return ''; }
        foreach ($release['assets'] as $asset) {
            $name = $asset['name'] ?? '';
            if (preg_match('/\.zip$/', $name) && !empty($asset['browser_download_url'])) { return $asset['browser_download_url']; }
        }
        return '';
    }

}

new Program_Agenda_Plugin();
