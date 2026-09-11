import test from 'node:test';
import assert from 'node:assert/strict';
import { importStudioDemo } from '../../resources/js/import-studio-demo.js';

test('the synthetic walkthrough starts at step one and remains within its fixed bounds', () => {
    const originalDocument = globalThis.document;
    let focused = null;
    globalThis.document = {
        getElementById: id => ({ focus: () => { focused = id; } }),
    };

    try {
        const demo = importStudioDemo(12);
        demo.$nextTick = callback => callback();

        assert.equal(demo.step, 1);
        demo.previous();
        assert.equal(demo.step, 1);
        demo.goTo(8);
        assert.equal(demo.step, 8);
        assert.equal(focused, 'import-demo-step-8');
        demo.goTo(999);
        assert.equal(demo.step, 12);
        demo.next();
        assert.equal(demo.step, 12);
        demo.goTo('not-a-step');
        assert.equal(demo.step, 1);
    } finally {
        globalThis.document = originalDocument;
    }
});
