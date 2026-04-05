document.addEventListener("DOMContentLoaded", () => {
    const counters = document.querySelectorAll(".counter");

    counters.forEach(counter => {
        let target = +counter.getAttribute("data-target");
        let text = counter.innerText.trim(); // Get initial text
        let extraSymbol = ""; 

        // Detect if the number contains '%' or '+'
        if (text.includes("%")) extraSymbol = "%";
        if (text.includes("+")) extraSymbol = "+";

        counter.innerText = "0" + extraSymbol; // Initialize with symbol

        const updateCounter = () => {
            let current = parseInt(counter.innerText.replace(/\D/g, "")); // Extract numbers
            let increment = Math.ceil(target / 80); // Speed of count

            if (current < target) {
                counter.innerText = (current + increment) + extraSymbol; // Append % or +
                setTimeout(updateCounter, 30);
            } else {
                counter.innerText = target + extraSymbol; // Ensure exact final value
            }
        };

        updateCounter();
    });
});

// back to top button

// get the button
let mybutton = document.getElementById("mybtn");


// when the user scrolls down 20px from the top document, show the button
window.onscroll = function() { scrollFunction() };

function scrollFunction() {
    if(document.body.scrollTop > 20 || document.documentElement.scrollTop > 20 ){
        mybutton.style.display = "block";
    } else {
        mybutton.style.display = "none";
    }
}

//  when the user click to the button then page scroll to the top 
function topfunction() {
    document.body.scrollTop = 0;
    document.documentElement.scrollTop = 0;
}


function goHome() {
    window.location.assign("index.php"); 
}