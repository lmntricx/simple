// written by: matt obose

document.getElementById("menu-btn").addEventListener("click",
    function (){
        const element = document.getElementById("main-menu");
        if(element.style.display === "flex"){
            element.style.display = "none";
        }else{
            element.style.display = "flex";
        }
    }
);
