</script>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.getElementById('togglePassword').addEventListener('click', function() {
    const pw = document.getElementById('password');
    const type = pw.getAttribute('type') === 'password' ? 'text' : 'password';
    pw.setAttribute('type', type);
    this.querySelector('i').classList.toggle('fa-eye');
    this.querySelector('i').classList.toggle('fa-eye-slash');
});

// SweetAlert for suspended supplier - remove duplicate
<?php if(!empty($suspended_reason)): ?>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: "error",
        title: "Account Suspended",
        html: `
            <p>Your account has been temporarily suspended.</p>
            <p><strong>Reason:</strong> <?php echo addslashes($suspended_reason); ?></p>
            <p>If you believe this is a mistake, please report it to the administrator.</p>
        `,
        confirmButtonText: "Report This Issue",
        showCancelButton: true,
        cancelButtonText: "Close",
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "report_suspension.php";
        } else {
            window.location.href = "login.php";
        }
    });
});
<?php endif; ?>
</script>