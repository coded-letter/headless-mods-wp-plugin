<?php
/*
Headless Security Tweaks
Description: A collection of security tweaks to enhance your WordPress site's security.
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}


// ==========================
// General Security Tweaks
// ==========================

// Hide WordPress version from RSS feeds
add_filter('the_generator', '__return_empty_string');

function my_remove_src_version($src)
{
    global $wp_version;

    $version_str = '?ver=' . $wp_version;
    $offset = strlen($src) - strlen($version_str);

    if ($offset >= 0 && strpos($src, $version_str, $offset) !== FALSE)
        return substr($src, 0, $offset);

    return $src;
}

add_filter('script_loader_src', 'my_remove_src_version');
add_filter('style_loader_src', 'my_remove_src_version');


// Disable login error messages
add_filter('login_errors', fn() => 'Something is wrong! Please try again.');

// Disable XML-RPC completely
add_filter('xmlrpc_enabled', '__return_false');
add_filter('xmlrpc_methods', function ($methods) {
    unset($methods['pingback.ping'], $methods['system.multicall']);
    return $methods;
});

// Disable self-pingbacks
add_action('pre_ping', function (&$links) {
    foreach ($links as $l => $link) {
        if (0 === strpos($link, get_option('home'))) {
            unset($links[$l]);
        }
    }
});

// Remove unnecessary links from wp_head
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wp_shortlink_wp_head');
remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'wp_oembed_add_discovery_links');
remove_action('wp_head', 'wp_oembed_add_host_js');

remove_action('wp_head', 'start_post_rel_link');
remove_action('wp_head', 'index_rel_link');
remove_action('wp_head', 'adjacent_posts_rel_link');

// Hide theme names from public
/*
add_filter('rest_endpoints', function ($endpoints) {
    unset($endpoints['/wp/v2/themes']);
    return $endpoints;
});
*/
// ==========================
// File and Directory Security
// ==========================

// Define constants based on settings
add_action('init', function () {
    $options = get_option('custom_login_settings');
    /* Blokuje modyfikacje wtyczek i motywów */
    if (!defined('DISALLOW_FILE_EDIT') && isset($options['disallow_file_edit']) && $options['disallow_file_edit']) {
        define('DISALLOW_FILE_EDIT', true);
    }
    /* Blokuje dodawanie wtyczek i motywów */
    if (!defined('DISALLOW_FILE_MODS') && isset($options['disallow_file_mods']) && $options['disallow_file_mods']) {
        define('DISALLOW_FILE_MODS', true);
    }
});

// Secure uploads directory
register_activation_hook(__FILE__, function () {
    $uploads_dir = wp_upload_dir();
    $htaccess_file = $uploads_dir['basedir'] . '/.htaccess';
    if (!file_exists($htaccess_file)) {
        file_put_contents($htaccess_file, "<Files *.php>\nDeny from all\n</Files>");
    }
});

// Disable directory listing dynamically
register_activation_hook(__FILE__, function () {
    $directories = [ABSPATH . 'wp-content/', ABSPATH . 'wp-includes/', ABSPATH . 'wp-admin/'];
    foreach ($directories as $directory) {
        $index_file = $directory . 'index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, "<?php // Silence is golden.");
        }
    }
});

// ==========================
// .htaccess Management
// ==========================

/**
 * Hook into WordPress initialization to dynamically write the rules to .htaccess.
 */
function wst_manage_htaccess()
{
    // Path to the .htaccess file
    $htaccess_path = ABSPATH . '.htaccess';

    // Security Rules to Inject
    $security_rules = <<<HTACCESS
# BEGIN WP Firewall
    
# Redirect all http traffic to https
RewriteCond %{HTTPS} off
RewriteRule (.*) https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]

# Add security headers XSS protection
<ifModule mod_headers.c>
Header set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" env=HTTPS
Header set X-XSS-Protection "1; mode=block"
Header set X-Content-Type-Options nosniff
Header set X-Frame-Options "SAMEORIGIN"
Header set Referrer-Policy "no-referrer-when-downgrade"
</ifModule>

# Allow access if the user is logged in to the users wp-json endpoint
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteCond %{HTTP_COOKIE} !wordpress_logged_in [NC]
RewriteCond %{REQUEST_URI} ^/wp-json/wp/v2/users [NC]
RewriteRule ^.*$ - [F,L]
</IfModule>

# hide wp-login links 
RewriteRule ^wp-login.php$ /404 [L,R=301]

# Block access to wp-config, htaccess, readme files
<FilesMatch "wp-config.*\.php|\.htaccess|readme\.html">
	Order allow,deny
	Deny from all
</FilesMatch>

# Block access to xmlrpc file
<Files xmlrpc.php>
	Order deny,allow
	Deny from all
</Files>

# Disable users enumeration
RewriteCond %{QUERY_STRING} author=\d
RewriteRule ^ /? [L,R=301]

# Block bots (basic)
RewriteCond %{HTTP_USER_AGENT} (MJ12bot|VeBot|BLP_bbot|datagnionbot|YandexBot|PetalBot|YandexImages|bingbot|AspiegelBot|dotbot|Baiduspider) [NC]
RewriteRule .* - [R=403,L]

# Block bots (advanced)
<IfModule mod_rewrite.c>
RewriteCond %{HTTP_USER_AGENT} (alexibot|majestic|mj12bot|rogerbot) [NC,OR]
RewriteCond %{HTTP_USER_AGENT} (econtext|eolasbot|eventures|liebaofast|nominet|oppo\sa33) [NC,OR]
RewriteCond %{HTTP_USER_AGENT} (acapbot|acoonbot|asterias|attackbot|backdorbot|becomebot|binlar|blackwidow|blekkobot|blexbot|blowfish|bullseye|bunnys|butterfly|careerbot|casper|checkpriv|cheesebot|cherrypick|chinaclaw|choppy|clshttp|cmsworld|copernic|copyrightcheck|cosmos|crescent|cy_cho|datacha|demon|diavol|discobot|dittospyder|dotbot|dotnetdotcom|dumbot|emailcollector|emailsiphon|emailwolf|extract|eyenetie|feedfinder|flaming|flashget|flicky|foobot|g00g1e|getright|gigabot|go-ahead-got|gozilla|grabnet|grafula|harvest|heritrix|httrack|icarus6j|jetbot|jetcar|jikespider|kmccrew|leechftp|libweb|linkextractor|linkscan|linkwalker|loader|masscan|miner|mechanize|morfeus|moveoverbot|netmechanic|netspider|nicerspro|nikto|ninja|nutch|octopus|pagegrabber|petalbot|planetwork|postrank|proximic|purebot|pycurl|python|queryn|queryseeker|radian6|radiation|realdownload|scooter|seekerspider|semalt|siclab|sindice|sistrix|sitebot|siteexplorer|sitesnagger|skygrid|smartdownload|snoopy|sosospider|spankbot|spbot|sqlmap|stackrambler|stripper|sucker|surftbot|sux0r|suzukacz|suzuran|takeout|teleport|telesoft|true_robots|turingos|turnit|vampire|vikspider|voideye|webleacher|webreaper|webstripper|webvac|webviewer|webwhacker|winhttp|wwwoffle|woxbot|xaldon|xxxyy|yamanalab|yioopbot|youda|zeus|zmeu|zune|zyborg) [NC]
RewriteCond %{REMOTE_HOST} (163data|amazonaws|colocrossing|crimea|g00g1e|justhost|kanagawa|loopia|masterhost|onlinehome|poneytel|sprintdatacenter|reverse.softlayer|safenet|ttnet|woodpecker|wowrack) [NC]
RewriteCond %{HTTP_REFERER} (semalt\.com|todaperfeita) [NC,OR]
RewriteCond %{HTTP_REFERER} (blue\spill|cocaine|ejaculat|erectile|erections|hoodia|huronriveracres|impotence|levitra|libido|lipitor|phentermin|pro[sz]ac|sandyauer|tramadol|troyhamby|ultram|unicauca|valium|viagra|vicodin|xanax|ypxaieo) [NC]
RewriteRule .* - [F,L]
</IfModule>

# Block undefined bots
SetEnvIfNoCase user-agent bot\[.+\]|.*mj12bot.*|.*baiduspider.*|.*semrushbot.* bad_bot=1
Order Allow,Deny
Allow from all
Deny from env=bad_bot

# Block query strings in urls
<IfModule mod_rewrite.c>
RewriteCond %{QUERY_STRING} (((/|%2f){3,3})|((\.|%2e){3,3})|((\.|%2e){2,2})(/|%2f|%u2215)) [NC,OR]
RewriteCond %{QUERY_STRING} (/|%2f)(:|%3a)(/|%2f) [NC,OR]
RewriteCond %{QUERY_STRING} (/|%2f)(\*|%2a)(\*|%2a)(/|%2f) [NC,OR]
RewriteCond %{QUERY_STRING} (absolute_|base|root_)(dir|path)(=|%3d)(ftp|https?) [NC,OR]
RewriteCond %{QUERY_STRING} (/|%2f)(=|%3d|$&|_mm|cgi(\.|-)|inurl(:|%3a)(/|%2f)|(mod|path)(=|%3d)(\.|%2e)) [NC,OR]
RewriteCond %{REQUEST_URI} (\^|`|<|>|\|\|) [NC,OR]
RewriteCond %{REQUEST_URI} ([a-z0-9]{2000,}) [NC]
RewriteRule .* - [F,L]
</IfModule>

# Block debug.log access
<Files "debug.log">
    Order allow,deny
    Deny from all
</Files>
# END WP Firewall
HTACCESS;

    /*
    Ensure rules only added if not already in the file
    */
    if (file_exists($htaccess_path)) {
        // Read current .htaccess content
        $current_content = file_get_contents($htaccess_path);

        // Only add rules if they are not already present
        if (strpos($current_content, $security_rules) === false) {
            // Append rules to the end of the file
            file_put_contents($htaccess_path, "\n" . $security_rules . "\n" . $current_content);
        }
    } else {
        // If no .htaccess exists, create and write the rules
        file_put_contents($htaccess_path, $security_rules . "\n");
    }
}

/*omitted for bitnami wordpress*/

// add_action('init', 'wst_manage_htaccess');


// ==========================
// Additional Security Measures
// ==========================

// Block user enumeration
if (!is_admin() && isset($_REQUEST['author'])) {
    wp_die('User enumeration is not allowed.', 'Security Block', 403);
}

// Add HTTP security headers
add_action('send_headers', function () {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: no-referrer-when-downgrade");
    header("Permissions-Policy: geolocation=(self)");
});


function wst_limit_login_attempts($user, $username, $password)
{
    error_log('Function triggered'); // Debugging log
    if (empty($username)) {
        return $user; // Skip if no username is provided
    }

    $max_attempts = 5;
    $lockout_time = 900; // 15 minutes

    $attempts = get_transient('login_attempts_' . $username);

    if ($attempts && $attempts >= $max_attempts) {
        error_log('Too many login attempts for: ' . $username);
        return new WP_Error('too_many_attempts', 'Too many failed login attempts. Please try again later.');
    }

    if (is_wp_error($user)) {
        // Increment failed login attempts
        set_transient('login_attempts_' . $username, ($attempts ?: 0) + 1, $lockout_time);
        error_log('Login failed. Attempts: ' . (($attempts ?: 0) + 1));
        return $user;
    }

    // Successful login, clear failed attempts
    delete_transient('login_attempts_' . $username);
    error_log('Successful login. Clearing attempts for: ' . $username);

    return $user;
}
add_filter('authenticate', 'wst_limit_login_attempts', 30, 3);






// Hook into WordPress
add_action('login_enqueue_scripts', 'custom_login_styles');
add_action('admin_menu', 'custom_login_admin_menu');
add_action('admin_init', 'save_custom_colors');


// Enqueue custom styles for the login page
function custom_login_styles()
{
    $options = get_option('custom_login_colors');
    $background_color = isset($options['background_color']) ? $options['background_color'] : '#ffffff';
    $form_color = isset($options['form_color']) ? $options['form_color'] : '#f1f1f1';
    $form_text_color = isset($options['form_text_color']) ? $options['form_text_color'] : '#111111';
    $button_color = isset($options['button_color']) ? $options['button_color'] : '#0073aa';
    $link_color = isset($options['link_color']) ? $options['link_color'] : '#0073aa';
    $logo_url = isset($options['logo_url']) ? $options['logo_url'] : '';

    echo "
    <style>
        body.login {
            background: {$background_color} !important;
        }
        form {
            background: {$form_color} !important;
        }
        label {
            color: {$form_text_color} !important;
        }
        #login h1 a {
            background-image: url('{$logo_url}') !important;
            background-size: contain !important;
            height: 65px !important;
            width: auto !important;
        }
        .button-primary {
            background-color: {$button_color} !important;
            border-color: {$button_color} !important;
        }
        a {
            color: {$link_color} !important;
        }
        
        #kt_g_recaptcha_1>div>div>iframe {
        border-radius: 10px 0px 0px 10px;
        }
   

		#login-message a {
			color: white !important;
		}

		#login {
			padding: 2% !important;
		}

		.wrapper {
			position: fixed !important;
			height: 100vh;
			overflow: hidden;
			background: linear-gradient(135deg, {$button_color} 25%, transparent 25%) -10px 0,
				linear-gradient(225deg, {$button_color} 25%, transparent 25%) -10px 0,
				linear-gradient(315deg, {$button_color} 25%, transparent 25%),
				linear-gradient(45deg, {$button_color} 25%, transparent 25%);
			background-size: 10px 10px;
			width: 100vw;
			z-index: -1;
			overflow: scroll;
		}


		p#nav {
			display: flex !important;
			justify-content: space-between !important;
		}

		.language-switcher {
			display: none !important;
		}

		input {
			border: 1px solid lightgray !important;
			border-radius: 5px !important;
			border-bottom: 2px solid {$button_color} !important;
		}

		input:focus {
			border: 1px solid #111 !important;
			border-bottom: 2px solid #111 !important;
			box-shadow: none !important;
		}

		.login #login_error,
		.login .message,
		.login .success {
			border-width: 14px !important;
			border-radius: 5px;
		}

		.button-large {
			color: white !important;
			margin: 1px !important;
			background-color: {$button_color} !important;
			border: 0px solid white !important;
			border-radius: 10px !important;
			transition-duration: 0.5s;
		}

		.button-large:hover {
			background-color: white !important;
			color: {$button_color} !important;
			border: 1px solid {$button_color} !important;
		}

		.message {
			color: white;
			background-color: {$button_color} !important;
			border-left: 14px solid white !important;
			border-radius: 5px;
		}


		form#loginform,
		form#lostpasswordform,
		form#registerform {
			color: #111 !important;
			background-color: {$form_color};
			padding: 36px 34px 36px;
			border: 1px solid {$button_color};
			border-radius: 15px;
			border: 0px solid white;
			box-shadow: rgba(17, 17, 26, 0.1) 0px 8px 24px, rgba(17, 17, 26, 0.1) 0px 16px 56px, rgba(17, 17, 26, 0.1) 0px 24px 80px;
		}

		body.login div#login p#nav {
			display: -webkit-box;
		}

		p#nav>a,
		p#backtoblog>a,
		.privacy-policy-link {
			color: {$link_color} !important;
			font-weight: bold;
		}

		p#nav>a:hover,
		p#backtoblog>a:hover,
		.privacy-policy-link:hover {
			color: {$form_color} !important;
		}

        span.kt-recaptcha-branding-string a {
        color: {$button_color} !important;
        }
    </style>
    ";
}

add_action('init', fn() => strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false && wp_die('Not Found', '404', ['response' => 404]));


// Admin menu for configuring custom login styles and URL
function custom_login_admin_menu()
{
    add_menu_page(
        'Login Settings',
        'Secure Login',
        'manage_options',
        'custom-login-settings',
        'render_custom_login_settings_page',
        'dashicons-lock'
    );
}

// Render the admin settings page
function render_custom_login_settings_page()
{
    $options = get_option('custom_login_settings');
    $color_options = get_option('custom_login_colors');
    ?>
    <div class="wrap">
        <h1>Security Tweaks options</h1>
        <p>The plugin modifies htaccess settings, make sure to check them for any redirect loops or errors.</p>
        <p>Learn more about the functions and htaccess management by reading the documentation: <a
                href="https://github.com/swieza-strona/security-tweaks/blob/main/README.md">README</a>.</p>
        It covers hardening, firewall and protection for various scenarios including, enumeration, brute force, XSS, bots,
        crawlers and other malicious techniques.
        <pre>
        1. Hide WordPress version from RSS feeds
        2. Disable login error messages
        3. Disable XML-RPC completely
        4. Disable self-pingbacks
        5. Remove unnecessary links from wp_head
        6. DISALLOW_FILE_EDIT - Prevent editing of theme and plugin files
        7. DISALLOW_FILE_MODS - Prevent adding, updating, or deleting plugins and themes
        8. Secure uploads directory (wp-uploads)
        9. Disable directory listing dynamically
        10. Block user enumeration
        11. Add HTTP security headers
        12. Limit login attempts
        13. Admin menu for configuring custom login styles and URL
        14. Redirect default login URL to a custom URL specified in settings
        15. Copy and modify wp-login.php to create a custom login file
        16. Flush rewrite rules on activation or slug change remove .php extension
        17. Wave background animation
        18. Add math question to the registration form
        19. Validate the math question answer during registration (checkout excluded)
        20. Add, validate and clear honeypot field to all registration / login forms
        </pre>

        <form method="post" action="options.php">
            <?php
            settings_fields('custom_login_settings');
            do_settings_sections('custom_login_settings');
            ?>
            <h2>Custom URL</h2>
            <table class="form-table">
                <tr>
                    <th><label for="custom_login_slug">Custom Login URL Slug</label></th>
                    <td>
                        <input type="text" name="custom_login_settings[custom_login_slug]" id="custom_login_slug"
                            class="regular-text"
                            value="<?php echo isset($options['custom_login_slug']) ? esc_attr($options['custom_login_slug']) : ''; ?>">
                        <p class="description">Example: If you enter `my-login`, your login page will be at:
                            <code>yoursite.com/my-login.php</code>. Initially, <code>custom-login.php</code> will be the new
                            login
                            page if You don't change this.
                            When you change this, you can remove the initial login file from the server for additional
                            safety.
                        </p>
                    </td>
                </tr>
            </table>
            <h2>Security Settings</h2>
            <table class="form-table">
                <tr>
                    <th><label for="disallow_file_edit">Disable File Editing</label></th>
                    <td>
                        <label class="description"><input type="checkbox" name="custom_login_settings[disallow_file_edit]"
                                id="disallow_file_edit" value="1" <?php checked(isset($options['disallow_file_edit']) && $options['disallow_file_edit'], 1); ?>>
                            Prevent editing of theme and plugin files in the WordPress admin.</label>
                    </td>
                </tr>
                <tr>
                    <th><label for="disallow_file_mods">Disable File Modifications</label></th>
                    <td>
                        <label class="description">
                            <input type="checkbox" name="custom_login_settings[disallow_file_mods]" id="disallow_file_mods"
                                value="1" <?php checked(isset($options['disallow_file_mods']) && $options['disallow_file_mods'], 1); ?>>
                            Prevent adding, updating, or deleting plugins and themes.</label>
                    </td>
                </tr>
            </table>
            <h2>Customize Login Page Appearance</h2>
            <table class="form-table">
                <tr>
                    <th><label for="background_color">Background Color</label></th>
                    <td><input type="text" name="custom_login_colors[background_color]" id="background_color"
                            class="color-field"
                            value="<?php echo isset($color_options['background_color']) ? esc_attr($color_options['background_color']) : '#ffffff'; ?>">
                    </td>
                </tr>
                <tr>
                    <th><label for="form_color">Form Background Color</label></th>
                    <td><input type="text" name="custom_login_colors[form_color]" id="form_color" class="color-field"
                            value="<?php echo isset($color_options['form_color']) ? esc_attr($color_options['form_color']) : '#f1f1f1'; ?>">
                    </td>
                </tr>
                <tr>
                    <th><label for="form_text_color">Form Text Color</label></th>
                    <td><input type="text" name="custom_login_colors[form_text_color]" id="form_text_color"
                            class="color-field"
                            value="<?php echo isset($color_options['form_text_color']) ? esc_attr($color_options['form_text_color']) : '#111111'; ?>">
                    </td>
                </tr>
                <tr>
                    <th><label for="button_color">Button Color</label></th>
                    <td><input type="text" name="custom_login_colors[button_color]" id="button_color" class="color-field"
                            value="<?php echo isset($color_options['button_color']) ? esc_attr($color_options['button_color']) : '#0073aa'; ?>">
                    </td>
                </tr>
                <tr>
                    <th><label for="link_color">Link Color</label></th>
                    <td><input type="text" name="custom_login_colors[link_color]" id="link_color" class="color-field"
                            value="<?php echo isset($color_options['link_color']) ? esc_attr($color_options['link_color']) : '#0073aa'; ?>">
                    </td>
                </tr>
                <tr>
                    <th><label for="logo_url">Logo URL</label></th>
                    <td><input type="text" name="custom_login_colors[logo_url]" id="logo_url" class="regular-text"
                            value="<?php echo isset($color_options['logo_url']) ? esc_attr($color_options['logo_url']) : ''; ?>">
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}


// Save settings
function save_custom_colors()
{
    register_setting('custom_login_settings', 'custom_login_settings');
    register_setting('custom_login_settings', 'custom_login_colors');
}




//Redirect custom login
function redirect_custom_login()
{

    $options = get_option('custom_login_settings');
    $custom_login_slug = isset($options['custom_login_slug']) ? trim($options['custom_login_slug']) : 'custom-login'; // Default fallback slug


    if ($custom_login_slug) {

        // Register the query variable to detect the login page
        add_filter('query_vars', function ($vars) {
            $vars[] = 'custom_login_page';
            return $vars;
        });

        // Load the custom-login.php template when the custom login page is accessed
        add_action('template_redirect', function () use ($custom_login_slug) {
            if (get_query_var('custom_login_page')) {
                $custom_login_file = ABSPATH . $custom_login_slug . '.php';
                if (file_exists($custom_login_file)) {
                    include $custom_login_file;
                    exit;
                } else {
                    wp_die('Custom login page file not found.', 'Error', ['response' => 404]);
                }
            }
        });


        // Dynamically create the custom login file if it doesn't exist
        $login_file_path = ABSPATH . $custom_login_slug . '.php';
        if (!file_exists($login_file_path)) {
            copy_wp_login_to_custom_file($login_file_path, $custom_login_slug);
        }

        // Update login-related URLs
        add_filter('login_url', fn() => home_url('/' . $custom_login_slug . '.php'));
        add_filter('register_url', fn() => home_url('/' . $custom_login_slug . '.php?action=register'));
        add_filter('lostpassword_url', fn() => home_url('/' . $custom_login_slug . '.php?action=lostpassword'));
        add_filter('logout_url', fn() => wp_nonce_url(home_url('/' . $custom_login_slug . '.php?action=logout'), 'log-out'));
    }
}
add_action('init', 'redirect_custom_login');

// Flush rewrite rules when the custom login slug is updated
function flush_custom_login_rewrite_rules()
{
    redirect_custom_login(); // Ensure rewrite rules are registered
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'flush_custom_login_rewrite_rules');
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');


function load_custom_login_page()
{
    $options = get_option('custom_login_settings');
    $custom_login_slug = isset($options['custom_login_slug']) ? trim($options['custom_login_slug']) : 'custom-login';

    if (isset($_SERVER['REQUEST_URI'])) {
        $request_uri = trim($_SERVER['REQUEST_URI'], '/');
        $custom_login_path = trim(parse_url(home_url($custom_login_slug), PHP_URL_PATH), '/');

        if ($request_uri === $custom_login_path) {
            $custom_login_file = ABSPATH . $custom_login_slug . '.php';

            if (file_exists($custom_login_file)) {
                include $custom_login_file;
                exit;
            } else {
                wp_die('Custom login page file not found.', 'Error', ['response' => 404]);
            }
        }
    }
}
add_action('template_redirect', 'load_custom_login_page');


// Copy and modify wp-login.php to create a custom login file
function copy_wp_login_to_custom_file($path, $custom_login_slug)
{
    $wp_login_path = ABSPATH . 'wp-login.php';

    // Check if wp-login.php exists
    if (!file_exists($wp_login_path)) {
        return;
    }

    // Read wp-login.php content
    $wp_login_content = file_get_contents($wp_login_path);

    if ($wp_login_content) {
        // Replace references to `wp-login.php` with the custom login slug
        $wp_login_content = str_replace('wp-login', $custom_login_slug, $wp_login_content);

        // Replace any WordPress external links with the site's home URL
        $site_url = home_url();
        $wp_login_content = preg_replace('/https?:\/\/wordpress\.org/', $site_url, $wp_login_content);

        // Write modified content to the custom login file
        file_put_contents($path, $wp_login_content);

        // Secure permissions for the custom login file
        chmod($path, 0600);
    }
}


// Modyfikacja linku w mailach wysyłanych przy ustawianiu hasła
function my_custom_retrieve_password_message($message)
{

    $options = get_option('custom_login_settings');
    $custom_login_slug = isset($options['custom_login_slug']) ? trim($options['custom_login_slug']) : 'custom-login';
    $default_reset_url = 'wp-login';
    $custom_reset_url = $custom_login_slug;
    $message = str_replace($default_reset_url, $custom_reset_url, $message);
    return $message;
}



add_filter('retrieve_password_message', 'my_custom_retrieve_password_message', 10, 4);
;

// Modyfikacja adresu przy rejestracji nowych użytkowników
/* case specific - wyłączone LMS */
function my_custom_new_user_notification_email($wp_new_user_notification_email)
{
    $options = get_option('custom_login_settings');
    $custom_login_slug = isset($options['custom_login_slug']) ? trim($options['custom_login_slug']) : 'custom-login';
    // Define your custom login URL
    $default_reset_url = 'wp-login';
    $custom_reset_url = $custom_login_slug;

    // Replace the default login URL with your custom one
    $wp_new_user_notification_email['message'] = str_replace($default_reset_url, $custom_reset_url, $wp_new_user_notification_email['message']);

    return $wp_new_user_notification_email;
}

add_filter('wp_new_user_notification_email', 'my_custom_new_user_notification_email', 10, 3);




function add_wrapper_to_login_page()
{
    $options = get_option('custom_login_settings');
    $custom_login_slug = isset($options['custom_login_slug']) ? trim($options['custom_login_slug']) : '';

    // Ensure this runs only on the login page
    if (strpos($_SERVER['REQUEST_URI'], $custom_login_slug) !== false) {
        ?>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function () {
                // Select the body element
                const body = document.querySelector('body');

                if (body && body.firstChild) {
                    // Create the wrapper div
                    const wrapper = document.createElement('div');
                    wrapper.className = 'wrapper';

                    // Move the first child of body into the wrapper
                    wrapper.appendChild(body.firstChild);

                    // Append the wrapper as the new first child of the body
                    body.insertBefore(wrapper, body.firstChild);
                }

                //wave background animation
                let waveContainer = document.querySelector(".wrapper");
                var posX = 0;
                var posY = 0;
                if (waveContainer) {
                    function animateWave() {
                        posX += 0.2;
                        posY -= 0.1;
                        waveContainer.style.backgroundPosition = `${posX}px ${posY}px, ${posX}px ${posY}px, ${posX}px ${posY}px, ${posX}px ${posY}px`;
                        requestAnimationFrame(animateWave);
                    }
                    animateWave();
                }
            });
        </script>
        <?php
    }
}
add_action('login_head', 'add_wrapper_to_login_page');


// Add honeypot field to the registration form
function add_honeypot_to_registration_form()
{
    ?>
    <p style="display:none;">
        <label for="honeypot"><?php _e('Leave this field empty', 'your-text-domain'); ?></label>
        <input type="text" name="honeypot" id="honeypot" value="" />
    </p>
    <?php
}
add_action('register_form', 'add_honeypot_to_registration_form');

// Add honeypot field to the login form
function add_honeypot_to_login_form()
{
    ?>
    <p style="display:none;">
        <label for="honeypot"><?php _e('Leave this field empty', 'your-text-domain'); ?></label>
        <input type="text" name="honeypot" id="honeypot" value="" />
    </p>
    <?php
}
add_action('login_form', 'add_honeypot_to_login_form');

// Validate honeypot field during registration
function validate_honeypot_on_registration($errors, $sanitized_user_login, $user_email)
{
    if (!empty($_POST['honeypot'])) {
        $errors->add('honeypot_error', __('Error: Honeypot field should be empty. Please try again.', 'your-text-domain'));
    }
    return $errors;
}
add_filter('registration_errors', 'validate_honeypot_on_registration', 10, 3);

// Validate honeypot field during login
function validate_honeypot_on_login($user, $username, $password)
{
    if (!empty($_POST['honeypot'])) {
        return new WP_Error('honeypot_error', __('Login failed: Honeypot field should be empty. Please try again.', 'your-text-domain'));
    }
    return $user;
}
add_filter('authenticate', 'validate_honeypot_on_login', 20, 3);

// Clear honeypot field on successful registration
function clear_honeypot_session()
{
    if (isset($_POST['honeypot'])) {
        unset($_POST['honeypot']);
    }
}
add_action('user_register', 'clear_honeypot_session');

