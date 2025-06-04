document.addEventListener('DOMContentLoaded', () => {
    loadProducts();
    loadBills();

    // Add Product Form
    document.getElementById('productForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);

        try {
            const response = await fetch('api.php?action=add_product', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            if (result.success) {
                loadProducts();
                e.target.reset();
                alert('Product added successfully!');
            } else {
                alert('Error adding product: ' + result.error);
            }
        } catch (error) {
            alert('Error: ' + error.message);
        }
    });

    // Add Item to Bill
    document.getElementById('addItem').addEventListener('click', () => {
        const billItems = document.getElementById('billItems');
        const item = billItems.querySelector('.bill-item').cloneNode(true);
        item.querySelector('select').value = '';
        item.querySelector('input[name="quantity"]').value = '';
        billItems.appendChild(item);
        updateBillTotal();
    });

    // Remove Item from Bill
    document.getElementById('billItems').addEventListener('click', (e) => {
        if (e.target.classList.contains('remove-item') && document.querySelectorAll('.bill-item').length > 1) {
            e.target.closest('.bill-item').remove();
            updateBillTotal();
        }
    });

    // Update Bill Total
    document.getElementById('billItems').addEventListener('input', updateBillTotal);

    // Generate Bill
    document.getElementById('billForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const items = Array.from(document.querySelectorAll('.bill-item')).map(item => ({
            product_id: item.querySelector('select').value,
            quantity: item.querySelector('input[name="quantity"]').value
        }));

        try {
            const response = await fetch('api.php?action=generate_bill', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ items })
            });
            const result = await response.json();
            if (result.success) {
                loadBills();
                document.getElementById('billForm').reset();
                document.getElementById('billItems').innerHTML = document.getElementById('billItems').querySelector('.bill-item').outerHTML;
                document.getElementById('billTotal').textContent = '0.00';
                alert('Bill generated successfully!');
            } else {
                alert('Error generating bill: ' + result.error);
            }
        } catch (error) {
            alert('Error: ' + error.message);
        }
    });

    async function loadProducts() {
        try {
            const response = await fetch('api.php?action=get_products');
            const products = await response.json();
            const productList = document.getElementById('productList');
            productList.innerHTML = products.map(p => `
                <div class="flex justify-between items-center p-2 border-b">
                    <span>${p.name} - $${parseFloat(p.price).toFixed(2)}</span>
                    <button onclick="deleteProduct(${p.id})" class="bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600">Delete</button>
                </div>
            `).join('');

            // Update bill form product dropdowns
            const selects = document.querySelectorAll('select[name="product_id"]');
            selects.forEach(select => {
                select.innerHTML = '<option value="">Select Product</option>' + 
                    products.map(p => `<option value="${p.id}">${p.name} - $${parseFloat(p.price).toFixed(2)}</option>`).join('');
            });
        } catch (error) {
            console.error('Error loading products:', error);
        }
    }

    async function loadBills() {
        try {
            const response = await fetch('api.php?action=get_bills');
            const bills = await response.json();
            const billList = document.getElementById('billList');
            billList.innerHTML = bills.map(b => `
                <div class="p-2 border-b">
                    <p>Bill #${b.id} - Total: $${parseFloat(b.total).toFixed(2)}</p>
                    <p>Items: ${b.items.map(i => `${i.name} (x${i.quantity})`).join(', ')}</p>
                </div>
            `).join('');
        } catch (error) {
            console.error('Error loading bills:', error);
        }
    }

    async function deleteProduct(id) {
        if (confirm('Are you sure you want to delete this product?')) {
            try {
                const response = await fetch(`api.php?action=delete_product&id=${id}`, { method: 'POST' });
                const result = await response.json();
                if (result.success) {
                    loadProducts();
                    alert('Product deleted successfully!');
                } else {
                    alert('Error deleting product: ' + result.error);
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
    }

    async function updateBillTotal() {
        let total = 0;
        const items = document.querySelectorAll('.bill-item');
        for (const item of items) {
            const productId = item.querySelector('select').value;
            const quantity = parseInt(item.querySelector('input[name="quantity"]').value) || 0;
            if (productId) {
                const response = await fetch(`api.php?action=get_product&id=${productId}`);
                const product = await response.json();
                if (product) total += product.price * quantity;
            }
        }
        document.getElementById('billTotal').textContent = total.toFixed(2);
    }
});