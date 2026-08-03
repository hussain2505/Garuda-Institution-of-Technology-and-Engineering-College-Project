// Garuda Institute of Technology & Engineering College - ERP
// Small client-side niceties. No sensitive logic lives here -
// all validation and authorization happens server-side in PHP.

document.addEventListener('DOMContentLoaded', function () {
    // Auto-hide success/info alerts after a few seconds
    document.querySelectorAll('.alert-success, .alert-info').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity 0.6s ease';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 700);
        }, 4000);
    });
});
