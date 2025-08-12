# Import Formats

## Questions (CSV/XLSX)
- Columns order: `Question, A, B, C, D, Answer, Marks`
- `Answer` must be one of: `a`, `b`, `c`, `d` (case-insensitive)
- `Marks` is optional, defaults to `1`
- Upload separately for each Set (A/B/C) in the Upload Questions page

## Students (CSV/XLSX)
- Columns order: `Name, Email`
- System generates `Seat Number` and a random `Password`
- Duplicates by `Email` are skipped automatically
- After import, you can download the generated credentials as CSV from the page