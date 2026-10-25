type CapEntry = [string, string];

let capTable: Promise<Record<string, CapEntry>> | null = null;

const loadCapTable = () =>
    (capTable ??= fetch('/data/it-cap.json')
        .then((response) => response.json() as Promise<Record<string, CapEntry>>)
        .catch(() => {
            capTable = null;

            return {} as Record<string, CapEntry>;
        }));

export async function lookupItalianPostcode(cap: string): Promise<{ city: string; state: string } | null> {
    if (!/^\d{5}$/.test(cap)) {
        return null;
    }

    const entry = (await loadCapTable())[cap];

    return entry ? { city: entry[0], state: entry[1] } : null;
}

export function provinceCode(provinces: Record<string, string>, state: string): string {
    const value = state.trim();

    if (value.toUpperCase() in provinces) {
        return value.toUpperCase();
    }

    return Object.entries(provinces).find(([, name]) => name.toLowerCase() === value.toLowerCase())?.[0] ?? '';
}
