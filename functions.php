<?php

require get_theme_file_path('includes/movie-route.php');






function register_custom_post_types()
{


    register_post_type('streamingservice', array(
        'rewrite' => array('slug' => 'Streaming Services'),
        'supports' => array('title'),
        'public' => true,
        'menu_icon' => 'dashicons-video-alt3',
        'show_in_rest' => true,
        'labels' => array(
            'name' => 'Streaming Services',
            'add_new_item' => 'Add new Streaming Service',
            'edit_item' => 'Edit Streaming Service',
            'all_items' => 'All Streaming Services',
            'singular_name' => 'Streaming Service',
        )
    ));

    register_post_type('movie', array(
        'rewrite' => array('slug' => 'Movies'),
        'supports' => array('title'),
        'public' => true,
        'menu_icon' => 'dashicons-editor-video',
        'show_in_rest' => true,
        'labels' => array(
            'name' => 'Movies',
            'add_new_item' => 'Add new Movie',
            'edit_item' => 'Edit Movie',
            'all_items' => 'All Movies',
            'singular_name' => 'Movie',
        )
    ));
    register_post_type('genre', array(
        'rewrite' => array('slug' => 'genres'),
        'supports' => array('editor', 'title'),
        'public' => true,
        'menu_icon' => 'dashicons-tickets-alt',
        'show_in_rest' => true,
        'labels' => array(
            'name' => 'Genres',
            'add_new_item' => 'Add new Genre',
            'edit_item' => 'Edit Genre',
            'all_items' => 'All Genres',
            'singular_name' => 'Genre',
        )
    ));
}





// init custom plugin option

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





add_action('rest_api_init', 'registerMovieRoutes');
add_action('admin_menu', 'addThemeAdminMenu');
add_action('admin_init', 'addThemeAdminOptions');
add_action("init", "register_custom_post_types");
