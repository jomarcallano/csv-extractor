#!/usr/bin/env php
<?php
/**
 * CSV Column Extractor
 *
 * Professional PHP script to extract specific column values from CSV files
 *
 * @author Professional PHP Developer
 * @version 1.0.0
 * @license MIT
 *
 * Usage: php csvxtract.php --find=columnName [--file=path/to/file.csv] [--output=json|table|csv] [--save=output.txt] [--unique]
 *
 * Examples:
 *   php csvxtract.php --find=spTelephone
 *   php csvxtract.php --find=email --file=data.csv --output=json
 *   php csvxtract.php --find=fname --output=table --unique
 *   php csvxtract.php --find=spTelephone --save=results.txt
 */

class CSVExtractor {

    private $csvFile;
    private $columnsToFind;
    private $outputFormat;
    private $uniqueOnly;
    private $saveToFile;
    private $outputFileHandle;
    private $headers = [];
    private $data = [];
    private $isMultiColumn = false;

    /**
     * Constructor
     */
    public function __construct() {
        $this->parseArguments();
        $this->validateInputs();
    }

    /**
     * Parse command line arguments
     */
    private function parseArguments() {
        global $argv;

        $this->csvFile = null;
        $this->columnsToFind = [];
        $this->outputFormat = 'table'; default format
        $this->uniqueOnly = false;
        $this->saveToFile = null;
        $this->outputFileHandle = null;
        $this->isMultiColumn = false;

        if (count($argv) < 2) {
            $this->showUsage();
            exit(1);
        }

        foreach ($argv as $arg) {
            if (strpos($arg, '--find=') === 0) {
                $columnsString = substr($arg, 7);
                $this->columnsToFind = array_map('trim', explode(',', $columnsString));
                $this->isMultiColumn = count($this->columnsToFind) > 1;
            } elseif (strpos($arg, '--file=') === 0) {
                $this->csvFile = substr($arg, 7);
            } elseif (strpos($arg, '--output=') === 0) {
                $this->outputFormat = substr($arg, 9);
            } elseif (strpos($arg, '--save=') === 0) {
                $this->saveToFile = substr($arg, 7);
            } elseif ($arg === '--unique') {
                $this->uniqueOnly = true;
            } elseif ($arg === '--help' || $arg === '-h') {
                $this->showUsage();
                exit(0);
            }
        }

        Auto-detect CSV file if not specified
        if (!$this->csvFile) {
            $this->csvFile = $this->autoDetectCSVFile();
        }
    }

    /**
     * Auto-detect CSV file in current directory
     */
    private function autoDetectCSVFile() {
        $csvFiles = glob('*.csv');

        if (empty($csvFiles)) {
            Try .txt extension
            $csvFiles = glob('*.txt');
        }

        if (empty($csvFiles)) {
            $this->error("No CSV file found in current directory. Please specify with --file parameter.");
        }

        if (count($csvFiles) === 1) {
            $this->log("Auto-detected file: {$csvFiles[0]}");
            return $csvFiles[0];
        }

        If multiple files, try to find snippet.txt or data.csv
        foreach ($csvFiles as $file) {
            if (in_array($file, ['snippet.txt', 'data.csv', 'employees.csv'])) {
                $this->log("Auto-detected file: {$file}");
                return $file;
            }
        }

        Use first file found
        $this->log("Multiple CSV files found. Using: {$csvFiles[0]}");
        return $csvFiles[0];
    }

    /**
     * Validate inputs
     */
    private function validateInputs() {
        if (empty($this->columnsToFind)) {
            $this->error("Column name is required. Use --find=columnName");
        }

        if (!file_exists($this->csvFile)) {
            $this->error("File not found: {$this->csvFile}");
        }

        if (!is_readable($this->csvFile)) {
            $this->error("File is not readable: {$this->csvFile}");
        }

        $validFormats = ['table', 'json', 'csv', 'list'];
        if (!in_array($this->outputFormat, $validFormats)) {
            $this->error("Invalid output format. Valid options: " . implode(', ', $validFormats));
        }
    }

    /**
     * Parse CSV file
     */
    public function parseCSV() {
        $this->log("Reading file: {$this->csvFile}");
        $this->log("Searching for column(s): " . implode(', ', $this->columnsToFind));
        echo "\n";

        $handle = fopen($this->csvFile, 'r');

        if ($handle === false) {
            $this->error("Failed to open file: {$this->csvFile}");
        }

        Read headers
        $this->headers = fgetcsv($handle);

        if ($this->headers === false) {
            $this->error("Failed to read CSV headers");
        }

        Find column indices for all requested columns
        $columnIndices = [];
        foreach ($this->columnsToFind as $column) {
            $index = array_search($column, $this->headers);
            if ($index === false) {
                $this->showAvailableColumns();
                $this->error("Column '{$column}' not found in CSV");
            }
            $columnIndices[$column] = $index;
        }

        Read data rows
        $rowNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            Extract values for all requested columns
            $values = [];
            $isValidRow = true;
            foreach ($this->columnsToFind as $column) {
                $index = $columnIndices[$column];
                $val = isset($row[$index]) ? trim($row[$index]) : '';

                Sanitize and validate based on column name
                if (strtolower($column) === 'email' || strtolower($column) === 'emailaddress') {
                    if (empty($val) || !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                        $isValidRow = false;
                        break;
                    }
                }

                $values[$column] = $val;
            }

            Store data only if it passes validation
            if ($isValidRow) {
                $this->data[] = [
                        'row' => $rowNumber,
                        'values' => $values,
                        'empID' => isset($row[0]) ? $row[0] : '',
                        'name' => $this->getFullName($row)
                ];
            }
        }

        fclose($handle);

        Apply unique filter if requested
        if ($this->uniqueOnly) {
            $this->data = $this->filterUnique();
        }

        $this->log("Found " . count($this->data) . " records");
    }

    /**
     * Get full name from row
     */
    private function getFullName($row) {
        $fname = isset($row[1]) ? $row[1] : '';
        $mname = isset($row[2]) ? $row[2] : '';
        $lname = isset($row[3]) ? $row[3] : '';

        return trim("$fname $mname $lname");
    }

    /**
     * Filter unique values
     */
    private function filterUnique() {
        $seen = [];
        $unique = [];

        foreach ($this->data as $item) {
            Create a key from all column values
            $key = implode('|', $item['values']);
            if (!isset($seen[$key]) && !empty($key)) {
                $seen[$key] = true;
                $unique[] = $item;
            }
        }

        return $unique;
    }

    /**
     * Open output file for appending
     */
    private function openOutputFile() {
        $this->outputFileHandle = fopen($this->saveToFile, 'a');

        if ($this->outputFileHandle === false) {
            $this->error("Failed to open output file: {$this->saveToFile}");
        }

        $this->log("Appending output to file: {$this->saveToFile}");
    }

    /**
     * Close output file
     */
    private function closeOutputFile() {
        if ($this->outputFileHandle) {
            fclose($this->outputFileHandle);
            $this->log("✓ Results saved to: {$this->saveToFile}");

            Auto-remove duplicate lines
            $this->removeDuplicateLines();
        }
    }

    /**
     * Remove duplicate lines from output file
     */
    private function removeDuplicateLines() {
        if (!file_exists($this->saveToFile)) {
            return;
        }

        $this->log("Removing duplicate lines...");

        Read all lines from file
        $lines = file($this->saveToFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            $this->log("⚠ Warning: Could not read file for deduplication");
            return;
        }

        $totalLines = count($lines);

        Remove duplicates while preserving order
        $uniqueLines = [];
        $seen = [];

        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            if (!isset($seen[$trimmedLine]) && !empty($trimmedLine)) {
                $seen[$trimmedLine] = true;
                $uniqueLines[] = $trimmedLine;
            }
        }

        $uniqueCount = count($uniqueLines);
        $duplicatesRemoved = $totalLines - $uniqueCount;

        Write back only unique lines
        if ($duplicatesRemoved > 0) {
            $handle = fopen($this->saveToFile, 'w');
            if ($handle === false) {
                $this->log("⚠ Warning: Could not write deduplicated file");
                return;
            }

            foreach ($uniqueLines as $line) {
                fwrite($handle, $line . "\n");
            }

            fclose($handle);

            $this->log("✓ Removed {$duplicatesRemoved} duplicate line(s)");
            $this->log("✓ Total unique records: {$uniqueCount}");
        } else {
            $this->log("✓ No duplicates found - all {$uniqueCount} lines are unique");
        }
    }

    /**
     * Write to file only (not to screen)
     */
    private function writeToFile($text) {
        if ($this->outputFileHandle) {
            fwrite($this->outputFileHandle, $text);
        }
    }

    /**
     * Display output based on format
     */
    public function displayOutput() {
        if (empty($this->data)) {
            echo "\nNo data found for column(s): " . implode(', ', $this->columnsToFind) . "\n\n";
            return;
        }

        Open file for appending if --save parameter is provided
        if ($this->saveToFile) {
            $this->openOutputFile();
        }

        switch ($this->outputFormat) {
            case 'json':
                $this->outputJSON();
                break;
            case 'csv':
                $this->outputCSV();
                break;
            case 'list':
                $this->outputList();
                break;
            case 'table':
            default:
                $this->outputTable();
                break;
        }

        Close file handle if it was opened
        if ($this->outputFileHandle) {
            $this->closeOutputFile();
        }
    }

    /**
     * Output as table
     */
    private function outputTable() {
        Screen output - nice table format
        $this->printSeparator();
        $columnHeaders = implode(" | ", $this->columnsToFind);
        $header = sprintf("| %-6s | %-8s | %-35s | %s |\n", "Row", "Emp ID", "Name", $columnHeaders);
        echo $header;
        $this->printSeparator();

        foreach ($this->data as $item) {
            Build display values
            $displayValues = [];
            foreach ($this->columnsToFind as $col) {
                $displayValues[] = $item['values'][$col];
            }

            $line = sprintf("| %-6s | %-8s | %-35s | %s |\n",
                    $item['row'],
                    $item['empID'],
                    substr($item['name'], 0, 35),
                    implode(", ", $displayValues)
            );
            echo $line;

            File output - just the values (CSV format)
            if ($this->outputFileHandle) {
                $this->writeToFile(implode(',', $displayValues) . "\n");
            }
        }

        $this->printSeparator();
        echo "\nTotal records: " . count($this->data) . "\n\n";
    }

    /**
     * Output as JSON
     */
    private function outputJSON() {
        $output = [
                'columns' => $this->columnsToFind,
                'total_records' => count($this->data),
                'data' => $this->data
        ];

        $json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        echo $json;

        For file output, just save the values in CSV format
        if ($this->outputFileHandle) {
            foreach ($this->data as $item) {
                $values = [];
                foreach ($this->columnsToFind as $col) {
                    $values[] = $item['values'][$col];
                }
                $this->writeToFile(implode(',', $values) . "\n");
            }
        }
    }

    /**
     * Output as CSV
     */
    private function outputCSV() {
        Create temp handle for building screen CSV
        $tempHandle = fopen('php:temp', 'r+');

        Write header for screen
        $headers = array_merge(['Row', 'Emp ID', 'Name'], $this->columnsToFind);
        fputcsv($tempHandle, $headers);

        Write data
        foreach ($this->data as $item) {
            $row = [$item['row'], $item['empID'], $item['name']];
            foreach ($this->columnsToFind as $col) {
                $row[] = $item['values'][$col];
            }
            fputcsv($tempHandle, $row);

            File output - just the requested column values
            if ($this->outputFileHandle) {
                $values = [];
                foreach ($this->columnsToFind as $col) {
                    $values[] = $item['values'][$col];
                }
                $this->writeToFile(implode(',', $values) . "\n");
            }
        }

        Read back the CSV content for screen display
        rewind($tempHandle);
        $csvOutput = stream_get_contents($tempHandle);
        fclose($tempHandle);

        echo $csvOutput;
    }

    /**
     * Output as simple list
     */
    private function outputList() {
        foreach ($this->data as $item) {
            $values = [];
            foreach ($this->columnsToFind as $col) {
                if (!empty($item['values'][$col])) {
                    $values[] = $item['values'][$col];
                }
            }
            if (!empty($values)) {
                $line = implode(',', $values);
                echo $line . "\n";

                File output - same format
                if ($this->outputFileHandle) {
                    $this->writeToFile($line . "\n");
                }
            }
        }
    }

    /**
     * Show available columns
     */
    private function showAvailableColumns() {
        echo "\n╔═══════════════════════════════════════════════════════════╗\n";
        echo "║           AVAILABLE COLUMNS IN CSV FILE                  ║\n";
        echo "╚═══════════════════════════════════════════════════════════╝\n\n";

        $columns = array_chunk($this->headers, 3);
        foreach ($columns as $row) {
            foreach ($row as $col) {
                echo sprintf("  • %-25s", $col);
            }
            echo "\n";
        }
        echo "\n";
    }

    /**
     * Print table separator
     */
    private function printSeparator() {
        echo "+--------+----------+-------------------------------------+---------------------------+\n";
    }

    /**
     * Show usage information
     */
    private function showUsage() {
        echo "\n";
        echo "╔═══════════════════════════════════════════════════════════╗\n";
        echo "║              CSV COLUMN EXTRACTOR v1.0.0                  ║\n";
        echo "║         Professional CSV Data Extraction Tool            ║\n";
        echo "╚═══════════════════════════════════════════════════════════╝\n\n";
        echo "USAGE:\n";
        echo "  php csvxtract.php --find=<column_name> [options]\n\n";
        echo "REQUIRED:\n";
        echo "  --find=<column>      Column name to extract (e.g., spTelephone, email)\n\n";
        echo "OPTIONS:\n";
        echo "  --file=<path>        Path to CSV file (auto-detects if not specified)\n";
        echo "  --output=<format>    Output format: table|json|csv|list (default: table)\n";
        echo "  --save=<filename>    Save output to file in append mode (e.g., results.txt)\n";
        echo "  --unique             Show only unique values\n";
        echo "  --help, -h           Show this help message\n\n";
        echo "EXAMPLES:\n";
        echo "  php csvxtract.php --find=spTelephone\n";
        echo "  php csvxtract.php --find=email --output=json\n";
        echo "  php csvxtract.php --find=mobile --file=employees.csv --unique\n";
        echo "  php csvxtract.php --find=fname --output=list\n";
        echo "  php csvxtract.php --find=spTelephone --save=results.txt\n";
        echo "  php csvxtract.php --find=Email,fname,spTelephone --save=output.txt\n";
        echo "  php csvxtract.php --find=email --output=json --save=output.txt\n\n";
    }

    /**
     * Log message
     */
    private function log($message) {
        echo "→ {$message}\n";
    }

    /**
     * Display error and exit
     */
    private function error($message) {
        echo "\n";
        echo "╔═══════════════════════════════════════════════════════════╗\n";
        echo "║  ERROR                                                    ║\n";
        echo "╚═══════════════════════════════════════════════════════════╝\n";
        echo "\n  {$message}\n\n";
        exit(1);
    }

    /**
     * Run the extractor
     */
    public function run() {
        $this->parseCSV();
        $this->displayOutput();
    }
}

 ============================================================================
 Main Execution
 ============================================================================

try {
    $extractor = new CSVExtractor();
    $extractor->run();
} catch (Exception $e) {
    echo "\n╔═══════════════════════════════════════════════════════════╗\n";
    echo "║  FATAL ERROR                                              ║\n";
    echo "╚═══════════════════════════════════════════════════════════╝\n";
    echo "\n  " . $e->getMessage() . "\n\n";
    exit(1);
}