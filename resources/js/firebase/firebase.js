/*
 | Firebase client (web SDK v12, modular).
 | Public config comes from Vite env (VITE_FIREBASE_*) — these keys are
 | public by design. Server-side verification uses the Service Account.
 */
import { initializeApp } from 'firebase/app';
import {
    getAuth,
    onAuthStateChanged,
    sendPasswordResetEmail,
    signInWithEmailAndPassword,
    signOut as fbSignOut,
} from 'firebase/auth';

const firebaseConfig = {
    apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
    authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
    projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
    storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET,
    messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
    appId: import.meta.env.VITE_FIREBASE_APP_ID,
};

export const app = initializeApp(firebaseConfig);
export const auth = getAuth(app);

/** Current user's ID token, or null if signed out. Used by lib/http.js. */
export async function getIdToken(forceRefresh = false) {
    const user = auth.currentUser;
    if (!user) return null;
    return user.getIdToken(forceRefresh);
}

export function onUser(callback) {
    return onAuthStateChanged(auth, callback);
}

export function signIn(email, password) {
    return signInWithEmailAndPassword(auth, email, password);
}

export function signOut() {
    return fbSignOut(auth);
}

/** Send a Firebase password-reset email (password reset stays client-side). */
export function sendPasswordReset(email) {
    return sendPasswordResetEmail(auth, email);
}
