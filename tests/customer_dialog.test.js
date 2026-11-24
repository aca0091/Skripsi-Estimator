const fs = require('fs');
const vm = require('vm');
const assert = require('assert/strict');
// Membuat tombol tiruan untuk pengujian dialog.
function button(dataset = {}) {
    return {
        dataset,
        listeners: {},
        addEventListener(event, fn) {
            this.listeners[event] = fn;
        }
    };
}
const form = {
    dataset: { openOnLoad: 'false' },
    open: false,
    showModal() {
        this.open = true;
    },
    close() {
        this.open = false;
    }
};
const deletion = { ...form, dataset: {} };
const add = button(),
    close = button(),
    remove = button({ deleteCustomer: '42', customerName: '<script>test</script>' });
close.closest = () => form;
const id = {},
    name = {};
const document = {
    getElementById(key) {
        return {
            customerDialog: form,
            deleteCustomerDialog: deletion,
            deleteCustomerId: id,
            deleteCustomerName: name
        }[key];
    },
    querySelectorAll(key) {
        return {
            '[data-open-customer]': [add],
            '[data-close-dialog]': [close],
            '[data-delete-customer]': [remove]
        }[key];
    }
};
const script = fs.readFileSync('assets/js/customer.js', 'utf8');
vm.runInNewContext(script, { document });
assert.equal(form.open, false);
add.listeners.click();
assert.equal(form.open, true);
close.listeners.click();
assert.equal(form.open, false);
remove.listeners.click();
assert.equal(deletion.open, true);
assert.equal(id.value, '42');
assert.equal(name.textContent, '<script>test</script>');
assert.equal(name.innerHTML, undefined);
form.dataset.openOnLoad = 'true';
vm.runInNewContext(script, { document });
assert.equal(form.open, true);
const page = fs.readFileSync('data_customer.php', 'utf8');
assert.ok(!page.includes('Tambah customer lain'));
assert.match(page, /<dialog id="customerDialog"/);
assert.match(page, /Jumlah Pesanan/);
console.log(
    'Customer modal open/close, validation reopening, safe delete confirmation and detail labels passed.'
);
