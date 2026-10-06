/*
 | Auth bridge — Firebase sign-in -> Laravel session.
 | signInWithSession(): signs in with Firebase, then POSTs the ID token to
 | /auth/session so Blade pages get a normal Laravel session.
 */
import { signIn, signOut, getIdToken, sendPasswordReset } from '../firebase/firebase.js';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function postSession(path, body = {}) {
    const res = await fetch(path, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(body),
    });
    if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        throw new Error(data.message || `Request failed (${res.status})`);
    }
    return res.json();
}

/** Full login: Firebase email/password -> session exchange. */
export async function signInWithSession(email, password) {
    await signIn(email, password);
    const idToken = await getIdToken(true);
    await postSession('/auth/session', { id_token: idToken });
}

export async function logout() {
    try {
        await postSession('/auth/logout');
    } finally {
        await signOut().catch(() => {});
    }
}

/** Trigger a Firebase password-reset email for the given address. */
export async function resetPassword(email) {
    return sendPasswordReset(email);
}

export const auth = { signInWithSession, logout, resetPassword };
