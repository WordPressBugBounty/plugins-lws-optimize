document.addEventListener("DOMContentLoaded", function () {
    const lazyElements = document.querySelectorAll(".lws-optimize-lazyload");

    if ("IntersectionObserver" in window) {
        let observer = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    let el = entry.target;
                    if (el.dataset.src) {
                        el.src = el.dataset.src;
                        el.removeAttribute("data-src");
                    } else if (el.dataset.datasrc) {
                        el.src = el.dataset.datasrc;
                        el.removeAttribute("data-data-src");
                    }

                    if (el.tagName === "VIDEO") {
                        const sources = el.querySelectorAll("source[data-src]");
                        sources.forEach(source => {
                            if (source.dataset.src) {
                                source.src = source.dataset.src;
                                source.removeAttribute("data-src");
                            } else if (source.dataset.datasrc) {
                                source.src = source.dataset.datasrc;
                                source.removeAttribute("data-data-src");
                            }
                        });
                        el.load();
                    }
                    el.classList.remove("lws-optimize-lazyload");
                    observer.unobserve(el);
                }
            });
        });

        lazyElements.forEach(el => {
            observer.observe(el);
        });
    } else {
        // Fallback for browsers that don't support IntersectionObserver
        lazyElements.forEach(el => {
            if (el.dataset.src) {
                el.src = el.dataset.src;
                el.removeAttribute("data-src");
            } else if (el.dataset.datasrc) {
                el.src = el.dataset.datasrc;
                el.removeAttribute("data-data-src");
            }
        });
    }
});
