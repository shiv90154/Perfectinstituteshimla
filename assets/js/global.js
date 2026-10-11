/* =========================================
   GLOBAL SAFE JAVASCRIPT
   (UI SAFE • PRODUCTION READY)
========================================= */

document.addEventListener("DOMContentLoaded", () => {

    /* -----------------------------------------
       1. Remove focus outline on mouse click
          (keyboard navigation safe)
    ----------------------------------------- */
    document.body.addEventListener("mousedown", () => {
        document.body.classList.add("using-mouse");
    });

    document.body.addEventListener("keydown", () => {
        document.body.classList.remove("using-mouse");
    });

    /* -----------------------------------------
       2. Prevent accidental double click
    ----------------------------------------- */
    let lastClickTime = 0;
    document.addEventListener("click", (e) => {
        const now = Date.now();
        if (now - lastClickTime < 300) {
            e.preventDefault();
        }
        lastClickTime = now;
    });

    /* -----------------------------------------
       3. Safe external links handling
    ----------------------------------------- */
    document.querySelectorAll("a[target='_blank']").forEach(link => {
        link.setAttribute("rel", "noopener noreferrer");
    });

    /* -----------------------------------------
       4. Disable drag on images (UX polish)
    ----------------------------------------- */
    document.querySelectorAll("img").forEach(img => {
        img.setAttribute("draggable", "false");
    });

    /* -----------------------------------------
       5. Smooth anchor scroll fallback
    ----------------------------------------- */
    document.querySelectorAll("a[href^='#']").forEach(anchor => {
        anchor.addEventListener("click", function (e) {
            const targetId = this.getAttribute("href");
            if (targetId.length > 1) {
                const target = document.querySelector(targetId);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: "smooth" });
                }
            }
        });
    });

});

/* =========================================
   SAFETY: NO GLOBAL VARIABLES EXPOSED
========================================= */
