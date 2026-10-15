import { describe, expect, it } from 'vitest';
import { addPhotos, formatBytes, type PhotoLimits } from '../review-photos';

const MB = 1024 * 1024;
const limits: PhotoLimits = { max_files: 4, max_file_size: 5 * MB, max_request_size: 12 * MB };

function photo(name: string, size: number, type = 'image/jpeg'): File {
    return new File([new Uint8Array(size)], name, { type });
}

describe('addPhotos', () => {
    it('keeps valid photos in order', () => {
        const { files, problems } = addPhotos([photo('a.jpg', 10)], [photo('b.png', 10, 'image/png'), photo('c.webp', 10, 'image/webp')], limits);

        expect(files.map((f) => f.name)).toEqual(['a.jpg', 'b.png', 'c.webp']);
        expect(problems).toEqual([]);
    });

    it('stops at the maximum number of photos and reports it once', () => {
        const incoming = ['1', '2', '3', '4', '5', '6'].map((n) => photo(`${n}.jpg`, 10));
        const { files, problems } = addPhotos([], incoming, limits);

        expect(files).toHaveLength(4);
        expect(problems).toEqual([{ key: 'too_many' }]);
    });

    it('drops files of another type and files over the per-photo limit', () => {
        const { files, problems } = addPhotos([], [photo('doc.pdf', 10, 'application/pdf'), photo('huge.jpg', 6 * MB), photo('ok.jpg', MB)], limits);

        expect(files.map((f) => f.name)).toEqual(['ok.jpg']);
        expect(problems).toEqual([
            { key: 'wrong_type', name: 'doc.pdf' },
            { key: 'too_large', name: 'huge.jpg' },
        ]);
    });

    it('keeps the whole request under the server limit', () => {
        const { files, problems } = addPhotos([], [photo('a.jpg', 5 * MB), photo('b.jpg', 5 * MB), photo('c.jpg', 4 * MB)], limits);

        expect(files.map((f) => f.name)).toEqual(['a.jpg', 'b.jpg']);
        expect(problems).toEqual([{ key: 'total_too_large' }]);
    });
});

describe('formatBytes', () => {
    it('uses the largest whole unit', () => {
        expect(formatBytes(512)).toBe('512 B');
        expect(formatBytes(5 * MB)).toBe('5 MB');
        expect(formatBytes(1.5 * MB, 'it')).toBe('1,5 MB');
    });
});
