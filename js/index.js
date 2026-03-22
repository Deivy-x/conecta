window.addEventListener("scroll", () => {
    document.querySelector(".navbar")
        .classList.toggle("abajo", window.scrollY > 50);
});
