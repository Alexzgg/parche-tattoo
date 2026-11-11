/*=========================================
        PARCHE DEL TATTOO
        app.js
==========================================*/

//=============================
// MENSAJE DE BIENVENIDA
//=============================

window.addEventListener("load", () => {

    console.log("Bienvenido a Parche del Tattoo");

});

//=============================
// BUSCADOR
//=============================

const buscador = document.querySelector(".buscador input");

if(buscador){

    buscador.addEventListener("keyup", function(){

        let texto = this.value.toLowerCase();

        let tarjetas = document.querySelectorAll(".tattoo");

        tarjetas.forEach(card=>{

            let titulo = card.querySelector("h3").textContent.toLowerCase();

            if(titulo.indexOf(texto)>-1){

                card.style.display="block";

            }else{

                card.style.display="none";

            }

        });

    });

}

//=============================
// EFECTO BOTONES
//=============================

const botones = document.querySelectorAll("button");

botones.forEach(btn=>{

    btn.addEventListener("mouseenter",()=>{

        btn.style.transform="scale(1.05)";

    });

    btn.addEventListener("mouseleave",()=>{

        btn.style.transform="scale(1)";

    });

});

//=============================
// EFECTO TARJETAS
//=============================

const cards = document.querySelectorAll(".card");

cards.forEach(card=>{

    card.addEventListener("mouseenter",()=>{

        card.style.boxShadow="0 15px 35px rgba(157,92,255,.4)";

    });

    card.addEventListener("mouseleave",()=>{

        card.style.boxShadow="none";

    });

});

//=============================
// ANIMACIÓN GALERÍA
//=============================

const tatuajes = document.querySelectorAll(".tattoo");

tatuajes.forEach((card,index)=>{

    card.style.opacity="0";

    card.style.transform="translateY(60px)";

    setTimeout(()=>{

        card.style.transition="1s";

        card.style.opacity="1";

        card.style.transform="translateY(0px)";

    },index*200);

});

//=============================
// SCROLL HEADER
//=============================

window.addEventListener("scroll",()=>{

    const header = document.querySelector("header");

    if(window.scrollY>50){

        header.style.background="8b3dff";
        header.style.boxShadow="0 5px 20px 8b3dff";

    }else{

        header.style.background="8b3dff";
        header.style.boxShadow="none";

    }

});

//=============================
// BOTÓN VOLVER ARRIBA
//=============================

const subir = document.createElement("div");

subir.innerHTML="⬆";

subir.style.position="fixed";
subir.style.right="25px";
subir.style.bottom="25px";
subir.style.width="50px";
subir.style.height="50px";
subir.style.background="#8b3dff";
subir.style.color="white";
subir.style.display="flex";
subir.style.justifyContent="center";
subir.style.alignItems="center";
subir.style.borderRadius="50%";
subir.style.cursor="pointer";
subir.style.fontSize="22px";
subir.style.display="none";
subir.style.zIndex="9999";

document.body.appendChild(subir);

window.addEventListener("scroll",()=>{

    if(window.scrollY>400){

        subir.style.display="flex";

    }else{

        subir.style.display="none";

    }

});

subir.addEventListener("click",()=>{

    window.scrollTo({

        top:0,

        behavior:"smooth"

    });

});

//=============================
// FECHA EN CONSOLA
//=============================

const fecha = new Date();

console.log(

"Hoy es: " +

fecha.toLocaleDateString()

);

//=============================
// MENSAJE AL HACER CLICK
//=============================

const explorar = document.querySelector("button");

if(explorar){

explorar.addEventListener("click",()=>{

    alert("¡Bienvenido a la comunidad Parche del Tattoo!");

});

}