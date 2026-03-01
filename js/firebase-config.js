import { initializeApp } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js";
import { getFirestore, collection, doc, getDoc, getDocs, addDoc, setDoc, updateDoc, deleteDoc, query, where, orderBy, runTransaction, serverTimestamp } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-firestore.js";
import { getAuth, signInWithEmailAndPassword, onAuthStateChanged, signOut } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js";
import { getStorage } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-storage.js";

const firebaseConfig = {
    apiKey: "AIzaSyCb8HROrweiV0_CGb9Zd3nHxW1wjk8RPvk",
    authDomain: "jth-glass-and-aluminum.firebaseapp.com",
    projectId: "jth-glass-and-aluminum",
    storageBucket: "jth-glass-and-aluminum.firebasestorage.app",
    messagingSenderId: "590505595394",
    appId: "1:590505595394:web:a1d17afe7c875c64034d1b",
    measurementId: "G-6DCJN3720B"
};

const app = initializeApp(firebaseConfig);
const db = getFirestore(app);
const auth = getAuth(app);
const storage = getStorage(app);

export const formatCurrency = (amount) => {
    return '₱' + parseFloat(amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
};

export { app, db, auth, storage, collection, doc, getDoc, getDocs, addDoc, setDoc, updateDoc, deleteDoc, query, where, orderBy, runTransaction, serverTimestamp, signInWithEmailAndPassword, onAuthStateChanged, signOut };
