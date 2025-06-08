<?php get_header() ?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">

            <div id="loading" class="text-center">
                <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Chargement...</span>
                </div>
                <p class="mt-3 text-muted">Chargement d'un film aléatoire...</p>
            </div>

            <div id="error-state" class="text-center" style="display: none;">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                    <h4>Film non trouvé</h4>
                    <p id="error-message">Une erreur s'est produite lors du chargement du film.</p>
                    <button class="btn btn-primary" onclick="location.reload()">
                        <i class="fas fa-redo me-2"></i>Recharger la page
                    </button>
                </div>
            </div>

            <div id="movie-content" style="display: none;">
                <div class="card shadow-lg">
                    <div class="row g-0">
                        <div class="col-md-4">
                            <img id="movie-poster" src="/placeholder.svg" class="img-fluid rounded-start h-100"
                                style="object-fit: cover; min-height: 400px;" alt="">
                        </div>

                        <div class="col-md-8">
                            <div class="card-body h-100 d-flex flex-column">
                                <div class="flex-grow-1">
                                    <h2 id="movie-title" class="card-title text-primary mb-3"></h2>

                                    <div class="row mb-3">
                                        <div class="col-sm-6">
                                            <small class="text-muted">Année:</small>
                                            <p id="movie-year" class="mb-1"></p>
                                        </div>
                                        <div class="col-sm-6">
                                            <small class="text-muted">Durée:</small>
                                            <p id="movie-runtime" class="mb-1"></p>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <small class="text-muted">Genre:</small>
                                        <p id="movie-genre" class="mb-1"></p>
                                    </div>

                                    <div class="mb-3">
                                        <small class="text-muted">Réalisateur:</small>
                                        <p id="movie-director" class="mb-1"></p>
                                    </div>

                                    <div class="mb-3">
                                        <small class="text-muted">Acteurs:</small>
                                        <p id="movie-actors" class="mb-1"></p>
                                    </div>

                                    <div id="movie-plot-container" class="mb-4">
                                        <small class="text-muted">Synopsis:</small>
                                        <p id="movie-plot"></p>
                                    </div>
                                </div>

                                <div class="border-top pt-3">
                                    <div id="current-rating" class="mb-3" style="display: none;">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <small class="text-muted">Note moyenne:</small>
                                                <div class="d-flex align-items-center">
                                                    <span id="average-rating" class="h4 text-warning me-2">-</span>
                                                    <div id="rating-stars"></div>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <small class="text-muted">
                                                    <i class="fas fa-users me-1"></i>
                                                    <span id="total-votes">0</span> vote(s)
                                                </small>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="no-rating" class="mb-3">
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Aucune note pour ce film. Soyez le premier à le noter !
                                        </div>
                                    </div>

                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <i class="fas fa-star text-warning me-2"></i>Donnez votre note
                                            </h6>
                                            <form id="rating-form">
                                                <div class="mb-3">
                                                    <div class="btn-group" role="group" aria-label="Rating">
                                                        <input type="radio" class="btn-check" name="rating" id="rating1"
                                                            value="1">
                                                        <label class="btn btn-outline-warning" for="rating1">1 ⭐</label>

                                                        <input type="radio" class="btn-check" name="rating" id="rating2"
                                                            value="2">
                                                        <label class="btn btn-outline-warning" for="rating2">2 ⭐</label>

                                                        <input type="radio" class="btn-check" name="rating" id="rating3"
                                                            value="3">
                                                        <label class="btn btn-outline-warning" for="rating3">3 ⭐</label>

                                                        <input type="radio" class="btn-check" name="rating" id="rating4"
                                                            value="4">
                                                        <label class="btn btn-outline-warning" for="rating4">4 ⭐</label>

                                                        <input type="radio" class="btn-check" name="rating" id="rating5"
                                                            value="5">
                                                        <label class="btn btn-outline-warning" for="rating5">5 ⭐</label>
                                                    </div>
                                                </div>
                                                <div class="d-grid gap-2 d-md-flex">
                                                    <button type="submit" class="btn btn-success me-md-2">
                                                        <i class="fas fa-check me-2"></i>Noter ce film
                                                    </button>
                                                    <button type="button" class="btn btn-outline-primary"
                                                        onclick="location.reload()">
                                                        <i class="fas fa-refresh me-2"></i>Nouveau film
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php get_footer() ?>