import { test } from 'node:test';
import assert from 'node:assert/strict';
import { completeLines, readerName, nextOffset } from '../../resources/js/timing/lines.js';

const bytes = (s) => new TextEncoder().encode(s);

test('takes only complete lines and counts their bytes', () => {
    assert.deepEqual(completeLines(bytes('1\ta\r\n2\tb\r\n3\tc')), { text: '1\ta\r\n2\tb\r\n', length: 10 });
});

test('takes nothing while the first line is still being written', () => {
    assert.deepEqual(completeLines(bytes('704\t2024-12')), { text: '', length: 0 });
});

test('handles CR CR LF', () => {
    assert.equal(completeLines(bytes('1\ta\r\r\n')).length, 6);
});

test('names the reader by the last part of the IP in the file name', () => {
    assert.equal(readerName('192.168.1.241.txt'), '241');
    assert.equal(readerName('192.168.1.240.txt'), '240');
});

test('ignores files that are not reader files', () => {
    assert.equal(readerName('RFIDServer.ini'), null);
    assert.equal(readerName('anteckningar.txt'), null);
});

test('starts over when a file has been replaced by a shorter one', () => {
    assert.equal(nextOffset(500, 1000), 500);
    assert.equal(nextOffset(500, 200), 0);
});
