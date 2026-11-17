// The extension's popup: the CareerCraft address and token, and "Fill this form", which asks
// CareerCraft for the person's details and this job's documents, then fills the open tab.

const $ = (id) => document.getElementById(id);

async function settings() {
    return chrome.storage.local.get({ server: '', token: '' });
}

/** A GET to CareerCraft's extension API, as JSON or as base64 bytes. */
async function api(path, { server, token }, asBytes = false) {
    const response = await fetch(new URL(path, server), { headers: { Authorization: `Bearer ${token}`, Accept: asBytes ? 'application/pdf' : 'application/json' } });
    if (response.status === 401) {
        throw new Error('CareerCraft didn\'t accept the token. Make a new one under Settings → Browser extension.');
    }
    if (!response.ok) {
        const body = await response.json().catch(() => ({}));
        throw new Error(body.message ?? `CareerCraft answered ${response.status}.`);
    }
    if (!asBytes) {
        return response.json();
    }
    const bytes = new Uint8Array(await response.arrayBuffer());
    let binary = '';
    bytes.forEach((byte) => { binary += String.fromCharCode(byte); });
    return btoa(binary);
}

/** What the form needs: the person's details, and this job's documents and answers. */
async function collect(pageUrl, config) {
    const [profile, application] = await Promise.all([
        api('/api/extension/profile', config),
        api(`/api/extension/application?url=${encodeURIComponent(pageUrl)}`, config),
    ]);
    const values = { ...profile, full_name: `${profile.first_name} ${profile.last_name}`.trim() };
    for (const answer of application.answers) {
        values[answer.question] = answer.text;
    }
    const files = {};
    for (const [key, document] of Object.entries(application.documents)) {
        files[key] = { name: document.name, base64: await api(document.url, config, true) };
    }
    return { application, data: { values, files } };
}

async function fill() {
    const button = $('fill-button');
    button.disabled = true;
    $('result').className = '';
    $('result').textContent = 'Filling…';
    try {
        const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
        const { application, data } = await collect(tab.url, await settings());
        await chrome.scripting.executeScript({ target: { tabId: tab.id }, files: ['fill.js'] });
        const [{ result }] = await chrome.scripting.executeScript({ target: { tabId: tab.id }, func: (data) => globalThis.careerCraftFill(data), args: [data] });
        $('result').textContent = result.filled.length > 0
            ? `Filled ${result.filled.length} fields for ${application.title}. Check them, add anything missing, and submit.`
            : 'No empty fields matched. Fill this form by hand.';
    } catch (error) {
        $('result').className = 'error';
        $('result').textContent = error.message;
    } finally {
        button.disabled = false;
    }
}

async function save(event) {
    event.preventDefault();
    const config = { server: $('server').value.replace(/\/+$/, ''), token: $('token').value.trim() };
    const button = event.submitter;
    button.disabled = true;
    $('settings-result').className = '';
    $('settings-result').textContent = 'Checking…';
    try {
        await api('/api/extension/profile', config);
    } catch (error) {
        $('settings-result').className = 'error';
        $('settings-result').textContent = error.message.startsWith('Failed to fetch') ? 'CareerCraft can\'t be reached at this address.' : error.message;
        return;
    } finally {
        button.disabled = false;
    }
    $('settings-result').textContent = '';
    await chrome.storage.local.set(config);
    show();
}

async function show() {
    const { server, token } = await settings();
    $('fill').hidden = !token;
    $('settings').hidden = Boolean(token);
    if (server) {
        $('server').value = server;
    }
}

$('fill-button').addEventListener('click', fill);
$('settings').addEventListener('submit', save);
$('change').addEventListener('click', () => { $('fill').hidden = true; $('settings').hidden = false; });
show();
