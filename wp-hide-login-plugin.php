<?php
/**
 * Plugin Name: Hide Login
 * Description: Protect your website by changing the login URL and preventing access to wp-login.php page and wp-admin directory while not logged-in
 * Version: 1.0.0
 * Requires at least: 6.4
 * Tested up to: 6.8
 * Requires PHP: 8.0
 * Domain Path: /languages/
 * Text Domain: whl
 *
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

namespace hide_login;

// don't load directly
if (! defined('ABSPATH')) {
    exit('-1');
}

// Plugin constants
class Plugin
{
    private static ?Plugin $instance = null;

    private bool $wp_login_php = false;

    private function __construct()
    {
        $this->init();
    }

    protected function init(): void
    {
        load_plugin_textdomain('whl', false, dirname(plugin_basename(__FILE__)).'/languages');

        global $wp_version;

        if (version_compare($wp_version, '4.0-RC1-src', '<')) {
            add_action('admin_notices', [$this, 'admin_notices_incompatible']);
            add_action('network_admin_notices', [$this, 'admin_notices_incompatible']);
            return;
        }

        $this->add_common_hooks();
    }

    private function add_common_hooks(): void
    {
        add_action('admin_init', [$this, 'admin_init']);
        add_action('plugins_loaded', [$this, 'plugins_loaded'], 9999);
        add_action('admin_notices', [$this, 'admin_notices']);
        add_action('wp_loaded', [$this, 'wp_loaded']);

        add_filter('plugin_action_links_'.self::get_basename(), [$this, 'plugin_action_links']);
        add_filter('site_url', [$this, 'site_url'], 10, 4);
        add_filter('network_site_url', [$this, 'network_site_url'], 10, 3);
        add_filter('wp_redirect', [$this, 'wp_redirect'], 10, 2);
        add_filter('login_url', [$this, 'login_url'], 10, 3);
    }

    private static function get_basename(): string
    {
        return plugin_basename(__FILE__);
    }

    public static function get_instance(): self
    {
        if (is_null(self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    public static function activate(): void
    {
        do_action('hide_login_activate');
    }

    public function admin_notices_incompatible(): void
    {
        echo '<div class="error notice is-dismissible"><p>'.__('Please upgrade to the latest version of WordPress to activate', 'whl').' <strong>'.__('Hide Login', 'whl').'</strong>.</p></div>';
    }

    public function admin_init(): void
    {
        add_settings_section(
            'whl-section',
            __('Hide Login', 'whl'),
            [$this, 'whl_section_desc'],
            'general'
        );

        add_settings_field(
            'whl_page',
            '<label for="whl_page">'.__('Login url', 'whl').'</label>',
            [$this, 'whl_page_input'],
            'general',
            'whl-section'
        );

        add_settings_field(
            'whl_redirect_admin',
            '<label for="whl_redirect_admin">'.__('Redirection url', 'whl').'</label>',
            [$this, 'whl_redirect_admin_input'],
            'general',
            'whl-section'
        );

        register_setting('general', 'whl_page', 'sanitize_title_with_dashes');
        register_setting('general', 'whl_redirect_admin', 'sanitize_title_with_dashes');
    }

    public function whl_section_desc(): void
    {
        echo '<p id="whl_settings">'.__('Protect your website by changing the login URL and preventing access to the wp-login.php page and the wp-admin directory to non-connected people.', 'whl').'</p>';
    }

    public function whl_page_input(): void
    {
        if (get_option('permalink_structure')) {
            echo '<code>'.trailingslashit(home_url()).'</code> <input id="whl_page" type="text" name="whl_page" value="'.$this->new_login_slug().'">'.($this->use_trailing_slashes() ? ' <code>/</code>' : '');
        } else {
            echo '<code>'.trailingslashit(home_url()).'?</code> <input id="whl_page" type="text" name="whl_page" value="'.$this->new_login_slug().'">';
        }
        echo '<p class="description">'.__('Protect your website by changing the login URL and preventing access to the wp-login.php page and the wp-admin directory to non-connected people.', 'whl').'</p>';
    }

    private function new_login_slug(): string
    {
        if ($slug = get_option('whl_page')) {
            return $slug;
        }
        return 'signin';
    }

    private function use_trailing_slashes(): bool
    {
        return str_ends_with(get_option('permalink_structure'), '/');
    }

    public function whl_redirect_admin_input(): void
    {
        if (get_option('permalink_structure')) {
            echo '<code>'.trailingslashit(home_url()).'</code> <input id="whl_redirect_admin" type="text" name="whl_redirect_admin" value="'.$this->new_redirect_slug().'">'.($this->use_trailing_slashes() ? ' <code>/</code>' : '');
        } else {
            echo '<code>'.trailingslashit(home_url()).'?</code> <input id="whl_redirect_admin" type="text" name="whl_redirect_admin" value="'.$this->new_redirect_slug().'">';
        }
        echo '<p class="description">'.__('Redirect URL when someone tries to access the wp-login.php page and the wp-admin directory while not logged in.', 'whl').'</p>';
    }

    private function new_redirect_slug(): string
    {
        if ($slug = get_option('whl_redirect_admin')) {
            return $slug;
        }

        return '404';
    }

    public function admin_notices(): void
    {
        global $pagenow;

        if ($pagenow === 'options-general.php' && isset($_GET['settings-updated']) && ! isset($_GET['page'])) {
            echo '<div class="updated notice is-dismissible"><p>'.sprintf(__('Your login page is now here: <strong><a href="%1$s">%2$s</a></strong>. Bookmark this page!', 'whl'), $this->new_login_url(), $this->new_login_url()).'</p></div>';
        }
    }

    public function new_login_url(?string $scheme = null): string
    {
        $url = apply_filters('hide_login_home_url', home_url('/', $scheme));
        if (get_option('permalink_structure')) {
            return $this->user_trailingslashit($url.$this->new_login_slug());
        }
        return $url.'?'.$this->new_login_slug();
    }

    private function user_trailingslashit(string $string): string
    {
        return $this->use_trailing_slashes() ? trailingslashit($string) : untrailingslashit($string);
    }

    public function plugin_action_links(array $links): array
    {
        array_unshift($links, '<a href="'.admin_url('options-general.php#whl_settings').'">'.__('Settings', 'whl').'</a>');
        return $links;
    }

    public function plugins_loaded()
    {
        $this->handle_wp_login_php();
        $this->handle_custom_login();
    }

    private function handle_wp_login_php()
    {
        $request = parse_url(rawurldecode($_SERVER['REQUEST_URI']));
        if ((strpos(rawurldecode($_SERVER['REQUEST_URI']), 'wp-login.php') !== false
                || (isset($request['path']) && untrailingslashit($request['path']) === site_url('wp-login', 'relative')))
            && ! is_admin()) {
            $this->wp_login_php = true;
            $_SERVER['REQUEST_URI'] = $this->user_trailingslashit('/'.str_repeat('-/', 10));
            global $pagenow;
            $pagenow = 'index.php';
        }
    }

    private function handle_custom_login()
    {
        $request = parse_url(rawurldecode($_SERVER['REQUEST_URI']));
        if ((isset($request['path']) && untrailingslashit($request['path']) === home_url($this->new_login_slug(), 'relative'))
            || (! get_option('permalink_structure')
                && isset($_GET[$this->new_login_slug()])
                && empty($_GET[$this->new_login_slug()]))) {
            $_SERVER['SCRIPT_NAME'] = $this->new_login_slug();
            global $pagenow;
            $pagenow = 'wp-login.php';
        }
    }

    public function wp_loaded()
    {
        global $pagenow;

        $request = parse_url(rawurldecode($_SERVER['REQUEST_URI']));

        if (is_admin() && ! is_user_logged_in() && ! defined('WP_CLI') && ! defined('DOING_AJAX') && ! defined('DOING_CRON') && $pagenow !== 'admin-post.php' && $request['path'] !== '/wp-admin/options.php') {
            wp_safe_redirect($this->new_redirect_url());
            exit();
        }

        if (! is_user_logged_in() && isset($request['path']) && $request['path'] === '/wp-admin/options.php') {
            header('Location: '.$this->new_redirect_url());
            exit;
        }

        if ($pagenow === 'wp-login.php' && isset($request['path']) && $request['path'] !== $this->user_trailingslashit($request['path']) && get_option('permalink_structure')) {
            wp_safe_redirect($this->user_trailingslashit($this->new_login_url()).(! empty($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : ''));
            exit;
        } elseif ($this->wp_login_php) {
            $this->wp_template_loader();
        } elseif ($pagenow === 'wp-login.php') {
            global $error, $interim_login, $action, $user_login;

            $redirect_to = admin_url();

            if (isset($_REQUEST['redirect_to'])) {
                $redirect_to = $_REQUEST['redirect_to'];
            }

            if (is_user_logged_in()) {
                $user = wp_get_current_user();
                wp_safe_redirect($redirect_to);
                exit();
            }

            @require_once ABSPATH.'wp-login.php';
            exit;
        }
    }

    public function new_redirect_url(?string $scheme = null): string
    {
        if (get_option('permalink_structure')) {
            return $this->user_trailingslashit(home_url('/', $scheme).$this->new_redirect_slug());
        }
        return home_url('/', $scheme).'?'.$this->new_redirect_slug();
    }

    private function wp_template_loader(): void
    {
        global $pagenow;
        $pagenow = 'index.php';
        if (! defined('WP_USE_THEMES')) {
            define('WP_USE_THEMES', true);
        }
        wp();
        require_once ABSPATH.WPINC.'/template-loader.php';
        exit;
    }

    public function site_url($url, $path, $scheme, $blog_id)
    {

        return $this->filter_wp_login_php($url, $scheme);

    }

    public function filter_wp_login_php($url, $scheme = null)
    {
        if (str_contains($url, 'wp-login.php?action=postpass')) {
            return $url;
        }

        if (str_contains($url, 'wp-login.php') && strpos(wp_get_referer(), 'wp-login.php') === false) {

            if (is_ssl()) {
                $scheme = 'https';
            }

            $args = explode('?', $url);
            if (isset($args[1])) {
                parse_str($args[1], $args);
                if (isset($args['login'])) {
                    $args['login'] = rawurlencode($args['login']);
                }
                $url = add_query_arg($args, $this->new_login_url($scheme));
            } else {
                $url = $this->new_login_url($scheme);
            }
        }

        return $url;
    }

    public function network_site_url($url, $path, $scheme)
    {

        return $this->filter_wp_login_php($url, $scheme);

    }

    public function wp_redirect($location, $status)
    {
        if (str_contains($location, 'https://wordpress.com/wp-login.php')) {
            return $location;
        }

        return $this->filter_wp_login_php($location);
    }

    public function login_url($login_url, $redirect, $force_reauth)
    {
        if (is_404()) {
            return '#';
        }

        if ($force_reauth === false) {
            return $login_url;
        }

        if (empty($redirect)) {
            return $login_url;
        }

        $redirect = explode('?', $redirect);

        if ($redirect[0] === admin_url('options.php')) {
            $login_url = admin_url();
        }

        return $login_url;
    }
}

register_activation_hook(__FILE__, [\hide_login\Plugin::class, 'activate']);

add_action('plugins_loaded', function () {
    \hide_login\Plugin::get_instance();
});
