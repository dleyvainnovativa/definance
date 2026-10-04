/*
 | HTTP helper — thin fetch wrapper for the JSON API.
 | - Attaches the Firebase ID token as a Bearer header automatically.
 | - Sends/reads JSON, throws a typed error on non-2xx with the parsed body.
 | Usage:  const data = await http.get('/accounts');
 |         await http.post('/entries', payload);
 */
import { getIdToken } from '../firebase/firebase.js';

const BASE_URL = '/api';

export class HttpError extends Error {
    constructor(message, status, body) {
        super(message);
        this.name = 'HttpError';
        this.status = status;
        this.body = body;
    }
}

async function request(method, path, body = null, options = {}) {
    const headers = {
        Accept: 'application/json',
        ...(body ? { 'Content-Type': 'application/json' } : {}),
        ...options.headers,
    };

    const token = await getIdToken();
    if (token) headers.Authorization = `Bearer ${token}`;

    const url = path.startsWith('http') ? path : `${BASE_URL}${path}`;

    const res = await fetch(url, {
        method,
        headers,
        body: body ? JSON.stringify(body) : undefined,
        ...options,
    });

    const isJson = res.headers.get('content-type')?.includes('application/json');
    const payload = isJson ? await res.json().catch(() => null) : await res.text();

    if (!res.ok) {
        const message = (isJson && payload?.message) || `Request failed (${res.status})`;
        throw new HttpError(message, res.status, payload);
    }

    return payload;
}

export const http = {
    get: (path, options) => request('GET', path, null, options),
    post: (path, body, options) => request('POST', path, body, options),
    put: (path, body, options) => request('PUT', path, body, options),
    del: (path, body, options) => request('DELETE', path, body, options),
};
