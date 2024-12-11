jQuery(document).ready(function ($) {
    const plugins = routerPluginsData.plugins;

    let customPluginsTable = `
        <h2>Plugins Personalizados</h2>
        <table class="wp-list-table widefat plugins">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>`;

    $.each(plugins, function (slug, data) {
        const isActive = data.status === 1;
        const action = isActive ? 'deactivate' : 'activate';
        const actionLabel = isActive ? 'Desactivar' : 'Activar';

        customPluginsTable += `
            <tr>
                <td>${data.Name}</td>
                <td>${data.Description}</td>
                <td>
                    <button class="router-plugin-action" data-slug="${slug}" data-action="${action}">
                        ${actionLabel}
                    </button>
                </td>
            </tr>`;
    });

    customPluginsTable += `</tbody></table>`;

    // Inyectar la tabla debajo de la lista principal de plugins
    $('.wp-list-table.plugins').after(customPluginsTable);

    // Manejar acciones de activación/desactivación
    $('.router-plugin-action').on('click', function () {
        const button = $(this);
        const slug = button.data('slug');
        const action = button.data('action');

        $.post(ajaxurl, {
            action: 'router_toggle_plugin',
            plugin_slug: slug,
            action_type: action,
        }).done(function (response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.data);
            }
        });
    });
});
