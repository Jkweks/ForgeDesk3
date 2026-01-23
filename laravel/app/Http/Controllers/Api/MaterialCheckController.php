<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MaterialCheckController extends Controller
{
    /**
     * Check materials from uploaded estimate file against inventory
     */
    public function checkMaterials(Request $request)
    {
        try {
            Log::info('Material check started');

            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:xlsx,xlsm|max:10240',
                'sheet_name' => 'nullable|string|max:255',
                'header_row' => 'nullable|integer|min:1',
                'data_start_row' => 'nullable|integer|min:1',
                'part_number_column' => 'nullable|string|max:255',
                'quantity_column' => 'nullable|string|max:255',
                'description_column' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                Log::warning('Validation failed', ['errors' => $validator->errors()]);
                return response()->json([
                    'error' => 'Validation failed',
                    'details' => $validator->errors(),
                ], 422);
            }

            $file = $request->file('file');
            Log::info('File received', ['name' => $file->getClientOriginalName(), 'size' => $file->getSize()]);

            $sheetName = $request->input('sheet_name');
            $headerRow = $request->input('header_row', 1);
            $dataStartRow = $request->input('data_start_row', $headerRow + 1);
            $partNumberColumn = $request->input('part_number_column', 'Part Number');
            $quantityColumn = $request->input('quantity_column', 'Quantity');
            $descriptionColumn = $request->input('description_column', 'Description');

            // Load the spreadsheet
            Log::info('Loading spreadsheet');
            $spreadsheet = IOFactory::load($file->getRealPath());

            // Select the worksheet
            if ($sheetName) {
                try {
                    $worksheet = $spreadsheet->getSheetByName($sheetName);
                    if (!$worksheet) {
                        $availableSheets = array_map(fn($sheet) => $sheet->getTitle(), $spreadsheet->getAllSheets());
                        return response()->json([
                            'error' => "Sheet '{$sheetName}' not found. Available sheets: " . implode(', ', $availableSheets),
                        ], 400);
                    }
                } catch (\Exception $e) {
                    return response()->json([
                        'error' => "Failed to load sheet '{$sheetName}': " . $e->getMessage(),
                    ], 400);
                }
            } else {
                $worksheet = $spreadsheet->getActiveSheet();
            }

            $rows = $worksheet->toArray();
            Log::info('Spreadsheet loaded', ['sheet' => $worksheet->getTitle(), 'row_count' => count($rows)]);

            if (empty($rows)) {
                return response()->json([
                    'error' => 'The uploaded file is empty',
                ], 400);
            }

            // Get headers from specified row (1-indexed)
            if ($headerRow > count($rows)) {
                return response()->json([
                    'error' => "Header row {$headerRow} is beyond the file's row count (" . count($rows) . ")",
                ], 400);
            }

            $headers = $rows[$headerRow - 1]; // Convert to 0-indexed
            $headers = array_map(fn($h) => trim((string)$h), $headers);

            // Remove rows before data start
            $rows = array_slice($rows, $dataStartRow - 1); // Convert to 0-indexed

            // Find column indexes - support both column names and letters (A, B, C, etc.)
            $partNumberIndex = $this->resolveColumnIndex($partNumberColumn, $headers);
            $quantityIndex = $this->resolveColumnIndex($quantityColumn, $headers);
            $descriptionIndex = $this->resolveColumnIndex($descriptionColumn, $headers);

            if ($partNumberIndex === false) {
                return response()->json([
                    'error' => "Column '{$partNumberColumn}' not found in the file. Available columns: " . implode(', ', array_filter($headers)),
                ], 400);
            }

            if ($quantityIndex === false) {
                return response()->json([
                    'error' => "Column '{$quantityColumn}' not found in the file. Available columns: " . implode(', ', array_filter($headers)),
                ], 400);
            }

            // Process each row
            $results = [];
            $summary = [
                'total' => 0,
                'available' => 0,
                'partial' => 0,
                'unavailable' => 0,
                'not_found' => 0,
            ];

            foreach ($rows as $rowIndex => $row) {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                $partNumber = isset($row[$partNumberIndex]) ? trim($row[$partNumberIndex]) : null;
                $requiredQty = isset($row[$quantityIndex]) ? floatval($row[$quantityIndex]) : 0;
                $description = ($descriptionIndex !== false && isset($row[$descriptionIndex]))
                    ? trim($row[$descriptionIndex])
                    : null;

                // Skip if no part number or quantity
                if (empty($partNumber) || $requiredQty <= 0) {
                    continue;
                }

                $summary['total']++;

                // Look up the part in inventory
                $product = $this->findProduct($partNumber);

                if (!$product) {
                    $results[] = [
                        'part_number' => $partNumber,
                        'description' => $description,
                        'required_quantity' => $requiredQty,
                        'available_quantity' => 0,
                        'shortage' => $requiredQty,
                        'status' => 'not_found',
                        'location' => null,
                    ];
                    $summary['not_found']++;
                    continue;
                }

                // Calculate availability
                $availableQty = $product->quantity_available ?? $product->quantity_on_hand ?? 0;
                $shortage = max(0, $requiredQty - $availableQty);

                // Determine status
                $status = 'available';
                if ($availableQty <= 0) {
                    $status = 'unavailable';
                    $summary['unavailable']++;
                } elseif ($availableQty < $requiredQty) {
                    $status = 'partial';
                    $summary['partial']++;
                } else {
                    $summary['available']++;
                }

                $results[] = [
                    'part_number' => $partNumber,
                    'description' => $description ?: $product->description,
                    'required_quantity' => $requiredQty,
                    'available_quantity' => $availableQty,
                    'shortage' => $shortage,
                    'status' => $status,
                    'location' => $product->location,
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                ];
            }

            return response()->json([
                'message' => 'Material check completed',
                'summary' => $summary,
                'results' => $results,
            ]);

        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            Log::error('Excel file read error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'error' => 'Failed to read the Excel file: ' . $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
            ], 500);
        } catch (\Exception $e) {
            Log::error('Material check error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Material check failed: ' . $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Resolve column reference to array index
     * Supports column names ("Part Number") or column letters ("A", "I", "AA", etc.)
     */
    private function resolveColumnIndex(string $column, array $headers)
    {
        // Try exact name match first
        $index = array_search($column, $headers);
        if ($index !== false) {
            return $index;
        }

        // Try case-insensitive name match
        $lowerColumn = strtolower($column);
        foreach ($headers as $idx => $header) {
            if (strtolower($header) === $lowerColumn) {
                return $idx;
            }
        }

        // Try as column letter (A, B, C... AA, AB, etc.)
        $letterIndex = $this->columnLetterToIndex($column);
        if ($letterIndex !== false && $letterIndex < count($headers)) {
            return $letterIndex;
        }

        return false;
    }

    /**
     * Convert Excel column letter to zero-based array index
     * A=0, B=1, ... Z=25, AA=26, AB=27, etc.
     */
    private function columnLetterToIndex(string $letter): int|false
    {
        $letter = strtoupper(trim($letter));

        if ($letter === '' || !ctype_alpha($letter)) {
            return false;
        }

        $index = 0;
        $length = strlen($letter);

        for ($i = 0; $i < $length; $i++) {
            $char = ord($letter[$i]);
            if ($char < 65 || $char > 90) { // A-Z
                return false;
            }
            $index = ($index * 26) + ($char - 64);
        }

        return $index - 1; // Convert to 0-based index
    }

    /**
     * Find a product by part number/SKU
     * Tries multiple approaches to find the best match
     */
    private function findProduct($partNumber)
    {
        // Try exact match on SKU first
        $product = Product::where('sku', $partNumber)
            ->where('is_active', true)
            ->first();

        if ($product) {
            return $product;
        }

        // Try case-insensitive match on SKU
        $product = Product::whereRaw('LOWER(sku) = ?', [strtolower($partNumber)])
            ->where('is_active', true)
            ->first();

        if ($product) {
            return $product;
        }

        // Try removing leading zeros and special characters
        $cleanPartNumber = ltrim($partNumber, '0');
        $product = Product::where('sku', $cleanPartNumber)
            ->where('is_active', true)
            ->first();

        if ($product) {
            return $product;
        }

        // Try matching with spaces and dashes removed
        $normalizedPartNumber = str_replace([' ', '-', '_'], '', $partNumber);
        $product = Product::whereRaw('REPLACE(REPLACE(REPLACE(sku, " ", ""), "-", ""), "_", "") = ?', [$normalizedPartNumber])
            ->where('is_active', true)
            ->first();

        return $product;
    }
}
