function base_url($path = '') {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $domain = $_SERVER['HTTP_HOST'];
    $baseUrl = $protocol . $domain . '/capstone/';
    return $baseUrl . $path;
}

function asset_url($path = '') {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $domain = $_SERVER['HTTP_HOST'];
    $baseUrl = $protocol . $domain . '/capstone/assets/';
    return $baseUrl . $path;
}