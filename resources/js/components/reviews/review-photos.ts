/**
 * Client-side checks for the review photo picker. The server enforces the same limits (and sniffs the content);
 * these only spare the customer a failed upload.
 */

export interface PhotoLimits {
    max_files: number;
    /** bytes, per photo */
    max_file_size: number;
    /** bytes, the whole multipart request */
    max_request_size: number;
}

export const PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

/** Room left in the request for the rating, the comment and the multipart overhead. */
const REQUEST_HEADROOM = 64 * 1024;

export type PhotoProblem =
    | { key: 'too_many' }
    | { key: 'wrong_type'; name: string }
    | { key: 'too_large'; name: string }
    | { key: 'total_too_large' };

/** Add the picked files to the current selection, dropping (and reporting) the ones the server would refuse. */
export function addPhotos(current: File[], incoming: File[], limits: PhotoLimits): { files: File[]; problems: PhotoProblem[] } {
    const files = [...current];
    const problems: PhotoProblem[] = [];
    let total = files.reduce((sum, file) => sum + file.size, 0);
    let reportedTooMany = false;
    let reportedTotal = false;

    for (const file of incoming) {
        if (!PHOTO_TYPES.includes(file.type)) {
            problems.push({ key: 'wrong_type', name: file.name });
            continue;
        }
        if (file.size > limits.max_file_size) {
            problems.push({ key: 'too_large', name: file.name });
            continue;
        }
        if (files.length >= limits.max_files) {
            if (!reportedTooMany) problems.push({ key: 'too_many' });
            reportedTooMany = true;
            continue;
        }
        if (total + file.size > limits.max_request_size - REQUEST_HEADROOM) {
            if (!reportedTotal) problems.push({ key: 'total_too_large' });
            reportedTotal = true;
            continue;
        }
        files.push(file);
        total += file.size;
    }

    return { files, problems };
}

export function formatBytes(bytes: number, locale = 'en'): string {
    const units = ['B', 'KB', 'MB', 'GB'];
    let value = Math.max(bytes, 0);
    let unit = 0;
    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit++;
    }

    return `${new Intl.NumberFormat(locale, { maximumFractionDigits: unit === 0 ? 0 : 1 }).format(value)} ${units[unit]}`;
}
