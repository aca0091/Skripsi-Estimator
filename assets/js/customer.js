(() => {
    'use strict';
    const formDialog = document.getElementById('customerDialog');
    const deleteDialog = document.getElementById('deleteCustomerDialog');
    document.querySelectorAll('[data-open-customer]').forEach(button => {
        button.addEventListener('click', () => formDialog.showModal());
    });
    document.querySelectorAll('[data-close-dialog]').forEach(button => {
        button.addEventListener('click', () => button.closest('dialog').close());
    });
    document.querySelectorAll('[data-delete-customer]').forEach(button => {
        button.addEventListener('click', () => {
            document.getElementById('deleteCustomerId').value = button.dataset.deleteCustomer;
            document.getElementById('deleteCustomerName').textContent = button.dataset.customerName;
            deleteDialog.showModal();
        });
    });
    if (formDialog?.dataset.openOnLoad === 'true') formDialog.showModal();
})();
