// Pure helpers for reading RFIDServer files while the box is writing to them.

const NEWLINE = 0x0a;

// Only whole lines: the last line may be half written.
export function completeLines(bytes) {
    const end = bytes.lastIndexOf(NEWLINE) + 1;

    return { text: new TextDecoder().decode(bytes.subarray(0, end)), length: end };
}

// RFIDServer names each file after its reader's IP, e.g. 192.168.1.241.txt -> '241'.
export function readerName(fileName) {
    const match = fileName.match(/^\d{1,3}\.\d{1,3}\.\d{1,3}\.(\d{1,3})\.txt$/);

    return match ? match[1] : null;
}

// A file shorter than what we already read has been replaced: read it from the start.
export function nextOffset(offset, size) {
    return size < offset ? 0 : offset;
}
