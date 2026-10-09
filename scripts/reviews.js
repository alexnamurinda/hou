// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Star rating functionality in the review form
    const ratingStars = document.querySelectorAll('.rating-select i');
    const ratingValue = document.getElementById('rating-value');

    ratingStars.forEach(star => {
        star.addEventListener('mouseover', function() {
            const rating = this.dataset.rating;
            highlightStars(rating);
        });

        star.addEventListener('mouseout', function() {
            highlightStars(ratingValue.value);
        });

        star.addEventListener('click', function() {
            const rating = this.dataset.rating;
            ratingValue.value = rating;
            highlightStars(rating);
        });
    });

    function highlightStars(rating) {
        ratingStars.forEach(star => {
            if (star.dataset.rating <= rating) {
                star.classList.remove('far');
                star.classList.add('fas');
            } else {
                star.classList.remove('fas');
                star.classList.add('far');
            }
        });
    }

    // Form submission
    const reviewForm = document.getElementById('review-form');
    if (reviewForm) {
        reviewForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // You can implement form submission logic here
            // For example, show a success message:

            const formContainer = reviewForm.parentElement;
            formContainer.innerHTML = `
                <div class="submission-success">
                    <i class="fas fa-check-circle text-success mb-3" style="font-size: 48px;"></i>
                    <h3>Thank You for Your Review!</h3>
                    <p>Your feedback has been submitted successfully and will appear on our site after review.</p>
                </div>
            `;
        });
    }
});
