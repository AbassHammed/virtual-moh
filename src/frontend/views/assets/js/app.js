class FilmOMetre {
  constructor() {
    this.currentMovie = null;
    this.apiBase = "/api/v1";
    this.init();
  }

  init() {
    this.loadRandomMovie();
    this.setupEventListeners();
  }

  setupEventListeners() {
    document.getElementById("rating-form").addEventListener("submit", (e) => {
      e.preventDefault();
      this.submitRating();
    });
  }

  async loadRandomMovie() {
    try {
      this.showLoading();

      const response = await fetch(`${this.apiBase}/film`);
      const data = await response.json();

      if (data.erreur) {
        throw new Error(data.erreur);
      }

      if (!data.Response || data.Response !== "True") {
        throw new Error("Film non trouvé. Veuillez réessayer.");
      }

      this.currentMovie = data;
      this.displayMovie(data);

      await this.loadMovieRating(data.imdbID);
    } catch (error) {
      console.error("Error loading movie:", error);
      this.showError(error.message);
    }
  }

  async loadMovieRating(movieId) {
    try {
      const response = await fetch(`${this.apiBase}/note/${movieId}`);

      if (response.status === 404) {
        this.showNoRating();
        return;
      }

      if (!response.ok) {
        throw new Error("Erreur lors du chargement de la note");
      }

      const ratingData = await response.json();
      this.displayRating(ratingData);
    } catch (error) {
      console.error("Error loading rating:", error);
      this.showNoRating();
    }
  }

  displayMovie(movie) {
    // Update movie poster
    const poster = document.getElementById("movie-poster");
    poster.src =
      movie.Poster && movie.Poster !== "N/A"
        ? movie.Poster
        : "/placeholder.svg?height=400&width=300";
    poster.alt = movie.Title;

    document.getElementById("movie-title").textContent = movie.Title;
    document.getElementById("movie-year").textContent =
      movie.Year !== "N/A" ? movie.Year : "Non spécifié";
    document.getElementById("movie-runtime").textContent =
      movie.Runtime !== "N/A" ? movie.Runtime : "Non spécifié";
    document.getElementById("movie-genre").textContent =
      movie.Genre !== "N/A" ? movie.Genre : "Non spécifié";
    document.getElementById("movie-director").textContent =
      movie.Director !== "N/A" ? movie.Director : "Non spécifié";
    document.getElementById("movie-actors").textContent =
      movie.Actors !== "N/A" ? movie.Actors : "Non spécifié";

    const plotContainer = document.getElementById("movie-plot-container");
    const plotElement = document.getElementById("movie-plot");
    if (movie.Plot && movie.Plot !== "N/A") {
      plotElement.textContent = movie.Plot;
      plotContainer.style.display = "block";
    } else {
      plotContainer.style.display = "none";
    }

    this.hideLoading();
    this.hideError();
    document.getElementById("movie-content").style.display = "block";
  }

  displayRating(ratingData) {
    const currentRatingDiv = document.getElementById("current-rating");
    const noRatingDiv = document.getElementById("no-rating");

    currentRatingDiv.style.display = "block";
    noRatingDiv.style.display = "none";

    const averageRating = Number.parseFloat(ratingData.moyenne);
    document.getElementById("average-rating").textContent =
      averageRating.toFixed(1);
    document.getElementById("total-votes").textContent = ratingData.votes;

    this.generateStars(averageRating);
  }

  showNoRating() {
    const currentRatingDiv = document.getElementById("current-rating");
    const noRatingDiv = document.getElementById("no-rating");

    currentRatingDiv.style.display = "none";
    noRatingDiv.style.display = "block";
  }

  generateStars(rating) {
    const starsContainer = document.getElementById("rating-stars");
    const fullStars = Math.floor(rating);
    const hasHalfStar = rating - fullStars >= 0.5;

    let starsHtml = "";

    for (let i = 0; i < fullStars; i++) {
      starsHtml += '<i class="fas fa-star text-warning"></i>';
    }

    if (hasHalfStar) {
      starsHtml += '<i class="fas fa-star-half-alt text-warning"></i>';
    }

    const remainingStars = 5 - fullStars - (hasHalfStar ? 1 : 0);
    for (let i = 0; i < remainingStars; i++) {
      starsHtml += '<i class="far fa-star text-warning"></i>';
    }

    starsContainer.innerHTML = starsHtml;
  }

  async submitRating() {
    const formData = new FormData(document.getElementById("rating-form"));
    const rating = formData.get("rating");

    if (!rating) {
      this.showAlert("Veuillez sélectionner une note.", "warning");
      return;
    }

    if (!this.currentMovie) {
      this.showAlert("Erreur: aucun film sélectionné.", "danger");
      return;
    }

    try {
      this.setFormDisabled(true);

      const response = await fetch(`${this.apiBase}/note`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          tconst: this.currentMovie.imdbID,
          rating: rating,
        }),
      });

      if (!response.ok) {
        throw new Error("Erreur lors de la soumission de la note");
      }

      const result = await response.json();

      this.showAlert(`Merci pour votre note de ${rating}/5 !`, "success");

      await this.loadMovieRating(this.currentMovie.imdbID);

      document.getElementById("rating-form").reset();
    } catch (error) {
      console.error("Error submitting rating:", error);
      this.showAlert(
        "Erreur lors de la soumission de votre note. Veuillez réessayer.",
        "danger"
      );
    } finally {
      this.setFormDisabled(false);
    }
  }

  setFormDisabled(disabled) {
    const form = document.getElementById("rating-form");
    const inputs = form.querySelectorAll("input, button");
    inputs.forEach((input) => {
      input.disabled = disabled;
    });
  }

  showLoading() {
    document.getElementById("loading").style.display = "block";
    document.getElementById("movie-content").style.display = "none";
    document.getElementById("error-state").style.display = "none";
  }

  hideLoading() {
    document.getElementById("loading").style.display = "none";
  }

  showError(message) {
    document.getElementById("error-message").textContent = message;
    document.getElementById("error-state").style.display = "block";
    document.getElementById("movie-content").style.display = "none";
    document.getElementById("loading").style.display = "none";
  }

  hideError() {
    document.getElementById("error-state").style.display = "none";
  }

  showAlert(message, type = "info") {
    const alertDiv = document.createElement("div");
    alertDiv.className = `alert alert-${type} alert-dismissible fade show mt-3`;
    alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

    const movieContent = document.getElementById("movie-content");
    movieContent.parentNode.insertBefore(alertDiv, movieContent.nextSibling);

    setTimeout(() => {
      if (alertDiv.parentNode) {
        alertDiv.remove();
      }
    }, 5000);
  }
}

document.addEventListener("DOMContentLoaded", () => {
  new FilmOMetre();
});
