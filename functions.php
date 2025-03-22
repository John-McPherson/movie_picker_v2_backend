<?php

function apiOptionsPageHTML()
{
?>
    <div class="wrap">
        <h1>API Keys</h1>
        <form action="options.php" method="POST">
            <?php
            settings_fields(option_group: 'themeoptions');
            do_settings_sections(page: 'theme_api_keys');
            submit_button();
            ?>
        </form>
    </div>
<?php
}

function addThemeAdminOptions()
{
    add_settings_section(
        id: 'theme_api_keys',
        title: null,
        callback: null,
        page: 'theme_api_keys'
    );

    add_settings_field(
        id: 'the_moviedatabase_api_key',
        title: 'The Movie Database API KEY',
        callback: 'apiKeyHTML',
        page: 'theme_api_keys',
        section: 'theme_api_keys'
    );

    register_setting(
        option_group: "themeoptions",
        option_name: 'the_moviedatabase_api_key',
        args: [
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '0'
        ]
    );
}

function apiKeyHTML()
{
?>
    <input type="text" name="the_moviedatabase_api_key" value="<?php echo esc_attr(get_option('the_moviedatabase_api_key')); ?>">
<?php
}

function addThemeAdminMenu()
{
    add_options_page(
        page_title: 'API Keys',
        menu_title: 'API Keys',
        capability: 'manage_options',
        menu_slug: 'theme_api_keys',
        callback: 'apiOptionsPageHTML'
    );
}

add_action('admin_menu', 'addThemeAdminMenu');
add_action('admin_init', 'addThemeAdminOptions');
