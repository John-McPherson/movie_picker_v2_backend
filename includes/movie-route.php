<?php


function registerMovieRoutes()
{
    error_log('registerMovieRoutes called');

    register_rest_route('moviepicker/v1', 'watchlist', array(
        "methods" => 'GET',
        'callback' =>  'getWatchList',
    ));
    register_rest_route('moviepicker/v1', 'watchlist', array(
        'methods'  => 'POST',
        'callback' =>  'updateMovieData',
        'permission_callback' => '__return_true',
    ));
}



function updateMovieData($data)
{
    $movieId = sanitize_text_field($data['movie']);
    $mainUrl = 'https://api.themoviedb.org/3/movie/' . $movieId . '?language=en-US';
    $watchProvidersUrl = 'https://api.themoviedb.org/3/movie/' . $movieId . '/watch/providers';

    $args = [
        'headers' => [
            'Authorization' => 'Bearer ' . esc_attr(get_option('the_moviedatabase_api_key'))
        ]
    ];
    $mainResponse = wp_remote_get($mainUrl, $args);
    $body = wp_remote_retrieve_body($mainResponse);
    $data = json_decode($body, true);

    $watchProvidersResponse = wp_remote_get($watchProvidersUrl, $args);
    $watchProvidersBody = wp_remote_retrieve_body($watchProvidersResponse);
    $watchProvidersdata = json_decode($watchProvidersBody, true);
    $watchProvidersdata = $watchProvidersdata['results']['GB'] ?? [];

    function processStreamingData($serviceData)
    {


        $existingServicesQuery =  get_posts([
            'post_type'      => 'streamingservice',
            'fields'         => 'ids',
            'meta_key'       => 'provider_id',
            'meta_value'     => array_map(function ($data) {
                return  $data['provider_id'];
            }, $serviceData),
            'posts_per_page' => -1,
        ]);
        $existingServiceIds = [];
        if (is_array($existingServicesQuery) && !empty($existingServicesQuery)) {

            foreach ($existingServicesQuery as $postId) {

                $providerId = get_post_meta($postId, 'provider_id', true);

                if ($providerId) {
                    $existingServiceIds[$postId] = $providerId;
                }
            }
        }
        $existingServiceIds = array_flip(
            $existingServiceIds
        );




        return array_map(
            function ($data) use ($existingServiceIds) {


                if (isset($existingServiceIds[$data['provider_id']])) {

                    return $existingServiceIds[$data['provider_id']];
                } else {



                    $serviceData = [
                        'post_title'   => $data['provider_name'],
                        'post_status'  => 'publish',
                        'post_author'  => get_current_user_id(),
                        'post_type'    => 'streamingservice',
                    ];
                    $serviceID = wp_insert_post($serviceData);
                    if ($serviceID) {
                        update_field('provider_id', $data['provider_id'], $serviceID);
                        update_field('logo_path', $data['logo_path'], $serviceID);
                    }
                    return $serviceID;
                }
            },
            $serviceData
        );
    }

    $existingMovie = get_posts([
        'post_type'  => 'movie',
        'meta_key'   => 'tmdb_id',
        'meta_value' => $data['id'],
        'numberposts' => 1
    ]);

    if ($existingMovie) {
        $post_id = $existingMovie[0];
    } else {
        $post_data = [
            'post_title'  => $data['title'],
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
            'post_type'   => 'movie',
        ];
        $post_id = wp_insert_post($post_data);
    }


    update_field('tmdb_id', $data['id'], $post_id);
    update_field('poster_path', $data['poster_path'], $post_id);
    update_field('runtime', $data['runtime'], $post_id);
    update_field('overview', $data['overview'], $post_id);





    $genres = array_map(function ($genre) {
        $query = new WP_Query([
            'post_type'      => 'genre',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'     => 'genre_id',
                    'value'   => $genre['id'],
                    'compare' => '='
                ]
            ]
        ]);
        if (! $query->have_posts()) {
            $genre_data = [
                'post_title' => $genre['name'],
                'post_status' => 'publish',
                'post_date' => date('Y-m-d H:i:s'),
                'post_author' => get_current_user_id(),
                'post_type' => 'genre',
                'post_category' => []
            ];
            $genre_id = wp_insert_post($genre_data);
            update_field('genre_id', $genre['id'], $genre_id);
        } else {
            $query->the_post();
            $genre_id = get_the_ID();
            wp_reset_postdata();
        }
        return $genre_id;
    }, $data['genres']);


    update_field('genres', $genres, $post_id);

    $streaming_services = [
        'flatrate' => !empty($watchProvidersdata['flatrate']) ? processStreamingData($watchProvidersdata['flatrate']) : [],
        'rent'     => !empty($watchProvidersdata['rent']) ? processStreamingData($watchProvidersdata['rent']) : [],
        'buy'      => !empty($watchProvidersdata['buy']) ? processStreamingData($watchProvidersdata['buy']) : [],
    ];

    update_field('streaming_services', $streaming_services, $post_id);

    return wp_send_json_success([
        'title' => $data['title'],
        'poster_path' => $data['poster_path'],
        'runtime' => $data['runtime'],
        'id' =>  $data['id'],
        'overview' => $data['overview'],
        'genres' => array_map(
            function ($genreID) {
                return [
                    'id' => esc_attr(get_field('genre_id', $genreID)),
                    'name' => esc_html(get_the_title($genreID))
                ];
            },
            $genres
        ),
        'streaming_services' => [
            'flatrate' => prepStreamingData($streaming_services['flatrate']),
            'rent' => prepStreamingData($streaming_services['rent']),
            'buy' => prepStreamingData($streaming_services['buy'])
        ]
    ]);
}

function prepStreamingData($data)
{
    if (empty($data) || !is_array($data)) {
        return [];
    }
    return array_map(function ($id) {
        return [
            'provider_name' => esc_html(get_the_title($id)),
            'provider_id' => esc_attr(get_field('provider_id', $id)),
            'logo_path' => esc_url(get_field('logo_path', $id)),
        ];
    }, $data);
}

function getWatchList()
{
    $mainQuery = new WP_Query([
        'post_type' => 'movie',
        'posts_per_page' => -1,
    ]);

    if (!$mainQuery->have_posts()) {
        return new \WP_REST_Response(['error' => 'No movies found'], 404);
    }

    $results = [];

    while ($mainQuery->have_posts()) {
        $mainQuery->the_post();

        $genres = get_field('genres');
        $genresData = [];

        $streamingServices = get_field('streaming_services') ?: [];

        $streamingServicesData = [
            "flatrate" => prepStreamingData($streamingServices['flatrate'] ?? []),
            "rent" => prepStreamingData($streamingServices['rent'] ?? []),
            "buy" => prepStreamingData($streamingServices['buy'] ?? []),
        ];

        if (!empty($genres) && is_array($genres)) {
            foreach ($genres as $genre) {
                $genresData[] = [
                    'id' => esc_attr(get_field('genre_id', $genre)),
                    'name' => esc_html(get_the_title($genre))
                ];
            }
        }

        $results[] = [
            'title' => esc_html(get_the_title()),
            'poster_path' => esc_url(get_field('poster_path')),
            'runtime' => esc_attr(get_field('runtime')),
            'id' => esc_attr(get_field('tmdb_id')),
            'overview' => esc_html(get_field('overview')),
            'genres' => $genresData,
            'streaming_services' => $streamingServicesData
        ];
    }

    wp_reset_postdata();

    return new \WP_REST_Response($results, 200);
}



add_action('rest_api_init',  'registerMovieRoutes');
