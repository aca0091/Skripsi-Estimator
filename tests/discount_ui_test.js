const fs = require('fs');
const vm = require('vm');
const assert = require('assert/strict');
const source = fs.readFileSync(__dirname + '/../estimasi.php', 'utf8');
const code = source.slice(
    source.indexOf('        function updateDiscountSummary()'),
    source.indexOf('        // 13. SUBMIT FORM')
);
const elements = {};
// Menyediakan elemen tiruan untuk menguji ringkasan tanpa browser.
function element(id) {
    return (elements[id] ??= {
        value: '',
        validity: { badInput: false },
        setCustomValidity(message) {
            this.error = message;
        },
        setAttribute() {}
    });
}
const context = vm.createContext({
    document: { getElementById: element },
    selectedMetode: 'Disambung'
});
vm.runInContext(code, context);
for (const key of ['karpet', 'sambung', 'obras', 'lem', 'pasang', 'total'])
    element('discount-type-' + key).value = 'percent';
element('selectBahanKarpet').selectedOptions = [
    { dataset: { harga: '200000' } }
];
for (const [key, value] of Object.entries({
    post_meter_lari: 10,
    post_sambungan: 8,
    tarifSambungan: 25000,
    post_obras: 20,
    tarifObras: 15000
}))
    element(key).value = value;
element('discount-value-karpet').value = 10;
element('discount-type-obras').value = 'amount';
element('discount-value-obras').value = 50000;
element('discount-type-total').value = 'amount';
element('discount-value-total').value = 250000;
assert.equal(vm.runInContext('updateDiscountSummary()', context), true);
assert.equal(element('post_biaya').value, 2000000);
element('discount-value-total').value = 9999999;
assert.equal(vm.runInContext('updateDiscountSummary()', context), false);
assert.equal(element('discount-final').textContent, '—');
element('discount-value-total').value = 0;
context.selectedMetode = 'Permanen';
element('post_biaya_lem').value = 100000;
element('post_biaya_jasa_pasang').value = 200000;
assert.equal(vm.runInContext('updateDiscountSummary()', context), true);
assert.equal(element('post_biaya').value, 2100000);
assert.equal(element('discount-value-obras').disabled, true);
assert.equal(element('discount-row-obras').hidden, true);
console.log('Pengujian ringkasan, batas diskon, dan pergantian metode lulus.');
