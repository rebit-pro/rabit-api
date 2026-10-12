/**
 * A CSV cell for Excel in a Russian locale: quoted when it holds a separator, a quote or a line break. A value that
 * Excel would run as a formula (a buyer's email may start with `=`) gets a leading apostrophe and stays plain text.
 */
function csvCell(value: string | number): string {
  const raw = String(value);
  const text = /^[=+\-@\t\r]/.test(raw) ? "'" + raw : raw;
  return /[;"\r\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
}

/** CSV that Excel opens with Cyrillic intact: UTF-8 BOM, semicolons, CRLF (#92 DEC-03). */
export function csvText(header: readonly string[], rows: readonly (readonly (string | number)[])[]): string {
  return '﻿' + [header, ...rows].map((row) => row.map(csvCell).join(';')).join('\r\n') + '\r\n';
}

/** Saves the text as a file on the viewer's device; nothing leaves the browser. */
export function downloadCsv(name: string, text: string): void {
  const url = URL.createObjectURL(new Blob([text], { type: 'text/csv;charset=utf-8' }));
  const link = document.createElement('a');
  link.href = url;
  link.download = name;
  link.click();
  URL.revokeObjectURL(url);
}
