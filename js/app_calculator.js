import { db, collection, getDocs, runTransaction, doc, addDoc, serverTimestamp } from "./firebase-config.js";

const form = document.getElementById('calcForm');
const selProduct = document.getElementById('selProduct');
const selGlass = document.getElementById('selGlass');
const selFinish = document.getElementById('selFinish');
const loading = document.getElementById('loadingMessage');

let priceData = [];

(async function init() {
    try {
        const snap = await getDocs(collection(db, "prices"));
        
        if (snap.empty) {
            loading.innerHTML = `<div class="alert alert-warning">System setup is still in progress. Please contact support if this persists.</div>`;
            return;
        }
        
        snap.forEach(d => priceData.push(d.data()));
        
        const products = [...new Set(priceData.map(i => i.product))];
        const glasses = [...new Set(priceData.map(i => i.glass))];
        const finishes = [...new Set(priceData.map(i => i.finish))];

        products.forEach(p => selProduct.add(new Option(p, p)));
        glasses.forEach(g => selGlass.add(new Option(g, g)));
        finishes.forEach(f => selFinish.add(new Option(f, f)));

        loading.classList.add('d-none');
        form.classList.remove('d-none');

    } catch (e) {
        console.error("Error loading prices:", e);
        loading.innerText = "Error loading data. Check console.";
    }
})();

function calculate() {
    const h = parseFloat(document.getElementById('inpHeight').value) || 0;
    const w = parseFloat(document.getElementById('inpWidth').value) || 0;
    const qty = parseInt(document.getElementById('inpQty').value) || 1;
    
    const p = selProduct.value;
    const g = selGlass.value;
    const f = selFinish.value;

    if (!p || !g || !f) return;

    const match = priceData.find(item => item.product === p && item.glass === g && item.finish === f);
    const rate = match ? parseFloat(match.price) : 0;

    const area = h * w * qty;
    const total = area * rate;

    document.getElementById('outArea').innerText = area.toFixed(2);
    document.getElementById('outRate').innerText = `₱${rate.toFixed(2)}`;
    document.getElementById('outTotal').innerText = `₱${total.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})}`;
}

[selProduct, selGlass, selFinish, document.getElementById('inpHeight'), document.getElementById('inpWidth'), document.getElementById('inpQty')]
    .forEach(el => el.addEventListener('input', calculate));

document.getElementById('btnReview').addEventListener('click', () => {
    if (form.checkValidity()) {
        const modal = new bootstrap.Modal(document.getElementById('userModal'));
        modal.show();
    } else {
        form.classList.add('was-validated');
    }
});

document.getElementById('userForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button');
    btn.disabled = true;
    btn.innerText = "Generating...";

    const h = parseFloat(document.getElementById('inpHeight').value);
    const w = parseFloat(document.getElementById('inpWidth').value);
    const qty = parseInt(document.getElementById('inpQty').value);
    const rateString = document.getElementById('outRate').innerText.replace('₱', '');
    const totalString = document.getElementById('outTotal').innerText.replace('₱', '').replace(/,/g, '');

    const data = {
        name: document.getElementById('uName').value,
        phone: document.getElementById('uPhone').value,
        email: document.getElementById('uEmail').value,
        address: document.getElementById('uAddress').value,
        notes: document.getElementById('uNotes').value,
        item: {
            product: selProduct.value,
            glass: selGlass.value,
            finish: selFinish.value,
            height: h,
            width: w,
            qty: qty,
            rate: parseFloat(rateString),
            total: parseFloat(totalString)
        },
        status: "Draft",
        timestamp: serverTimestamp()
    };

    try {
        const counterRef = doc(db, "counters", "quotations");
        let newId = "";
        
        await runTransaction(db, async (transaction) => {
            const cDoc = await transaction.get(counterRef);
            let current = 1000;
            if (cDoc.exists()) {
                current = cDoc.data().current;
            }
            current++;
            newId = `QTN-2025-${current}`;
            transaction.set(counterRef, { current });
        });

        data.qtnId = newId;

        const d = new Date();
        d.setDate(d.getDate() + 7);
        data.validUntil = d;

        const docRef = await addDoc(collection(db, "quotations"), data);
        window.location.href = `quotation.html?id=${docRef.id}`;

    } catch (err) {
        console.error(err);
        alert("Error generating quotation.");
        btn.disabled = false;
        btn.innerText = "Get Quotation";
    }
});
