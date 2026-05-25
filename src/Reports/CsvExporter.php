<?php

declare(strict_types=1);

namespace MYVH\Reports;

final class CsvExporter {
    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function output(array $rows): string {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new \RuntimeException('Unable to open CSV output stream.');
        }

        $headers = $this->headers_for_rows($rows);
        if ($headers !== []) {
            fputcsv($stream, $headers);

            foreach ($rows as $row) {
                $line = [];
                foreach ($headers as $header) {
                    $line[] = $row[$header] ?? '';
                }

                fputcsv($stream, $line);
            }
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return is_string($csv) ? $csv : '';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, string>
     */
    private function headers_for_rows(array $rows): array {
        $headers = [];

        foreach ($rows as $row) {
            foreach (array_keys($row) as $key) {
                if (in_array($key, $headers, true)) {
                    continue;
                }

                $headers[] = (string) $key;
            }
        }

        return $headers;
    }
}