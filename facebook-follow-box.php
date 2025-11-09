<?php
/**
 * Plugin Name: Facebook Follow Box
 * Plugin URI: https://urocibg.eu
 * Description: Добавя призив за последване във Facebook в края на всяка статия
 * Version: 1.3
 * Author: UrociBG
 * Text Domain: facebook-follow-box
 */

// Предотвратява директен достъп до файла
if (!defined('ABSPATH')) {
    exit;
}

// Директна инициализация - без проверка за клас
function facebook_follow_box_init() {
    new Facebook_Follow_Box();
}
add_action('plugins_loaded', 'facebook_follow_box_init');

class Facebook_Follow_Box {
    
    private $options;
    
    public function __construct() {
        $this->options = get_option('fb_follow_options');
        
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
        add_filter('the_content', array($this, 'add_facebook_box'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        
        // Debug info
        add_action('wp_footer', array($this, 'debug_info'), 100);
    }
    
    public function debug_info() {
        if (current_user_can('manage_options')) {
            echo '<!-- Facebook Follow Box Debug: ';
            echo 'is_single: ' . (is_single() ? 'yes' : 'no') . ', ';
            echo 'is_page: ' . (is_page() ? 'yes' : 'no') . ', ';
            echo 'Options: ' . print_r($this->options, true);
            echo ' -->';
        }
    }
    
    public function add_plugin_page() {
        add_options_page(
            'Facebook Follow Box Settings',
            'Facebook Follow Box',
            'manage_options',
            'facebook-follow-box',
            array($this, 'create_admin_page')
        );
    }
    
    public function create_admin_page() {
        ?>
        <div class="wrap">
            <h1>Facebook Follow Box Настройки</h1>
            <form method="post" action="options.php">
            <?php
                settings_fields('fb_follow_option_group');
                do_settings_sections('facebook-follow-box');
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }
    
    public function page_init() {
        register_setting(
            'fb_follow_option_group',
            'fb_follow_options',
            array($this, 'sanitize')
        );
        
        add_settings_section(
            'fb_follow_section',
            'Основни настройки',
            array($this, 'section_info'),
            'facebook-follow-box'
        );
        
        add_settings_field(
            'facebook_url',
            'Facebook URL',
            array($this, 'facebook_url_callback'),
            'facebook-follow-box',
            'fb_follow_section'
        );
        
        add_settings_field(
            'custom_text',
            'Текст на съобщението',
            array($this, 'custom_text_callback'),
            'facebook-follow-box',
            'fb_follow_section'
        );
        
        add_settings_field(
            'background_color',
            'Цвят на фона',
            array($this, 'background_color_callback'),
            'facebook-follow-box',
            'fb_follow_section'
        );
        
        add_settings_field(
            'show_on_pages',
            'Покажи на страници',
            array($this, 'show_on_pages_callback'),
            'facebook-follow-box',
            'fb_follow_section'
        );
        
        add_settings_field(
            'show_on_posts',
            'Покажи на постове',
            array($this, 'show_on_posts_callback'),
            'facebook-follow-box',
            'fb_follow_section'
        );
    }
    
    public function sanitize($input) {
        $sanitary_values = array();
        
        if (isset($input['facebook_url'])) {
            $sanitary_values['facebook_url'] = sanitize_text_field($input['facebook_url']);
        }
        
        if (isset($input['custom_text'])) {
            $sanitary_values['custom_text'] = sanitize_text_field($input['custom_text']);
        }
        
        if (isset($input['background_color'])) {
            $sanitary_values['background_color'] = sanitize_hex_color($input['background_color']);
        }
        
        $sanitary_values['show_on_pages'] = isset($input['show_on_pages']) ? '1' : '0';
        $sanitary_values['show_on_posts'] = isset($input['show_on_posts']) ? '1' : '0';
        
        return $sanitary_values;
    }
    
    public function section_info() {
        echo 'Въведете настройките за Facebook Follow Box:';
    }
    
    public function facebook_url_callback() {
        $value = isset($this->options['facebook_url']) ? $this->options['facebook_url'] : 'https://facebook.com/yourpage';
        printf(
            '<input class="regular-text" type="text" name="fb_follow_options[facebook_url]" id="facebook_url" value="%s">',
            esc_attr($value)
        );
        echo '<p class="description">Пълният URL на вашата Facebook страница</p>';
    }
    
    public function custom_text_callback() {
        $value = isset($this->options['custom_text']) ? $this->options['custom_text'] : 'Последвайте ни във Facebook за още IT съвети и новини';
        printf(
            '<input class="regular-text" type="text" name="fb_follow_options[custom_text]" id="custom_text" value="%s">',
            esc_attr($value)
        );
    }
    
    public function background_color_callback() {
        $value = isset($this->options['background_color']) ? $this->options['background_color'] : '#f0f8ff';
        printf(
            '<input type="text" name="fb_follow_options[background_color]" id="background_color" value="%s" class="color-picker">',
            esc_attr($value)
        );
    }
    
    public function show_on_pages_callback() {
        $value = isset($this->options['show_on_pages']) ? $this->options['show_on_pages'] : '0';
        printf(
            '<input type="checkbox" name="fb_follow_options[show_on_pages]" id="show_on_pages" value="1" %s>',
            checked($value, '1', false)
        );
        echo '<label for="show_on_pages"> Показвай и на статични страници</label>';
    }
    
    public function show_on_posts_callback() {
        $value = isset($this->options['show_on_posts']) ? $this->options['show_on_posts'] : '1';
        printf(
            '<input type="checkbox" name="fb_follow_options[show_on_posts]" id="show_on_posts" value="1" %s>',
            checked($value, '1', false)
        );
        echo '<label for="show_on_posts"> Показвай в постове</label>';
    }
    
    public function add_facebook_box($content) {
        // Проверка дали сме в админ панел
        if (is_admin()) {
            return $content;
        }
        
        // Проверка дали сме на публична част от сайта
        if (!is_singular()) {
            return $content;
        }
        
        // Проверка за постове
        if (is_single() && (!isset($this->options['show_on_posts']) || $this->options['show_on_posts'] != '1')) {
            return $content;
        }
        
        // Проверка за страници
        if (is_page() && (!isset($this->options['show_on_pages']) || $this->options['show_on_pages'] != '1')) {
            return $content;
        }
        
        $fb_url = isset($this->options['facebook_url']) ? esc_url($this->options['facebook_url']) : 'https://facebook.com/yourpage';
        $text = isset($this->options['custom_text']) ? esc_html($this->options['custom_text']) : 'Последвайте ни във Facebook за още IT съвети и новини';
        $bg_color = isset($this->options['background_color']) ? esc_attr($this->options['background_color']) : '#f0f8ff';
        
        $facebook_box = '
        <div class="urocibg-fb-follow-wrapper" style="background-color: ' . $bg_color . '; margin: 40px auto; padding: 35px; border-radius: 16px; border: 3px solid #1877f2; box-shadow: 0 8px 24px rgba(24, 119, 242, 0.2); max-width: 800px; clear: both; overflow: hidden; box-sizing: border-box; position: relative;">
            <div class="urocibg-fb-follow-inner" style="display: flex; align-items: center; gap: 30px; margin: 0; padding: 0; flex-wrap: nowrap;">
                <div class="urocibg-fb-icon" style="flex-shrink: 0; line-height: 1; display: flex; align-items: center; justify-content: center; width: 56px; height: 56px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="#1877f2">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                </div>
                <div class="urocibg-fb-content" style="flex-grow: 1; margin: 0; padding: 0; min-width: 0;">
                    <p class="urocibg-fb-text" style="margin: 0 0 18px 0; padding: 0; font-size: 18px; line-height: 1.6; color: #1c1e21; font-weight: 600; letter-spacing: -0.02em;">' . $text . '</p>
                    <a href="' . $fb_url . '" target="_blank" rel="noopener noreferrer" class="urocibg-fb-button" style="display: inline-flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #1877f2 0%, #0c63d4 100%); color: #ffffff; padding: 14px 32px; border-radius: 10px; text-decoration: none; font-weight: 700; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); font-size: 16px; border: none; cursor: pointer; box-shadow: 0 4px 14px rgba(24, 119, 242, 0.4); position: relative; overflow: hidden;">
                        <span class="urocibg-fb-button-text" style="position: relative; z-index: 1; color: #ffffff;">Последвайте ни</span>
                    </a>
                </div>
            </div>
        </div>';
        
        return $content . $facebook_box;
    }
    
    public function enqueue_styles() {
        // Проверка дали сме на публична част от сайта
        if (!is_singular()) {
            return;
        }
        
        // Проверка за постове
        if (is_single() && (!isset($this->options['show_on_posts']) || $this->options['show_on_posts'] != '1')) {
            return;
        }
        
        // Проверка за страници
        if (is_page() && (!isset($this->options['show_on_pages']) || $this->options['show_on_pages'] != '1')) {
            return;
        }
        
        // Зареди CSS файл вместо inline стилове
        wp_enqueue_style(
            'urocibg-fb-follow-style',
            plugin_dir_url(__FILE__) . 'facebook-follow-box.css',
            array(),
            '1.3.0'
        );
    }
    
    public function enqueue_admin_styles($hook) {
        if ('settings_page_facebook-follow-box' !== $hook) {
            return;
        }
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_add_inline_script('wp-color-picker', 'jQuery(document).ready(function($){ $(".color-picker").wpColorPicker(); });');
    }
}

// Activation hook
register_activation_hook(__FILE__, 'fb_follow_box_activate');
function fb_follow_box_activate() {
    $default_options = array(
        'facebook_url' => 'https://facebook.com/yourpage',
        'custom_text' => 'Последвайте ни във Facebook за още IT съвети и новини',
        'background_color' => '#f0f8ff',
        'show_on_pages' => '0',
        'show_on_posts' => '1'
    );
    
    if (false === get_option('fb_follow_options')) {
        add_option('fb_follow_options', $default_options);
    }
}

// Създай CSS файл програмно ако не съществува
function create_fb_follow_css_file() {
    $css_file = plugin_dir_path(__FILE__) . 'facebook-follow-box.css';
    
    if (!file_exists($css_file)) {
        $css_content = "
.urocibg-fb-follow-wrapper {
    margin: 40px auto !important;
    padding: 35px !important;
    border-radius: 16px !important;
    border: 3px solid #1877f2 !important;
    box-shadow: 0 8px 24px rgba(24, 119, 242, 0.2) !important;
    max-width: 800px !important;
    clear: both !important;
    overflow: hidden !important;
    box-sizing: border-box !important;
    position: relative !important;
}

.urocibg-fb-follow-wrapper::before {
    content: '' !important;
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    height: 4px !important;
    background: linear-gradient(90deg, #1877f2, #0c63d4) !important;
}

.urocibg-fb-follow-inner {
    display: flex !important;
    align-items: center !important;
    gap: 30px !important;
    margin: 0 !important;
    padding: 0 !important;
    flex-wrap: nowrap !important;
}

.urocibg-fb-icon {
    flex-shrink: 0 !important;
    line-height: 1 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 56px !important;
    height: 56px !important;
}

.urocibg-fb-icon svg {
    display: block !important;
    width: 56px !important;
    height: 56px !important;
    filter: drop-shadow(0 2px 4px rgba(24, 119, 242, 0.3)) !important;
}

.urocibg-fb-content {
    flex-grow: 1 !important;
    margin: 0 !important;
    padding: 0 !important;
    min-width: 0 !important;
}

.urocibg-fb-text {
    margin: 0 0 18px 0 !important;
    padding: 0 !important;
    font-size: 18px !important;
    line-height: 1.6 !important;
    color: #1c1e21 !important;
    font-weight: 600 !important;
    letter-spacing: -0.02em !important;
}

.urocibg-fb-button {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    background: linear-gradient(135deg, #1877f2 0%, #0c63d4 100%) !important;
    color: #ffffff !important;
    padding: 14px 32px !important;
    border-radius: 10px !important;
    text-decoration: none !important;
    font-weight: 700 !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    font-size: 16px !important;
    border: none !important;
    cursor: pointer !important;
    box-shadow: 0 4px 14px rgba(24, 119, 242, 0.4) !important;
    position: relative !important;
    overflow: hidden !important;
}

.urocibg-fb-button::before {
    content: '' !important;
    position: absolute !important;
    top: 0 !important;
    left: -100% !important;
    width: 100% !important;
    height: 100% !important;
    background: rgba(255, 255, 255, 0.2) !important;
    transition: left 0.5s ease !important;
}

.urocibg-fb-button:hover::before {
    left: 100% !important;
}

.urocibg-fb-button:hover {
    background: linear-gradient(135deg, #0c63d4 0%, #084a9e 100%) !important;
    text-decoration: none !important;
    transform: translateY(-3px) scale(1.02) !important;
    box-shadow: 0 6px 20px rgba(24, 119, 242, 0.5) !important;
    color: #ffffff !important;
}

.urocibg-fb-button:active {
    transform: translateY(-1px) scale(1) !important;
}

.urocibg-fb-button-text {
    position: relative !important;
    z-index: 1 !important;
    color: #ffffff !important;
}

@media (max-width: 768px) {
    .urocibg-fb-follow-inner {
        flex-direction: column !important;
        text-align: center !important;
        gap: 25px !important;
    }
    
    .urocibg-fb-follow-wrapper {
        padding: 30px 25px !important;
        margin: 30px auto !important;
    }
    
    .urocibg-fb-icon {
        width: 48px !important;
        height: 48px !important;
    }
    
    .urocibg-fb-icon svg {
        width: 48px !important;
        height: 48px !important;
    }
    
    .urocibg-fb-text {
        font-size: 17px !important;
    }
    
    .urocibg-fb-button {
        padding: 12px 28px !important;
        font-size: 15px !important;
    }
}

@media (max-width: 480px) {
    .urocibg-fb-follow-wrapper {
        padding: 25px 20px !important;
        border-radius: 12px !important;
    }
    
    .urocibg-fb-text {
        font-size: 16px !important;
    }
    
    .urocibg-fb-button {
        padding: 11px 24px !important;
        font-size: 14px !important;
        width: 100% !important;
    }
}
";
        file_put_contents($css_file, $css_content);
    }
}
add_action('init', 'create_fb_follow_css_file');