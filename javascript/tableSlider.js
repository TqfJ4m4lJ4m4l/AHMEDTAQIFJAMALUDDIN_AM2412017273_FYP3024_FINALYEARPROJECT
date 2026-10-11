(() => {
    let currentIndex = 0;
   //How it works, Sliding the tables work by them being an int value.
   // +1 equals to next slide, -1 equals to previous slide
    function moveSlide(direction) {
        const track = document.getElementById("sliderTrack");
        const slides = document.getElementsByClassName("table-slide");

        if (!track || slides.length === 0) return;

        currentIndex += direction;

        if (currentIndex >= slides.length) {
            currentIndex = 0;
        } else if (currentIndex < 0) {
            currentIndex = slides.length - 1;
        }

        track.style.transform = `translateX(-${currentIndex * 100}%)`;
    }

    // Keeps existing onclick="moveSlide(...)" buttons working.
    window.moveSlide = moveSlide;
})();
