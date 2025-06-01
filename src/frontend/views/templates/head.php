<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="author" content="AbassHammed" />
    <link rel="shortcut icon"
        href="<?= get_image_url('favicon.png') ?>"
        type="">

    <title>
        <?= htmlspecialchars(Meta::get('title')) ?>
    </title>
    <meta name="description"
        content="<?= htmlspecialchars(Meta::get('description')) ?>">

    <meta property="og:title"
        content="<?= htmlspecialchars(Meta::get('title'))?>">
    <meta property="og:description"
        content="<?= htmlspecialchars(Meta::get('description'))?>">
    <meta property="og:image"
        content="<?= get_image_url(Meta::get('image'))?>">
    <meta property="og:url"
        content="<?= $_SERVER['REQUEST_URI'] ?>">
    <meta property="og:locale" content="fr_FR">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Film-o-mètre">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title"
        content="<?= htmlspecialchars(Meta::get('title'))?>">
    <meta name="twitter:description"
        content="<?= htmlspecialchars(Meta::get('description'))?>">
    <meta name="twitter:image"
        content="<?= get_image_url(Meta::get('image'))?>">

    <link rel="stylesheet" type="text/css"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" />

    <link rel="stylesheet" type="text/css"
        href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css" />
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/jquery-nice-select/1.1.0/css/nice-select.min.css"
        integrity="sha512-CruCP+TD3yXzlvvijET8wV5WxxEh5H8P4cmz0RFbKK6FlZ2sYl3AEsKlLPHbniXKSrDdFewhbmBK5skbdsASbQ=="
        crossorigin="anonymous" />
    <link
        href="<?= get_style_url('font-awesome.min.css') ?>"
        rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

</head>