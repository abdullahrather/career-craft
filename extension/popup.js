// The extension's popup: the CareerCraft address and token, and "Fill this form", which asks
// CareerCraft for the person's details and this job's documents, then fills the open tab.
// After a fill it remembers the job for that tab, so "I submitted it" can log it on the board,
// even from the page the site shows once the form is sent.

const $ = (id) => document.getElementById(id);

async function settings() {
    return chrome.storage.local.get({ server: '', token: '' });
}

/** A request to CareerCraft's extension API, answered as JSON or as base64 bytes. */
async function api(path, { server, token }, asBytes = false, method = 'GET') {
    const response = await fetch(new URL(path, server), { method, headers: { Authorization: `Bearer ${token}`, Accept: asBytes ? 'application/pdf' : 'application/json' } });
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

/** The job filled in a tab, until it is logged as submitted. */
const tabKey = (tab) => `filled-${tab.id}`;
const hostOf = (tab) => (tab.url ? new URL(tab.url).host : '');

async function activeTab() {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    return tab;
}

/**
 * The job filled in this tab, while the tab is still on that job's site (its confirmation
 * page included). A tab moved on to another site, or a reused tab id, gets nothing.
 */
async function filledHere(tab) {
    const filled = (await chrome.storage.session.get(tabKey(tab)))[tabKey(tab)];
    return filled && filled.host === hostOf(tab) ? filled : null;
}

async function fill() {
    const button = $('fill-button');
    button.disabled = true;
    $('result').className = '';
    $('result').textContent = 'Filling…';
    try {
        const tab = await activeTab();
        const { application, data } = await collect(tab.url, await settings());
        await chrome.scripting.executeScript({ target: { tabId: tab.id }, files: ['fill.js'] });
        const [{ result }] = await chrome.scripting.executeScript({ target: { tabId: tab.id }, func: (data) => globalThis.careerCraftFill(data), args: [data] });
        $('result').textContent = result.filled.length > 0
            ? `Filled ${result.filled.length} fields for ${application.title}. Check them, add anything missing, and submit.`
            : 'No empty fields matched. Fill this form by hand.';
        await chrome.storage.session.set({ [tabKey(tab)]: { id: application.id, title: application.title, host: hostOf(tab) } });
        await showSubmitted();
    } catch (error) {
        $('result').className = 'error';
        $('result').textContent = error.message;
    } finally {
        button.disabled = false;
    }
}

async function logSubmitted() {
    const button = $('submitted-button');
    button.disabled = true;
    try {
        const tab = await activeTab();
        const filled = await filledHere(tab);
        if (!filled) {
            throw new Error('This tab has moved on from the job\'s site. Log it on CareerCraft instead.');
        }
        const { status } = await api(`/api/extension/applications/${filled.id}/submitted`, await settings(), false, 'POST');
        await chrome.storage.session.remove(tabKey(tab));
        $('submitted').hidden = true;
        $('result').className = '';
        $('result').textContent = status === 'applied'
            ? `Logged: ${filled.title} is in Applied on your board.`
            : `Logged on ${filled.title}'s timeline.`;
    } catch (error) {
        $('submitted-result').className = 'error';
        $('submitted-result').textContent = error.message;
    } finally {
        button.disabled = false;
    }
}

/** Offers "I submitted it" when this tab's form was filled and not logged yet. */
async function showSubmitted() {
    const filled = await filledHere(await activeTab());
    $('submitted').hidden = !filled;
    if (filled) {
        $('submitted-title').textContent = filled.title;
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
    if (token) {
        await showSubmitted();
    }
}

$('fill-button').addEventListener('click', fill);
$('submitted-button').addEventListener('click', logSubmitted);
$('settings').addEventListener('submit', save);
$('change').addEventListener('click', () => { $('fill').hidden = true; $('settings').hidden = false; });
show();
