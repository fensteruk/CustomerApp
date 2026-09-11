import test from 'node:test';
import assert from 'node:assert/strict';
import { officeAdminForm } from '../../resources/js/office-admin-form.js';

function harness(t, fetcher) {
    let focused = false;
    let destination = null;
    t.mock.method(globalThis, 'fetch', fetcher);
    const originalWindow = globalThis.window;
    const originalFormData = globalThis.FormData;
    t.after(() => { globalThis.window = originalWindow; globalThis.FormData = originalFormData; });
    globalThis.window = {
        setTimeout, clearTimeout, location: { assign: url => { destination = url; } },
    };
    globalThis.FormData = class { constructor(form) { this.form = form; } };
    const state = officeAdminForm({ redirect: '/customers/__UUID__', kind: 'customer' });
    state.$nextTick = callback => callback();
    state.$refs = { summary: { focus: () => { focused = true; } } };
    return { state, event: { target: { action: '/api/customers' } },
        focused: () => focused, destination: () => destination };
}

function response(status, payload = {}) {
    return { status, ok: status >= 200 && status < 300, redirected: false, json: async () => payload };
}

test('successful saves navigate only to the returned record in the fixed local route', async t => {
    let count = 0;
    const h = harness(t, async () => { count++; return response(201, { customer: { uuid: '12345678-1234-4234-8234-123456789012' } }); });
    await h.state.save(h.event);
    await h.state.save(h.event);
    assert.equal(count, 1);
    assert.equal(h.state.saving, true);
    assert.equal(h.destination(), '/customers/12345678-1234-4234-8234-123456789012');
    assert.equal(h.state.uncertain, false);
});

test('a login redirect never becomes a successful save', async t => {
    const h = harness(t, async () => ({ ...response(200), redirected: true }));
    await h.state.save(h.event);
    assert.equal(h.state.mustReload, true);
    assert.equal(h.destination(), null);
});

test('a server failure never exposes internal text or retries automatically', async t => {
    let count = 0;
    const h = harness(t, async () => { count++; return response(500, { message: 'PRIVATE-DATABASE-ERROR' }); });
    await h.state.save(h.event);
    await h.state.save(h.event);
    assert.equal(count, 1);
    assert.equal(h.state.uncertain, true);
    assert.equal(h.state.message.includes('PRIVATE'), false);
});

test('simultaneous submit events issue one request', async t => {
    let resolve;
    let count = 0;
    const h = harness(t, () => { count++; return new Promise(r => { resolve = r; }); });
    const first = h.state.save(h.event);
    await h.state.save(h.event);
    assert.equal(count, 1);
    resolve(response(422, { errors: { name: ['Required'] } }));
    await first;
    assert.equal(h.state.saving, false);
});

test('validation focuses the summary and preserves only field errors', async t => {
    const h = harness(t, async () => response(422, { errors: { name: ['Already used'], internal: ['PRIVATE'] } }));
    await h.state.save(h.event);
    assert.deepEqual(h.state.errors, { name: ['Already used'] });
    assert.equal(h.focused(), true);
    assert.equal(h.state.mustReload, false);
});

test('stale record validation prevents a repeat with the old version', async t => {
    let count = 0;
    const h = harness(t, async () => { count++; return response(422, { errors: { lock_version: ['Changed'] } }); });
    await h.state.save(h.event);
    await h.state.save(h.event);
    assert.equal(count, 1);
    assert.equal(h.state.mustReload, true);
});

for (const status of [401, 403, 419, 409]) {
    test('access/session/conflict status ' + status + ' requires reload', async t => {
        const h = harness(t, async () => response(status));
        await h.state.save(h.event);
        assert.equal(h.state.mustReload, true);
        assert.equal(h.destination(), null);
    });
}

test('lost response reports uncertainty and prevents blind retry', async t => {
    let count = 0;
    const h = harness(t, async () => { count++; throw new Error('Private transport detail'); });
    await h.state.save(h.event);
    await h.state.save(h.event);
    assert.equal(count, 1);
    assert.equal(h.state.uncertain, true);
    assert.equal(h.state.message.includes('Private'), false);
});

test('an unexpected success payload never redirects or claims success', async t => {
    const h = harness(t, async () => response(200, { customer: { uuid: 'https://evil.test' } }));
    await h.state.save(h.event);
    assert.equal(h.destination(), null);
    assert.equal(h.state.uncertain, true);
});

test('rate limiting permits a deliberate retry without auto-submitting', async t => {
    let count = 0;
    const h = harness(t, async () => { count++; return response(429); });
    await h.state.save(h.event);
    assert.equal(count, 1);
    assert.equal(h.state.uncertain, false);
    assert.equal(h.state.mustReload, false);
    assert.match(h.state.message, /Wait/);
});
