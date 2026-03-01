import { db, doc, getDoc, collection, addDoc, serverTimestamp, query, where, getDocs } from "./firebase-config.js";
import { formatCurrency } from "./firebase-config.js";

const params = new URLSearchParams(window.location.search);
const qId = params.get('id');
let currentQtnData = null;

if (!qId) window.location.href = 'index.html';

(async () => {
    try {
        const snap = await getDoc(doc(db, "quotations", qId));
        if (!snap.exists()) {
            document.body.innerHTML = "<div class='p-5'>Quotation not found.</div>";
            return;
        }
        currentQtnData = snap.data();
        renderQtn(currentQtnData);
    } catch (e) {
        console.error(e);
    }
})();

function renderQtn(data) {
    document.getElementById('qtnIdDisplay').innerText = data.qtnId;
    
    const created = data.timestamp ? data.timestamp.toDate() : new Date();
    document.getElementById('qtnDate').innerText = created.toLocaleDateString();
    
    const valid = data.validUntil ? data.validUntil.toDate() : new Date();
    document.getElementById('qtnValid').innerText = valid.toLocaleDateString();

    document.getElementById('qCustName').innerText = data.name;
    document.getElementById('qCustPhone').innerText = data.phone;
    document.getElementById('qCustEmail').innerText = data.email;
    document.getElementById('qCustAddress').innerText = data.address;

    const i = data.item;
    const row = `
        <tr>
            <td>
                <div class="font-medium text-zinc-900">${i.product}</div>
                <div class="text-xs text-zinc-500">${i.glass} Glass, ${i.finish} Finish</div>
            </td>
            <td class="text-center">${i.height}ft x ${i.width}ft</td>
            <td class="text-center">${i.qty}</td>
            <td class="text-end">${formatCurrency(i.rate)}</td>
            <td class="text-end font-bold">${formatCurrency(i.total)}</td>
        </tr>
    `;
    document.getElementById('qItemsBody').innerHTML = row;
    document.getElementById('qTotal').innerText = formatCurrency(i.total);

    if (data.notes) {
        const li = document.createElement('li');
        li.innerText = `Client Request: ${data.notes}`;
        li.classList.add('text-zinc-800', 'font-medium');
        document.getElementById('qNotesList').prepend(li);
    }
}

const showBooking = () => {
    document.getElementById('bkRef').innerText = currentQtnData.qtnId;
    document.getElementById('bkLoc').innerText = currentQtnData.address;
    new bootstrap.Modal(document.getElementById('bookingModal')).show();
};

document.getElementById('btnTopBook').onclick = showBooking;
document.getElementById('btnBigBook').onclick = showBooking;

document.getElementById('bookingForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const date = document.getElementById('bkDate').value;
    const time = document.getElementById('bkTime').value;

    const blockedQ = query(collection(db, "blocked_dates"), where("date", "==", date));
    const blockedSnap = await getDocs(blockedQ);
    if(!blockedSnap.empty) {
        alert("Sorry, this date is unavailable/holiday. Please choose another.");
        return;
    }

    try {
        await addDoc(collection(db, "bookings"), {
            qtnId: currentQtnData.qtnId,
            qtnRefId: qId,
            customerName: currentQtnData.name,
            phone: currentQtnData.phone,
            address: currentQtnData.address,
            date: date,
            timeSlot: time,
            status: "Pending",
            timestamp: serverTimestamp()
        });

        alert("Booking request sent! We will confirm via call/text.");
        bootstrap.Modal.getInstance(document.getElementById('bookingModal')).hide();
        document.getElementById('btnBigBook').innerText = "Booking Requested ✓";
        document.getElementById('btnBigBook').disabled = true;

    } catch (err) {
        alert("Error saving booking");
    }
});
