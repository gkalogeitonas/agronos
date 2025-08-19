export function useTimestamp() {
  // Helper: parse timestamp strings that may be either ISO (with T/Z) or DB-style "YYYY-MM-DD HH:MM:SS"
  function parseAsUTCDate(input: any): Date | null {
    if (!input && input !== 0) return null;
    if (input instanceof Date) return input;
    if (typeof input !== 'string') {
      const d = new Date(input);
      return isNaN(d.getTime()) ? null : d;
    }

    // DB format: "2025-08-19 20:03:47" -> treat as UTC
    if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(input)) {
      return new Date(input.replace(' ', 'T') + 'Z');
    }

    // Otherwise try Date constructor (handles ISO with Z and offsets)
    const d = new Date(input);
    return isNaN(d.getTime()) ? null : d;
  }

  // Format a timestamp into "YYYY-MM-DD HH:MM:SS" in the user's browser timezone (default)
  function formatTimestamp(input: any, timeZone?: string): string {
    if (!input && input !== 0) return '—';
    const d = parseAsUTCDate(input);
    if (!d) return String(input);

    // use browser timezone by default, or provided timezone (e.g. 'Europe/Athens')
    const tz = timeZone || Intl.DateTimeFormat().resolvedOptions().timeZone || 'Europe/Athens';

    // Use Intl to get parts in the target timezone
    const fmt = new Intl.DateTimeFormat('en-GB', {
      timeZone: tz,
      year: 'numeric', month: '2-digit', day: '2-digit',
      hour: '2-digit', minute: '2-digit', second: '2-digit',
      hour12: false,
    });
    const parts = fmt.formatToParts(d);
    const map: Record<string, string> = {} as Record<string, string>;
    parts.forEach(p => { if (p.type !== 'literal') map[p.type] = p.value; });
    if (!map.year) return d.toString();
    return `${map.year}-${map.month}-${map.day} ${map.hour}:${map.minute}:${map.second}`;
  }

  return { parseAsUTCDate, formatTimestamp };
}

export default useTimestamp;
