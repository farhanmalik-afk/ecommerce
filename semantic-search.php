<?php
declare(strict_types=1);

function semanticSearchEnsureSchema(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS semantic_search_embeddings (
            document_type VARCHAR(20) NOT NULL,
            document_id INT UNSIGNED NOT NULL,
            content_hash CHAR(64) NOT NULL,
            embedding JSON NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (document_type, document_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function semanticSearchEmbed(array $texts): array
{
    if ($texts === []) {
        return [];
    }

    if (!function_exists('curl_init')) {
        throw new RuntimeException('The PHP cURL extension is required for semantic search.');
    }

    $baseUrl = rtrim(getenv('OLLAMA_BASE_URL') ?: 'http://127.0.0.1:11434', '/');
    $model = getenv('OLLAMA_EMBEDDING_MODEL') ?: 'embeddinggemma';
    $handle = curl_init($baseUrl . '/api/embed');
    if ($handle === false) {
        throw new RuntimeException('Unable to initialize the Ollama embedding request.');
    }

    $requestBody = json_encode(
        ['model' => $model, 'input' => array_values($texts)],
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $requestBody,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 1,
        CURLOPT_TIMEOUT => 4,
    ]);

    $responseBody = curl_exec($handle);
    $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($handle);
    curl_close($handle);

    if (!is_string($responseBody)) {
        throw new RuntimeException('Ollama embedding request failed: ' . $curlError);
    }
    if ($statusCode < 200 || $statusCode >= 300) {
        throw new RuntimeException('Ollama returned HTTP ' . $statusCode . '.');
    }

    try {
        $payload = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RuntimeException('Ollama returned invalid JSON.', 0, $exception);
    }

    $embeddings = $payload['embeddings'] ?? null;
    if (!is_array($embeddings) || count($embeddings) !== count($texts)) {
        throw new RuntimeException('Ollama returned an invalid embedding response.');
    }

    foreach ($embeddings as $embedding) {
        if (!is_array($embedding) || $embedding === []) {
            throw new RuntimeException('Ollama returned an empty or invalid embedding.');
        }
        foreach ($embedding as $value) {
            if (!is_numeric($value) || !is_finite((float) $value)) {
                throw new RuntimeException('Ollama returned a non-numeric embedding value.');
            }
        }
    }

    return $embeddings;
}

function semanticSearchCosineSimilarity(array $left, array $right): float
{
    if ($left === [] || count($left) !== count($right)) {
        return 0.0;
    }

    $dotProduct = 0.0;
    $leftMagnitude = 0.0;
    $rightMagnitude = 0.0;
    foreach ($left as $index => $leftValue) {
        if (!isset($right[$index]) || !is_numeric($leftValue) || !is_numeric($right[$index])) {
            return 0.0;
        }
        $leftNumber = (float) $leftValue;
        $rightNumber = (float) $right[$index];
        $dotProduct += $leftNumber * $rightNumber;
        $leftMagnitude += $leftNumber * $leftNumber;
        $rightMagnitude += $rightNumber * $rightNumber;
    }

    if ($leftMagnitude === 0.0 || $rightMagnitude === 0.0) {
        return 0.0;
    }

    return $dotProduct / (sqrt($leftMagnitude) * sqrt($rightMagnitude));
}

function semanticSearchFindSimilar(mysqli $conn, string $query, int $limit = 8): array
{
    $tableCheck = $conn->query(
        "SELECT COUNT(*) AS table_count
         FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = 'semantic_search_embeddings'"
    );
    if ((int) $tableCheck->fetch_assoc()['table_count'] === 0) {
        return ['available' => false, 'matches' => []];
    }

    $statement = $conn->prepare(
        'SELECT document_type, document_id, embedding FROM semantic_search_embeddings'
    );
    $statement->execute();
    $rows = $statement->get_result();
    $documents = [];
    while ($row = $rows->fetch_assoc()) {
        $embedding = json_decode((string) $row['embedding'], true);
        if (is_array($embedding)) {
            $documents[] = [
                'type' => (string) $row['document_type'],
                'id' => (int) $row['document_id'],
                'embedding' => $embedding,
            ];
        }
    }
    $statement->close();

    if ($documents === []) {
        return ['available' => false, 'matches' => []];
    }

    $queryEmbedding = semanticSearchEmbed([$query])[0];
    $matches = [];
    foreach ($documents as $document) {
        $similarity = semanticSearchCosineSimilarity($queryEmbedding, $document['embedding']);
        if ($similarity >= 0.35) {
            $matches[] = [
                'type' => $document['type'],
                'id' => $document['id'],
                'similarity' => $similarity,
            ];
        }
    }

    usort(
        $matches,
        static fn(array $left, array $right): int => $right['similarity'] <=> $left['similarity']
    );

    return ['available' => true, 'matches' => array_slice($matches, 0, $limit)];
}
