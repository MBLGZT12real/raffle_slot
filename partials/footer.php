<!-- Bootstrap 5 JS Bundle (termasuk Popper) -->
<script src="../assets/js/bootstrap.bundle.min.js" defer></script>

<script>
    // highlight active menu
    document.querySelectorAll('#mainMenu a').forEach(link => {
        if (link.href === location.href) {
            link.classList.add('active', 'fw-bold');
        }
    });
</script>