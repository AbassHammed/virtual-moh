<?php

/**
 * Load a template part into a template.
 *
 * @param string $_template_file Path to template file.
 * @param bool   $load_once      Whether to require_once or require. Default true.
 */
function load_template($_template_file, $load_once = true)
{
    if ($load_once) {
        require_once $_template_file;
    } else {
        require $_template_file;
    }
}

/**
 * Retrieves the name of the highest priority template file that exists.
 *
 * @param string $template_name Template file to search for
 * @param bool         $load           If true the template file will be loaded if it is found.
 * @param bool         $load_once      Whether to require_once or require. Has no effect if `$load` is false.
 *                                     Default true.
 * @param bool   $is_page       Whether the template is a page template. Default false.
 * @return string The template filename if one is located.
 */
function locate_template($template_name, $load = false, $load_once = true, $is_page = false)
{
    $located = '';
    if (! $template_name) {
        return $located;
    }

    if ($is_page && file_exists(ABSPATH  . PAGES . '/' . $template_name)) {
        $located = ABSPATH . PAGES . '/' . $template_name;
    } elseif (file_exists(ABSPATH . $template_name)) {
        $located = ABSPATH.$template_name;
    } elseif (file_exists(ABSPATH . VIEWS . '/' . $template_name)) {
        $located = ABSPATH . VIEWS . '/' . $template_name;
    } elseif (file_exists(ABSPATH . VIEWS . '/templates/' . $template_name)) {
        $located = ABSPATH . VIEWS . '/templates/' . $template_name;
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

/**
 * Loads header template.
 *
 * Includes the header template for a theme or if a name is specified then a
 * specialized header will be included.
 *
 * For the parameter, if the file is called "header-special.php" then specify
 * "special".
 *
 * @param string $name The name of the specialized header.
 * @return void|false Void on success, false if the template does not exist.
 */
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

/**
 * Loads footer template.
 *
 * Includes the footer template for a theme or if a name is specified then a
 * specialized footer will be included.
 *
 * For the parameter, if the file is called "footer-special.php" then specify
 * "special".
 *
 * @param string $name The name of the specialized footer.
 * @return void|false Void on success, false if the template does not exist.
 */
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

/**
 * Generate the site's URL with optional path and hash fragment.
 *
 * @param string $path (Optional) Path and/or fragment (e.g. 'about#contact')
 * @return string Full URL
 */
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

function api_url(string $path = ''): string
{
    $base_url = auto_detect_base_url();

    $parts = explode('#', $path, 2);
    $path_segment = $parts[0];
    $fragment = isset($parts[1]) ? sanitize_fragment($parts[1]) : '';

    $sanitized_path = sanitize_url_path($path_segment);
    $full_url = combine_url_segments($base_url, "api/$sanitized_path");

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
    return home_url("view/assets/images/$image_name");
}

/**
 * Loads a template part into a template.
 *
 * The template is included using require, not require_once, so you may include the
 * same template part multiple times.
 *
 * For the $name parameter, if the file is called "{slug}-special.php" then specify
 * "special".
 *
 * @param string      $slug The slug name for the generic template.
 * @param string|null $name Optional. The name of the specialized template.
 * @return void|false Void on success, false if the template does not exist.
 */
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

/**
 * Get current page template filename
 * @return string e.g. 'home.php' or 'products.php'
 */
function _current_template(): string
{
    return $GLOBALS['current_page_template'] ?? 'home.php';
}

/**
 * Fonction utilitaire pour obtenir l'URL actuelle complète, y compris les paramètres de requête
 * @return string
 */
function get_current_url()
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";

    $host = $_SERVER['HTTP_HOST'];

    $requestUri = $_SERVER['REQUEST_URI'];

    $url = rawurlencode("$protocol$host$requestUri");

    $GLOBALS['redirect_to'] = $url;
    return $url;

}
