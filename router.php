<?php
/*
Plugin Name: Plugin Router Mejorado con AJAX y Acciones en Lote
Description: Gestiona plugins personalizados mediante AJAX y permite acciones en lote.
Version: 2.5
Author: Gabriel Magallanes
*/

if (!defined('ABSPATH')) exit;

// Crear la tabla en la base de datos para guardar el estado de los plugins
register_activation_hook(__FILE__, 'router_create_table');

function router_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'router_plugins';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id INT(11) NOT NULL AUTO_INCREMENT,
        plugin_name VARCHAR(255) NOT NULL,
        plugin_slug VARCHAR(255) NOT NULL,
        status TINYINT(1) DEFAULT 0,
        PRIMARY KEY (id),
        FULLTEXT KEY plugin_name (plugin_name)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Encola estilos y scripts solo en la página de plugins (plugins.php).
 */
function enqueue_plugin_admin_assets($hook_suffix) {
    if ($hook_suffix === 'plugins.php') {
        wp_enqueue_style(
            'folders', 
            plugin_dir_url(__FILE__) . 'assets/css/folders.css',
            [], 
            null
        );

        // // Encola el script JS del plugin
        // wp_enqueue_script(
        //     'plugin-admin-script', // Handle del script
        //     plugin_dir_url(__FILE__) . 'assets/js/admin-script.js', // Ruta del JS
        //     ['jquery'], // Dependencias
        //     '1.0.0', // Versión
        //     true // Cargar en el footer
        // );

        // // Pasar datos de PHP a JS (opcional)
        // wp_localize_script('plugin-admin-script', 'pluginData', [
        //     'ajax_url' => admin_url('admin-ajax.php'),
        //     'nonce'    => wp_create_nonce('plugin_nonce'),
        // ]);

        wp_enqueue_style(
            'google-fonts-inter', 
            'https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap',
            [], 
            null 
        );
    }
}

add_action('admin_enqueue_scripts', 'enqueue_plugin_admin_assets');

// Cargar los plugins activos
function router_load_active_plugins() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'router_plugins';
    $active_plugins = $wpdb->get_col("SELECT plugin_slug FROM $table_name WHERE status = 1");

    foreach ($active_plugins as $plugin_slug) {
        $plugin_file = WP_CONTENT_DIR . "/$plugin_slug";
        if (file_exists($plugin_file)) {
            include_once($plugin_file);
        }
    }
}
add_action('plugins_loaded', 'router_load_active_plugins');

// Obtener plugins personalizados del directorio
function router_get_custom_plugins() {
    $custom_plugins_dir = WP_CONTENT_DIR . '/plugins-custom';
    $plugins = [];

    if (is_dir($custom_plugins_dir)) {
        $folders = scandir($custom_plugins_dir);

        foreach ($folders as $folder) {
            if ($folder === '.' || $folder === '..') continue;

            $plugin_files = [];

            // Revisa el directorio de cada plugin y busca archivos PHP con cabeceras de plugins
            $plugin_root = "$custom_plugins_dir/$folder";
            if (is_dir($plugin_root)) {
                $files = scandir($plugin_root);

                foreach ($files as $file) {
                    if (str_starts_with($file, '.')) continue;

                    // Si es un archivo PHP directamente en la raíz del directorio, agréguelo
                    if (str_ends_with($file, '.php')) {
                        $plugin_files[] = "$folder/$file";
                    }
                }
            }

            // Revisar cada archivo PHP encontrado en el directorio
            foreach ($plugin_files as $plugin_file) {
                $plugin_path = "$custom_plugins_dir/$plugin_file";
                if (!is_readable($plugin_path)) continue;

                // Obtener los datos del plugin utilizando get_file_data
                $plugin_data = get_file_data($plugin_path, [
                    'Name'        => 'Plugin Name',
                    'Description' => 'Description',
                    'Version'     => 'Version',
                    'Author'      => 'Author'
                ]);

                if (!empty($plugin_data['Name'])) {
                    // Usamos plugin_basename para generar el slug relativo desde plugins-custom/
                    $plugin_slug = str_replace(WP_CONTENT_DIR . '/', '', $plugin_path);
                    $plugins[$plugin_slug] = $plugin_data;
                }
            }
        }
    }

    return $plugins;
}


// Inyectar la tabla de plugins personalizados con jQuery y agregar el sistema de acciones en lote
add_action('admin_footer-plugins.php', 'router_inject_custom_plugins_table');

function router_inject_custom_plugins_table() {
    $plugins = router_get_custom_plugins();
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function ($) {
            let customPluginsTable = `
                <h2>Plugins Personalizados</h2>
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <label for="bulk-action-selector-top" class="screen-reader-text">Seleccionar acción en lote</label>
                        <select name="action" id="bulk-action-selector-top">
                            <option value="-1">Acciones en lote</option>
                            <option value="activate-selected">Activar</option>
                            <option value="deactivate-selected">Desactivar</option>
                        </select>
                        <input type="submit" id="doaction" class="button action" value="Aplicar">
                    </div>
                    <br class="clear">
                </div>

                <table class="wp-list-table widefat plugins">
                    <thead>
                        <tr>
                            <th scope="col" id="cb" class="manage-column column-cb check-column" style="padding: 8px 7px;">
                                <label class="screen-reader-text" for="cb-select-all-1">Seleccionar todos</label>
                                <input id="cb-select-all-1" type="checkbox">
                            </th>
                            <th scope="col" class="manage-column column-primary">Nombre</th>
                            <th scope="col">Descripción</th>
                            <th scope="col">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="the-list-custom">
            `;

            <?php foreach ($plugins as $slug => $plugin) : 
                $is_active = router_get_plugin_state($slug);
                $action_label = $is_active ? 'Desactivar' : 'Activar';
                $row_class = $is_active ? 'active' : 'inactive';
            ?>
                customPluginsTable += `
                    <tr class="<?php echo $row_class; ?>" data-slug="<?php echo esc_attr($slug); ?>" data-plugin="<?php echo esc_attr($slug); ?>">
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="checked[]" value="<?php echo esc_attr($slug); ?>">
                        </th>
                        <td class="plugin-title column-primary">
                            <strong><?php echo esc_html($plugin['Name']); ?></strong>
                            <div class="row-actions visible">
                                <span class="<?php echo $is_active ? 'deactivate' : 'activate'; ?>">
                                    <a href="#" class="router-plugin-action" data-plugin="<?php echo esc_attr($slug); ?>" data-action="<?php echo $is_active ? 'deactivate' : 'activate'; ?>">
                                        <?php echo $action_label; ?>
                                    </a>
                                </span>
                            </div>
                            <button type="button" class="toggle-row">
                                <span class="screen-reader-text">Mostrar más detalles</span>
                            </button>
                        </td>
                        <td class="column-description desc">
                            <div class="plugin-description">
                                <p><?php echo esc_html($plugin['Description']); ?></p>
                            </div>
                            <div class="<?php echo $row_class; ?> second plugin-version-author-uri">
                                Versión <?php echo esc_html($plugin['Version']); ?> | Por <?php echo esc_html($plugin['Author']); ?>
                            </div>
                        </td>
                        <td class="column-auto-updates">
                            <!-- Si necesitas agregar algo más aquí, puedes hacerlo -->
                        </td>
                    </tr>
                `;
            <?php endforeach; ?>

            customPluginsTable += `
                    </tbody>
                </table>
                <div class="tablenav bottom">
                    <div class="alignleft actions bulkactions">
                        <label for="bulk-action-selector-bottom" class="screen-reader-text">Seleccionar acción en lote</label>
                        <select name="action" id="bulk-action-selector-bottom">
                            <option value="-1">Acciones en lote</option>
                            <option value="activate-selected">Activar</option>
                            <option value="deactivate-selected">Desactivar</option>
                        </select>
                        <input type="submit" id="doaction2" class="button action" value="Aplicar">
                    </div>
                    <br class="clear">
                </div>
            `;

            // Insertar el nuevo listado debajo de la tabla original de plugins
            $('.wp-list-table.widefat.plugins').after(customPluginsTable);

            // Manejar activación/desactivación con AJAX
            $('.router-plugin-action').click(function (e) {
                e.preventDefault();
                let pluginSlug = $(this).data('plugin');
                let action = $(this).data('action');

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'router_toggle_plugin',
                        plugin: pluginSlug,
                        toggle_action: action,
                        _ajax_nonce: '<?php echo wp_create_nonce('router_toggle_plugin'); ?>'
                    },
                    success: function (response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error: ' + response.data);
                        }
                    }
                });
            });

            // Manejar acciones en lote
            $('#doaction, #doaction2').click(function (e) {
                e.preventDefault();
                let bulkAction = $(this).closest('.tablenav').find('select[name="action"]').val();
                let selectedPlugins = [];

                $('#the-list-custom input[name="checked[]"]:checked').each(function () {
                    selectedPlugins.push($(this).val());
                });

                if (bulkAction === '-1' || selectedPlugins.length === 0) {
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'router_bulk_toggle_plugins',
                        plugins: selectedPlugins,
                        toggle_action: bulkAction,
                        _ajax_nonce: '<?php echo wp_create_nonce('router_bulk_toggle_plugins'); ?>'
                    },
                    success: function (response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error: ' + response.data);
                        }
                    }
                });
            });
        });
    </script>
    <?php
}

// Verificar el estado del plugin
function router_get_plugin_state($plugin_slug) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'router_plugins';

    // Se asegura de que los slugs coincidan con lo almacenado en la base de datos
    $status = $wpdb->get_var($wpdb->prepare(
        "SELECT status FROM $table_name WHERE plugin_slug = %s",
        $plugin_slug
    ));

    return $status === '1';
}

// Manejar la activación/desactivación de plugins con AJAX
add_action('wp_ajax_router_toggle_plugin', 'router_toggle_plugin');
add_action('wp_ajax_router_bulk_toggle_plugins', 'router_bulk_toggle_plugins');

function router_toggle_plugin() {
    check_ajax_referer('router_toggle_plugin');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('No tienes permisos suficientes.');
    }

    $plugin_slug = sanitize_text_field($_POST['plugin']);
    $action = sanitize_text_field($_POST['toggle_action']);

    if ($action === 'activate') {
        router_activate_plugin($plugin_slug);
    } elseif ($action === 'deactivate') {
        router_deactivate_plugin($plugin_slug);
    }

    wp_send_json_success();
}

// Manejar activación/desactivación en lote
function router_bulk_toggle_plugins() {
    check_ajax_referer('router_bulk_toggle_plugins');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('No tienes permisos suficientes.');
    }

    $plugins = $_POST['plugins'];
    $action = sanitize_text_field($_POST['toggle_action']);

    foreach ($plugins as $plugin_slug) {
        if ($action === 'activate-selected') {
            router_activate_plugin($plugin_slug);
        } elseif ($action === 'deactivate-selected') {
            router_deactivate_plugin($plugin_slug);
        }
    }

    wp_send_json_success();
}

// Activar un plugin
function router_activate_plugin($plugin_slug) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'router_plugins';

    $plugins = router_get_custom_plugins();
    $plugin_name = isset($plugins[$plugin_slug]['Name']) ? $plugins[$plugin_slug]['Name'] : '';

    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT plugin_slug FROM $table_name WHERE plugin_slug = %s",
        $plugin_slug
    ));

    if ($exists) {
        $wpdb->update(
            $table_name,
            ['status' => 1, 'plugin_name' => $plugin_name],
            ['plugin_slug' => $plugin_slug]
        );
    } else {
        $wpdb->insert(
            $table_name,
            ['plugin_slug' => $plugin_slug, 'plugin_name' => $plugin_name, 'status' => 1]
        );
    }
}

// Desactivar un plugin
function router_deactivate_plugin($plugin_slug) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'router_plugins';

    $wpdb->update($table_name, ['status' => 0], ['plugin_slug' => $plugin_slug]);
}

add_action('admin_footer-plugins.php', 'router_inject_folder_list');

function router_inject_folder_list() {
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function ($) {
            var folderListHtml = '<div class="router-folders">' +
                '<h1 class="folder-title">Folders</h1>' +
                '<div class="folder-container">' +
                '<div class="folder">' +
                '<img class="folder-icon" style="transform: translateX(-9px); width: 62px;" src="https://img.icons8.com/?size=100&id=12160&format=png&color=000000">' +
                '<div class="folder-name">Results 2023</div>' +
                '<div class="folder-info">23 Files · 137 MB</div>' +
                '</div>' +
                '<div class="folder">' +
                '<img class="folder-icon" style="transform: translateX(-9px); width: 62px;" src="https://img.icons8.com/?size=100&id=12160&format=png&color=000000">' +
                '<div class="folder-name">Market Analysis</div>' +
                '<div class="folder-info">8 Files · 56 MB</div>' +
                '</div>' +
                '<div class="folder">' +
                '<img class="folder-icon" style="transform: translateX(-9px); width: 62px;" src="https://img.icons8.com/?size=100&id=12160&format=png&color=000000">' +
                '<div class="folder-name">All contract</div>' +
                '<div class="folder-info">37 Files · 92 MB</div>' +
                '</div>' +
                '<div class="folder">' +
                '<img class="folder-icon" style="transform: translateX(-9px); width: 62px;" src="https://img.icons8.com/?size=100&id=12160&format=png&color=000000">' +
                '<div class="folder-name">Archived</div>' +
                '<div class="folder-info">99 Files · 267 MB</div>' +
                '</div>' +
                '</div></div>';

            $('.tablenav.top').before(folderListHtml);
        });
    </script>
    <?php
}

