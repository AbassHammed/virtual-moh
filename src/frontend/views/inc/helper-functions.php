<?php

function load_template($_template_file, $load_once = true)
{
    if ($load_once) {
        require_once $_template_file;
    } else {
        require $_template_file;
    }
}

function locate_template($template_name, $load = false, $load_once = true, $is_page = false)
{
    $located = '';
    if (! $template_name) {
        return $located;
    }

    if ($is_page && file_exists(PAGES . '/' . $template_name)) {
        $located = PAGES . '/' . $template_name;
    } elseif (file_exists(ABSPATH . $template_name)) {
        $located = ABSPATH.$template_name;
    } elseif (file_exists(VIEWS . '/' . $template_name)) {
        $located = VIEWS . '/' . $template_name;
    } elseif (file_exists(VIEWS . '/templates/' . $template_name)) {
        $located = VIEWS . '/templates/' . $template_name;
    }

    if ($load && '' !== $located) {
        load_template($located, $load_once);
    }

    return $located;
}

function get_page(string $name = '')
{
    $page = 'home.php';
    if (!empty($name)) {
        $page = "$name.php";
    }

    $GLOBALS['current_page_template'] = $page;

    if (! locate_template($page, true, true, true)) {
        return false;
    }
}

function get_header($name = null)
{

    $name      = (string) $name;
    $template = 'header.php';
    if ('' !== $name) {
        $template = "header-{$name}.php";
    }


    if (! locate_template($template, true, true)) {
        return false;
    }
}

function get_head()
{
    $template = 'head.php';

    if (! locate_template($template, true, true)) {
        return false;
    }
}

function get_footer($name = null)
{

    $name      = (string) $name;
    $template = 'footer.php';
    if ('' !== $name) {
        $template = "footer-{$name}.php";
    }


    if (! locate_template($template, true, true)) {
        return false;
    }
}

function get_footer_scripts()
{
    $template = 'footer-scripts.php';

    if (! locate_template($template, true, true)) {
        return false;
    }
}

function is_error($var): bool
{
    return is_object($var) && $var instanceof Error;
}

function home_url(string $path = ''): string
{
    $base_url = auto_detect_base_url();

    $parts = explode('#', $path, 2);
    $path_segment = $parts[0];
    $fragment = isset($parts[1]) ? sanitize_fragment($parts[1]) : '';

    $sanitized_path = sanitize_url_path($path_segment);
    $full_url = combine_url_segments($base_url, $sanitized_path);

    if ($fragment !== '') {
        $full_url .= "#$fragment";
    }

    return $full_url;
}

function auto_detect_base_url(): string
{
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return rtrim("{$scheme}://{$host}", '/');
}

function sanitize_url_path(string $path): string
{
    $sanitized = filter_var($path, FILTER_SANITIZE_URL);
    return ltrim($sanitized, '/');
}

function sanitize_fragment(string $fragment): string
{
    return rawurlencode($fragment);
}

function combine_url_segments(string $base, string $path): string
{
    return $base . ($path !== '' ? "/$path" : '');
}

function get_image_url(string $image_name): string
{
    return home_url(ASSETS . "/images/$image_name");
}

function get_script_url(string $script_name): string
{
    return home_url(ASSETS . "/js/$script_name");
}

function get_style_url(string $style_name): string
{
    return home_url(ASSETS . "/css/$style_name");
}


function get_template_part($slug, $name = null)
{

    $template = '' ;
    $name      = (string) $name;
    if ('' !== $name) {
        $template = "{$slug}-{$name}.php";
    }

    $template = "{$slug}.php";

    if (! locate_template($template, true, false, false)) {
        return false;
    }
}

function _current_template(): string
{
    return $GLOBALS['current_page_template'] ?? 'home.php';
}

function get_current_url()
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";

    $host = $_SERVER['HTTP_HOST'];

    $requestUri = $_SERVER['REQUEST_URI'];

    $url = rawurlencode("$protocol$host$requestUri");

    $GLOBALS['redirect_to'] = $url;
    return $url;

}
