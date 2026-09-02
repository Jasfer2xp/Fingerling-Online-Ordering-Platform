document.addEventListener('DOMContentLoaded', function () {
    var toggles = document.querySelectorAll('.toggle-password');
    if (!toggles.length) {
        return;
    }

    toggles.forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            var targetSelector = toggle.getAttribute('toggle');
            if (!targetSelector) {
                return;
            }
            var input = document.querySelector(targetSelector);
            if (!input) {
                return;
            }

            var isPassword = input.getAttribute('type') === 'password';
            input.setAttribute('type', isPassword ? 'text' : 'password');

            toggle.classList.toggle('fa-eye', !isPassword);
            toggle.classList.toggle('fa-eye-slash', isPassword);
        });
    });
});

