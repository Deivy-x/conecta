
window.addEventListener("scroll", () => {

const navbar = document.querySelector(".navbar");

if(window.scrollY > 50){
navbar.style.boxShadow = "0 4px 20px rgba(0,0,0,0.15)";
}else{
navbar.style.boxShadow = "none";
}

});

const botones = document.querySelectorAll(".empleo-card button");

botones.forEach(boton => {

boton.addEventListener("click", () => {

alert("Próximamente podrás ver los detalles del empleo.");

});

});

const searchBtn = document.querySelector(".search-btn");

searchBtn.addEventListener("click", () => {

alert("Buscador en desarrollo");

});