# Changelog — gradereport_markingguide

## 1.405.01 (2026052800)

Refactor for 4.5 - 5.2 release; added PHPUnit test suite.

- Deleted `classes/csv.php`. The `csv` class was made redundant by the 1.405.01 refactor to
  `flexible_table` downloads and was no longer called anywhere in the plugin.
- Added `tests/report_test.php` with six PHPUnit integration tests covering:
  - The `GRADABLES` constant structure;
  - Enrolled-user lookup (students found, teachers excluded);
  - Grading area SQL with and without a markingguide area present;
  - Markingguide criteria and max-score query;
  - `markingguidearray` structure built from the criteria/levels recordset;
- Improved README: added configuration, usage, and troubleshooting sections; corrected version support statement.

Replaced manual CSV/Excel download code with `flexible_table`'s built-in download mechanism.

- Removed various separate CSV array-building paths due to flexible_table refactor.
- Minimum Moodle version raised to 4.5. Requires Moodle 4.5 or later.
- Maximum Moodle version raised to 5.2.
- Refactored to use accessible flexible_table class with download options.
- WCAG 2.2 AA data-table requirements met.

## Earlier releases

No prior CHANGES.md existed. Earlier version history is recorded in git.
