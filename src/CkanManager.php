<?php

namespace WebstudioTeam\CkanManager;

use Exception;

class CkanManager
{
    private $ckanBaseUrl;

    public function __construct(string $ckanBaseUrl)
    {
        $this->ckanBaseUrl = $ckanBaseUrl;
    }

    /**
     * @throws Exception
     */
    public function updateCkanDataset(string $ckanApiKey, string $ckanResourceId, string $title,
                                      string $description)
    {
        $datasetId = $this->getCkanDatasetIdForResource($ckanApiKey, $ckanResourceId);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $ckanApiKey,
                'Content-type: application/json',
            ],
            CURLOPT_HEADER => false,
            CURLOPT_URL => $this->ckanBaseUrl . '/api/action/package_show',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['id' => $datasetId]),
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if ($response['success']) {
            $result = $response['result'];
            $result['title'] = $title;
            $result['notes'] = $description;

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => [
                    'Authorization: ' . $ckanApiKey,
                    'Content-type: application/json',
                ],
                CURLOPT_HEADER => false,
                CURLOPT_URL => $this->ckanBaseUrl . '/api/action/package_update',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($result),
                CURLOPT_RETURNTRANSFER => true,
            ]);
            $response = json_decode(curl_exec($ch), true);
            curl_close($ch);

            if (!$response['success']) {
                throw new Exception($response['error']['message'] ?? __FUNCTION__ . ': Undefined error.');
            }
        } else {
            throw new Exception($response['error']['message'] ?? __FUNCTION__ . ': Undefined error.');
        }
    }

    /**
     * @throws Exception
     */
    public function updateCkanResource(string $ckanApiKey, string $ckanResourceId, string $url, string $title,
                                       string $description, string $licenseUrl, string $startDate,
                                       string $endDate = null)
    {
        $body = [
            'id' => $ckanResourceId,
            'url' => $url,
            'format' => 'CSV',
            'describedBy' => $url . '-metadata.json',
            'describedByType' => 'application/csvm+json',
            'license_link' => $licenseUrl,
            'mimetype' => 'application/csv',
            'name' => $title,
            'description' => $description,
            'temporal_start' => $startDate,
            'temporal_end' => $endDate ?? date('Y-m-d'),
            'last_modified' => date('Y-m-d'),
        ];
        $ch = curl_init();
        curl_setopt_array($ch,
            [
                CURLOPT_HTTPHEADER => [
                    'Authorization: ' . $ckanApiKey,
                    'Content-type: application/json',
                ],
                CURLOPT_HEADER => false,
                CURLOPT_URL => $this->ckanBaseUrl . '/api/3/action/resource_update',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($body),
                CURLOPT_RETURNTRANSFER => true,
            ]
        );
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (!$response['success']) {
            throw new Exception($response['error']['message'] ?? __FUNCTION__ . ': Undefined error.');
        }
    }

    /**
     * @throws Exception
     */
    public function getCkanDatasetUrlForId(string $ckanApiKey, string $ckanDatasetId): string
    {
        return $this->ckanBaseUrl . '/dataset/' . $this->getCkanDatasetAttr($ckanApiKey, $ckanDatasetId, 'name');
    }

    public function writeToCsv(string $filepath, array $data, array $headers = null)
    {
        $handle = fopen($filepath, 'w');

        if (!is_null($headers)) {
            fputcsv($handle, $headers);
            fseek($handle, -1, SEEK_CUR);
            fwrite($handle, "\r\n");
        }

        foreach ($data as $row) {
            fputcsv($handle, $row);
            fseek($handle, -1, SEEK_CUR);
            fwrite($handle, "\r\n");
        }

        fclose($handle);
    }

    public function writeToCsvSchema(string $schemaFilepath, string $csvFilename, string $title, string $description,
                                     string $licenseUrl, string $source, array $keywords, array $columns)
    {
        $schema = (object)[
            '@context' => ['http://www.w3.org/ns/csvw', (object)['@language' => 'cs']],
            'url' => $csvFilename . '.csv',
            'dc:title' => $title,
            'dc:description' => $description,
            'dc:source' => $source,
            'dcat:keyword' => $keywords,
            'dc:publisher' => (object)[
                'schema:name' => 'ÚZIS ČR',
                'schema:url' => (object)['@id' => 'https://www.uzis.cz/'],
            ],
            'dc:license' => (object)['@id' => $licenseUrl],
            'dc:modified' => (object)[
                '@value' => date('Y-m-d'),
                '@type' => 'xsd:date',
            ],
            'tableSchema' => (object)[
                'columns' => array_map(function ($column) {
                    return (object)$column;
                }, $columns),
            ],
        ];

        $handle = fopen($schemaFilepath, 'w');
        fwrite($handle, json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fclose($handle);
    }

    /**
     * @throws Exception
     */
    private function getCkanDatasetIdForResource(string $ckanApiKey, string $resourceId): string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $ckanApiKey,
                'Content-type: application/json',
            ],
            CURLOPT_HEADER => false,
            CURLOPT_URL => $this->ckanBaseUrl . '/api/action/resource_show',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['id' => $resourceId]),
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if ($response['success']) {
            return $response['result']['package_id'];
        } else {
            throw new Exception($response['error']['message'] ?? __FUNCTION__ . ': Undefined error.');
        }
    }

    /**
     * @throws Exception
     */
    private function getCkanDatasetAttr(string $ckanApiKey, string $ckanDatasetId, string $attribute)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $ckanApiKey,
                'Content-type: application/json',
            ],
            CURLOPT_HEADER => false,
            CURLOPT_URL => $this->ckanBaseUrl . '/api/action/package_show',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['id' => $ckanDatasetId]),
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if ($response['success']) {
            return $response['result'][$attribute];
        } else {
            throw new Exception($response['error']['message'] ?? __FUNCTION__ . ': Undefined error.');
        }
    }
}
