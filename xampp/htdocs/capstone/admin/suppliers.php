<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="actionForm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="actionModalTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="action_type">
                    <input type="hidden" name="supplier_id" id="action_supplier_id">
                    <input type="hidden" name="ajax" value="1">
                    <p id="action_message"></p>
                    <div class="mb-3">
                        <label class="form-label">Admin Notes <span id="notesRequiredIndicator" class="text-danger" style="display: none;">*</span></label>
                        <textarea class="form-control" name="admin_notes" id="admin_notes" rows="3" placeholder="Add notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" id="action_submit">Confirm</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Approve Supplier
function approveSupplier(supplierId) {
    if (confirm('Are you sure you want to approve this supplier?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="supplier_id" value="${supplierId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Reject Supplier Modal
function rejectSupplier(supplierId) {
    document.getElementById('rejectSupplierId').value = supplierId;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

// Suspend / Reactivate Modal
function actOnSupplier(id, name, action) {
    const modal = new bootstrap.Modal(document.getElementById('actionModal'));
    const title = document.getElementById('actionModalTitle');
    const msg = document.getElementById('action_message');
    const submit = document.getElementById('action_submit');
    const typeInput = document.getElementById('action_type');
    const idInput = document.getElementById('action_supplier_id');

    idInput.value = id;
    typeInput.value = action === 'suspend' ? 'suspend_supplier' : 'reactivate_supplier';

    // Get the reason from the supplier record
    const supplierRow = document.querySelector(`tr[data-supplier-id="${id}"]`);
    const reasonCell = supplierRow.querySelector('td:nth-child(7)');
    const reason = reasonCell.textContent.trim();

    if (action === 'suspend') {
        title.textContent = 'Suspend Supplier';
        msg.innerHTML = `Suspend <strong>${name}</strong>? They will lose access temporarily.<br><br><em>Current reason: ${reason}</em>`;
        submit.className = 'btn btn-warning';
        submit.textContent = 'Suspend';
        // Make the admin notes field required for suspension
        document.querySelector('textarea[name="admin_notes"]').setAttribute('required', 'required');
    } else {
        title.textContent = 'Reactivate Supplier';
        msg.innerHTML = `Reactivate <strong>${name}</strong>? They will regain access.`;
        submit.className = 'btn btn-success';
        submit.textContent = 'Reactivate';
        // Remove required attribute for reactivation
        document.querySelector('textarea[name="admin_notes"]').removeAttribute('required');
    }
    modal.show();
}

// AJAX Submit (Suspend/Reactivate)
document.getElementById('actionForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    const submitBtn = document.getElementById('action_submit');
    const originalHTML = submitBtn.innerHTML;
    const actionType = document.getElementById('action_type').value;
    const adminNotes = document.querySelector('textarea[name="admin_notes"]');
    
    // Validate admin notes for suspension
    if (actionType === 'suspend_supplier' && !adminNotes.value.trim()) {
        adminNotes.focus();
        alert('Please provide a reason for suspending the supplier.');
        return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';

    fetch('suppliers.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHTML;
        }
    })
    .catch(err => {
        console.error('AJAX Error:', err);
        alert('Failed to update status. Check console for details.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHTML;
    });
});
</script>