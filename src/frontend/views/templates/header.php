<?php get_head() ?>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="<?= home_url()?>">
                <i class="fas fa-film me-2"></i>Film-o-mètre
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= home_url() ?>">
                            <i class="fas fa-home me-1"></i>Accueil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= home_url('random-movie.php') ?>">
                            <i class="fas fa-random me-1"></i>Film Aléatoire
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= home_url('ratings.php') ?>">
                            <i class="fas fa-star me-1"></i>Notes
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>