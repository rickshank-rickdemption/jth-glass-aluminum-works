import { auth, db, signInWithEmailAndPassword, onAuthStateChanged, signOut, collection, getDocs, addDoc, updateDoc, doc, query, orderBy, deleteDoc } from "./firebase-config.js";
import { formatCurrency } from "./firebase-config.js";

const loginOverlay = document.getElementById('loginOverlay');

onAuthStateChanged(auth, user => {
    if(user) {
        loginOverlay.classList.add('d-none');
        loadAllData();
    } else {
        loginOverlay.classList.remove('d-none');
    }
});

document.getElementById('btnLogin').onclick = async () => {
    try {
        await signInWithEmailAndPassword(auth, document.getElementById('admEmail').value, document.getElementById('admPass').value);
    } catch(e) { alert(e.message); }
};
document.getElementById('btnLogout').onclick = () => signOut(auth);

document.querySelectorAll('.nav-btn').forEach(btn => {
    btn.onclick = () => {
        document.querySelectorAll('.nav-btn').forEach(b => {
            b.classList.remove('text-zinc-900', 'font-bold');
            b.classList.add('text-zinc-500');
        });
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.add('d-none'));
        
        btn.classList.add('text-zinc-900', 'font-bold');
        document.getElementById(btn.dataset.target).classList.remove('d-none');
    };
});

async function loadAllData() {
    loadBookings();
    loadPrices();
    loadQtns();
}

async function loadBookings() {
    const q = query(collection(db, "bookings"), orderBy("date", "asc"));
    const snap = await getDocs(q);
    const tbody = document.getElementById('tblBookings');
    tbody.innerHTML = '';
    
    snap.forEach(d => {
        const data = d.data();
        const row = `
            <tr>
                <td>${data.date}</td>
                <td>${data.timeSlot}</td>
                <td>
                    <div class="font-bold">${data.customerName}</div>
                    <div class="text-xs text-muted">${data.phone}</div>
                </td>
                <td>${data.qtnId}</td>
                <td><span class="badge ${data.status === 'Confirmed' ? 'bg-success' : 'bg-warning'}">${data.status}</span></td>
                <td>
                    ${data.status === 'Pending' ? `<button class="btn btn-sm btn-success btn-approve" data-id="${d.id}">Approve</button>` : ''}
                    <button class="btn btn-sm btn-outline-danger btn-del-bk" data-id="${d.id}">Cancel</button>
                </td>
            </tr>
        `;
        tbody.innerHTML += row;
    });

    document.querySelectorAll('.btn-approve').forEach(b => {
        b.onclick = async (e) => {
            await updateDoc(doc(db, "bookings", e.target.dataset.id), { status: 'Confirmed' });
            loadBookings();
        };
    });
    
    document.querySelectorAll('.btn-del-bk').forEach(b => {
        b.onclick = async (e) => {
            if(confirm('Delete Booking?')) {
                await deleteDoc(doc(db, "bookings", e.target.dataset.id));
                loadBookings();
            }
        };
    });
}

async function loadPrices() {
    const snap = await getDocs(collection(db, "prices"));
    const tbody = document.getElementById('tblPrices');
    const dl = document.getElementById('existProds');
    tbody.innerHTML = '';
    const prods = new Set();

    snap.forEach(d => {
        const data = d.data();
        prods.add(data.product);
        tbody.innerHTML += `
            <tr>
                <td>${data.product}</td>
                <td>${data.glass}</td>
                <td>${data.finish}</td>
                <td>${formatCurrency(data.price)}</td>
                <td><button class="btn btn-sm btn-outline-danger btn-del-price" data-id="${d.id}">Remove</button></td>
            </tr>
        `;
    });

    dl.innerHTML = '';
    prods.forEach(p => dl.innerHTML += `<option value="${p}">`);

    document.querySelectorAll('.btn-del-price').forEach(b => {
        b.onclick = async (e) => {
            if(confirm('Delete price?')) {
                await deleteDoc(doc(db, "prices", e.target.dataset.id));
                loadPrices();
            }
        };
    });
}

document.getElementById('priceForm').onsubmit = async (e) => {
    e.preventDefault();
    await addDoc(collection(db, "prices"), {
        product: document.getElementById('npProd').value,
        glass: document.getElementById('npGlass').value,
        finish: document.getElementById('npFinish').value,
        price: parseFloat(document.getElementById('npPrice').value)
    });
    bootstrap.Modal.getInstance(document.getElementById('addPriceModal')).hide();
    e.target.reset();
    loadPrices();
};

async function loadQtns() {
    const q = query(collection(db, "quotations"), orderBy("timestamp", "desc"));
    const snap = await getDocs(q);
    const tbody = document.getElementById('tblQtns');
    tbody.innerHTML = '';

    snap.forEach(d => {
        const data = d.data();
        const date = data.timestamp ? data.timestamp.toDate().toLocaleDateString() : '-';
        tbody.innerHTML += `
            <tr>
                <td>${data.qtnId}</td>
                <td>${date}</td>
                <td>${data.name}</td>
                <td>${formatCurrency(data.item.total)}</td>
                <td><a href="quotation.html?id=${d.id}" target="_blank" class="text-indigo-600 hover:underline">View</a></td>
            </tr>
        `;
    });
}
