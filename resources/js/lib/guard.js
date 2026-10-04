/*
 | Auth-ready guard. Firebase restores the signed-in user asynchronously on
 | page load, so API calls (which attach the ID token) must wait for the first
 | auth-state resolution. ensureAuth() resolves to the user, or redirects to
 | /login when there is none — keeping Blade session and Firebase client in step.
 */
import { onUser } from '../firebase/firebase.js';

let readyPromise;

export function ready() {
    readyPromise ??= new Promise((resolve) => {
        const unsub = onUser((user) => {
            unsub?.();
            resolve(user);
        });
    });
    return readyPromise;
}

export async function ensureAuth(redirect = '/login') {
    const user = await ready();
    if (!user) {
        window.location.href = redirect;
    }
    return user;
}

export const guard = { ready, ensureAuth };
