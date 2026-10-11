document.addEventListener("DOMContentLoaded", () => {
    const links = document.querySelectorAll(".nav-menu a, .mobile-nav-item");
    const currentPath = window.location.pathname.replace(/\/$/, "");

    links.forEach(link => {
        const linkPath = link.getAttribute("href").replace(/\/$/, "");
        if (linkPath === currentPath || (linkPath === "/" && currentPath === "")) {
            link.classList.add("active");
        }
    });
});
