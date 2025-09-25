<?php
/**
 * Headless Dependency Manager
 *
 * Shows required and optional plugins/themes with colored statuses.
 * Blocks auto-updates for dependencies with allow_update = false.
 * To be included in the main Headless Mods plugin.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Headless_Dependency_Manager
{
    private $dependencies;

    public function __construct()
    {
        $this->dependencies = include __DIR__ . '/headless-deps.php';

        add_action('admin_menu', [$this, 'register_admin_page']);

        // Disable auto-updates for locked deps
        add_filter('auto_update_plugin', [$this, 'filter_plugin_updates'], 10, 2);
        add_filter('auto_update_theme', [$this, 'filter_theme_updates'], 10, 2);
    }

    /**
     * Register admin page under Plugins
     */
    public function register_admin_page()
    {
        add_plugins_page(
            __('Headless Dependencies', 'headless'),
            __('Headless Deps', 'headless'),
            'manage_options',
            'headless-dependencies',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Render admin page
     */
    public function render_admin_page()
    {
        ?>
        <div class="wrap headless-deps">
            <h1><?php esc_html_e('Headless Dependencies Manager', 'headless'); ?></h1>
            <p><?php esc_html_e('Monitor required and optional plugins, check versions, and install missing dependencies.', 'headless'); ?>
            </p>

            <!-- Inline styles for statuses -->
            <style>
                .headless-deps .status-active {
                    color: #2e7d32;
                    /* green */
                    font-weight: 600;
                }

                .headless-deps .status-inactive {
                    color: #f9a825;
                    /* yellow */
                    font-weight: 600;
                }

                .headless-deps .status-missing {
                    color: #c62828;
                    /* red */
                    font-weight: 600;
                }

                .headless-deps .status-outdated {
                    color: #ef6c00;
                    /* orange */
                    font-weight: 600;
                }

                .headless-deps .required-flag {
                    display: inline-block;
                    margin-left: 6px;
                    padding: 2px 6px;
                    font-size: 11px;
                    font-weight: 600;
                    color: #fff;
                    background: #c62828;
                    /* red */
                    border-radius: 3px;
                    text-transform: uppercase;
                }

                .headless-deps table td {
                    vertical-align: middle;
                }
            </style>

            <h2><?php esc_html_e('Themes', 'headless'); ?></h2>
            <?php $this->render_items_table($this->dependencies['themes'], 'theme'); ?>

            <h2><?php esc_html_e('Plugins', 'headless'); ?></h2>
            <?php $this->render_items_table($this->dependencies['plugins'], 'plugin'); ?>
        </div>
        <?php
    }

    /**
     * Render table of items
     */
    private function render_items_table($items, $type)
    {
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Name', 'headless'); ?></th>
                    <th><?php esc_html_e('Version', 'headless'); ?></th>
                    <th><?php esc_html_e('Author', 'headless'); ?></th>
                    <th><?php esc_html_e('Status', 'headless'); ?></th>
                    <th><?php esc_html_e('Actions', 'headless'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <?php $this->render_item_row($item, $type); ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render a single dependency row
     */
    private function render_item_row($item, $type)
    {
        $status = $this->check_status($item, $type);
        ?>
        <tr>
            <td>
                <strong><?php echo esc_html($item['name']); ?></strong>
                <?php if (!empty($item['required']) && $item['required']): ?>
                    <span class="required-flag"><?php esc_html_e('Required', 'headless'); ?></span>
                <?php endif; ?>
            </td>
            <td><?php echo !empty($item['version']) ? esc_html($item['version']) : '—'; ?></td>
            <td><?php echo !empty($item['author']) ? esc_html($item['author']) : '—'; ?></td>
            <td class="<?php echo esc_attr($status['class']); ?>"><?php echo esc_html($status['label']); ?></td>
            <td>
                <?php if ($status['action'] && !empty($item['url'])): ?>
                    <a href="<?php echo esc_url($item['url']); ?>" class="button" target="_blank">
                        <?php echo esc_html($status['action']); ?>
                    </a>
                <?php elseif ($status['action'] && $type === 'plugin'): ?>
                    <a href="<?php echo admin_url('plugins.php'); ?>" class="button">
                        <?php echo esc_html($status['action']); ?>
                    </a>
                <?php elseif ($status['action'] && $type === 'theme'): ?>
                    <a href="<?php echo admin_url('themes.php'); ?>" class="button">
                        <?php echo esc_html($status['action']); ?>
                    </a>
                <?php endif; ?>
            </td>
        </tr>
        <?php
        if (!empty($item['children'])) {
            foreach ($item['children'] as $child) {
                $this->render_item_row($child, $type);
            }
        }
    }

    /**
     * Check plugin or theme status
     */
    private function check_status($item, $type)
    {
        $status = [
            'label' => __('Not installed', 'headless'),
            'class' => 'status-missing',
            'action' => __('Download', 'headless'),
        ];

        if ($type === 'plugin') {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            $plugins = get_plugins();

            foreach ($plugins as $file => $data) {
                if (stripos($file, $item['slug']) !== false) {
                    if (is_plugin_active($file)) {
                        $status['label'] = __('Active', 'headless');
                        $status['class'] = 'status-active';
                        $status['action'] = false;
                    } else {
                        $status['label'] = __('Installed but inactive', 'headless');
                        $status['class'] = 'status-inactive';
                        $status['action'] = __('Activate', 'headless');
                    }

                    if (!empty($item['version']) && version_compare($data['Version'], $item['version'], '<')) {
                        $status['label'] = sprintf(__('Outdated (Installed %s)', 'headless'), $data['Version']);
                        $status['class'] = 'status-outdated';
                        $status['action'] = __('Update manually', 'headless');
                    }
                    return $status;
                }
            }
        }

        if ($type === 'theme') {
            $themes = wp_get_themes();
            foreach ($themes as $slug => $theme) {
                if ($slug === $item['slug']) {
                    if (wp_get_theme()->get_stylesheet() === $slug) {
                        $status['label'] = __('Active', 'headless');
                        $status['class'] = 'status-active';
                        $status['action'] = false;
                    } else {
                        $status['label'] = __('Installed but inactive', 'headless');
                        $status['class'] = 'status-inactive';
                        $status['action'] = __('Activate', 'headless');
                    }

                    if (!empty($item['version']) && version_compare($theme->get('Version'), $item['version'], '<')) {
                        $status['label'] = sprintf(__('Outdated (Installed %s)', 'headless'), $theme->get('Version'));
                        $status['class'] = 'status-outdated';
                        $status['action'] = __('Update manually', 'headless');
                    }
                    return $status;
                }
            }
        }

        return $status;
    }

    /**
     * Disable plugin auto-updates if allow_update = false
     */
    public function filter_plugin_updates($update, $item)
    {
        $locked = $this->collect_locked_slugs('plugin');
        foreach ($locked as $slug) {
            if (strpos($item->plugin, $slug) !== false) {
                return false; // block update
            }
        }
        return $update;
    }

    /**
     * Disable theme auto-updates if allow_update = false
     */
    public function filter_theme_updates($update, $item)
    {
        $locked = $this->collect_locked_slugs('theme');
        foreach ($locked as $slug) {
            if ($item->stylesheet === $slug || $item->template === $slug) {
                return false; // block update
            }
        }
        return $update;
    }

    /**
     * Collect all locked slugs
     */
    private function collect_locked_slugs($type)
    {
        $slugs = [];
        $items = ($type === 'theme') ? $this->dependencies['themes'] : $this->dependencies['plugins'];

        foreach ($items as $item) {
            if (isset($item['allow_update']) && $item['allow_update'] === false) {
                $slugs[] = $item['slug'];
            }
            if (!empty($item['children'])) {
                foreach ($item['children'] as $child) {
                    if (isset($child['allow_update']) && $child['allow_update'] === false) {
                        $slugs[] = $child['slug'];
                    }
                }
            }
        }

        return $slugs;
    }
}

new Headless_Dependency_Manager();
