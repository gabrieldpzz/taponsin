// firebase/config.js
import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.0/firebase-app.js";
import { getAuth, signInWithEmailAndPassword, createUserWithEmailAndPassword } from "https://www.gstatic.com/firebasejs/10.12.0/firebase-auth.js";

const firebaseConfig = {
    apiKey: "AIzaSyCfJD1P8oQJ53ul5G9H29i-4btxcF6ihc4",
    authDomain: "carpooling-5c37c.firebaseapp.com",
    databaseURL: "https://carpooling-5c37c-default-rtdb.firebaseio.com",
    projectId: "carpooling-5c37c",
    storageBucket: "carpooling-5c37c.appspot.com",
    messagingSenderId: "645137971067",
    appId: "1:645137971067:web:0fa9fe3cdcf6a0b2f66fde",
    measurementId: "G-QH6T9KSRL0"
};

const app = initializeApp(firebaseConfig);
const auth = getAuth(app);

export { auth, signInWithEmailAndPassword, createUserWithEmailAndPassword };
