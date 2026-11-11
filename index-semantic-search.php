<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require __DIR__ . '/config.php';
require __DIR__ . '/semantic-search.php';

try {
    semanticSearchEnsureSchema($conn);

    $documents = [];
    $model = getenv('OLLAMA_EMBEDDING_MODEL') ?: 'embeddinggemma';
    $productResult = $conn->query(
        "SELECT p.id, p.name, p.sku, p.description, c.name AS category_name
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.is_deleted = 0"
    );
    while ($product = $productResult->fetch_assoc()) {
        $text = 'Product: ' . $product['name']
            . '. SKU: ' . $product['sku']
            . '. Category: ' . ($product['category_name'] ?? '')
            . '. Description: ' . ($product['description'] ?? '');
        $documents[] = [
            'type' => 'product',
            'id' => (int) $product['id'],
            'text' => $text,
            'hash' => hash('sha256', $model . "\0" . $text),
        ];
    }

    $categoryResult = $conn->query('SELECT id, name FROM categories');
    while ($category = $categoryResult->fetch_assoc()) {
        $text = 'Product category: ' . $category['name'];
        $documents[] = [
            'type' => 'category',
            'id' => (int) $category['id'],
            'text' => $text,
            'hash' => hash('sha256', $model . "\0" . $text),
        ];
    }

    $existingStatement = $conn->prepare(
        'SELECT content_hash FROM semantic_search_embeddings WHERE document_type = ? AND document_id = ?'
    );
    $upsertStatement = $conn->prepare(
        'INSERT INTO semantic_search_embeddings (document_type, document_id, content_hash, embedding)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE content_hash = VALUES(content_hash), embedding = VALUES(embedding)'
    );

    $indexedCount = 0;
    $batchSize = 32;
    foreach (array_chunk($documents, $batchSize) as $batch) {
        $pending = [];
        foreach ($batch as $document) {
            $type = $document['type'];
            $id = $document['id'];
            $existingStatement->bind_param('si', $type, $id);
            $existingStatement->execute();
            $existing = $existingStatement->get_result()->fetch_assoc();
            if (($existing['content_hash'] ?? null) !== $document['hash']) {
                $pending[] = $document;
            }
        }

        if ($pending === []) {
            continue;
        }

        $embeddings = semanticSearchEmbed(array_column($pending, 'text'));
        foreach ($pending as $index => $document) {
            $type = $document['type'];
            $id = $document['id'];
            $hash = $document['hash'];
            $embeddingJson = json_encode($embeddings[$index], JSON_THROW_ON_ERROR);
            $upsertStatement->bind_param('siss', $type, $id, $hash, $embeddingJson);
            $upsertStatement->execute();
            $indexedCount++;
        }
    }

    $existingStatement->close();
    $upsertStatement->close();

    $conn->query(
        "DELETE FROM semantic_search_embeddings
         WHERE document_type = 'product'
           AND document_id NOT IN (SELECT id FROM products WHERE is_deleted = 0)"
    );
    $conn->query(
        "DELETE FROM semantic_search_embeddings
         WHERE document_type = 'category'
           AND document_id NOT IN (SELECT id FROM categories)"
    );

    echo "Semantic index ready. Updated {$indexedCount} document(s); "
        . count($documents) . " product/category document(s) checked.\n";
} catch (Throwable $exception) {
    error_log('Semantic search indexing failed: ' . $exception->getMessage());
    fwrite(STDERR, 'Indexing failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
