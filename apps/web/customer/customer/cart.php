// Update all quantities and proceed to checkout
function updateAllQuantitiesAndProceed() {
    // Create a form to submit all quantities
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'cart.php';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'update_all_quantities';
    
    form.appendChild(actionInput);
    
    // Add all quantities to the form
    document.querySelectorAll('.quantity-input').forEach(input => {
        const productId = input.dataset.productId;
        const quantity = input.value;
        
        const quantityInput = document.createElement('input');
        quantityInput.type = 'hidden';
        quantityInput.name = 'quantities[' + productId + ']';
        quantityInput.value = quantity;
        
        form.appendChild(quantityInput);
    });
    
    document.body.appendChild(form);
    form.submit();
}