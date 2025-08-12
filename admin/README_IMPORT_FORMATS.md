# Import Formats

## Questions (CSV Only)
- **File Format**: CSV (.csv) files only
- **Columns order**: `Question, A, B, C, D, Answer, Marks`
- **Requirements**:
  - `Question`: The question text (required)
  - `A, B, C, D`: The four answer options (all required)
  - `Answer`: Must be one of: `a`, `b`, `c`, `d` (case-insensitive, required)
  - `Marks`: Points for correct answer (optional, defaults to `1`)
- **Upload Process**: Upload separately for each Set (A/B/C) in the Upload Questions page
- **Note**: Excel files (.xlsx, .xls) are no longer supported. Please save as CSV format.

## Students (CSV Only)
- **File Format**: CSV (.csv) files only
- **Columns order**: `Name, Email`
- **Requirements**:
  - `Name`: Student's full name (required)
  - `Email`: Valid email address (required, must be unique)
- **Auto-Generated**: System automatically generates `Seat Number` and a random `Password`
- **Duplicate Handling**: Entries with duplicate emails are automatically skipped
- **Credential Export**: After import, download the generated credentials as CSV from the page
- **Note**: Excel files (.xlsx, .xls) are no longer supported. Please save as CSV format.

## CSV Format Guidelines
- Use comma (`,`) as field separator
- Enclose text containing commas in double quotes (`"`)
- First row can contain headers (will be auto-detected and skipped)
- Encoding: UTF-8 recommended for special characters
- Empty rows are automatically ignored

## Sample CSV Files

### Questions CSV Example:
```csv
Question,A,B,C,D,Answer,Marks
"What is 2+2?","3","4","5","6","b",1
"Capital of France?","London","Paris","Berlin","Madrid","b",1
```

### Students CSV Example:
```csv
Name,Email
"John Doe","john.doe@example.com"
"Jane Smith","jane.smith@example.com"
```