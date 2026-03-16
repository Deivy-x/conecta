const botones = document.querySelectorAll(".empleo-card button");

botones.forEach(boton => {
boton.addEventListener("click", () => {
alert("Próximamente podrás ver los detalles del empleo.");
});
});