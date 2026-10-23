/** [comune, province code]: the comune is '' when the CAP is shared by several comuni. */
type CapEntry = [string, string];

let capTable: Promise<Record<string, CapEntry>> | null = null;

// Self-hosted (public/data/it-cap.json, ~33 KB gzipped), fetched once and only when an Italian CAP is typed
const loadCapTable = () =>
    (capTable ??= fetch('/data/it-cap.json')
        .then((response) => response.json() as Promise<Record<string, CapEntry>>)
        .catch(() => {
            capTable = null;

            return {} as Record<string, CapEntry>;
        }));

/** City and province of an Italian CAP; null when it is not 5 digits or not in the table (the customer types them). */
export async function lookupItalianPostcode(cap: string): Promise<{ city: string; state: string } | null> {
    if (!/^\d{5}$/.test(cap)) {
        return null;
    }

    const entry = (await loadCapTable())[cap];

    return entry ? { city: entry[0], state: entry[1] } : null;
}

/** The province code of a stored state: the code itself, or a province name (addresses saved as free text); '' when unknown. */
export function provinceCode(provinces: Record<string, string>, state: string): string {
    const value = state.trim();

    if (value.toUpperCase() in provinces) {
        return value.toUpperCase();
    }

    return Object.entries(provinces).find(([, name]) => name.toLowerCase() === value.toLowerCase())?.[0] ?? '';
}
