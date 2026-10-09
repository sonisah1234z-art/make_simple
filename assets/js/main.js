// Signal that the main script file was loaded
try {
    console.log('[main.js] script loaded');
} catch (e) {}

// Global error catcher to surface any JS errors that may stop execution
window.addEventListener('error', function (ev) {
    try {
        console.log('[main.js] window error:', ev && (ev.message || ev.error && ev.error.message) || ev);
    } catch (e) {}
});

document.addEventListener("DOMContentLoaded", function () {
    try {
        console.log('[main.js] DOMContentLoaded');
    } catch (e) {}
    var sidebarToggle = document.getElementById("sidebarToggle");
    var sidebar = document.querySelector(".sidebar");

    function setSidebarOpen(open) {
        if (!sidebar) return;
        if (open) {
            sidebar.classList.remove('d-none');
            sidebarToggle && sidebarToggle.setAttribute('aria-expanded', 'true');
            document.documentElement.classList.add('sidebar-open');
        } else {
            sidebar.classList.add('d-none');
            sidebarToggle && sidebarToggle.setAttribute('aria-expanded', 'false');
            document.documentElement.classList.remove('sidebar-open');
        }
    }

    if (sidebarToggle && sidebar) {
        // Click handler
        sidebarToggle.addEventListener('click', function (ev) {
            ev.preventDefault();
            var isHidden = sidebar.classList.contains('d-none');
            setSidebarOpen(isHidden);
        });

        // Support keyboard activation (Enter / Space)
        sidebarToggle.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' || ev.key === ' ') {
                ev.preventDefault();
                sidebarToggle.click();
            }
        });

        // Close sidebar when a sidebar link is clicked on small screens
        sidebar.addEventListener('click', function (ev) {
            var target = ev.target;
            while (target && target !== sidebar) {
                if (target.matches && target.matches('a.nav-link')) {
                    if (window.innerWidth < 992) {
                        setSidebarOpen(false);
                    }
                    return;
                }
                target = target.parentNode;
            }
        });

        // Ensure initial aria state
        sidebarToggle.setAttribute('aria-controls', 'main-sidebar');
        sidebar.setAttribute('id', 'main-sidebar');
        if (!sidebarToggle.hasAttribute('aria-expanded')) sidebarToggle.setAttribute('aria-expanded', sidebar.classList.contains('d-none') ? 'false' : 'true');
    }

    var chartCanvas = document.getElementById("adminChart");
    if (chartCanvas && window.Chart) {
        var values = (chartCanvas.dataset.values || "0,0,0,0")
            .split(",")
            .map(function (value) {
                return Number(value) || 0;
            });

        new Chart(chartCanvas, {
            type: "doughnut",
            data: {
                labels: ["Patients", "Doctors", "Appointments", "Pending"],
                datasets: [{
                    data: values,
                    backgroundColor: ["#2563eb", "#14b8a6", "#f59e0b", "#ef4444"],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: "bottom",
                        labels: {
                            boxWidth: 10,
                            font: { size: 11 }
                        }
                    }
                }
            }
        });
    }

    var heroSlides = document.querySelectorAll('.hero-slide');
    var sliderDots = document.querySelectorAll('.slider-dot');
    if (heroSlides.length && sliderDots.length) {
        var activeIndex = 0;
        var slideInterval = setInterval(function () {
            var nextIndex = (activeIndex + 1) % heroSlides.length;
            heroSlides[activeIndex].classList.remove('active');
            sliderDots[activeIndex].classList.remove('active');
            heroSlides[nextIndex].classList.add('active');
            sliderDots[nextIndex].classList.add('active');
            activeIndex = nextIndex;
        }, 6000);

        sliderDots.forEach(function (dot) {
            dot.addEventListener('click', function () {
                clearInterval(slideInterval);
                heroSlides[activeIndex].classList.remove('active');
                sliderDots[activeIndex].classList.remove('active');
                activeIndex = Number(dot.dataset.slide) || 0;
                heroSlides[activeIndex].classList.add('active');
                sliderDots[activeIndex].classList.add('active');
                slideInterval = setInterval(arguments.callee, 6000);
            });
        });
    }
});
