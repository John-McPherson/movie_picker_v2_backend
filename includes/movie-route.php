<?php


function registerMovieRoutes()
{
    error_log('registerMovieRoutes called');

    register_rest_route('moviepicker/v1', 'watchlist', array(
        "methods" => 'GET',
        'callback' =>  'getWatchList',
    ));
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
